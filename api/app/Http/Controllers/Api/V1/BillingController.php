<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Configuración → Plan y facturación (docs/cobro.md). Outside the "writable"
 * lock: an expired clinic must be able to see its plan and ask to activate it.
 * Online payment (Mercado Pago) comes later; today "Elegir plan" leaves a
 * request and the team activates it with `subscriptions:activate`.
 */
class BillingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);
        $subscription = Subscription::latestForClinic();
        $plan = $subscription?->plan;

        return response()->json(['data' => [
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status,
                'plan' => ['code' => $plan->code, 'name' => $plan->name],
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'trial_days_left' => $subscription->trialDaysLeft(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'grace_ends_at' => $subscription->status === Subscription::STATUS_PAST_DUE ? $subscription->graceEndsAt()?->toIso8601String() : null,
                'allows_writes' => $subscription->allowsWrites(),
            ],
            'usage' => [
                'whatsapp_messages' => ['used' => UsageCounter::used('whatsapp_utility'), 'limit' => $plan?->limits['whatsapp_messages'] ?? null],
                'branches' => ['used' => Branch::where('is_active', true)->count(), 'limit' => $plan?->limits['branches'] ?? null],
                'users' => ['used' => ClinicMembership::count(), 'limit' => $plan?->limits['users'] ?? null],
            ],
            'plans' => Plan::catalog(),
            'request' => $clinic->settings['plan_request'] ?? null,
            'online_payment' => false, // Mercado Pago: pending (docs/cobro.md)
        ]]);
    }

    /** "Quiero este plan": recorded for the Zooetia team until online payment exists. */
    public function requestPlan(Request $request): JsonResponse
    {
        $data = $request->validate(['plan' => ['required', Rule::exists('plans', 'code')->where('is_active', true)]]);
        $clinic = $this->clinic($request);
        $plan = Plan::where('code', $data['plan'])->firstOrFail();

        $clinic->update(['settings' => array_merge($clinic->settings ?? [], ['plan_request' => [
            'plan' => $plan->code,
            'plan_name' => $plan->name,
            'requested_at' => now()->toIso8601String(),
            'requested_by' => $request->user()->name,
        ]])]);

        Log::info('Solicitud de plan', ['clinic' => $clinic->slug, 'plan' => $plan->code, 'user' => $request->user()->email]);

        return $this->show($request);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
