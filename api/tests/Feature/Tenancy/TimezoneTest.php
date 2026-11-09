<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Regression: a PostgreSQL server running in America/Bogota shifted every
 * timestamp by 5 hours because the session timezone was not forced to UTC.
 */

it('forces the database session to UTC regardless of the server timezone', function () {
    expect(DB::selectOne('SHOW timezone')->TimeZone)->toBe('UTC');
});

it('stores a local (Bogotá) datetime as the right UTC instant', function () {
    $clinic = Clinic::factory()->create();
    $bogota = Carbon::parse('2026-10-05 08:00', 'America/Bogota');

    inClinic($clinic);
    $subscription = Subscription::create([
        'plan_id' => Plan::where('code', Plan::PRO)->value('id'),
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => $bogota,
    ]);

    expect($subscription->fresh()->trial_ends_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-05 13:00')
        ->and($bogota->timezoneName)->toBe('America/Bogota'); // the caller's object is not mutated
});

it('round-trips timestamps without shifting them', function () {
    $clinic = Clinic::factory()->create();
    $instant = Carbon::parse('2026-10-15 23:30:00', 'UTC');

    inClinic($clinic);
    $subscription = Subscription::create([
        'plan_id' => Plan::where('code', Plan::PRO)->value('id'),
        'status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => $instant,
    ]);

    expect($subscription->fresh()->trial_ends_at->equalTo($instant))->toBeTrue();
});
