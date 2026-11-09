<?php

namespace App\Billing;

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Subscription;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * A paid period for a clinic (docs/cobro.md). Used today by the platform team
 * after a manual payment (`subscriptions:activate`) and later by the Mercado
 * Pago webhook, with the same rules:
 *  - same plan still running → the new months are added after its end;
 *  - otherwise (trial, other plan, expired) → the plan starts now and the
 *    previous subscription is closed.
 */
final class ActivatePlan
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function __invoke(Clinic $clinic, Plan $plan, int $months, string $provider, ?string $reference = null): Subscription
    {
        // The "quiero este plan" request (if any) is fulfilled.
        $settings = $clinic->settings ?? [];
        unset($settings['plan_request']);
        $clinic->update(['settings' => $settings]);

        return $this->tenant->runAs($clinic->id, fn () => DB::transaction(function () use ($plan, $months, $provider, $reference) {
            $current = Subscription::current();

            if ($current !== null && $current->plan_id === $plan->id && in_array($current->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE], true)) {
                // Renewal: counts from the end of the paid period (paying early loses nothing).
                $from = $current->current_period_end?->isFuture() ? $current->current_period_end : now();
                $current->update([
                    'status' => Subscription::STATUS_ACTIVE,
                    'current_period_end' => $from->copy()->addMonthsNoOverflow($months),
                    'billing_provider' => $provider,
                    'billing_reference' => $reference,
                ]);

                return $current->refresh();
            }

            $current?->update(['status' => Subscription::STATUS_CANCELED, 'canceled_at' => now()]);

            return Subscription::create([
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonthsNoOverflow($months),
                'billing_provider' => $provider,
                'billing_reference' => $reference,
            ])->load('plan');
        }));
    }
}
