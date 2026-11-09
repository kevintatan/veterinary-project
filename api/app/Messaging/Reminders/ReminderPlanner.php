<?php

namespace App\Messaging\Reminders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Reminder;
use App\Models\ReminderRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Keeps the `reminders` table in line with the agenda and the clinical record
 * (Fase 4c). Called by model observers whenever an appointment or a clinical
 * record changes, and by `replanClinic()` when the clinic edits its rules.
 *
 * Planning only decides WHEN; everything that can change later (consent, the
 * pet's death, a newer vaccine…) is checked again right before sending.
 */
class ReminderPlanner
{
    /** If the planned moment already passed, still send when the event is at least this far away. */
    private const LATE_SEND_MIN_HOURS = 12;

    public function syncAppointment(Appointment $appointment): void
    {
        $settings = ReminderSettings::for(Clinic::findOrFail($appointment->clinic_id));
        $eligible = in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)
            && $appointment->pet_id !== null
            && $appointment->owner_id !== null
            && $appointment->deleted_at === null;

        foreach (ReminderRule::where('trigger', 'appointment')->get() as $rule) {
            $when = null;

            if ($eligible && $rule->is_active) {
                $start = $appointment->starts_at;
                $bookedLastMinute = $appointment->created_at !== null
                    && $appointment->created_at->diffInMinutes($start, false) < $settings->minNoticeHours * 60;

                if (! $bookedLastMinute) {
                    $when = $this->when($settings, $start->copy()->addMinutes($rule->offset_minutes), $start);
                }
            }

            $this->apply($appointment, $rule, 'appointment_reminder', $appointment->owner_id, $appointment->starts_at, $when);
        }
    }

    public function syncRecord(ClinicalRecord $record): void
    {
        $clinic = Clinic::findOrFail($record->clinic_id);
        $settings = ReminderSettings::for($clinic);
        $record->loadMissing('pet');
        $eligible = $record->next_control_at !== null && $record->voided_at === null;
        // A control is due on a day: reminders count from 9:00 of that day (clinic time).
        $event = $eligible ? Carbon::parse($record->next_control_at->toDateString().' 09:00', $clinic->timezone)->utc() : now();

        foreach (ReminderRule::where('trigger', 'next_control')->get() as $rule) {
            $applies = $eligible && $rule->is_active && ($rule->record_type === null || $rule->record_type === $record->type);
            $when = $applies ? $this->when($settings, $event->copy()->addMinutes($rule->offset_minutes), $event) : null;

            $this->apply($record, $rule, 'next_control', $record->pet?->owner_id, $event, $when);
        }
    }

    /**
     * Re-plans every future appointment and control of the clinic in context
     * (after changing rules or the sending window).
     */
    public function replanClinic(): void
    {
        Appointment::where('starts_at', '>', now())
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->each(fn (Appointment $a) => $this->syncAppointment($a));

        ClinicalRecord::whereNotNull('next_control_at')
            ->where('next_control_at', '>=', now()->subDay()->toDateString())
            ->each(fn (ClinicalRecord $r) => $this->syncRecord($r));
    }

    /** When to send, or null if it makes no sense any more. */
    private function when(ReminderSettings $settings, Carbon $planned, Carbon $event): ?Carbon
    {
        $at = $settings->clamp($planned);

        if ($at->lte(now())) {
            if (now()->diffInHours($event, false) < self::LATE_SEND_MIN_HOURS) {
                return null;
            }
            $at = $settings->next(now());
        }

        return $at->lt($event) ? $at : null;
    }

    /**
     * One pending reminder per (subject, rule): created, moved or cancelled.
     * A reminder already sent (or skipped) for the same event is never repeated.
     */
    private function apply(Model $subject, ReminderRule $rule, string $purpose, ?string $ownerId, Carbon $event, ?Carbon $when): void
    {
        $existing = Reminder::where('remindable_type', $subject->getMorphClass())
            ->where('remindable_id', $subject->getKey())
            ->where('rule_id', $rule->id)
            ->get();

        $pending = $existing->firstWhere('status', Reminder::PENDING);
        $alreadyHandled = $existing->contains(fn (Reminder $r) => ! in_array($r->status, [Reminder::PENDING, Reminder::CANCELLED], true)
            && $r->event_at->equalTo($event));

        if ($when === null || $alreadyHandled) {
            $pending?->update(['status' => Reminder::CANCELLED]);

            return;
        }

        if ($pending !== null) {
            $pending->update(['scheduled_for' => $when, 'event_at' => $event, 'owner_id' => $ownerId]);

            return;
        }

        Reminder::create([
            'remindable_type' => $subject->getMorphClass(),
            'remindable_id' => $subject->getKey(),
            'owner_id' => $ownerId,
            'rule_id' => $rule->id,
            'channel' => $rule->channel,
            'purpose' => $purpose,
            'event_at' => $event,
            'scheduled_for' => $when,
            'status' => Reminder::PENDING,
        ]);
    }
}
