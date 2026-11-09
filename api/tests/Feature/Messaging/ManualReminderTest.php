<?php

use App\Actions\Patients\RecordConsents;
use App\Auth\ClinicRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Message;
use App\Models\Owner;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', 'America/Bogota')); // Monday

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->clinic->update(['name' => 'Veterinaria Anipet']);
    $this->token = clinicToken($this->admin, $this->clinic);

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Luz Marina Pérez', 'mobile_phone' => '+573123334455']);
    $this->pet = makePet($this->owner, ['name' => 'Kira']);
    allowWhatsapp($this->owner);

    $this->silent = makeOwner($this->clinic, ['full_name' => 'Jorge Ruiz', 'mobile_phone' => '+573001112233']);
    $this->silentPet = makePet($this->silent, ['name' => 'Toby']);
});

function allowWhatsapp(Owner $owner): void
{
    tenant()->runAs($owner->clinic_id, fn () => app(RecordConsents::class)($owner, ['data_processing' => true, 'whatsapp' => true], 'in_person', null));
}

function reminderAppointment(string $localStart, $pet, string $status = 'scheduled'): Appointment
{
    return tenant()->runAs(test()->clinic->id, function () use ($localStart, $pet, $status) {
        $start = Carbon::parse($localStart, 'America/Bogota');

        return Appointment::create([
            'branch_id' => Branch::first()->id,
            'pet_id' => $pet->id,
            'owner_id' => $pet->owner_id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'status' => $status,
        ]);
    });
}

function dueVaccine(string $pet, string $occurredAt, string $nextControl, string $name = 'Rabia'): string
{
    return test()->freshRequest()->withToken(clinicToken(test()->vet, test()->clinic))
        ->postJson("/api/v1/pets/{$pet}/clinical-records", [
            'type' => 'vaccination',
            'occurred_at' => $occurredAt,
            'next_control_at' => $nextControl,
            'vaccination' => ['vaccine_name' => $name],
        ])
        ->assertCreated()
        ->json('data.id');
}

it('lists tomorrow pending appointments and the vaccines coming due, with whether WhatsApp is allowed', function () {
    $kira = reminderAppointment('2026-10-06 09:30', $this->pet);
    reminderAppointment('2026-10-06 10:00', $this->silentPet, 'confirmed');
    reminderAppointment('2026-10-06 11:00', $this->pet, 'cancelled');    // not to remind
    reminderAppointment('2026-10-07 09:00', $this->pet);                 // another day

    $due = dueVaccine($this->pet->id, '2025-10-08 10:00', '2026-10-08');
    $old = dueVaccine($this->silentPet->id, '2025-10-01 10:00', '2026-10-09');
    dueVaccine($this->silentPet->id, '2026-10-02 10:00', '2027-10-02'); // already re-vaccinated: the old one is answered
    dueVaccine($this->pet->id, '2025-12-01 10:00', '2026-12-01', 'Parvovirus'); // later but another vaccine: the rabies one is still due

    $data = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reminders/due')->assertOk()->json('data');

    expect($data['date'])->toBe('2026-10-06')
        ->and(collect($data['appointments'])->pluck('pet.name')->all())->toBe(['Kira', 'Toby'])
        ->and($data['appointments'][0]['id'])->toBe($kira->id)
        ->and($data['appointments'][0]['whatsapp_allowed'])->toBeTrue()
        ->and($data['appointments'][1]['whatsapp_allowed'])->toBeFalse()
        ->and($data['appointments'][1]['whatsapp_blocked_reason'])->toBe('El tutor no autorizó mensajes por WhatsApp.')
        ->and(collect($data['controls'])->pluck('id')->all())->toBe([$due])
        ->and($data['controls'][0]['summary'])->toBe('Rabia')
        ->and(collect($data['controls'])->pluck('id'))->not->toContain($old);
});

it('builds the WhatsApp link with the clinic wording and logs it', function () {
    $appointment = reminderAppointment('2026-10-06 09:30', $this->pet);

    $data = $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/reminders/manual', ['type' => 'appointment', 'id' => $appointment->id])
        ->assertOk()
        ->json('data');

    expect($data['body'])->toBe(
        'Hola Luz, te escribimos de *Veterinaria Anipet*. Te recordamos que *Kira* tiene cita a las 9:30 a. m. del martes 6 de octubre. '
        .'Si no puedes asistir, por favor avísanos respondiendo este mensaje. ¡Gracias!'
    )->and($data['url'])->toStartWith('https://wa.me/573123334455?text=Hola%20Luz');

    $message = tenant()->runAs($this->clinic->id, fn () => Message::sole());
    expect($message->provider)->toBe('manual')
        ->and($message->status)->toBe('opened')
        ->and($message->sent_by)->toBe($this->admin->id)
        ->and($message->related_id)->toBe($appointment->id);

    $last = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/reminders/due')->json('data.appointments.0.last_reminder');
    expect($last['by'])->toBe($this->admin->name)->and($last['provider'])->toBe('manual');
});

it('writes the next-control reminder for a vaccine', function () {
    $id = dueVaccine($this->pet->id, '2025-10-08 10:00', '2026-10-08');

    $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/reminders/manual', ['type' => 'control', 'id' => $id])
        ->assertOk()
        ->assertJsonPath('data.body', 'Hola Luz, te escribimos de *Veterinaria Anipet*. A *Kira* le corresponde el refuerzo de la vacuna Rabia el 8 de octubre. ¿Te ayudamos a agendar la cita? Responde este mensaje. ¡Gracias!');
});

it('refuses without WhatsApp authorization and never across clinics', function () {
    $toby = reminderAppointment('2026-10-06 10:00', $this->silentPet);

    $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/reminders/manual', ['type' => 'appointment', 'id' => $toby->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.id.0', 'El tutor no autorizó mensajes por WhatsApp.');

    $kira = reminderAppointment('2026-10-06 09:30', $this->pet);
    [$other, $stranger] = clinicWithMember();

    $this->freshRequest()->withToken(clinicToken($stranger, $other))
        ->postJson('/api/v1/reminders/manual', ['type' => 'appointment', 'id' => $kira->id])
        ->assertNotFound();

    expect(tenant()->runAs($this->clinic->id, fn () => Message::count()))->toBe(0);
});
