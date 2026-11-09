<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire-trials')]
#[Description('Marca como vencidas las pruebas gratis cuya fecha ya pasó (todas las clínicas).')]
class ExpireTrials extends Command
{
    public function handle(TenantContext $tenant): int
    {
        $expired = $tenant->withoutTenancy('nightly trial expiration', fn () => Subscription::query()
            ->where('status', Subscription::STATUS_TRIALING)
            ->where('trial_ends_at', '<=', now())
            ->update(['status' => Subscription::STATUS_EXPIRED]));

        $this->info("Pruebas vencidas: {$expired}");

        return self::SUCCESS;
    }
}
