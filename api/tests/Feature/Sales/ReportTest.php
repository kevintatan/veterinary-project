<?php

use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Subscription;
use App\Sales\Sales;
use Illuminate\Support\Carbon;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->receptionist] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->owner = makeOwner($this->clinic, ['full_name' => 'Paola Andrea Díaz', 'document_type' => 'CC', 'document_number' => '1095812233']);

    // Yesterday: a 30.000 consultation, unpaid. Today: it gets paid (an "abono" from an earlier sale).
    // Today: consultation 60.000 paid by card; vaccine 40.000 with 15.000 by Nequi (25.000 pending);
    // a 10.000 sale voided. Times in Bogotá.
    tenant()->runAs($this->clinic->id, function () {
        $sales = app(Sales::class);
        $branch = Branch::first();
        $consult = Service::create(['name' => 'Consulta general', 'category' => 'consultation', 'duration_minutes' => 30, 'price' => 60000, 'tax_rate' => 0]);
        $vaccine = Service::create(['name' => 'Vacunación', 'category' => 'vaccination', 'duration_minutes' => 15, 'price' => 40000, 'tax_rate' => 0]);
        $add = fn ($sale, $data) => $sales->addItem($sale, ['quantity' => 1, ...$data], $this->admin);

        Carbon::setTestNow(Carbon::parse('2026-10-04 16:00', 'America/Bogota'));
        $yesterday = $sales->open($branch, $this->owner, $this->admin);
        $add($yesterday, ['type' => 'custom', 'description' => 'Control', 'unit_price' => 30000]);

        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00', 'America/Bogota'));
        $sales->addPayment($yesterday, 'cash', 30000, null, $this->receptionist);

        $a = $sales->open($branch, $this->owner, $this->admin);
        $add($a, ['type' => 'service', 'service_id' => $consult->id]);
        $sales->addPayment($a->refresh(), 'card', 60000, 'AP-1', $this->receptionist);

        $b = $sales->open($branch, null, $this->receptionist);
        $add($b, ['type' => 'service', 'service_id' => $vaccine->id]);
        $sales->addPayment($b->refresh(), 'nequi', 15000, 'N-9', $this->receptionist);

        $c = $sales->open($branch, null, $this->receptionist);
        $add($c, ['type' => 'custom', 'description' => 'Collar', 'unit_price' => 10000]);
        $sales->void($c->refresh(), 'Error de digitación', $this->admin);

        Carbon::setTestNow(Carbon::parse('2026-10-05 18:00', 'America/Bogota'));
    });
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->recepToken = clinicToken($this->receptionist, $this->clinic);
});

it('closes the day: money by method, earlier sales paid today, pending and voids', function () {
    $close = $this->freshRequest()->withToken($this->recepToken)->getJson('/api/v1/reports/daily-close?date=2026-10-05')->assertOk()->json('data');

    expect($close['collected'])->toEqual(105000) // 30.000 + 60.000 + 15.000
        ->and($close['collected_from_earlier_sales'])->toEqual(30000)
        ->and(collect($close['by_method'])->pluck('total', 'method.value')->all())->toEqual(['card' => 60000, 'cash' => 30000, 'nequi' => 15000])
        ->and($close['sales'])->toEqual(['count' => 2, 'total' => 100000]) // the voided one doesn't count
        ->and($close['pending']['total'])->toEqual(25000)
        ->and($close['voided_sales'][0]['reason'])->toBe('Error de digitación')
        ->and($close['by_receiver'][0]['name'])->toBe($this->receptionist->name);

    // The vet doesn't charge: no daily close.
    $this->freshRequest()->withToken(clinicToken($this->vet, $this->clinic))->getJson('/api/v1/reports/daily-close')->assertForbidden();
});

it('reports a period against the previous one, by day, item, seller and method (admin, Pro)', function () {
    $report = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reports/sales?from=2026-10-05&to=2026-10-05')->assertOk()->json('data');

    expect($report['summary'])->toMatchArray(['sales_count' => 2, 'sold' => 100000.0, 'collected' => 105000.0, // includes the 30.000 paid today for yesterday's sale
        'pending' => 25000.0, 'average_ticket' => 50000.0, 'voided_count' => 1])
        ->and($report['previous'])->toMatchArray(['sales_count' => 1, 'sold' => 30000.0])
        ->and($report['daily'])->toEqual([['date' => '2026-10-05', 'sold' => 100000.0, 'collected' => 105000.0]])
        ->and($report['top_services'][0])->toMatchArray(['name' => 'Consulta general', 'total' => 60000.0])
        ->and($report['by_type']['service'])->toEqual(100000);

    // Reception closes the day but doesn't see the sales report.
    $this->freshRequest()->withToken($this->recepToken)->getJson('/api/v1/reports/sales?from=2026-10-05&to=2026-10-05')->assertForbidden();
});

it('exports items and payments for the accountant as Excel-friendly CSV', function () {
    $items = $this->freshRequest()->withToken($this->token)->get('/api/v1/reports/sales/export?kind=items&from=2026-10-04&to=2026-10-05')->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
    expect($items)->toStartWith("\xEF\xBB\xBFFecha venta;Venta N.º;Estado")
        ->toContain('Paola Andrea Díaz;CC 1095812233')
        ->toContain('Consulta general;1;60000;0;0;0;60000')
        ->toContain('Anulada');

    $payments = $this->freshRequest()->withToken($this->token)->get('/api/v1/reports/sales/export?kind=payments&from=2026-10-05&to=2026-10-05')->assertOk()->getContent();
    expect(substr_count($payments, "\r\n"))->toBe(4) // header + 3 payments
        ->and($payments)->toContain('Tarjeta;AP-1;60000');
});

it('keeps the sales report and export for plans that include them', function () {
    tenant()->runAs($this->clinic->id, fn () => Subscription::first()->update(['plan_id' => Plan::where('code', 'basic')->value('id'), 'status' => 'active', 'current_period_end' => now()->addMonth()]));

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reports/sales?from=2026-10-05&to=2026-10-05')->assertStatus(402);
    $this->freshRequest()->withToken($this->token)->get('/api/v1/reports/sales/export?kind=items&from=2026-10-05&to=2026-10-05')->assertStatus(402);
    // The daily close is for everyone.
    $this->freshRequest()->withToken($this->recepToken)->getJson('/api/v1/reports/daily-close?date=2026-10-05')->assertOk();
});
