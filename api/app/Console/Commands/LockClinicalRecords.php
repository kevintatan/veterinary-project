<?php

namespace App\Console\Commands;

use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('clinical-records:lock')]
#[Description('Cierra los registros clínicos que superaron la ventana de edición de su clínica (todas las clínicas).')]
class LockClinicalRecords extends Command
{
    public function handle(TenantContext $tenant): int
    {
        // Single statement per run; the edit window comes from each clinic's settings (default 24 h).
        // Uses the application clock (not the database's) so there is one source of time.
        $now = now()->utc()->toDateTimeString();

        $locked = $tenant->withoutTenancy('clinical records lock', fn () => DB::update("
            UPDATE clinical_records AS r
               SET locked_at = ?::timestamptz
              FROM clinics AS c
             WHERE c.id = r.clinic_id
               AND r.locked_at IS NULL
               AND r.voided_at IS NULL
               AND r.created_at <= ?::timestamptz - make_interval(hours => COALESCE((c.settings->>'edit_window_hours')::int, 24))
        ", [$now, $now]));

        $this->info("Registros cerrados: {$locked}");

        return self::SUCCESS;
    }
}
