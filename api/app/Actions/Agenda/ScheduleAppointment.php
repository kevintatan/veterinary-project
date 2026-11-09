<?php

namespace App\Actions\Agenda;

use App\Models\Appointment;
use App\Models\AppointmentStatusChange;
use App\Models\Clinic;
use App\Models\Pet;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or reschedules an appointment. When the clinic prevents overlaps,
 * every assigned professional is locked (PostgreSQL advisory lock) while
 * checking, so two receptionists cannot book the same slot at the same time.
 */
final class ScheduleAppointment
{
    /**
     * @param  array{branch_id: string, owner_id?: string|null, pet_id?: string|null, service_id?: string|null,
     *               starts_at: Carbon, ends_at?: Carbon|null, user_ids: list<string>, is_time_unset?: bool,
     *               is_home_visit?: bool, home_address?: string|null, notes_for_owner?: string|null,
     *               internal_notes?: string|null, source?: string, status?: string, booking_details?: array<string, mixed>}  $data
     */
    public function __invoke(Clinic $clinic, array $data, ?User $actor, ?Appointment $appointment = null): Appointment
    {
        $data = $this->resolveDefaults($clinic, $data, $appointment);

        return DB::transaction(function () use ($clinic, $data, $actor, $appointment) {
            // Online requests are always checked: a stranger can't double-book a professional.
            if (($clinic->setting('prevent_overlaps') || ($data['source'] ?? null) === 'online_booking') && ! $data['is_time_unset']) {
                $this->lockProfessionals($data['user_ids']);
                $this->ensureNoConflicts($data, $appointment?->id);
            }

            $creating = $appointment === null;
            $appointment ??= new Appointment(['created_by' => $actor?->id, 'source' => $data['source'] ?? 'staff']);
            $appointment->fill(collect($data)->except(['user_ids', 'source'])->all())->save();

            $appointment->users()->sync(collect($data['user_ids'])->mapWithKeys(fn (string $id, int $i) => [
                $id => ['clinic_id' => $clinic->id, 'role' => $i === 0 ? 'primary' : 'assistant'],
            ])->all());

            if ($creating) {
                AppointmentStatusChange::create([
                    'appointment_id' => $appointment->id,
                    'from_status' => null,
                    'to_status' => $appointment->status->value,
                    'changed_by' => $actor?->id,
                    'channel' => match ($appointment->source) {
                        'online_booking' => 'online',
                        'whatsapp' => 'whatsapp',
                        default => 'staff',
                    },
                ]);
            }

            return $appointment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveDefaults(Clinic $clinic, array $data, ?Appointment $appointment): array
    {
        // The pet determines the owner (and must belong to it).
        if (! empty($data['pet_id'])) {
            $pet = Pet::findOrFail($data['pet_id']);

            if (! empty($data['owner_id']) && $data['owner_id'] !== $pet->owner_id) {
                throw ValidationException::withMessages(['pet_id' => 'La mascota no pertenece a ese tutor.']);
            }

            $data['owner_id'] = $pet->owner_id;
        }

        $data['is_time_unset'] = (bool) ($data['is_time_unset'] ?? $appointment?->is_time_unset ?? false);

        if (empty($data['ends_at'])) {
            $serviceId = $data['service_id'] ?? $appointment?->service_id;
            $minutes = $serviceId !== null
                ? Service::findOrFail($serviceId)->duration_minutes
                : (int) $clinic->setting('default_appointment_minutes');
            $data['ends_at'] = $data['starts_at']->copy()->addMinutes($minutes);
        }

        if ($data['ends_at']->lte($data['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'La hora de fin debe ser posterior a la de inicio.']);
        }

        return $data;
    }

    /**
     * @param  list<string>  $userIds
     */
    private function lockProfessionals(array $userIds): void
    {
        $sorted = $userIds;
        sort($sorted); // same order everywhere => no deadlocks

        foreach ($sorted as $userId) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['appointment-user:'.$userId]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureNoConflicts(array $data, ?string $ignoreId): void
    {
        /** @var Carbon $from */
        $from = $data['starts_at'];
        /** @var Carbon $to */
        $to = $data['ends_at'];
        $messages = [];

        foreach ($data['user_ids'] as $userId) {
            $busy = Appointment::query()
                ->blockingTime()
                ->overlapping($from, $to)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
                ->exists();

            $blocked = ScheduleBlock::query()
                ->overlapping($from, $to)
                ->affecting($data['branch_id'], $userId)
                ->exists();

            if ($busy || $blocked) {
                $name = User::find($userId)?->name ?? 'El profesional';
                $messages[] = $busy
                    ? "{$name} ya tiene una cita en ese horario."
                    : "{$name} no está disponible en ese horario (bloqueo de agenda).";
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['starts_at' => $messages]);
        }
    }
}
