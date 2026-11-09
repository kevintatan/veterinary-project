<?php

use App\Actions\Patients\RecordConsents;
use App\Auth\ClinicRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Reminder;
use App\Models\ReminderRule;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', 'America/Bogota'));

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);
    tenant()->runAs($this->clinic->id, fn () => ReminderRule::createDefaults());

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Luz Marina Pérez', 'mobile_phone' => '+573123334455']);
    $this->pet = makePet($this->owner, ['name' => 'Kira']);
    tenant()->runAs($this->clinic->id, fn () => app(RecordConsents::class)($this->owner, ['data_processing' => true, 'whatsapp' => true], 'in_person', null));

    $this->appointment = tenant()->runAs($this->clinic->id, fn () => Appointment::create([
        'branch_id' => Branch::first()->id, 'pet_id' => $this->pet->id, 'owner_id' => $this->owner->id,
        'starts_at' => Carbon::parse('2026-10-06 09:30', 'America/Bogota'), 'ends_at' => Carbon::parse('2026-10-06 10:00', 'America/Bogota'), 'status' => 'scheduled',
    ]));
});

function settingsPayload(array $window, bool $appointmentsOn = true, int $offset = -1440): array
{
    $rules = tenant()->runAs(test()->clinic->id, fn () => ReminderRule::all());

    return [
        'window' => $window,
        'rules' => $rules->map(fn ($r) => [
            'id' => $r->id,
            'is_active' => $r->trigger === 'appointment' ? $appointmentsOn : true,
            'offset_minutes' => $r->trigger === 'appointment' ? $offset : $r->offset_minutes,
        ])->all(),
    ];
}

it('shows the rules, window, plan and usage', function () {
    $data = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reminders/settings')->assertOk()->json('data');

    expect($data['driver'])->toBe('fake')
        ->and($data['plan']['automatic'])->toBeTrue()
        ->and($data['plan']['monthly_limit'])->toBe(500)
        ->and($data['window'])->toBe(['send_from' => '08:00', 'send_until' => '19:00', 'min_notice_hours' => 3])
        ->and(collect($data['rules'])->pluck('offset_minutes')->sort()->values()->all())->toBe([-10080, -1440]);
});

it('re-plans when the clinic changes the rules or the window', function () {
    $this->freshRequest()->withToken($this->token)
        ->putJson('/api/v1/reminders/settings', settingsPayload(['send_from' => '10:00', 'send_until' => '18:00', 'min_notice_hours' => 3], offset: -2880))
        ->assertOk()
        ->assertJsonPath('data.window.send_from', '10:00');

    // 48 h before Tue 9:30 = Sun 9:30 → already past; the event is still > 12 h away → next window opening (Mon 10:00).
    $reminder = tenant()->runAs($this->clinic->id, fn () => Reminder::where('remindable_id', $this->appointment->id)->where('status', 'pending')->sole());
    expect($reminder->scheduled_for->setTimezone('America/Bogota')->format('Y-m-d H:i'))->toBe('2026-10-05 10:00');

    $this->freshRequest()->withToken($this->token)
        ->putJson('/api/v1/reminders/settings', settingsPayload(['send_from' => '08:00', 'send_until' => '19:00', 'min_notice_hours' => 3], appointmentsOn: false))
        ->assertOk();
    expect(tenant()->runAs($this->clinic->id, fn () => Reminder::where('remindable_id', $this->appointment->id)->where('status', 'pending')->count()))->toBe(0);
});

it('validates the window and lets only managers change it', function () {
    $this->freshRequest()->withToken($this->token)
        ->putJson('/api/v1/reminders/settings', settingsPayload(['send_from' => '19:00', 'send_until' => '08:00', 'min_notice_hours' => 3]))
        ->assertJsonValidationErrors('window.send_until');

    $this->freshRequest()->withToken(clinicToken($this->vet, $this->clinic))
        ->putJson('/api/v1/reminders/settings', settingsPayload(['send_from' => '08:00', 'send_until' => '19:00', 'min_notice_hours' => 3]))
        ->assertForbidden();
});

it('lists what is scheduled, what was sent and the owner messages log', function () {
    $upcoming = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reminders?view=upcoming')->assertOk()->json('data');
    expect($upcoming)->toHaveCount(1)->and($upcoming[0]['pet']['name'])->toBe('Kira')->and($upcoming[0]['status']['label'])->toBe('Programado');

    Carbon::setTestNow(Carbon::parse('2026-10-05 09:31', 'America/Bogota'));
    $this->artisan('reminders:dispatch');

    $sent = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reminders?view=sent')->json('data');
    expect($sent[0]['message']['provider'])->toBe('fake');

    $log = $this->freshRequest()->withToken($this->token)->getJson("/api/v1/owners/{$this->owner->id}/messages")->assertOk()->json('data');
    expect($log)->toHaveCount(1)->and($log[0]['purpose'])->toBe('appointment_reminder');

    $due = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reminders/due?date=2026-10-06')->json('data.appointments.0.automatic');
    expect($due['status']['value'])->toBe('sent');
});
