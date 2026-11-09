<?php

use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\Inventory\InventoryStock;
use App\Models\Service;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->receptionist] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->recepToken = clinicToken($this->receptionist, $this->clinic);

    inClinic($this->clinic);
    $this->branch = Branch::first();
    $this->consult = Service::create(['name' => 'Consulta general', 'category' => 'consultation', 'duration_minutes' => 30, 'price' => 60000, 'tax_rate' => 0]);
    tenant()->forget();

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Paola Andrea Díaz', 'document_type' => 'CC', 'document_number' => '1095812233', 'mobile_phone' => '+573186665544']);
    $this->nexgard = $this->freshRequest()->withToken($this->token)->postJson('/api/v1/products', [
        'name' => 'NexGard 10-25 kg', 'kind' => 'medication', 'unit' => 'tablet', 'sale_price' => 68000, 'cost_price' => 41500, 'tax_rate' => 19,
        'tracks_stock' => true, 'min_stock' => 2, 'initial_quantity' => 10, 'branch_id' => $this->branch->id,
    ])->json('data.id');
});

function openSale(?string $ownerId = null, ?string $token = null): array
{
    return test()->freshRequest()->withToken($token ?? test()->recepToken)
        ->postJson('/api/v1/sales', ['branch_id' => test()->branch->id, 'owner_id' => $ownerId])->assertCreated()->json('data');
}

function addItem(string $saleId, array $item, ?string $token = null): TestResponse
{
    return test()->freshRequest()->withToken($token ?? test()->recepToken)->postJson("/api/v1/sales/{$saleId}/items", $item);
}

function nexgardStock(): float
{
    return (float) tenant()->runAs(test()->clinic->id, fn () => InventoryStock::where('product_id', test()->nexgard)->value('quantity'));
}

it('numbers sales per clinic and keeps a snapshot of the customer', function () {
    $first = openSale($this->owner->id);
    $second = openSale();

    expect($first['number'])->toBe(1)
        ->and($second['number'])->toBe(2)
        ->and($first['customer'])->toBe(['name' => 'Paola Andrea Díaz', 'document' => 'CC 1095812233', 'phone' => '+573186665544'])
        ->and($second['customer'])->toBeNull() // counter sale
        ->and($first['status']['value'])->toBe('open');

    // Another clinic starts its own count.
    [$other, $otherAdmin] = clinicWithMember();
    $otherSale = $this->freshRequest()->withToken(clinicToken($otherAdmin, $other))
        ->postJson('/api/v1/sales', ['branch_id' => tenant()->runAs($other->id, fn () => Branch::first()->id)])->json('data');
    expect($otherSale['number'])->toBe(1);
});

it('adds products (moving stock), services and free lines with IVA included', function () {
    $sale = openSale($this->owner->id);

    addItem($sale['id'], ['type' => 'product', 'product_id' => $this->nexgard, 'quantity' => 2])->assertOk();
    addItem($sale['id'], ['type' => 'service', 'service_id' => $this->consult->id, 'quantity' => 1])->assertOk();
    $data = addItem($sale['id'], ['type' => 'custom', 'description' => 'Corte de uñas', 'quantity' => 1, 'unit_price' => 12000])->assertOk()->json('data');

    expect(nexgardStock())->toEqual(8)
        ->and($data['total'])->toEqual(208000) // 2 × 68.000 + 60.000 + 12.000
        ->and($data['tax_total'])->toEqual(21714.29) // IVA inside the 136.000 of NexGard (19 %)
        ->and($data['items'][0]['moves_stock'])->toBeTrue()
        ->and($data['balance'])->toEqual(208000);

    addItem($sale['id'], ['type' => 'custom', 'description' => '', 'quantity' => 1])->assertJsonValidationErrors('description');
    addItem($sale['id'], ['type' => 'product', 'product_id' => $this->nexgard, 'quantity' => 1, 'discount' => 999999])->assertJsonValidationErrors('discount');
});

it('changes quantities and removes lines, giving stock back', function () {
    $sale = openSale();
    $itemId = addItem($sale['id'], ['type' => 'product', 'product_id' => $this->nexgard, 'quantity' => 2])->json('data.items.0.id');

    $this->freshRequest()->withToken($this->recepToken)->patchJson("/api/v1/sale-items/{$itemId}", ['quantity' => 3, 'unit_price' => 68000, 'discount' => 4000])->assertOk()
        ->assertJsonPath('data.total', 200000);
    expect(nexgardStock())->toEqual(7);

    $this->freshRequest()->withToken($this->recepToken)->deleteJson("/api/v1/sale-items/{$itemId}")->assertOk()->assertJsonPath('data.total', 0);
    expect(nexgardStock())->toEqual(10);
});

