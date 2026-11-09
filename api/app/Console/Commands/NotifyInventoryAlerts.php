<?php

namespace App\Console\Commands;

use App\Auth\Permission;
use App\Inventory\InventoryAlerts;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Notifications\Staff\NotifyStaff;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:alerts')]
#[Description('Aviso diario en la campana: productos con existencias bajas y lotes por vencer, por sede.')]
class NotifyInventoryAlerts extends Command
{
    public function handle(TenantContext $tenant, InventoryAlerts $alerts, NotifyStaff $notify): int
    {
        $clinicIds = $tenant->withoutTenancy('daily inventory alerts', fn () => Clinic::where('status', Clinic::STATUS_ACTIVE)->pluck('id'));
        $sent = 0;

        foreach ($clinicIds as $clinicId) {
            $tenant->runAs($clinicId, function () use ($alerts, $notify, &$sent) {
                if (! Subscription::current()?->allowsWrites()) {
                    return; // read-only clinics get no nagging
                }

                $branches = Branch::where('is_active', true)->orderBy('name')->get();
                foreach ($branches as $branch) {
                    ['low' => $low, 'expiring' => $expiring] = $alerts->for($branch->id);
                    $parts = array_filter([
                        $low->count() ? $low->count().' '.($low->count() === 1 ? 'producto con existencias bajas' : 'productos con existencias bajas') : null,
                        $expiring->count() ? $expiring->count().' '.($expiring->count() === 1 ? 'lote por vencer' : 'lotes por vencer') : null,
                    ]);

                    if ($parts !== []) {
                        $sent += $notify->withPermission(
                            Permission::InventoryManage,
                            'inventory_alert',
                            'Inventario: '.implode(' y ', $parts),
                            $branches->count() > 1 ? $branch->name : null,
                            '/app/inventario?vista=alertas&sede='.$branch->id,
                        );
                    }
                }
            });
        }

        $this->info("Avisos de inventario: {$sent}");

        return self::SUCCESS;
    }
}
