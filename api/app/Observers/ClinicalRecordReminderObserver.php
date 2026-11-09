<?php

namespace App\Observers;

use App\Messaging\Reminders\ReminderPlanner;
use App\Models\Clinical\ClinicalRecord;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Re-plans "next control" reminders when the date is set/changed or the record is voided. */
class ClinicalRecordReminderObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly ReminderPlanner $planner) {}

    public function saved(ClinicalRecord $record): void
    {
        if ($record->wasRecentlyCreated || $record->wasChanged(['next_control_at', 'voided_at'])) {
            $this->planner->syncRecord($record);
        }
    }
}
