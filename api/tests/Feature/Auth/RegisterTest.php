<?php

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'clinic_name' => 'Veterinaria Anipet',
        'name' => 'Juan Pérez',
        'email' => 'Juan@Anipet.co',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
        'phone' => '+573001234567',
        'accept_terms' => true,
    ], $overrides);
}

it('registers a clinic with main branch, admin and a 15-day Pro trial', function () {
    $response = $this->postJson('/api/v1/auth/register', registrationPayload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['token', 'clinic' => ['id', 'name']]]);

    $clinic = Clinic::findOrFail($response->json('data.clinic.id'));
    $user = User::where('email', 'juan@anipet.co')->firstOrFail();

    inClinic($clinic);

    $subscription = Subscription::with('plan')->sole();
    expect($clinic->slug)->toBe('veterinaria-anipet')
        ->and(Branch::pluck('name')->all())->toBe(['Sede principal'])
        ->and($user->hasRole('admin'))->toBeTrue()
        ->and($subscription->status)->toBe('trialing')
        ->and($subscription->plan->code)->toBe('pro')
        ->and($subscription->trial_ends_at->isSameDay(now()->addDays(15)))->toBeTrue()
        ->and($user->branches()->count())->toBe(1);
});

it('returns a token that works on clinic endpoints', function () {
    $token = $this->postJson('/api/v1/auth/register', registrationPayload())->json('data.token');

    $this->freshRequest()
        ->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.clinic.name', 'Veterinaria Anipet')
        ->assertJsonPath('data.role.name', 'admin')
        ->assertJsonPath('data.subscription.trial_days_left', 15)
        ->assertJsonPath('data.subscription.allows_writes', true);
});

it('makes slugs unique', function () {
    $this->postJson('/api/v1/auth/register', registrationPayload())->assertCreated();
    $response = $this->postJson('/api/v1/auth/register', registrationPayload(['email' => 'otra@anipet.co']))->assertCreated();

    expect(Clinic::find($response->json('data.clinic.id'))->slug)->toBe('veterinaria-anipet-2');
});

it('rejects an email that already exists (case-insensitive)', function () {
    $this->postJson('/api/v1/auth/register', registrationPayload())->assertCreated();

    $this->postJson('/api/v1/auth/register', registrationPayload(['email' => 'JUAN@anipet.co']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('requires a password with letters and numbers', function () {
    $this->postJson('/api/v1/auth/register', registrationPayload(['password' => 'solo-letras', 'password_confirmation' => 'solo-letras']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('requires accepting the terms and records when and which version (Ley 1581)', function () {
    $this->postJson('/api/v1/auth/register', registrationPayload(['accept_terms' => false]))->assertJsonValidationErrors('accept_terms');

    $this->postJson('/api/v1/auth/register', registrationPayload())->assertCreated();
    $user = User::where('email', 'juan@anipet.co')->sole();
    expect($user->terms_accepted_at)->not->toBeNull()
        ->and($user->terms_version)->toBe(config('app.terms_version'));
});

it('shows the first steps to a new clinic and ticks them off with real data', function () {
    $token = $this->postJson('/api/v1/auth/register', registrationPayload())->json('data.token');

    $steps = $this->freshRequest()->withToken($token)->getJson('/api/v1/onboarding')->assertOk()->json('data');
    expect($steps['visible'])->toBeTrue()->and($steps['done'])->toBe(0)->and($steps['total'])->toBe(6);

    $this->freshRequest()->withToken($token)->patchJson('/api/v1/clinic', ['tax_id' => '900123456-7', 'address' => 'Cra 33 # 48-20', 'phone' => '6076432210'])->assertOk();
    $steps = $this->freshRequest()->withToken($token)->getJson('/api/v1/onboarding')->json('data');
    expect(collect($steps['steps'])->firstWhere('key', 'clinic')['done'])->toBeTrue()->and($steps['done'])->toBe(1);

    $this->freshRequest()->withToken($token)->postJson('/api/v1/onboarding/dismiss')->assertOk()->assertJsonPath('data.visible', false);
});
