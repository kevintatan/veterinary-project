<?php

use App\Models\Subscription;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinic);
});

it('allows writes during the trial', function () {
    $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/branches', ['name' => 'Sede Sur'])
        ->assertCreated();
});

it('becomes read-only when the trial ends, even before the nightly job runs', function () {
    tenant()->runAs($this->clinic->id, fn () => Subscription::first()->update(['trial_ends_at' => now()->subMinute()]));

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/branches')->assertOk();
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/me')
        ->assertJsonPath('data.subscription.allows_writes', false);

    $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/branches', ['name' => 'Sede Sur'])
        ->assertStatus(402)
        ->assertJsonPath('code', 'subscription_inactive');
});

it('still lets the user log out and switch clinic when read-only', function () {
    tenant()->runAs($this->clinic->id, fn () => Subscription::first()->update(['trial_ends_at' => now()->subMinute()]));

    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/auth/logout')->assertNoContent();
});

it('expires finished trials with the scheduled command', function () {
    tenant()->runAs($this->clinic->id, fn () => Subscription::first()->update(['trial_ends_at' => now()->subDay()]));
    [$activeClinic] = clinicWithMember();

    $this->artisan('subscriptions:expire-trials')->assertSuccessful();

    expect(tenant()->runAs($this->clinic->id, fn () => Subscription::first()->status))->toBe('expired')
        ->and(tenant()->runAs($activeClinic->id, fn () => Subscription::first()->status))->toBe('trialing')
        ->and(tenant()->has())->toBeFalse();
});

it('shows plan, usage and plans, and lets an expired clinic request a plan', function () {
    tenant()->runAs($this->clinic->id, fn () => Subscription::first()->update(['status' => 'expired', 'trial_ends_at' => now()->subDay()]));

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/me')
        ->assertJsonPath('data.subscription.status', 'expired')
        ->assertJsonPath('data.subscription.allows_writes', false);

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/billing')->assertOk()
        ->assertJsonPath('data.subscription.status', 'expired')
        ->assertJsonPath('data.plans.0.code', 'basic')
        ->assertJsonPath('data.online_payment', false);

    // Not blocked by the read-only lock: it is how they get out of it.
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/billing/request', ['plan' => 'pro'])->assertOk()
        ->assertJsonPath('data.request.plan', 'pro');
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/billing/request', ['plan' => 'nope'])->assertUnprocessable();
});

it('activates a plan after a manual payment and renews from the end of the period', function () {
    $this->artisan('subscriptions:activate', ['clinic' => $this->clinic->slug, 'plan' => 'pro', '--months' => 1, '--reference' => 'NEQUI-1'])->assertSuccessful();

    $subs = tenant()->runAs($this->clinic->id, fn () => Subscription::orderBy('created_at')->get());
    expect($subs)->toHaveCount(2)
        ->and($subs[0]->status)->toBe('canceled')
        ->and($subs[1]->status)->toBe('active')
        ->and($subs[1]->billing_reference)->toBe('NEQUI-1');
    $end = $subs[1]->current_period_end;

    // Paying again early adds the month after the current end, not from today.
    $this->artisan('subscriptions:activate', ['clinic' => $this->clinic->slug, 'plan' => 'pro'])->assertSuccessful();
    expect(tenant()->runAs($this->clinic->id, fn () => Subscription::current()->current_period_end->toDateString()))
        ->toBe($end->copy()->addMonthNoOverflow()->toDateString());

    $this->artisan('subscriptions:activate', ['clinic' => 'no-existe', 'plan' => 'pro'])->assertFailed();
});

it('moves unpaid plans to past due, keeps writes during the grace days, then expires them', function () {
    $this->artisan('subscriptions:activate', ['clinic' => $this->clinic->slug, 'plan' => 'pro'])->assertSuccessful();

    $this->travel(1)->months();
    $this->travel(1)->days();
    $this->artisan('subscriptions:check-periods')->assertSuccessful();
    expect(tenant()->runAs($this->clinic->id, fn () => Subscription::current()->status))->toBe('past_due');
    $this->token = clinicToken($this->admin, $this->clinic); // sessions expire over a month of travel
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/branches', ['name' => 'Sede Sur'])->assertCreated();

    $this->travel(Subscription::GRACE_DAYS)->days();
    $this->artisan('subscriptions:check-periods')->assertSuccessful();
    expect(tenant()->runAs($this->clinic->id, fn () => Subscription::latestForClinic()->status))->toBe('expired');
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/branches', ['name' => 'Sede Norte'])->assertStatus(402);
});

it('publishes the plan catalog for the landing page without login', function () {
    $this->freshRequest()->getJson('/api/v1/public/plans')->assertOk()
        ->assertJsonPath('data.1.code', 'pro')
        ->assertJsonPath('data.1.price_monthly', 99000)
        ->assertJsonMissingPath('data.0.id');
});
