<?php

namespace App\Actions\Portal;

use App\Models\Owner;
use App\Models\TutorAccount;
use App\Models\TutorAccountOwner;
use App\Models\TutorInvitation;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a portal invitation link and links it to an account: a new one
 * (first time) or the existing one of the same phone (another clinic invited
 * them). The invitation is single use.
 */
class AcceptPortalInvitation
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Find a usable invitation by its plain token, inside its clinic.
     *
     * @return array{invitation: TutorInvitation, owner: Owner}
     */
    public function find(string $token): array
    {
        $clinicId = DB::table('tutor_invitations')->where('token_hash', TutorInvitation::hash($token))->value('clinic_id');
        abort_if($clinicId === null, 404, 'Esta invitación no existe.');

        return $this->tenant->runAs($clinicId, function () use ($token) {
            $invitation = TutorInvitation::with('owner')->where('token_hash', TutorInvitation::hash($token))->firstOrFail();
            abort_if($invitation->accepted_at !== null, 410, 'Esta invitación ya se usó. Inicia sesión con tu correo y contraseña.');
            abort_if($invitation->expires_at->isPast(), 410, 'Esta invitación venció. Pídele a tu veterinaria que te envíe una nueva.');
            abort_if($invitation->owner === null || blank($invitation->owner->mobile_phone), 410, 'Esta invitación ya no es válida.');

            return ['invitation' => $invitation, 'owner' => $invitation->owner];
        });
    }

    /** @param array{name: string, email: string, password: string} $data */
    public function withNewAccount(string $token, array $data): TutorAccount
    {
        ['invitation' => $invitation, 'owner' => $owner] = $this->find($token);

        if (TutorAccount::where('phone', $owner->mobile_phone)->exists()) {
            throw ValidationException::withMessages(['email' => 'Ya tienes una cuenta con este celular. Inicia sesión para agregar esta veterinaria.']);
        }

        return DB::transaction(function () use ($invitation, $owner, $data) {
            $account = TutorAccount::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $owner->mobile_phone,
                'password' => $data['password'],
            ]);
            $this->link($invitation, $owner, $account);

            return $account;
        });
    }

    public function withExistingAccount(string $token, TutorAccount $account): void
    {
        ['invitation' => $invitation, 'owner' => $owner] = $this->find($token);

        // The link reached the phone the clinic has on file: it must be the account's phone.
        if ($owner->mobile_phone !== $account->phone) {
            throw ValidationException::withMessages(['token' => 'Esta invitación es para otro número de celular.']);
        }

        DB::transaction(fn () => $this->link($invitation, $owner, $account));
    }

    private function link(TutorInvitation $invitation, Owner $owner, TutorAccount $account): void
    {
        $this->tenant->runAs($invitation->clinic_id, function () use ($invitation, $owner, $account) {
            // Lock the invitation: two clicks on the same link must not create two links.
            $fresh = TutorInvitation::whereKey($invitation->id)->lockForUpdate()->first();
            abort_if($fresh === null || $fresh->accepted_at !== null, 410, 'Esta invitación ya se usó.');

            TutorAccountOwner::updateOrCreate(
                ['owner_id' => $owner->id],
                ['tutor_account_id' => $account->id, 'link_method' => 'invitation', 'linked_at' => now()],
            );
            $fresh->update(['accepted_at' => now(), 'tutor_account_id' => $account->id]);
        });
    }
}
