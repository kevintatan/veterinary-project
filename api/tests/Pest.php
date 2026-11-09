<?php

use App\Actions\Auth\IssueClinicToken;
use App\Actions\Members\AddMember;
use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\Catalogs\Species;
use App\Models\Clinic;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Put a clinic in tenant context for the rest of the test (or until changed).
 */
function inClinic(Clinic $clinic): Clinic
{
    app(TenantContext::class)->set($clinic->id);

    return $clinic;
}

function tenant(): TenantContext
{
    return app(TenantContext::class);
}

/**
 * A ready-to-use clinic (trial subscription + main branch) with a member.
 * Leaves no clinic in context.
 *
 * @return array{0: Clinic, 1: User}
 */
function clinicWithMember(?User $user = null, ClinicRole $role = ClinicRole::Admin, ?Clinic $clinic = null): array
{
    $user ??= User::factory()->create();
    $clinic ??= Clinic::factory()->create();

    tenant()->runAs($clinic->id, function () use ($clinic, $user, $role) {
        if (! Subscription::exists()) {
            Subscription::create([
                'plan_id' => Plan::where('code', Plan::PRO)->firstOrFail()->id,
                'status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => now()->addDays(Subscription::TRIAL_DAYS),
            ]);
            Branch::create(['name' => 'Sede principal '.$clinic->name]);
        }

        app(AddMember::class)($user, $role);
    });

    // Spatie caches roles on the model instance; start clean for the next clinic.
    $user->unsetRelation('roles')->unsetRelation('permissions');

    return [$clinic, $user];
}

function clinicToken(User $user, Clinic $clinic): string
{
    return app(IssueClinicToken::class)($user, $clinic->id);
}

/**
 * Owner (+ optional pet) created directly in a clinic, for test setup.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeOwner(Clinic $clinic, array $attributes = []): Owner
{
    return tenant()->runAs($clinic->id, fn () => Owner::create(array_merge([
        'full_name' => 'Tutor '.fake()->unique()->lastName(),
        'mobile_phone' => '+573'.fake()->numerify('#########'),
    ], $attributes)));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makePet(Owner $owner, array $attributes = []): Pet
{
    return tenant()->runAs($owner->clinic_id, fn () => Pet::create(array_merge([
        'owner_id' => $owner->id,
        'name' => 'Mascota '.fake()->unique()->firstName(),
        'species_id' => Species::where('code', 'canine')->value('id'),
    ], $attributes)));
}