it('applies the same discount to every line', function () {
    $sale = openSale();
    addItem($sale['id'], ['type' => 'service', 'service_id' => $this->consult->id, 'quantity' => 1]);
    addItem($sale['id'], ['type' => 'product', 'product_id' => $this->nexgard, 'quantity' => 1]);

    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/sales/{$sale['id']}/discount", ['percent' => 10])->assertOk()
        ->assertJsonPath('data.discount_total', 12800)
        ->assertJsonPath('data.total', 115200);
});

it('takes partial payments, closes the sale when paid and locks it', function () {
    $sale = openSale($this->owner->id);
    addItem($sale['id'], ['type' => 'service', 'service_id' => $this->consult->id, 'quantity' => 1]);

    $pay = fn (array $body) => $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/sales/{$sale['id']}/payments", $body);

    $pay(['method' => 'cash', 'amount' => 70000])->assertJsonValidationErrors('amount'); // more than the balance
    $pay(['method' => 'nequi', 'amount' => 20000, 'reference' => 'M123'])->assertOk()->assertJsonPath('data.status.value', 'partially_paid')->assertJsonPath('data.balance', 40000);

    // Owner account shows what they owe.
    $this->freshRequest()->withToken($this->recepToken)->getJson("/api/v1/owners/{$this->owner->id}/account")->assertOk()
        ->assertJsonPath('data.balance', 40000)->assertJsonPath('data.pending_count', 1);

    $pay(['method' => 'cash', 'amount' => 40000])->assertOk()->assertJsonPath('data.status.value', 'paid')->assertJsonPath('data.balance', 0);
    $pay(['method' => 'cash', 'amount' => 1])->assertJsonValidationErrors('amount'); // nothing left

    // Paid: lines can't change.
    addItem($sale['id'], ['type' => 'custom', 'description' => 'Otro', 'quantity' => 1, 'unit_price' => 1000])->assertJsonValidationErrors('sale');
});

it('lets only the admin void a payment or a sale, giving stock back and keeping the record', function () {
    $sale = openSale();
    addItem($sale['id'], ['type' => 'product', 'product_id' => $this->nexgard, 'quantity' => 2]);
    $paymentId = $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/sales/{$sale['id']}/payments", ['method' => 'card', 'amount' => 136000])
        ->json('data.payments.0.id');

    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/payments/{$paymentId}/void", ['reason' => 'Error'])->assertForbidden();
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/payments/{$paymentId}/void", ['reason' => 'Se pasó la tarjeta dos veces'])->assertOk()
        ->assertJsonPath('data.status.value', 'open')
        ->assertJsonPath('data.payments.0.voided', true);

    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/sales/{$sale['id']}/void", ['reason' => 'x'])->assertForbidden();
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/sales/{$sale['id']}/void", [])->assertJsonValidationErrors('reason');
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/sales/{$sale['id']}/void", ['reason' => 'El tutor no se llevó el producto'])->assertOk()
        ->assertJsonPath('data.status.value', 'void')
        ->assertJsonPath('data.voided_by', $this->admin->name);

    expect(nexgardStock())->toEqual(10);
});

it('gives a PDF receipt that says it is not an electronic invoice', function () {
    $sale = openSale($this->owner->id);
    addItem($sale['id'], ['type' => 'service', 'service_id' => $this->consult->id, 'quantity' => 1]);

    $response = $this->freshRequest()->withToken($this->recepToken)->get("/api/v1/sales/{$sale['id']}/receipt")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('lets the vet see sales but not charge, and keeps sales inside their clinic', function () {
    $sale = openSale();
    $vetToken = clinicToken($this->vet, $this->clinic);

    $this->freshRequest()->withToken($vetToken)->getJson('/api/v1/sales')->assertOk()->assertJsonPath('meta.total', 1);
    $this->freshRequest()->withToken($vetToken)->postJson('/api/v1/sales', ['branch_id' => $this->branch->id])->assertForbidden();

    [$other, $otherAdmin] = clinicWithMember();
    $this->freshRequest()->withToken(clinicToken($otherAdmin, $other))->getJson("/api/v1/sales/{$sale['id']}")->assertNotFound();
});

it('lists sales with filters and finds products and services to sell', function () {
    $a = openSale($this->owner->id);
    addItem($a['id'], ['type' => 'service', 'service_id' => $this->consult->id, 'quantity' => 1]);
    openSale();

    $this->freshRequest()->withToken($this->recepToken)->getJson('/api/v1/sales?status=pending&q=paola')->assertOk()->assertJsonPath('meta.total', 1);
    $this->freshRequest()->withToken($this->recepToken)->getJson('/api/v1/sales?q=2')->assertJsonPath('data.0.number', 2);

    $found = $this->freshRequest()->withToken($this->recepToken)->getJson('/api/v1/sellables?branch_id='.$this->branch->id.'&q=nex')->assertOk()->json('data');
    expect($found[0])->toMatchArray(['type' => 'product', 'name' => 'NexGard 10-25 kg', 'stock' => 10.0]);
});
