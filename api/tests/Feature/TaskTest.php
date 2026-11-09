<?php

use App\Auth\ClinicRole;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 10:00', 'America/Bogota'));

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    [, $this->reception] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    $this->receptionToken = clinicToken($this->reception, $this->clinic);
    $this->pet = makePet(makeOwner($this->clinic, ['full_name' => 'Carolina Restrepo']), ['name' => 'Luna']);
});

function createTask(array $payload, ?string $token = null)
{
    return test()->freshRequest()->withToken($token ?? test()->receptionToken)->postJson('/api/v1/tasks', $payload);
}

it('creates a task assigned to a colleague, linked to a pet and its owner', function () {
    createTask([
        'title' => 'Llamar para saber cómo sigue Luna',
        'pet_id' => $this->pet->id,
        'assignee_id' => $this->vet->id,
        'due_at' => '2026-10-06T09:00',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status.label', 'Pendiente')
        ->assertJsonPath('data.assignee.name', $this->vet->name)
        ->assertJsonPath('data.owner.full_name', 'Carolina Restrepo')
        ->assertJsonPath('data.due_at', '2026-10-06T09:00:00-05:00');
});

it('assigns the task to its creator by default', function () {
    createTask(['title' => 'Pedir vacunas al proveedor'])->assertJsonPath('data.assignee.name', $this->reception->name);
});

it('lists my pending tasks and flags overdue ones', function () {
    createTask(['title' => 'Vencida', 'assignee_id' => $this->vet->id, 'due_at' => '2026-10-04T09:00'])->assertCreated();
    createTask(['title' => 'De otro', 'assignee_id' => $this->admin->id])->assertCreated();

    $this->freshRequest()->withToken(clinicToken($this->vet, $this->clinic))->getJson('/api/v1/tasks')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Vencida')
        ->assertJsonPath('data.0.is_overdue', true);

    $this->freshRequest()->withToken($this->receptionToken)->getJson('/api/v1/tasks?scope=created')->assertJsonCount(2, 'data');
});

it('completes and reopens a task recording who did it', function () {
    $id = createTask(['title' => 'Enviar fórmula por correo'])->json('data.id');

    $this->freshRequest()->withToken($this->receptionToken)
        ->patchJson("/api/v1/tasks/{$id}", ['status' => 'done'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'done')
        ->assertJsonPath('data.completed_at', '2026-10-05T10:00:00-05:00');

    $this->freshRequest()->withToken($this->receptionToken)
        ->patchJson("/api/v1/tasks/{$id}", ['status' => 'pending'])
        ->assertOk()
        ->assertJsonPath('data.completed_at', null);
});

it('only lets the creator, the assignee or an admin change a task', function () {
    $id = createTask(['title' => 'Privada'])->json('data.id');

    $this->freshRequest()->withToken(clinicToken($this->vet, $this->clinic))
        ->patchJson("/api/v1/tasks/{$id}", ['status' => 'discarded'])
        ->assertForbidden();

    $this->freshRequest()->withToken(clinicToken($this->admin, $this->clinic))
        ->patchJson("/api/v1/tasks/{$id}", ['status' => 'discarded'])
        ->assertOk();
});

it('does not assign tasks to people or pets of another clinic', function () {
    [$other, $stranger] = clinicWithMember();
    $foreignPet = makePet(makeOwner($other));

    createTask(['title' => 'X', 'assignee_id' => $stranger->id])->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
    createTask(['title' => 'X', 'pet_id' => $foreignPet->id])->assertUnprocessable()->assertJsonValidationErrors('pet_id');

    $foreignTask = createTask(['title' => 'Ajena'], clinicToken($stranger, $other))->json('data.id');
    $this->freshRequest()->withToken(clinicToken($this->admin, $this->clinic))
        ->patchJson("/api/v1/tasks/{$foreignTask}", ['status' => 'done'])
        ->assertNotFound();
});
