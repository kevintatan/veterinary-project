<?php

namespace App\Http\Middleware;

use App\Models\TutorAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal routes (Fase 4e) only accept owner-portal tokens. Staff tokens are
 * rejected here, and portal tokens are rejected by the staff routes
 * (ResolveClinicFromToken needs a clinic token): the two worlds never mix.
 */
final class EnsureTutorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if (! $account instanceof TutorAccount || ! $account->currentAccessToken()?->can(TutorAccount::ABILITY)) {
            return response()->json(['message' => 'Inicia sesión en el portal.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
