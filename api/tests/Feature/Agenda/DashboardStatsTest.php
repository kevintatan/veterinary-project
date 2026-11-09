<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 10:00', 'America/Bogota'));

    [$this->clinic, $this->admin] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->pet = makePet(makeOwner($this->clinic));

    inClinic($this->clinic);
    $this->branch = Branch::first();
    $this->consult = Service::create(['name' => 'Consulta', 'category' => 'consultation', 'duration_minutes' => 30]);
    $this->vaccine = Service::create(['name' => 'Vacunación', 'category' => 'vaccination', 'duration_minutes' => 15]);
    tenant()->forget();
});

/** Creates an appointment at a Bogotá local time with a final status. */
function appointmentAt(string $local, string $status, ?Service $service = null): void
{
    tenant()->runAs(test()->clinic->id, function () use ($local, $status, $service) {
        $start = Carbon::parse($local, 'America/Bogota');

        Appointment::create([
            'branch_id' => test()->branch->id,
            'pet_id' => test()->pet->id,
            'owner_id' => test()->pet->owner_id,
            'service_id' => ($service ?? test()->consult)->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'status' => $status,
        ]);
    });
}

function stats(int $days = 30)
{
    return test()->freshRequest()->withToken(test()->token)->getJson("/api/v1/dashboard/stats?days={$days}")->assertOk();
}

it('returns one row per day of the range, including empty days', function () {
    $daily = stats(7)->assertJsonPath('data.range.from', '2026-09-29')->assertJsonPath('data.range.to', '2026-10-05')->json('data.daily');

    expect($daily)->toHaveCount(7)
        ->and($daily[0])->toBe(['date' => '2026-09-29', 'completed' => 0, 'missed' => 0, 'upcoming' => 0]);
});

it('groups by day in the clinic timezone, not in UTC', function () {
    // 8 p.m. in Bogotá = 01:00 UTC of the next day: it must count on the 4th.
    appointmentAt('2026-10-04 20:00', 'completed');

    $daily = collect(stats(7)->json('data.daily'))->keyBy('date');

    expect($daily['2026-10-04']['completed'])->toBe(1)
        ->and($daily['2026-10-05']['completed'])->toBe(0);
});

it('splits attended, missed and upcoming appointments', function () {
    appointmentAt('2026-10-05 08:00', 'completed');
    appointmentAt('2026-10-05 09:00', 'no_show');
    appointmentAt('2026-10-05 09:30', 'cancelled');
    appointmentAt('2026-10-05 11:00', 'confirmed');

    $today = collect(stats(7)->json('data.daily'))->firstWhere('date', '2026-10-05');

    expect($today)->toBe(['date' => '2026-10-05', 'completed' => 1, 'missed' => 2, 'upcoming' => 1]);
});

it('computes the no-show rate over due appointments and compares with the previous period', function () {
    // Current 7 days: 3 attended, 1 no-show, 1 cancelled → 25 % (cancellations excluded).
    appointmentAt('2026-10-01 09:00', 'completed');
    appointmentAt('2026-10-02 09:00', 'completed');
    appointmentAt('2026-10-03 09:00', 'completed');
    appointmentAt('2026-10-04 09:00', 'no_show');
    appointmentAt('2026-10-04 10:00', 'cancelled');
    // Previous 7 days: 1 attended, 1 no-show → 50 %.
    appointmentAt('2026-09-25 09:00', 'completed');
    appointmentAt('2026-09-26 09:00', 'no_show');

    stats(7)
        ->assertJsonPath('data.totals.completed', ['value' => 3, 'previous' => 1])
        ->assertJsonPath('data.totals.no_show_rate', ['value' => 25, 'previous' => 50])
        ->assertJsonPath('data.totals.cancelled', ['value' => 1, 'previous' => 0]);
});

it('returns a null rate when nothing was due', function () {
    stats(7)->assertJsonPath('data.totals.no_show_rate.value', null);
});

it('ranks booked services and ignores cancelled ones', function () {
    appointmentAt('2026-10-01 09:00', 'completed', $this->vaccine);
    appointmentAt('2026-10-02 09:00', 'scheduled', $this->vaccine);
    appointmentAt('2026-10-03 09:00', 'completed');
    appointmentAt('2026-10-03 10:00', 'cancelled');

    stats(7)->assertJsonPath('data.top_services', [
        ['name' => 'Vacunación', 'total' => 2],
        ['name' => 'Consulta', 'total' => 1],
    ]);
});

it('counts new patients in the period', function () {
    makePet(makeOwner($this->clinic)); // created "now"

    stats(7)->assertJsonPath('data.totals.new_patients.value', 2); // + the one from beforeEach
});

it('only counts the clinic in context', function () {
    appointmentAt('2026-10-05 08:00', 'completed');
    [$other, $otherAdmin] = clinicWithMember();

    $this->freshRequest()->withToken(clinicToken($otherAdmin, $other))
        ->getJson('/api/v1/dashboard/stats?days=7')
        ->assertJsonPath('data.totals.completed.value', 0)
        ->assertJsonPath('data.top_services', []);
});

it('only accepts the supported ranges', function () {
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/dashboard/stats?days=365')->assertUnprocessable();
    stats()->assertJsonPath('data.range.days', 30); // default
});
