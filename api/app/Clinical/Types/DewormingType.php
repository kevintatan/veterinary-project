<?php

namespace App\Clinical\Types;

use App\Clinical\RecordType;
use App\Clinical\Vocabulary;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Clinical\Deworming;
use Illuminate\Validation\Rule;

final class DewormingType implements RecordType
{
    public function key(): string
    {
        return 'deworming';
    }

    public function rules(bool $creating): array
    {
        return [
            'deworming' => [$creating ? 'required' : 'sometimes', 'array'],
            'deworming.kind' => ['required_with:deworming', Rule::in(array_keys(Vocabulary::DEWORMING_KIND))],
            'deworming.product_name' => ['required_with:deworming', 'string', 'max:120'],
            'deworming.dose' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function save(ClinicalRecord $record, array $data, bool $creating): void
    {
        if (isset($data['deworming'])) {
            Deworming::updateOrCreate(['id' => $record->id], $data['deworming']);
        }
    }

    public function relations(): array
    {
        return ['deworming'];
    }

    public function present(ClinicalRecord $record): array
    {
        $d = $record->deworming;

        return ['deworming' => $d === null ? null : [
            'kind' => Vocabulary::label(Vocabulary::DEWORMING_KIND, $d->kind),
            'product_name' => $d->product_name,
            'dose' => $d->dose,
        ]];
    }

    public function summary(ClinicalRecord $record): string
    {
        $d = $record->deworming;

        return $d === null ? 'Desparasitación' : (Vocabulary::DEWORMING_KIND[$d->kind] ?? $d->kind).' · '.$d->product_name;
    }

    public function defaultNextControl(ClinicalRecord $record, array $data): ?string
    {
        return null;
    }
}
