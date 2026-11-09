<?php

namespace App\Actions\Portal;

use App\Models\Owner;
use App\Models\TutorInvitation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The clinic invites an owner to the portal (Fase 4e). The link is the proof
 * of identity: it only reaches the phone/email the clinic has on file.
 * A new invitation replaces any pending one for the same owner.
 *
 * @return array{invitation: TutorInvitation, url: string}
 */
class InviteOwnerToPortal
{
    public function __invoke(Owner $owner, ?User $by): array
    {
        if (blank($owner->mobile_phone)) {
            throw ValidationException::withMessages(['owner' => 'El tutor necesita un celular registrado: es su identidad en el portal.']);
        }

        TutorInvitation::where('owner_id', $owner->id)->whereNull('accepted_at')->delete();

        $token = Str::random(48);
        $invitation = TutorInvitation::create([
            'owner_id' => $owner->id,
            'token_hash' => TutorInvitation::hash($token),
            'expires_at' => now()->addDays(TutorInvitation::DAYS),
            'created_by' => $by?->id,
        ]);

        return ['invitation' => $invitation, 'url' => rtrim((string) config('app.frontend_url'), '/').'/portal/activar/'.$token];
    }
}
