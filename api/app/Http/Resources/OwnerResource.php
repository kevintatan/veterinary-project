<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Owner
 */
class OwnerResource extends JsonResource
{
    private bool $withConsents = false;

    /** Include the current Habeas Data state (single-owner responses). */
    public function withConsents(): static
    {
        $this->withConsents = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type === null ? null : [
                'value' => $this->document_type,
                'label' => DocumentType::tryFrom($this->document_type)?->label(),
            ],
            'document_number' => $this->document_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'mobile_phone' => $this->mobile_phone,
            'phone' => $this->phone,
            'address' => $this->address,
            'municipality_code' => $this->municipality_code,
            'neighborhood' => $this->neighborhood,
            'alt_contact_name' => $this->alt_contact_name,
            'alt_contact_phone' => $this->alt_contact_phone,
            'referral_source' => $this->referral_source,
            'important_notes' => $this->important_notes,
            'consents' => $this->when($this->withConsents, fn () => $this->consentsPayload()),
            'pets' => PetResource::collection($this->whenLoaded('pets')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function consentsPayload(): array
    {
        $current = $this->currentConsents();

        return [
            'data_processing' => $current['data_processing'],
            'whatsapp' => $current['channel_whatsapp'],
            'sms' => $current['channel_sms'],
            'email' => $current['channel_email'],
            'marketing' => $current['marketing'],
        ];
    }
}
