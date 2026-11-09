<?php

namespace App\Http\Resources;

use App\Enums\PetAttributes;
use App\Models\PetAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PetAlert
 */
class PetAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => PetAttributes::label(PetAttributes::ALERT_TYPE, $this->type),
            'clinical_alert_id' => $this->clinical_alert_id,
            'description' => $this->description,
            'severity' => PetAttributes::label(PetAttributes::ALERT_SEVERITY, $this->severity),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
