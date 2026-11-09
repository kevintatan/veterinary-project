<?php

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Support\ClinicTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Times are returned in the clinic timezone (ISO 8601 with offset).
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Clinic $clinic */
        $clinic = $request->attributes->get('clinic') ?? Clinic::findOrFail($this->clinic_id);

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'starts_at' => ClinicTime::iso($this->starts_at, $clinic),
            'ends_at' => ClinicTime::iso($this->ends_at, $clinic),
            'is_time_unset' => $this->is_time_unset,
            'is_home_visit' => $this->is_home_visit,
            'home_address' => $this->home_address,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'allowed_next_statuses' => array_map(
                fn (AppointmentStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $this->status->allowedNext(),
            ),
            'source' => $this->source,
            // Online request not yet answered: who asked and for which pet (docs/reservas.md).
            'booking' => $this->status === AppointmentStatus::Requested ? collect($this->booking_details)->only(['name', 'phone', 'document_type', 'document_number', 'pet_name', 'species_name', 'notes'])->all() : null,
            'notes_for_owner' => $this->notes_for_owner,
            'internal_notes' => $this->internal_notes,
            'cancellation_reason' => $this->cancellation_reason,
            'service' => $this->whenLoaded('service', fn () => $this->service === null ? null : [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'color' => $this->service->color,
            ]),
            'pet' => $this->whenLoaded('pet', fn () => $this->pet === null ? null : [
                'id' => $this->pet->id,
                'name' => $this->pet->name,
                'species' => $this->pet->relationLoaded('species') ? $this->pet->species->name : null,
            ]),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'full_name' => $this->owner->full_name,
                'mobile_phone' => $this->owner->mobile_phone,
            ]),
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->pivot->role,
            ])->values()),
            'status_history' => $this->whenLoaded('statusChanges', fn () => $this->statusChanges->map(fn ($change) => [
                'from' => $change->from_status,
                'to' => $change->to_status,
                'label' => AppointmentStatus::from($change->to_status)->label(),
                'channel' => $change->channel,
                'changed_by' => $change->changedBy?->name,
                'note' => $change->note,
                'at' => ClinicTime::iso($change->created_at, $clinic),
            ])->values()),
        ];
    }
}
