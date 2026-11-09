<?php

use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\StaffNotification;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->receptionist] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->recepToken = clinicToken($this->receptionist, $this->clinic);

    inClinic($this->clinic);
    $this->branch = Branch::first();
    $this->north = Branch::create(['name' => 'Sede Norte']);
    tenant()->forget();
});

function newProduct(array $overrides = [], ?string $token = null): array
{
    return test()->freshRequest()->withToken($token ?? test()->token)->postJson('/api/v1/products', array_merge([
        'name' => 'Vacuna múltiple canina',
        'sku' => 'vac-mult',
        'kind' => 'vaccine',
        'unit' => 'dose',
        'sale_price' => 45000,
        'cost_price' => 22000,
        'tax_rate' => 0,
        'tracks_stock' => true,
        'min_stock' => 5,
        'initial_quantity' => 12,
        'branch_id' => test()->branch->id,
        'lot_number' => 'L-1',
        'expires_on' => '2027-06-30',
    ], $overrides))->assertCreated()->json('data');
}

function stockIn(string $productId, Branch $branch): float
{
    return (float) collect(test()->freshRequest()->withToken(test()->token)->getJson("/api/v1/products/{$productId}")->json('data.stocks'))
        ->firstWhere('branch.id', $branch->id)['quantity'];
}

it('creates a product with its initial stock and lists it per branch', function () {
    $product = newProduct();
    expect($product['sku'])->toBe('VAC-MULT')
        ->and($product['stock_total'])->toEqual(12)
        ->and($product['movements'][0]['type']['value'])->toBe('initial');

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/products?branch_id='.$this->branch->id)->assertOk()
        ->assertJsonPath('data.0.stock', 12)
        ->assertJsonPath('data.0.stock_status', 'ok')
        ->assertJsonPath('data.0.cost_price', 22000);

    // Same name (any case) is a duplicate.
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/products', ['name' => 'VACUNA MÚLTIPLE CANINA', 'kind' => 'vaccine', 'unit' => 'dose', 'sale_price' => 1, 'tax_rate' => 0, 'tracks_stock' => true])
        ->assertJsonValidationErrors('name');
});

it('hides costs from reception and lets only the admin adjust', function () {
    $product = newProduct();

    $this->freshRequest()->withToken($this->recepToken)->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.cost_price', null);
    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/products/{$product['id']}/adjust", ['branch_id' => $this->branch->id, 'reason' => 'loss', 'quantity' => 1])->assertForbidden();

    // Reception does register purchases; the vet only looks.
    $this->freshRequest()->withToken($this->recepToken)->postJson("/api/v1/products/{$product['id']}/purchase", ['branch_id' => $this->branch->id, 'quantity' => 10])->assertCreated();
    $this->freshRequest()->withToken(clinicToken($this->vet, $this->clinic))->postJson("/api/v1/products/{$product['id']}/purchase", ['branch_id' => $this->branch->id, 'quantity' => 1])->assertForbidden();

    expect(stockIn($product['id'], $this->branch))->toEqual(22);
});

it('records purchases, counts, losses and transfers, keeping the running balance', function () {
    $id = newProduct()['id'];

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/purchase", ['branch_id' => $this->branch->id, 'quantity' => 20, 'unit_cost' => 24000, 'lot_number' => 'L-2', 'expires_on' => '2027-12-31'])->assertCreated();
    expect(stockIn($id, $this->branch))->toEqual(32)
        ->and(tenant()->runAs($this->clinic->id, fn () => (float) Product::find($id)->cost_price))->toEqual(24000); // last purchase cost

    // Physical count: sets the stock (32 → 30).
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/adjust", ['branch_id' => $this->branch->id, 'reason' => 'count', 'quantity' => 30])->assertCreated();
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/adjust", ['branch_id' => $this->branch->id, 'reason' => 'count', 'quantity' => 30])->assertJsonValidationErrors('quantity'); // nothing to adjust
    // Loss: takes out.
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/adjust", ['branch_id' => $this->branch->id, 'reason' => 'damage', 'quantity' => 2])->assertCreated();
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/adjust", ['branch_id' => $this->branch->id, 'reason' => 'other', 'quantity' => 1])->assertJsonValidationErrors('note');
    // Transfer 8 to the north branch.
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/transfer", ['branch_id' => $this->branch->id, 'to_branch_id' => $this->north->id, 'quantity' => 8])->assertCreated();
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/transfer", ['branch_id' => $this->branch->id, 'to_branch_id' => $this->branch->id, 'quantity' => 1])->assertJsonValidationErrors('to_branch_id');

    expect(stockIn($id, $this->branch))->toEqual(20)->and(stockIn($id, $this->north))->toEqual(8);

    $movements = collect($this->freshRequest()->withToken($this->token)->getJson("/api/v1/products/{$id}")->json('data.movements'));
    expect($movements->pluck('type.value')->all())->toBe(['transfer_in', 'transfer_out', 'adjustment', 'adjustment', 'purchase', 'initial'])
        ->and($movements->firstWhere('type.value', 'transfer_out')['balance_after'])->toEqual(20);
});

it('allows negative stock and flags it instead of blocking the clinic', function () {
    $id = newProduct(['initial_quantity' => 1])['id'];

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/adjust", ['branch_id' => $this->branch->id, 'reason' => 'loss', 'quantity' => 3])->assertCreated();

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/products?status=low&branch_id='.$this->branch->id)
        ->assertJsonPath('data.0.stock', -2)
        ->assertJsonPath('data.0.stock_status', 'negative');
});

