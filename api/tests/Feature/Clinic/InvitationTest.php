<?php

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\ClinicInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    [$this->clinic, $this->admin] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinic);
});

/** Invites and returns the plain token captured from the (faked) email. */
function inviteAndCaptureToken(string $email, string $role = 'veterinarian'): string
{
    test()->freshRequest()->withToken(test()->token)
        ->postJson('/api/v1/invitations', ['email' => $email, 'role' => $role])
        ->assertCreated();

    $plainToken = null;
    Notification::assertSentTo(
        new AnonymousNotifiable,
        ClinicInvitationNotification::class,
        function (ClinicInvitationNotification $n, array $channels, AnonymousNotifiable $notifiable) use ($email, &$plainToken) {
            $plainToken = $n->plainToken;

            return $notifiable->routes['mail'] === $email;
        },
    );

    return $plainToken;
}

it('emails an invitation and never returns the token in the API', function () {
    $response = $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/invitations', ['email' => 'Nueva@Vet.co', 'role' => 'veterinarian'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'nueva@vet.co');

    expect($response->json('data'))->not->toHaveKey('token')->not->toHaveKey('token_hash');
    Notification::assertSentTimes(ClinicInvitationNotification::class, 1);
});

it('lets a new person create an account and join', function () {
    $plainToken = inviteAndCaptureToken('nueva@vet.co');

    $this->freshRequest()->getJson("/api/v1/invitations/{$plainToken}")
        ->assertOk()
        ->assertJsonPath('data.clinic.name', $this->clinic->name)
        ->assertJsonPath('data.account_exists', false);

    $clinicToken = $this->freshRequest()->postJson("/api/v1/invitations/{$plainToken}/accept", [
        'name' => 'Laura Gómez',
        'password' => 'clave1234',
        'password_confirmation' => 'clave1234',
    ])->assertCreated()->json('data.token');

    $this->freshRequest()->withToken($clinicToken)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.role.name', 'veterinarian');

    // Single use.
    $this->freshRequest()->getJson("/api/v1/invitations/{$plainToken}")->assertUnprocessable();
});

it('lets an existing user of another clinic join after logging in', function () {
    [$otherClinic, $existing] = clinicWithMember(User::factory()->create(['email' => 'ya@vet.co']));
    $plainToken = inviteAndCaptureToken('ya@vet.co', 'receptionist');

    $this->freshRequest()->postJson("/api/v1/invitations/{$plainToken}/accept", [
        'name' => 'X', 'password' => 'clave1234', 'password_confirmation' => 'clave1234',
    ])->assertConflict();

    $newToken = $this->freshRequest()->withToken(clinicToken($existing, $otherClinic))
        ->postJson("/api/v1/invitations/{$plainToken}/accept-existing")
        ->assertOk()
        ->json('data.token');

    $this->freshRequest()->withToken($newToken)->getJson('/api/v1/me')
        ->assertJsonPath('data.clinic.id', $this->clinic->id)
        ->assertJsonPath('data.role.name', 'receptionist');
});

it('cannot be accepted by a user with a different email', function () {
    [$otherClinic, $intruder] = clinicWithMember();
    $plainToken = inviteAndCaptureToken('destinatario@vet.co');

    $this->freshRequest()->withToken(clinicToken($intruder, $otherClinic))
        ->postJson("/api/v1/invitations/{$plainToken}/accept-existing")
        ->assertUnprocessable();
});

it('rejects expired and revoked invitations', function () {
    $expired = inviteAndCaptureToken('vence@vet.co');
    tenant()->runAs($this->clinic->id, fn () => Invitation::where('email', 'vence@vet.co')->update(['expires_at' => now()->subMinute()]));

    $this->freshRequest()->getJson("/api/v1/invitations/{$expired}")->assertUnprocessable();

    $revoked = inviteAndCaptureToken('revocada@vet.co');
    $id = tenant()->runAs($this->clinic->id, fn () => Invitation::where('email', 'revocada@vet.co')->value('id'));
    $this->freshRequest()->withToken($this->token)->deleteJson("/api/v1/invitations/{$id}")->assertNoContent();

    $this->freshRequest()->getJson("/api/v1/invitations/{$revoked}")->assertUnprocessable();
});

it('does not invite someone who is already a member', function () {
    $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/invitations', ['email' => $this->admin->email, 'role' => 'veterinarian'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('does not list invitations of other clinics', function () {
    [$otherClinic, $otherAdmin] = clinicWithMember();
    $this->freshRequest()->withToken(clinicToken($otherAdmin, $otherClinic))
        ->postJson('/api/v1/invitations', ['email' => 'ajena@vet.co', 'role' => 'veterinarian'])
        ->assertCreated();

    inviteAndCaptureToken('propia@vet.co');

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/invitations')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'propia@vet.co');
});
