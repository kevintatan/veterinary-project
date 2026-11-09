<?php

use App\Auth\ClinicRole;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\User;

/*
 * docs/database.md §3 — safeguards for users that work in several clinics.
 */

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'vet@zooetia.co']);
    [$this->clinicA] = clinicWithMember($this->user, ClinicRole::Admin, Clinic::factory()->create(['name' => 'Clínica A']));
    [$this->clinicB] = clinicWithMember($this->user, ClinicRole::Veterinarian, Clinic::factory()->create(['name' => 'Clínica B']));
});

function login(string $email = 'vet@zooetia.co', string $password = 'password')
{
    return test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

it('logs in directly when the user belongs to a single clinic', function () {
    [, $solo] = clinicWithMember(User::factory()->create(['email' => 'solo@zooetia.co']));

    $token = login('solo@zooetia.co')
        ->assertOk()
        ->assertJsonPath('data.requires_clinic_selection', false)
        ->json('data.token');

    $this->freshRequest()->withToken($token)->getJson('/api/v1/me')->assertOk();
});

it('asks to choose a clinic when the user belongs to several', function () {
    login()
        ->assertOk()
        ->assertJsonPath('data.requires_clinic_selection', true)
        ->assertJsonCount(2, 'data.clinics')
        ->assertJsonPath('data.clinics.0.name', 'Clínica A');
});

it('does not let the selection token access clinic data', function () {
    $selectionToken = login()->json('data.token');

    $this->freshRequest()->withToken($selectionToken)->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Selecciona una clínica para continuar.');
});

it('exchanges the selection token for a clinic token', function () {
    $selectionToken = login()->json('data.token');

    $clinicToken = $this->freshRequest()->withToken($selectionToken)
        ->postJson('/api/v1/auth/select-clinic', ['clinic_id' => $this->clinicB->id])
        ->assertOk()
        ->json('data.token');

    $this->freshRequest()->withToken($clinicToken)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.clinic.id', $this->clinicB->id);

    // The selection token is single-use.
    $this->freshRequest()->withToken($selectionToken)
        ->postJson('/api/v1/auth/select-clinic', ['clinic_id' => $this->clinicA->id])
        ->assertUnauthorized();
});

it('refuses to select a clinic the user does not belong to', function () {
    $foreignClinic = Clinic::factory()->create();
    $selectionToken = login()->json('data.token');

    $this->freshRequest()->withToken($selectionToken)
        ->postJson('/api/v1/auth/select-clinic', ['clinic_id' => $foreignClinic->id])
        ->assertForbidden();

    expect($this->user->tokens()->whereNotNull('clinic_id')->count())->toBe(0);
});

it('rejects wrong credentials without revealing which part failed', function () {
    login(password: 'incorrecta')
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'El correo o la contraseña no son correctos.');

    login(email: 'noexiste@zooetia.co')
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'El correo o la contraseña no son correctos.');
});

it('evaluates roles and permissions per clinic', function () {
    $this->freshRequest()->withToken(clinicToken($this->user, $this->clinicA))->getJson('/api/v1/me')
        ->assertJsonPath('data.role.name', 'admin')
        ->assertJsonFragment(['members.manage']);

    $meInB = $this->freshRequest()->withToken(clinicToken($this->user, $this->clinicB))->getJson('/api/v1/me')
        ->assertJsonPath('data.role.name', 'veterinarian');

    expect($meInB->json('data.permissions'))->not->toContain('members.manage');
});

it('switches clinic issuing a new token and revoking the old one', function () {
    $tokenA = clinicToken($this->user, $this->clinicA);

    $tokenB = $this->freshRequest()->withToken($tokenA)
        ->postJson('/api/v1/auth/switch-clinic', ['clinic_id' => $this->clinicB->id])
        ->assertOk()
        ->json('data.token');

    $this->freshRequest()->withToken($tokenB)->getJson('/api/v1/me')->assertJsonPath('data.clinic.id', $this->clinicB->id);
    $this->freshRequest()->withToken($tokenA)->getJson('/api/v1/me')->assertUnauthorized();
});

it('cuts access immediately when the clinic disables the member, only for that clinic', function () {
    $tokenA = clinicToken($this->user, $this->clinicA);
    $tokenB = clinicToken($this->user, $this->clinicB);

    tenant()->runAs($this->clinicA->id, fn () => ClinicMembership::where('user_id', $this->user->id)
        ->update(['status' => ClinicMembership::STATUS_DISABLED]));

    $this->freshRequest()->withToken($tokenA)->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'Ya no tienes acceso a esta clínica.');

    // Its tokens for clinic A were revoked; clinic B is unaffected.
    expect($this->user->tokens()->where('clinic_id', $this->clinicA->id)->count())->toBe(0);
    $this->freshRequest()->withToken($tokenB)->getJson('/api/v1/me')->assertOk();

    // And it no longer shows up at login.
    login()->assertJsonPath('data.requires_clinic_selection', false);
});

it('blocks every member of a suspended clinic', function () {
    $tokenA = clinicToken($this->user, $this->clinicA);

    $this->clinicA->update(['status' => Clinic::STATUS_SUSPENDED]);

    $this->freshRequest()->withToken($tokenA)->getJson('/api/v1/me')->assertForbidden();
});

it('never leaves a clinic in context after a request', function () {
    $this->freshRequest()->withToken(clinicToken($this->user, $this->clinicA))->getJson('/api/v1/me')->assertOk();

    expect(tenant()->has())->toBeFalse();
});

it('lists only the user own active clinics, marking the current one', function () {
    [$foreign] = clinicWithMember(); // the user is not a member

    $this->freshRequest()->withToken(clinicToken($this->user, $this->clinicB))->getJson('/api/v1/me/clinics')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Clínica A')
        ->assertJsonPath('data.0.is_current', false)
        ->assertJsonPath('data.1.is_current', true)
        ->assertJsonMissing(['id' => $foreign->id]);
});
