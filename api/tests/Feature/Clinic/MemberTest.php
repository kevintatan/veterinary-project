<?php

use App\Actions\Members\UpdateMember;
use App\Auth\ClinicRole;
use App\Models\ClinicMembership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    [$this->clinicA, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinicA);

    // The vet also works in clinic B, where they are admin.
    [$this->clinicB] = clinicWithMember($this->vet, ClinicRole::Admin);
    [, $this->strangerInB] = clinicWithMember(clinic: $this->clinicB);

    $this->token = clinicToken($this->admin, $this->clinicA);
    $this->vetMembership = tenant()->runAs($this->clinicA->id, fn () => ClinicMembership::where('user_id', $this->vet->id)->first());
});

it('lists only members of the clinic, without revealing their other clinics', function () {
    $response = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/members')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.*.user.email'))->not->toContain($this->strangerInB->email);
    expect(json_encode($response->json()))->not->toContain($this->clinicB->id)
        ->not->toContain($this->clinicB->name);
});

it('changes a role only in the clinic in context', function () {
    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/members/{$this->vetMembership->id}", ['role' => 'receptionist'])
        ->assertOk()
        ->assertJsonPath('data.role.name', 'receptionist');

    $vet = User::find($this->vet->id);
    expect(tenant()->runAs($this->clinicA->id, fn () => $vet->getRoleNames()->all()))->toBe(['receptionist']);

    $vet = User::find($this->vet->id);
    expect(tenant()->runAs($this->clinicB->id, fn () => $vet->getRoleNames()->all()))->toBe(['admin']);
});

it('cannot touch a membership of another clinic', function () {
    $foreign = tenant()->runAs($this->clinicB->id, fn () => ClinicMembership::where('user_id', $this->strangerInB->id)->first());

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/members/{$foreign->id}", ['status' => 'disabled'])
        ->assertNotFound();
});

it('does not let an admin change their own role or disable themselves', function () {
    $own = tenant()->runAs($this->clinicA->id, fn () => ClinicMembership::where('user_id', $this->admin->id)->first());

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/members/{$own->id}", ['role' => 'veterinarian'])
        ->assertUnprocessable();
});

it('lets an admin demote another admin while one remains', function () {
    [, $admin2] = clinicWithMember(clinic: $this->clinicA);
    $adminMembership = tenant()->runAs($this->clinicA->id, fn () => ClinicMembership::where('user_id', $this->admin->id)->first());

    $this->freshRequest()->withToken(clinicToken($admin2, $this->clinicA))
        ->patchJson("/api/v1/members/{$adminMembership->id}", ['role' => 'veterinarian'])
        ->assertOk();
});

it('never leaves the clinic without an active admin', function () {
    // Guard inside the action (reachable once clinics can customize roles).
    inClinic($this->clinicA);
    $onlyAdmin = ClinicMembership::where('user_id', $this->admin->id)->first();

    app(UpdateMember::class)(User::find($this->vet->id), $onlyAdmin, ['status' => 'disabled']);
})->throws(ValidationException::class, 'al menos un administrador');

it('revokes the member tokens of this clinic when disabling them', function () {
    $vetTokenA = clinicToken($this->vet, $this->clinicA);
    $vetTokenB = clinicToken($this->vet, $this->clinicB);

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/members/{$this->vetMembership->id}", ['status' => 'disabled'])
        ->assertOk();

    $this->freshRequest()->withToken($vetTokenA)->getJson('/api/v1/me')->assertUnauthorized();
    $this->freshRequest()->withToken($vetTokenB)->getJson('/api/v1/me')->assertOk();
});

it('forbids member management to non-admins', function () {
    $this->freshRequest()->withToken(clinicToken($this->vet, $this->clinicA))
        ->patchJson("/api/v1/members/{$this->vetMembership->id}", ['shows_in_agenda' => false])
        ->assertForbidden();
});
