<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant resolution for every clinic route (docs/database.md §3):
 * 1. the clinic comes ONLY from the token, never from URL/body/headers;
 * 2. the membership is re-checked on every request (disabling a member
 *    cuts access immediately and revokes their tokens for this clinic);
 * 3. the context is always cleared at the end.
 */
final class ResolveClinicFromToken
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || $token->clinic_id === null || ! $token->can(PersonalAccessToken::ABILITY_CLINIC)) {
            return response()->json(['message' => 'Selecciona una clínica para continuar.'], Response::HTTP_FORBIDDEN);
        }

        $clinic = Clinic::find($token->clinic_id);

        if ($clinic === null || ! $clinic->isActive()) {
            return response()->json(['message' => 'La clínica no está activa.'], Response::HTTP_FORBIDDEN);
        }

        $this->tenant->set($clinic->id);

        try {
            $isActiveMember = ClinicMembership::where('user_id', $user->id)
                ->where('status', ClinicMembership::STATUS_ACTIVE)
                ->exists();

            if (! $isActiveMember) {
                $user->tokens()->where('clinic_id', $clinic->id)->delete();

                return response()->json(['message' => 'Ya no tienes acceso a esta clínica.'], Response::HTTP_FORBIDDEN);
            }

            $request->attributes->set('clinic', $clinic);

            return $next($request);
        } finally {
            $this->tenant->forget();
        }
    }
}
