<?php

namespace App\Observers;

use App\Messaging\Reminders\ReminderPlanner;
use App\Models\Appointment;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Re-plans an appointment's reminders when its time, status or pet changes (after the transaction commits). */
class AppointmentReminderObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly ReminderPlanner $planner) {}

    public function saved(Appointment $appointment): void
    {
        if ($appointment->wasRecentlyCreated || $appointment->wasChanged(['starts_at', 'status', 'pet_id', 'owner_id'])) {
            $this->planner->syncAppointment($appointment);
        }
    }

    public function deleted(Appointment $appointment): void
    {
        $this->planner->syncAppointment($appointment);
    }
}