it('alerts low stock and lots about to expire, until the lot is handled', function () {
    $id = newProduct(['initial_quantity' => 4, 'expires_on' => now()->addDays(30)->toDateString()])['id']; // min 5 → low; lot expires in 30 days

    $alerts = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/inventory/alerts?branch_id='.$this->branch->id)->json('data');
    expect($alerts['low'][0]['status'])->toBe('low')
        ->and($alerts['expiring'][0]['lot_number'])->toBe('L-1');

    // Taking the expired lot out closes its alert.
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/adjust", [
        'branch_id' => $this->branch->id, 'reason' => 'expired', 'quantity' => 4, 'lot_movement_id' => $alerts['expiring'][0]['movement_id'],
    ])->assertCreated();

    $alerts = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/inventory/alerts?branch_id='.$this->branch->id)->json('data');
    expect($alerts['expiring'])->toBe([])->and($alerts['low'][0]['status'])->toBe('out');
});

it('sends a daily inventory notice to whoever manages inventory', function () {
    newProduct(['initial_quantity' => 2]);

    $this->artisan('inventory:alerts')->assertSuccessful();

    $titles = tenant()->runAs($this->clinic->id, fn () => StaffNotification::where('type', 'inventory_alert')->get()->groupBy('user_id'));
    expect($titles->keys()->all())->toContain($this->admin->id, $this->receptionist->id)
        ->not->toContain($this->vet->id)
        ->and($titles[$this->admin->id]->first()->title)->toBe('Inventario: 1 producto con existencias bajas');
});

it('does not move stock of products that do not track it', function () {
    $id = newProduct(['name' => 'Baño medicado', 'sku' => null, 'tracks_stock' => false, 'initial_quantity' => null, 'branch_id' => null])['id'];

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/products/{$id}/purchase", ['branch_id' => $this->branch->id, 'quantity' => 1])->assertJsonValidationErrors('product_id');

    // And a product with stock can't stop tracking it.
    $tracked = newProduct()['id'];
    $this->freshRequest()->withToken($this->token)->patchJson("/api/v1/products/{$tracked}", ['name' => 'Vacuna múltiple canina', 'kind' => 'vaccine', 'unit' => 'dose', 'sale_price' => 45000, 'tax_rate' => 0, 'tracks_stock' => false])
        ->assertJsonValidationErrors('tracks_stock');
});

it('imports the product list from a CSV saved by Excel in Spanish', function () {
    newProduct(); // "Vacuna múltiple canina" already exists → updated, not duplicated

    // Excel (Windows, Spanish): ";" separator, Windows-1252 accents, prices with thousand dots.
    $csv = "Nombre;Código;Tipo;Unidad;Precio venta;Costo;IVA;Mínimo;Existencia\r\n"
        ."Vacuna múltiple canina;;vacuna;dosis;48.000;23.000;0;5;99\r\n"
        ."NexGard 10-25 kg;NEX-M;Medicamento;Tableta;68.000;41.500;19;10;30\r\n"
        ."Collar isabelino;;producto;unidad;$ 15.000;;19;;4\r\n"
        ."Algo raro;;juguete;unidad;1000;;0;;\r\n"
        .";;producto;unidad;1000;;0;;\r\n";
    $file = UploadedFile::fake()->createWithContent('productos.csv', mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));

    $result = $this->freshRequest()->withToken($this->token)->post('/api/v1/inventory/import', ['file' => $file, 'branch_id' => $this->branch->id], ['Accept' => 'application/json'])
        ->assertOk()->json('data');

    expect($result['created'])->toBe(2)
        ->and($result['updated'])->toBe(1)
        ->and(collect($result['errors'])->pluck('line')->all())->toBe([5, 6])
        ->and($result['errors'][0]['message'])->toContain('Tipo no reconocido');

    tenant()->runAs($this->clinic->id, function () {
        $nex = Product::where('sku', 'NEX-M')->sole();
        expect((float) $nex->sale_price)->toEqual(68000)
            ->and((float) $nex->cost_price)->toEqual(41500)
            ->and($nex->kind)->toBe('medication')
            ->and((float) Product::where('name', 'Vacuna múltiple canina')->sole()->sale_price)->toEqual(48000)
            ->and(Product::count())->toBe(3);
    });
    // New products got their initial stock; the existing one kept its stock (12, not 99).
    $vacuna = tenant()->runAs($this->clinic->id, fn () => Product::where('name', 'Vacuna múltiple canina')->value('id'));
    expect(stockIn($vacuna, $this->branch))->toEqual(12);

    $this->freshRequest()->withToken($this->token)->get('/api/v1/inventory/import-template')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('keeps products and stock inside their clinic', function () {
    $id = newProduct()['id'];
    [$other, $otherAdmin] = clinicWithMember();

    $this->freshRequest()->withToken(clinicToken($otherAdmin, $other))->getJson("/api/v1/products/{$id}")->assertNotFound();
    $this->freshRequest()->withToken(clinicToken($otherAdmin, $other))->getJson('/api/v1/products')->assertOk()->assertJsonPath('data', []);
    expect(tenant()->runAs($other->id, fn () => InventoryMovement::count()))->toBe(0);
});

it('does not report as out of stock what a branch never carries', function () {
    newProduct(); // only in the main branch

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/products?branch_id='.$this->north->id)->assertJsonPath('data.0.stock_status', 'none');
    expect($this->freshRequest()->withToken($this->token)->getJson('/api/v1/inventory/alerts?branch_id='.$this->north->id)->json('data.low'))->toBe([]);
});
