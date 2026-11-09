<?php

namespace App\Enums;

/**
 * Appointment lifecycle. Allowed transitions live here and only here.
 *
 *   requested ─(clinic accepts: AcceptBookingRequest)→ scheduled
 *   scheduled → confirmed → checked_in → in_progress → completed
 *        └──────────┴────────────┴→ cancelled / no_show
 *
 * `requested` = online booking waiting for the clinic (docs/reservas.md):
 * it holds the slot, gets no reminders, and can only be accepted (with its
 * own action, which links the owner/pet) or rejected (→ cancelled).
 */
enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitud en línea',
            self::Scheduled => 'Programada',
            self::Confirmed => 'Confirmada',
            self::CheckedIn => 'En sala de espera',
            self::InProgress => 'En atención',
            self::Completed => 'Atendida',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No asistió',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Cancelled],
            self::Scheduled => [self::Confirmed, self::CheckedIn, self::Cancelled, self::NoShow],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::InProgress, self::Completed, self::Cancelled],
            self::InProgress => [self::Completed],
            // Arrived late after being marked as no-show.
            self::NoShow => [self::CheckedIn],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Occupies the professional's time (counts for overlaps and availability). */
    public function blocksTime(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow], true);
    }

    /** No more rescheduling/edits of time, people or service. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }

    /**
     * @return list<string>
     */
    public static function timeBlockingValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->blocksTime())));
    }
}
