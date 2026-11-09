<?php

use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\ClinicMembership;
use App\Models\Service;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', 'America/Bogota')); // Monday

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);

    inClinic($this->clinic);
    $this->branch = Branch::first();
    $this->consult = Service::create(['name' => 'Consulta', 'category' => 'consultation', 'duration_minutes' => 30]);
    tenant()->forget();

    // Branch hours: Monday 08:00–10:00 and 14:00–15:00.
    $this->freshRequest()->withToken($this->token)->putJson('/api/v1/schedules', [
        'branch_id' => $this->branch->id,
        'ranges' => [
            ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '10:00'],
            ['weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:00'],
        ],
    ])->assertOk()->assertJsonCount(2, 'data');
});

function vetSlots(string $date = '2026-10-05'): array
{
    $rows = test()->freshRequest()->withToken(test()->token)
        ->getJson('/api/v1/appointments/availability?'.http_build_query([
            'branch_id' => test()->branch->id,
            'date' => $date,
            'service_id' => test()->consult->id,
            'user_id' => test()->vet->id,
        ]))
        ->assertOk()
        ->json('data');

    return array_map(fn (array $slot) => substr($slot['starts_at'], 11, 5), $rows[0]['slots'] ?? []);
}

it('offers slots from the branch schedule in clinic time', function () {
    expect(vetSlots())->toBe(['08:00', '08:30', '09:00', '09:30', '14:00', '14:30']);
});

it('removes booked and blocked times', function () {
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/appointments', [
        'branch_id' => $this->branch->id,
        'service_id' => $this->consult->id,
        'starts_at' => '2026-10-05T08:30',
        'user_ids' => [$this->vet->id],
    ])->assertCreated();

    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/schedule-blocks', [
        'user_id' => $this->vet->id,
        'starts_at' => '2026-10-05T14:00',
        'ends_at' => '2026-10-05T15:00',
        'reason' => 'Cita médica',
    ])->assertCreated();

    expect(vetSlots())->toBe(['08:00', '09:00', '09:30']);
});

it('prefers the professional schedule over the branch schedule', function () {
    $this->freshRequest()->withToken($this->token)->putJson('/api/v1/schedules', [
        'branch_id' => $this->branch->id,
        'user_id' => $this->vet->id,
        'ranges' => [['weekday' => 1, 'start_time' => '16:00', 'end_time' => '17:00']],
    ])->assertOk();

    expect(vetSlots())->toBe(['16:00', '16:30']);
});

it('does not offer past times', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 09:10', 'America/Bogota'));

    expect(vetSlots())->toBe(['09:30', '14:00', '14:30']);
});

it('returns nothing on days without schedule', function () {
    expect(vetSlots('2026-10-06'))->toBe([]); // Tuesday
});

it('rejects overlapping ranges in a schedule', function () {
    $this->freshRequest()->withToken($this->token)->putJson('/api/v1/schedules', [
        'branch_id' => $this->branch->id,
        'ranges' => [
            ['weekday' => 2, 'start_time' => '08:00', 'end_time' => '12:00'],
            ['weekday' => 2, 'start_time' => '11:00', 'end_time' => '13:00'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('ranges');
});

it('lets only admins change agenda settings', function () {
    $vetToken = clinicToken($this->vet, $this->clinic);

    $this->freshRequest()->withToken($vetToken)->getJson('/api/v1/services')->assertOk();
    $this->freshRequest()->withToken($vetToken)
        ->postJson('/api/v1/services', ['name' => 'Baño', 'category' => 'grooming', 'duration_minutes' => 60])
        ->assertForbidden();
});

it('lists the agenda columns: active members shown in the agenda of the branch', function () {
    [, $hidden] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    tenant()->runAs($this->clinic->id, fn () => ClinicMembership::where('user_id', $hidden->id)->update(['shows_in_agenda' => false]));
    $otherBranch = tenant()->runAs($this->clinic->id, fn () => Branch::create(['name' => 'Sede Norte']));
    [$foreignClinic, $stranger] = clinicWithMember();

    $names = $this->freshRequest()->withToken($this->token)
        ->getJson("/api/v1/appointments/professionals?branch_id={$this->branch->id}")
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toContain($this->admin->name, $this->vet->name)
        ->not->toContain($hidden->name, $stranger->name);

    // Nobody was assigned to the new branch.
    $this->freshRequest()->withToken($this->token)
        ->getJson("/api/v1/appointments/professionals?branch_id={$otherBranch->id}")
        ->assertJsonCount(0, 'data');
});
