<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:check-periods')]
#[Description('Planes pagados: vencido el período pasan a "pago pendiente" y, tras los días de gracia, a vencidos (todas las clínicas).')]
class CheckSubscriptionPeriods extends Command
{
    public function handle(TenantContext $tenant): int
    {
        [$pastDue, $expired] = $tenant->withoutTenancy('nightly subscription periods', fn () => [
            Subscription::query()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where('current_period_end', '<=', now())
                ->update(['status' => Subscription::STATUS_PAST_DUE]),
            Subscription::query()
                ->where('status', Subscription::STATUS_PAST_DUE)
                ->where('current_period_end', '<=', now()->subDays(Subscription::GRACE_DAYS))
                ->update(['status' => Subscription::STATUS_EXPIRED]),
        ]);

        $this->info("Con pago pendiente: {$pastDue} · Vencidos: {$expired}");

        return self::SUCCESS;
    }
}
