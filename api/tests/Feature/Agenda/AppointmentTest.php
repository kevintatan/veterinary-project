<?php

use App\Auth\ClinicRole;
use App\Models\Appointment;
use App\Models\AppointmentStatusChange;
use App\Models\Branch;
use App\Models\ScheduleBlock;
use App\Models\Service;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', 'America/Bogota')); // Monday

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    [, $this->vet2] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Carolina Restrepo']);
    $this->pet = makePet($this->owner, ['name' => 'Luna']);

    inClinic($this->clinic);
    $this->branch = Branch::first();
    $this->consult = Service::create(['name' => 'Consulta general', 'category' => 'consultation', 'duration_minutes' => 30, 'price' => 60000]);
    tenant()->forget();
});

function book(array $overrides = [])
{
    return test()->freshRequest()->withToken(test()->token)->postJson('/api/v1/appointments', array_merge([
        'branch_id' => test()->branch->id,
        'pet_id' => test()->pet->id,
        'service_id' => test()->consult->id,
        'starts_at' => '2026-10-05T09:00',
        'user_ids' => [test()->vet->id],
    ], $overrides));
}

it('books an appointment in clinic time, taking duration from the service and owner from the pet', function () {
    book()
        ->assertCreated()
        ->assertJsonPath('data.starts_at', '2026-10-05T09:00:00-05:00')
        ->assertJsonPath('data.ends_at', '2026-10-05T09:30:00-05:00')
        ->assertJsonPath('data.owner.full_name', 'Carolina Restrepo')
        ->assertJsonPath('data.pet.name', 'Luna')
        ->assertJsonPath('data.status.label', 'Programada')
        ->assertJsonPath('data.users.0.role', 'primary');

    $stored = tenant()->runAs($this->clinic->id, fn () => Appointment::first());
    expect($stored->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-05 14:00'); // UTC in the database
});

it('records the creation in the status history', function () {
    $id = book()->json('data.id');

    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/appointments/{$id}")
        ->assertJsonPath('data.status_history.0.to', 'scheduled')
        ->assertJsonPath('data.status_history.0.channel', 'staff')
        ->assertJsonPath('data.status_history.0.changed_by', $this->admin->name);
});

it('rejects a pet that does not belong to the given owner', function () {
    $other = makeOwner($this->clinic);

    book(['owner_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('pet_id');
});

it('prevents double booking the same professional', function () {
    book()->assertCreated();

    book(['starts_at' => '2026-10-05T09:15'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('starts_at');

    // Another professional, or back-to-back, is fine.
    book(['starts_at' => '2026-10-05T09:15', 'user_ids' => [$this->vet2->id]])->assertCreated();
    book(['starts_at' => '2026-10-05T09:30'])->assertCreated();
});

it('frees the slot when an appointment is cancelled', function () {
    $id = book()->json('data.id');

    $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/appointments/{$id}/status", ['status' => 'cancelled', 'cancellation_reason' => 'El tutor viajó'])
        ->assertOk();

    book()->assertCreated();
});

it('allows overlaps when the clinic disables the check', function () {
    $this->clinic->update(['settings' => ['prevent_overlaps' => false]]);

    book()->assertCreated();
    book()->assertCreated();
});

it('ignores time-unset appointments for overlaps', function () {
    book()->assertCreated();
    book(['is_time_unset' => true])->assertCreated();
});

it('respects schedule blocks of the professional', function () {
    tenant()->runAs($this->clinic->id, fn () => ScheduleBlock::create([
        'user_id' => $this->vet->id,
        'starts_at' => Carbon::parse('2026-10-05 08:00', 'America/Bogota'),
        'ends_at' => Carbon::parse('2026-10-05 12:00', 'America/Bogota'),
        'reason' => 'Capacitación',
    ]));

    book()->assertUnprocessable()->assertJsonPath('errors.starts_at.0', "{$this->vet->name} no está disponible en ese horario (bloqueo de agenda).");
});

it('follows the status lifecycle and rejects invalid transitions', function () {
    $id = book()->json('data.id');
    $status = fn (string $to, array $extra = []) => $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/appointments/{$id}/status", ['status' => $to] + $extra);

    $status('completed')->assertUnprocessable()->assertJsonPath('errors.status.0', 'Una cita programada no puede pasar a atendida.');
    $status('confirmed')->assertOk()->assertJsonPath('data.status.label', 'Confirmada');
    $status('checked_in')->assertOk();
    $status('in_progress')->assertOk();
    $status('completed', ['note' => 'Sin novedades'])
        ->assertOk()
        ->assertJsonPath('data.allowed_next_statuses', [])
        ->assertJsonCount(5, 'data.status_history');

    $status('cancelled', ['cancellation_reason' => 'x'])->assertUnprocessable();
});

it('requires a reason to cancel', function () {
    $id = book()->json('data.id');

    $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/appointments/{$id}/status", ['status' => 'cancelled'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cancellation_reason');
});

it('reschedules keeping the duration and re-checking conflicts', function () {
    $first = book()->json('data.id');
    book(['starts_at' => '2026-10-05T10:00'])->assertCreated();

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/appointments/{$first}", ['starts_at' => '2026-10-05T11:00'])
        ->assertOk()
        ->assertJsonPath('data.ends_at', '2026-10-05T11:30:00-05:00');

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/appointments/{$first}", ['starts_at' => '2026-10-05T10:10'])
        ->assertUnprocessable();

    // Editing notes only does not touch the times.
    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/appointments/{$first}", ['internal_notes' => 'Traer exámenes'])
        ->assertOk()
        ->assertJsonPath('data.starts_at', '2026-10-05T11:00:00-05:00')
        ->assertJsonPath('data.ends_at', '2026-10-05T11:30:00-05:00');
});

it('does not reschedule closed appointments', function () {
    $id = book()->json('data.id');
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/appointments/{$id}/status", ['status' => 'no_show'])->assertOk();

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/appointments/{$id}", ['starts_at' => '2026-10-06T09:00'])
        ->assertUnprocessable();
});

it('lists the agenda for a date range with filters', function () {
    book()->assertCreated();
    book(['starts_at' => '2026-10-06T09:00'])->assertCreated();
    book(['starts_at' => '2026-10-05T11:00', 'user_ids' => [$this->vet2->id]])->assertCreated();

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/appointments?from=2026-10-05&to=2026-10-05')
        ->assertJsonCount(2, 'data');

    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/appointments?from=2026-10-05&to=2026-10-06&user_id={$this->vet->id}")
        ->assertJsonCount(2, 'data');
});

it('keeps the status history append-only', function () {
    book();
    inClinic($this->clinic);

    AppointmentStatusChange::first()->update(['to_status' => 'completed']);
})->throws(LogicException::class);

it('does not accept professionals or pets from another clinic', function () {
    [$other, $foreignVet] = clinicWithMember(role: ClinicRole::Veterinarian);
    $foreignPet = makePet(makeOwner($other));

    book(['user_ids' => [$foreignVet->id]])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
    book(['pet_id' => $foreignPet->id])->assertUnprocessable()->assertJsonValidationErrors('pet_id');
});

it('hides appointments of other clinics', function () {
    [$other, $otherAdmin] = clinicWithMember();
    $otherBranch = tenant()->runAs($other->id, fn () => Branch::first());
    $foreignId = $this->freshRequest()->withToken(clinicToken($otherAdmin, $other))->postJson('/api/v1/appointments', [
        'branch_id' => $otherBranch->id,
        'starts_at' => '2026-10-05T09:00',
        'user_ids' => [$otherAdmin->id],
    ])->assertCreated()->json('data.id');

    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/appointments/{$foreignId}")->assertNotFound();
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/appointments?from=2026-10-05&to=2026-10-05')->assertJsonCount(0, 'data');
});
