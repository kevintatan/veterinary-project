<?php

namespace App\Console\Commands;

use App\Jobs\SendReminder;
use App\Models\Reminder;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Every minute: queues the reminders that are due, clinic by clinic (Fase 4c).
 * pending → queued happens in one UPDATE, so two dispatchers can't queue the same reminder.
 */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch {--limit=500 : Máximo por clínica en cada corrida}';

    protected $description = 'Encola los recordatorios automáticos que ya deben enviarse';

    public function handle(TenantContext $tenant): int
    {
        // Platform-level read: only the clinic ids with something due (no tenant data).
        $clinics = DB::table('reminders')
            ->where('status', Reminder::PENDING)
            ->where('scheduled_for', '<=', now())
            ->distinct()
            ->pluck('clinic_id');

        // Reminders stuck in "queued" (worker down for a while) go back to the queue.
        DB::table('reminders')->where('status', Reminder::QUEUED)->where('updated_at', '<', now()->subMinutes(15))->update(['status' => Reminder::PENDING]);

        $total = 0;

        foreach ($clinics as $clinicId) {
            $tenant->runAs($clinicId, function () use (&$total) {
                $ids = Reminder::where('status', Reminder::PENDING)
                    ->where('scheduled_for', '<=', now())
                    ->orderBy('scheduled_for')
                    ->limit((int) $this->option('limit'))
                    ->pluck('id');

                $claimed = Reminder::whereIn('id', $ids)->where('status', Reminder::PENDING)->get(['id']);
                Reminder::whereIn('id', $claimed->pluck('id'))->update(['status' => Reminder::QUEUED, 'updated_at' => now()]);

                foreach ($claimed as $reminder) {
                    SendReminder::dispatch($reminder->id);
                    $total++;
                }
            });
        }

        $this->info("Recordatorios encolados: {$total}");

        return self::SUCCESS;
    }
}
