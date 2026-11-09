<?php

namespace App\Http\Resources;

use App\Enums\PetAttributes;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pet
 */
class PetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'species' => $this->whenLoaded('species', fn () => ['id' => $this->species->id, 'name' => $this->species->name, 'code' => $this->species->code]),
            'breed' => $this->whenLoaded('breed', fn () => $this->breed === null ? null : ['id' => $this->breed->id, 'name' => $this->breed->name]),
            'breed_other' => $this->breed_other,
            'sex' => PetAttributes::label(PetAttributes::SEX, $this->sex),
            'birth_date' => $this->birth_date?->toDateString(),
            'birth_date_is_estimated' => $this->birth_date_is_estimated,
            'age' => $this->ageLabel(),
            'color' => $this->color,
            'size' => PetAttributes::label(PetAttributes::SIZE, $this->size),
            'reproductive_status' => PetAttributes::label(PetAttributes::REPRODUCTIVE_STATUS, $this->reproductive_status),
            'temperament' => PetAttributes::label(PetAttributes::TEMPERAMENT, $this->temperament),
            'diet' => $this->diet,
            'microchip' => $this->microchip,
            'weight_kg' => $this->weight_kg === null ? null : (float) $this->weight_kg,
            // Served by the API (private disk); the hash busts caches on every new upload.
            'photo_url' => $this->photo_path === null ? null : "pets/{$this->id}/photo?v=".substr(md5($this->photo_path), 0, 8),
            'is_service_animal' => $this->is_service_animal,
            'is_emotional_support' => $this->is_emotional_support,
            'deceased_at' => $this->deceased_at?->toDateString(),
            'deceased_reason' => $this->deceased_reason,
            'important_notes' => $this->important_notes,
            'public_profile_enabled' => $this->public_profile_enabled,
            'public_token' => $this->public_token,
            'public_contact_phone' => $this->public_contact_phone,
            'lost_mode' => $this->lost_mode_enabled_at !== null,
            'public_url' => $this->publicUrl(),
            'alerts' => PetAlertResource::collection($this->whenLoaded('alerts')),
            'lifestyle' => $this->whenLoaded('lifestyle'),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'full_name' => $this->owner->full_name,
                'mobile_phone' => $this->owner->mobile_phone,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
