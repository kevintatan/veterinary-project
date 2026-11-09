<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\TutorInvitation;
use App\Notifications\TutorPasswordCodeNotification;
use App\Notifications\TutorPortalInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    $this->clinic->update(['name' => 'Veterinaria Anipet']);
    $this->token = clinicToken($this->admin, $this->clinic);

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Luz Marina Pérez', 'mobile_phone' => '+573123334455', 'email' => 'luz@example.com']);
    $this->kira = makePet($this->owner, ['name' => 'Kira']);
});

/** The clinic invites; returns the plain token from the link. */
function portalInvite($clinicToken, $owner, string $channel = 'link'): string
{
    $url = test()->freshRequest()->withToken($clinicToken)
        ->postJson("/api/v1/owners/{$owner->id}/portal-invitation", ['channel' => $channel])
        ->assertCreated()
        ->json('data.url');

    return basename($url);
}

function portalActivate(string $token, string $email = 'luz@example.com'): string
{
    return test()->freshRequest()->postJson("/api/v1/portal/invitations/{$token}/accept", [
        'name' => 'Luz Marina', 'email' => $email, 'password' => 'Kira2026x', 'password_confirmation' => 'Kira2026x',
    ])->assertCreated()->json('data.token');
}

it('activates the portal once from the clinic invitation and shows the pets with their card', function () {
    $token = portalInvite($this->token, $this->owner);

    $this->freshRequest()->getJson("/api/v1/portal/invitations/{$token}")
        ->assertOk()
        ->assertJsonPath('data.clinic.name', 'Veterinaria Anipet')
        ->assertJsonPath('data.owner.first_name', 'Luz')
        ->assertJsonPath('data.phone_hint', '••• 4455')
        ->assertJsonPath('data.pets', ['Kira'])
        ->assertJsonPath('data.account_exists', false);

    $session = portalActivate($token);

    // Upcoming appointment shows in the portal.
    tenant()->runAs($this->clinic->id, fn () => Appointment::create([
        'branch_id' => Branch::first()->id, 'pet_id' => $this->kira->id, 'owner_id' => $this->owner->id,
        'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addMinutes(30), 'status' => 'scheduled',
    ]));

    $pets = $this->freshRequest()->withToken($session)->getJson('/api/v1/portal/pets')->assertOk()->json('data');
    expect($pets)->toHaveCount(1)
        ->and($pets[0]['name'])->toBe('Kira')
        ->and($pets[0]['clinic']['name'])->toBe('Veterinaria Anipet')
        ->and($pets[0]['next_appointment'])->not->toBeNull();

    $this->freshRequest()->withToken($session)->getJson("/api/v1/portal/pets/{$this->kira->id}")
        ->assertOk()
        ->assertJsonPath('data.pet.name', 'Kira')
        ->assertJsonPath('data.clinic.name', 'Veterinaria Anipet')
        ->assertJsonPath('data.public_url', $this->kira->fresh()->publicUrl())
        ->assertJsonCount(1, 'data.upcoming');
    $this->freshRequest()->withToken($session)->get("/api/v1/portal/pets/{$this->kira->id}/qr")->assertOk()->assertHeader('Content-Type', 'image/png');

    // The clinic sees it activated.
    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/owners/{$this->owner->id}/portal")
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.pending_invitation', null);
});

it('uses each link once, expires it in 7 days and only stores its hash', function () {
    $token = portalInvite($this->token, $this->owner);
    expect(TutorInvitation::withoutGlobalScopes()->where('token_hash', $token)->exists())->toBeFalse();

    portalActivate($token);
    $this->freshRequest()->getJson("/api/v1/portal/invitations/{$token}")->assertStatus(410);

    $other = makeOwner($this->clinic, ['mobile_phone' => '+573001112233']);
    $expired = portalInvite($this->token, $other);
    Carbon::setTestNow(now()->addDays(8));
    $this->freshRequest()->getJson("/api/v1/portal/invitations/{$expired}")->assertStatus(410);
});

