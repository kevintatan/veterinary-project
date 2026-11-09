<?php

namespace App\Actions\Auth;

use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The only place a clinic-bound token is created (docs/database.md §3, safeguard 1).
 */
final class IssueClinicToken
{
    public const TTL_DAYS = 30;

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @throws AuthorizationException if the user is not an active member of an active clinic
     */
    public function __invoke(User $user, string $clinicId, string $deviceName = 'web'): string
    {
        $clinic = Clinic::find($clinicId);

        $isMember = $clinic !== null && $clinic->isActive() && $this->tenant->runAs(
            $clinic->id,
            fn () => ClinicMembership::where('user_id', $user->id)
                ->where('status', ClinicMembership::STATUS_ACTIVE)
                ->exists(),
        );

        if (! $isMember) {
            throw new AuthorizationException('No tienes acceso a esta clínica.');
        }

        $newToken = $user->createToken($deviceName, [PersonalAccessToken::ABILITY_CLINIC], now()->addDays(self::TTL_DAYS));

        /** @var PersonalAccessToken $token */
        $token = $newToken->accessToken;
        $token->forceFill(['clinic_id' => $clinic->id])->save();

        $user->forceFill(['last_login_at' => now()])->save();

        return $newToken->plainTextToken;
    }
}
