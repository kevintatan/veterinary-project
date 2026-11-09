<?php

namespace App\Actions\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way an appointment changes status: validates the transition and
 * appends it to the history with who and through which channel.
 */
final class ChangeAppointmentStatus
{
    public function __invoke(
        Appointment $appointment,
        AppointmentStatus $to,
        string $channel,
        ?string $userId,
        ?string $note = null,
        ?string $cancellationReason = null,
    ): Appointment {
        $from = $appointment->status;

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Una cita {$this->lower($from)} no puede pasar a {$this->lower($to)}.",
            ]);
        }

        if ($to === AppointmentStatus::Cancelled && blank($cancellationReason)) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Indica el motivo de la cancelación.']);
        }

        return DB::transaction(function () use ($appointment, $from, $to, $channel, $userId, $note, $cancellationReason) {
            $appointment->status = $to;

            if ($to === AppointmentStatus::Cancelled) {
                $appointment->cancellation_reason = $cancellationReason;
            }

            $appointment->save();

            AppointmentStatusChange::create([
                'appointment_id' => $appointment->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $userId,
                'channel' => $channel,
                'note' => $note,
            ]);

            return $appointment;
        });
    }

    private function lower(AppointmentStatus $status): string
    {
        return mb_strtolower($status->label());
    }
}
