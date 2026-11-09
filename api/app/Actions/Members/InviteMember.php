<?php

namespace App\Actions\Members;

use App\Auth\ClinicRole;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\ClinicInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InviteMember
{
    /**
     * @param  list<string>|null  $branchIds
     */
    public function __invoke(User $inviter, Clinic $clinic, string $email, ClinicRole $role, ?array $branchIds = null): Invitation
    {
        $email = Str::lower(trim($email));

        $alreadyMember = ClinicMembership::whereHas('user', fn ($q) => $q->where('email', $email))->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages(['email' => 'Esta persona ya hace parte de la clínica.']);
        }

        // Only one pending invitation per email: a new one replaces the previous.
        Invitation::where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $plainToken = Str::random(48);

        $invitation = Invitation::create([
            'email' => $email,
            'role' => $role->value,
            'branch_ids' => $branchIds,
            'token_hash' => Invitation::hashToken($plainToken),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ]);

        Notification::route('mail', $email)
            ->notify(new ClinicInvitationNotification($invitation, $clinic, $plainToken));

        return $invitation;
    }
}