it('keeps one account per phone: a second clinic adds itself after signing in', function () {
    $session = portalActivate(portalInvite($this->token, $this->owner));

    [$other, $otherAdmin] = clinicWithMember();
    $otherOwner = makeOwner($other, ['full_name' => 'Luz M. Pérez', 'mobile_phone' => '+573123334455']);
    makePet($otherOwner, ['name' => 'Tom']);
    $token = portalInvite(clinicToken($otherAdmin, $other), $otherOwner);

    $this->freshRequest()->getJson("/api/v1/portal/invitations/{$token}")->assertJsonPath('data.account_exists', true);
    $this->freshRequest()->postJson("/api/v1/portal/invitations/{$token}/accept", [
        'name' => 'Luz', 'email' => 'otro@example.com', 'password' => 'Kira2026x', 'password_confirmation' => 'Kira2026x',
    ])->assertJsonValidationErrors('email');

    // Signs in (no code) and adds the clinic.
    $session = $this->freshRequest()->postJson('/api/v1/portal/login', ['email' => 'LUZ@example.com', 'password' => 'Kira2026x'])->assertOk()->json('data.token');
    $this->freshRequest()->withToken($session)->postJson("/api/v1/portal/invitations/{$token}/link")->assertOk();

    $names = collect($this->freshRequest()->withToken($session)->getJson('/api/v1/portal/pets')->json('data'))->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['Kira', 'Tom']);
});

it('rejects an invitation meant for another phone', function () {
    $session = portalActivate(portalInvite($this->token, $this->owner));
    $stranger = makeOwner($this->clinic, ['mobile_phone' => '+573009998877']);
    $token = portalInvite($this->token, $stranger);

    $this->freshRequest()->withToken($session)->postJson("/api/v1/portal/invitations/{$token}/link")->assertJsonValidationErrors('token');
});

it('never mixes portal and staff tokens, nor shows other owners pets', function () {
    $session = portalActivate(portalInvite($this->token, $this->owner));
    $someoneElse = makePet(makeOwner($this->clinic, ['mobile_phone' => '+573005556677']), ['name' => 'Ajeno']);

    $this->freshRequest()->withToken($session)->getJson("/api/v1/portal/pets/{$someoneElse->id}")->assertNotFound();
    $this->freshRequest()->withToken($session)->getJson("/api/v1/portal/pets/{$someoneElse->id}/photo")->assertNotFound();

    // A portal token on staff routes, and a staff token on the portal.
    $this->freshRequest()->withToken($session)->getJson('/api/v1/owners')->assertForbidden();
    $this->freshRequest()->withToken($session)->getJson('/api/v1/me')->assertForbidden();
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/portal/pets')->assertUnauthorized();
});

it('sends the invitation by email and needs a phone to invite', function () {
    Notification::fake();
    portalInvite($this->token, $this->owner, 'email');
    Notification::assertSentTo(new AnonymousNotifiable, TutorPortalInvitationNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'luz@example.com');

    $whatsapp = $this->freshRequest()->withToken($this->token)->postJson("/api/v1/owners/{$this->owner->id}/portal-invitation", ['channel' => 'whatsapp'])->json('data');
    expect($whatsapp['whatsapp_url'])->toStartWith('https://wa.me/573123334455?text=')
        ->and($whatsapp['text'])->toContain('*Veterinaria Anipet*');

    $noPhone = makeOwner($this->clinic, ['mobile_phone' => null]);
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/owners/{$noPhone->id}/portal-invitation", ['channel' => 'link'])->assertJsonValidationErrors('owner');
});

