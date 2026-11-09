<?php

use App\Auth\ClinicRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStock;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Service;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    [, $this->receptionist] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->vetToken = clinicToken($this->vet, $this->clinic);
    $this->recepToken = clinicToken($this->receptionist, $this->clinic);

    inClinic($this->clinic);
    $this->branch = Branch::first();
    $this->vaccineService = Service::create(['name' => 'Vacunación', 'category' => 'vaccination', 'duration_minutes' => 15, 'price' => 20000, 'tax_rate' => 0]);
    tenant()->forget();

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Paola Andrea Díaz']);
    $this->pet = makePet($this->owner, ['name' => 'Rocky']);
    $this->rabies = $this->freshRequest()->withToken($this->token)->postJson('/api/v1/products', [
        'name' => 'Vacuna antirrábica', 'kind' => 'vaccine', 'unit' => 'dose', 'sale_price' => 35000, 'tax_rate' => 0,
        'tracks_stock' => true, 'initial_quantity' => 10, 'branch_id' => $this->branch->id,
    ])->json('data.id');
});

function appointmentFor(array $overrides = []): Appointment
{
    return tenant()->runAs(test()->clinic->id, fn () => Appointment::create(array_merge([
        'branch_id' => test()->branch->id,
        'owner_id' => test()->owner->id,
        'pet_id' => test()->pet->id,
        'service_id' => test()->vaccineService->id,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHour()->addMinutes(15),
        'status' => 'checked_in',
    ], $overrides)));
}

function vaccinateWithProduct(array $productUse, ?string $token = null): TestResponse
{
    return test()->freshRequest()->withToken($token ?? test()->vetToken)->postJson('/api/v1/pets/'.test()->pet->id.'/clinical-records', [
        'type' => 'vaccination',
        'branch_id' => test()->branch->id,
        'vaccination' => ['vaccine_name' => 'Rabia', 'lot_number' => 'RB-1'],
        'product_use' => $productUse,
    ]);
}

function rabiesStock(): float
{
    return (float) tenant()->runAs(test()->clinic->id, fn () => InventoryStock::where('product_id', test()->rabies)->value('quantity'));
}

it('charges an appointment once: the service goes to the owner account of the day', function () {
    $appointment = appointmentFor();

    $first = $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/appointments/{$appointment->id}/charge")->assertOk()->json('data');
    $again = $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/appointments/{$appointment->id}/charge")->assertOk()->json('data');

    expect($again['sale_id'])->toBe($first['sale_id']);
    $sale = $this->freshRequest()->withToken($this->recepToken)->getJson("/api/v1/sales/{$first['sale_id']}")->json('data');
    expect($sale['items'])->toHaveCount(1)
        ->and($sale['items'][0]['description'])->toBe('Vacunación')
        ->and($sale['items'][0]['origin']['kind'])->toBe('appointment')
        ->and($sale['customer']['name'])->toBe('Paola Andrea Díaz');

    // Nothing to charge: no owner, or cancelled.
    $noOwner = appointmentFor(['owner_id' => null, 'pet_id' => null]);
    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/appointments/{$noOwner->id}/charge")->assertJsonValidationErrors('appointment');
    $cancelled = appointmentFor(['status' => 'cancelled', 'cancellation_reason' => 'x']);
    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/appointments/{$cancelled->id}/charge")->assertJsonValidationErrors('appointment');

    // The vet can't charge appointments (reception does).
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/appointments/{$appointment->id}/charge")->assertForbidden();
});

it('adds the vaccine applied to the same account of the day, moving stock once', function () {
    $appointment = appointmentFor();
    $saleId = $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/appointments/{$appointment->id}/charge")->json('data.sale_id');

    // The vet registers the vaccine with the product: no sales permission needed.
    vaccinateWithProduct(['product_id' => $this->rabies, 'quantity' => 1, 'charge' => true])->assertCreated();

    $sale = $this->freshRequest()->withToken($this->recepToken)->getJson("/api/v1/sales/{$saleId}")->json('data');
    expect($sale['items'])->toHaveCount(2)
        ->and($sale['total'])->toEqual(55000) // 20.000 service + 35.000 vaccine
        ->and($sale['items'][1]['origin'])->toMatchArray(['kind' => 'clinical_record', 'label' => 'Vacunación · Rocky'])
        ->and(rabiesStock())->toEqual(9);

    expect(tenant()->runAs($this->clinic->id, fn () => Sale::count()))->toBe(1);
});

it('only takes the product out of stock when it is included (no charge)', function () {
    vaccinateWithProduct(['product_id' => $this->rabies, 'quantity' => 1, 'charge' => false])->assertCreated();

    tenant()->runAs($this->clinic->id, function () {
        expect(Sale::count())->toBe(0)
            ->and(InventoryMovement::where('type', 'clinical_use')->count())->toBe(1);
    });
    expect(rabiesStock())->toEqual(9);
});

it('opens a new account when today\'s one is already paid', function () {
    vaccinateWithProduct(['product_id' => $this->rabies, 'quantity' => 1, 'charge' => true])->assertCreated();
    $first = tenant()->runAs($this->clinic->id, fn () => Sale::sole());
    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/sales/{$first->id}/payments", ['method' => 'cash', 'amount' => 35000])->assertOk();

    vaccinateWithProduct(['product_id' => $this->rabies, 'quantity' => 1, 'charge' => true])->assertCreated();

    expect(tenant()->runAs($this->clinic->id, fn () => Sale::count()))->toBe(2);
});

it('rejects products on record types that do not use them, and keeps nothing if the record fails', function () {
    $this->freshRequest()->withToken($this->vetToken)->postJson('/api/v1/pets/'.$this->pet->id.'/clinical-records', [
        'type' => 'note', 'branch_id' => $this->branch->id, 'note' => ['body' => 'Hola'],
        'product_use' => ['product_id' => $this->rabies, 'quantity' => 1, 'charge' => true],
    ])->assertJsonValidationErrors('product_use');

    // Unknown product: the whole record is rolled back.
    vaccinateWithProduct(['product_id' => '01a0f831-0000-7000-8000-000000000000', 'quantity' => 1, 'charge' => true])->assertNotFound();
    tenant()->runAs($this->clinic->id, function () {
        expect(ClinicalRecord::count())->toBe(0)->and(SaleItem::count())->toBe(0);
    });
});
