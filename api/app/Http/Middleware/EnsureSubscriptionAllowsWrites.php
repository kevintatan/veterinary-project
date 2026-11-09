<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expired trial / canceled subscription => read-only (info.md §7).
 * Must run after ResolveClinicFromToken.
 */
final class EnsureSubscriptionAllowsWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $subscription = Subscription::query()
            ->whereIn('status', Subscription::CURRENT_STATUSES)
            ->latest()
            ->first();

        if ($subscription === null || ! $subscription->allowsWrites()) {
            return response()->json([
                'message' => 'Tu período de prueba o suscripción terminó. Puedes consultar tu información, pero para registrar cambios debes activar un plan.',
                'code' => 'subscription_inactive',
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