it('resets a forgotten password with a code by email and closes other sessions', function () {
    Notification::fake();
    $old = portalActivate(portalInvite($this->token, $this->owner));

    // Same answer whether the email exists or not.
    $unknown = $this->freshRequest()->postJson('/api/v1/portal/password/forgot', ['email' => 'nadie@example.com'])->assertOk()->json('data.message');
    $known = $this->freshRequest()->postJson('/api/v1/portal/password/forgot', ['email' => 'LUZ@example.com'])->assertOk()->json('data.message');
    expect($known)->toBe($unknown);

    $code = null;
    Notification::assertSentTo(new AnonymousNotifiable, TutorPasswordCodeNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });
    Notification::assertSentTimes(TutorPasswordCodeNotification::class, 1);

    $wrong = $code === '111111' ? '222222' : '111111';
    $this->freshRequest()->postJson('/api/v1/portal/password/reset', ['email' => 'luz@example.com', 'code' => $wrong, 'password' => 'Nueva2026x', 'password_confirmation' => 'Nueva2026x'])
        ->assertJsonValidationErrors('code');

    $session = $this->freshRequest()->postJson('/api/v1/portal/password/reset', ['email' => 'luz@example.com', 'code' => $code, 'password' => 'Nueva2026x', 'password_confirmation' => 'Nueva2026x'])
        ->assertOk()->json('data.token');

    $this->freshRequest()->withToken($session)->getJson('/api/v1/portal/me')->assertOk();
    $this->freshRequest()->withToken($old)->getJson('/api/v1/portal/me')->assertUnauthorized(); // old sessions closed
    $this->freshRequest()->postJson('/api/v1/portal/login', ['email' => 'luz@example.com', 'password' => 'Nueva2026x'])->assertOk();

    // A code works once.
    $this->freshRequest()->postJson('/api/v1/portal/password/reset', ['email' => 'luz@example.com', 'code' => $code, 'password' => 'Otra2026xx', 'password_confirmation' => 'Otra2026xx'])
        ->assertJsonValidationErrors('code');
});

it('locks a reset code after 5 wrong attempts and expires it in 15 minutes', function () {
    Notification::fake();
    portalActivate(portalInvite($this->token, $this->owner));
    $this->freshRequest()->postJson('/api/v1/portal/password/forgot', ['email' => 'luz@example.com'])->assertOk();
    $code = null;
    Notification::assertSentTo(new AnonymousNotifiable, TutorPasswordCodeNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });
    $wrong = $code === '111111' ? '222222' : '111111';
    $body = fn (string $c) => ['email' => 'luz@example.com', 'code' => $c, 'password' => 'Nueva2026x', 'password_confirmation' => 'Nueva2026x'];

    foreach (range(1, 5) as $i) {
        $this->freshRequest()->postJson('/api/v1/portal/password/reset', $body($wrong))->assertJsonValidationErrors('code');
    }
    // Even the right code no longer works.
    $this->freshRequest()->postJson('/api/v1/portal/password/reset', $body($code))->assertJsonValidationErrors('code');

    // A new code, expired after 15 minutes.
    Carbon::setTestNow(now()->addMinutes(2));
    $this->freshRequest()->postJson('/api/v1/portal/password/forgot', ['email' => 'luz@example.com'])->assertOk();
    $fresh = null;
    Notification::assertSentTo(new AnonymousNotifiable, TutorPasswordCodeNotification::class, function ($n) use (&$fresh) {
        $fresh = $n->code;

        return true;
    });
    Carbon::setTestNow(now()->addMinutes(16));
    $this->freshRequest()->postJson('/api/v1/portal/password/reset', $body($fresh))->assertJsonValidationErrors('code');
});

it('offers "Pedir una cita" only for clinics that take online requests, with the tutor data to prefill', function () {
    $session = portalActivate(portalInvite($this->token, $this->owner));

    $this->freshRequest()->withToken($session)->getJson('/api/v1/portal/booking')->assertOk()->assertJsonPath('data', []);

    $this->freshRequest()->withToken($this->token)->putJson('/api/v1/online-booking/settings', [
        'enabled' => true, 'min_notice_hours' => 2, 'max_days_ahead' => 30, 'choose_professional' => false, 'message' => null,
    ])->assertOk();

    $this->freshRequest()->withToken($session)->getJson('/api/v1/portal/booking')->assertOk()
        ->assertJsonPath('data.0.slug', $this->clinic->slug)
        ->assertJsonPath('data.0.prefill.phone', $this->owner->mobile_phone)
        ->assertJsonPath('data.0.pets.0.name', 'Kira');

    // Staff tokens can't use it.
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/portal/booking')->assertUnauthorized();
});
