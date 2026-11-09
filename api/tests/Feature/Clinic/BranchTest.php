<?php

use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Subscription;

beforeEach(function () {
    [$this->clinicA, $this->admin] = clinicWithMember();
    [$this->clinicB] = clinicWithMember();

    $this->branchB = tenant()->runAs($this->clinicB->id, fn () => Branch::first());
    $this->token = clinicToken($this->admin, $this->clinicA);
});

it('lists only the branches of the clinic in the token', function () {
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['id' => $this->branchB->id]);
});

it('returns 404 for a branch of another clinic (read and write)', function () {
    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/branches/{$this->branchB->id}")->assertNotFound();

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/branches/{$this->branchB->id}", ['name' => 'Hackeada'])
        ->assertNotFound();

    expect(tenant()->runAs($this->clinicB->id, fn () => $this->branchB->fresh()->name))->not->toBe('Hackeada');
});

it('returns 404 for malformed ids instead of a database error', function () {
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/branches/no-es-un-uuid')->assertNotFound();
});

it('lets an admin create a branch stamped with its clinic', function () {
    $id = $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/branches', ['name' => 'Sede Norte', 'address' => 'Calle 100 # 15-20'])
        ->assertCreated()
        ->json('data.id');

    expect(tenant()->runAs($this->clinicA->id, fn () => Branch::find($id)?->clinic_id))->toBe($this->clinicA->id);
});

it('ignores a clinic_id sent in the body', function () {
    $id = $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/branches', ['name' => 'Intento', 'clinic_id' => $this->clinicB->id])
        ->assertCreated()
        ->json('data.id');

    expect(tenant()->runAs($this->clinicB->id, fn () => Branch::find($id)))->toBeNull();
});

it('forbids branch management to non-admin roles', function () {
    [, $vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinicA);
    $vetToken = clinicToken($vet, $this->clinicA);

    $this->freshRequest()->withToken($vetToken)->getJson('/api/v1/branches')->assertOk();
    $this->freshRequest()->withToken($vetToken)
        ->postJson('/api/v1/branches', ['name' => 'No autorizada'])
        ->assertForbidden()
        ->assertJsonPath('message', 'No tienes permiso para esta acción.');
});

it('enforces the branch limit of the plan', function () {
    tenant()->runAs($this->clinicA->id, fn () => Subscription::first()->update([
        'plan_id' => Plan::where('code', 'basic')->value('id'),
    ]));

    $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/branches', ['name' => 'Segunda'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('keeps at least one active branch', function () {
    $branchA = tenant()->runAs($this->clinicA->id, fn () => Branch::first());

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/branches/{$branchA->id}", ['is_active' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_active');
});
