<?php

namespace App\Clinical\Types;

use App\Clinical\RecordType;
use App\Clinical\Vocabulary;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Clinical\FollowUp;
use Illuminate\Validation\Rule;

final class FollowUpType implements RecordType
{
    use RecordsVitalSigns;

    public function key(): string
    {
        return 'follow_up';
    }

    public function rules(bool $creating): array
    {
        return [
            'follow_up' => [$creating ? 'required' : 'sometimes', 'array'],
            'follow_up.kind' => ['required_with:follow_up', Rule::in(array_keys(Vocabulary::FOLLOW_UP_KIND))],
            'follow_up.reason' => ['required_with:follow_up', 'string', 'max:255'],
            'follow_up.details' => ['nullable', 'string', 'max:10000'],
        ] + $this->vitalSignsRules();
    }

    public function save(ClinicalRecord $record, array $data, bool $creating): void
    {
        if (isset($data['follow_up'])) {
            FollowUp::updateOrCreate(['id' => $record->id], $data['follow_up']);
        }

        $this->saveVitalSigns($record, $data);
    }

    public function relations(): array
    {
        return ['followUp', 'vitalSigns'];
    }

    public function present(ClinicalRecord $record): array
    {
        $f = $record->followUp;

        return [
            'follow_up' => $f === null ? null : [
                'kind' => Vocabulary::label(Vocabulary::FOLLOW_UP_KIND, $f->kind),
                'reason' => $f->reason,
                'details' => $f->details,
            ],
            'vital_signs' => $this->presentVitalSigns($record),
        ];
    }

    public function summary(ClinicalRecord $record): string
    {
        return $record->followUp?->reason ?? 'Seguimiento';
    }

    public function defaultNextControl(ClinicalRecord $record, array $data): ?string
    {
        return null;
    }
}
