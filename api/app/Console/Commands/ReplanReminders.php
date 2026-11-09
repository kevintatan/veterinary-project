<?php

namespace App\Console\Commands;

use App\Messaging\Reminders\ReminderPlanner;
use App\Models\Clinic;
use App\Models\ReminderRule;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Re-plans the reminders of future appointments and controls. Useful after
 * importing data, restoring a backup or creating rules for an old clinic.
 */
class ReplanReminders extends Command
{
    protected $signature = 'reminders:replan {--clinic= : Slug de una clínica (por defecto, todas)}';

    protected $description = 'Recalcula los recordatorios automáticos de citas y controles futuros';

    public function handle(TenantContext $tenant, ReminderPlanner $planner): int
    {
        $clinics = Clinic::query()->when($this->option('clinic'), fn ($q, $slug) => $q->where('slug', $slug))->get();

        foreach ($clinics as $clinic) {
            $tenant->runAs($clinic->id, function () use ($planner, $clinic) {
                if (! ReminderRule::exists()) {
                    ReminderRule::createDefaults();
                }
                $planner->replanClinic();
                $this->line("{$clinic->name}: listo");
            });
        }

        return self::SUCCESS;
    }
}
