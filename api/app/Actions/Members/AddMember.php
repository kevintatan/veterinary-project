<?php

namespace App\Actions\Members;

use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\ClinicMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Adds a user to the clinic in context with a role and branches.
 * The only way memberships are created (registration, accepted invitation).
 */
final class AddMember
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  list<string>|null  $branchIds  null = every active branch
     */
    public function __invoke(User $user, ClinicRole $role, ?array $branchIds = null): ClinicMembership
    {
        $clinicId = $this->tenant->requireId();

        return DB::transaction(function () use ($user, $role, $branchIds, $clinicId) {
            $membership = ClinicMembership::create([
                'user_id' => $user->id,
                'status' => ClinicMembership::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            // Spatie caches roles per model instance: drop any loaded for another clinic.
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->assignRole($role->value);

            $branches = $branchIds === null
                ? Branch::where('is_active', true)->pluck('id')
                : Branch::whereIn('id', $branchIds)->pluck('id');

            $user->branches()->attach($branches->mapWithKeys(fn (string $id) => [$id => ['clinic_id' => $clinicId]])->all());

            return $membership;
        });
    }
}
