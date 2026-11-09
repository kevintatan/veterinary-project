<?php

namespace App\Http\Resources;

use App\Clinical\RecordAccess;
use App\Clinical\RecordTypes;
use App\Models\Attachment;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Support\ClinicTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Timeline entry by default; full detail with ->withDetail().
 *
 * @mixin ClinicalRecord
 */
class ClinicalRecordResource extends JsonResource
{
    private bool $withDetail = false;

    public function withDetail(): static
    {
        $this->withDetail = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Clinic $clinic */
        $clinic = $request->attributes->get('clinic');
        $type = RecordTypes::for($this->type);
        $access = app(RecordAccess::class);
        $user = $request->user();

        return [
            'id' => $this->id,
            'type' => ['value' => $this->type, 'label' => $this->typeLabel()],
            'summary' => $type->summary($this->resource),
            'occurred_at' => ClinicTime::iso($this->occurred_at, $clinic),
            'pet_id' => $this->pet_id,
            'branch_id' => $this->branch_id,
            'appointment_id' => $this->appointment_id,
            'parent_record_id' => $this->parent_record_id,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'professional_license' => $this->author->professional_license,
            ]),
            'next_control_at' => $this->next_control_at?->toDateString(),
            'next_control_note' => $this->next_control_note,
            'visible_to_owner' => $this->visible_to_owner,
            'ai_assisted' => $this->ai_assisted,
            'is_locked' => $this->isLocked($clinic),
            'locks_at' => $this->isLocked($clinic) ? null : ClinicTime::iso($this->locksAt($clinic), $clinic),
            'is_voided' => $this->isVoided(),
            'void' => $this->isVoided() ? [
                'at' => ClinicTime::iso($this->voided_at, $clinic),
                'reason' => $this->void_reason,
                'by' => $this->voider?->name,
            ] : null,
            'can_edit' => $user !== null && $access->canEdit($user, $this->resource, $clinic),
            'can_void' => $user !== null && $access->canVoid($user, $this->resource, $clinic),
            'detail' => $this->when($this->withDetail, fn () => $type->present($this->resource)),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn (Attachment $a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size_bytes' => $a->size_bytes,
                'category' => $a->category,
                'visible_to_owner' => $a->visible_to_owner,
            ])->values()),
            'created_at' => ClinicTime::iso($this->created_at, $clinic),
        ];
    }
}
