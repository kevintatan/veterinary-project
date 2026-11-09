<?php

namespace App\Actions\Members;

use App\Auth\ClinicRole;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Invitation;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Public entry point: the invitation token identifies the clinic, so it is
 * looked up with an explicit tenancy bypass and everything else runs inside
 * that clinic (docs/database.md §3, safeguard 7).
 */
final class AcceptInvitation
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AddMember $addMember,
    ) {}

    public function findPending(string $plainToken): Invitation
    {
        $invitation = $this->tenant->withoutTenancy(
            'invitation lookup by token',
            fn () => Invitation::where('token_hash', Invitation::hashToken($plainToken))->first(),
        );

        if ($invitation === null || ! $invitation->isPending()) {
            throw ValidationException::withMessages(['token' => 'La invitación no es válida o ya venció.']);
        }

        return $invitation;
    }

    /**
     * @return array{clinic: Clinic, user: User}
     */
    public function __invoke(string $plainToken, User $user): array
    {
        $invitation = $this->findPending($plainToken);

        if ($user->email !== $invitation->email) {
            throw ValidationException::withMessages(['token' => 'Esta invitación es para otro correo electrónico.']);
        }

        return $this->tenant->runAs($invitation->clinic_id, function () use ($invitation, $user) {
            return DB::transaction(function () use ($invitation, $user) {
                if (ClinicMembership::where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages(['token' => 'Ya haces parte de esta clínica.']);
                }

                ($this->addMember)($user, ClinicRole::from($invitation->role), $invitation->branch_ids);

                $invitation->forceFill(['accepted_at' => now(), 'accepted_by' => $user->id])->save();

                return ['clinic' => Clinic::findOrFail($invitation->clinic_id), 'user' => $user];
            });
        });
    }
}
