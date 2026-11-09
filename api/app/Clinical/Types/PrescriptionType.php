<?php

namespace App\Clinical\Types;

use App\Clinical\RecordType;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Clinical\Prescription;
use App\Models\Clinical\PrescriptionItem;

/**
 * Fórmula médica: header + medications.
 */
final class PrescriptionType implements RecordType
{
    public function key(): string
    {
        return 'prescription';
    }

    public function rules(bool $creating): array
    {
        return [
            'prescription' => [$creating ? 'required' : 'sometimes', 'array'],
            'prescription.diagnosis' => ['nullable', 'string', 'max:255'],
            'prescription.instructions' => ['nullable', 'string', 'max:5000'],
            'prescription.items' => ['required_with:prescription', 'array', 'min:1', 'max:30'],
            'prescription.items.*.medication' => ['required', 'string', 'max:150'],
            'prescription.items.*.presentation' => ['nullable', 'string', 'max:100'],
            'prescription.items.*.dose' => ['required', 'string', 'max:100'],
            'prescription.items.*.frequency' => ['required', 'string', 'max:100'],
            'prescription.items.*.duration' => ['nullable', 'string', 'max:100'],
            'prescription.items.*.route' => ['nullable', 'string', 'max:50'],
            'prescription.items.*.quantity' => ['nullable', 'string', 'max:50'],
            'prescription.items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(ClinicalRecord $record, array $data, bool $creating): void
    {
        if (! isset($data['prescription'])) {
            return;
        }

        $header = $data['prescription'];
        $items = $header['items'];
        unset($header['items']);

        Prescription::updateOrCreate(['id' => $record->id], $header);

        PrescriptionItem::where('clinical_record_id', $record->id)->delete();

        foreach (array_values($items) as $i => $item) {
            PrescriptionItem::create(['clinical_record_id' => $record->id, 'sort' => $i] + $item);
        }
    }

    public function relations(): array
    {
        return ['prescription.items'];
    }

    public function present(ClinicalRecord $record): array
    {
        $p = $record->prescription;

        return ['prescription' => $p === null ? null : [
            'diagnosis' => $p->diagnosis,
            'instructions' => $p->instructions,
            'items' => $p->items->map(fn (PrescriptionItem $i) => $i->only([
                'medication', 'presentation', 'dose', 'frequency', 'duration', 'route', 'quantity', 'notes',
            ]))->values(),
        ]];
    }

    public function summary(ClinicalRecord $record): string
    {
        return $record->prescription?->items->pluck('medication')->implode(', ') ?: 'Fórmula médica';
    }

    public function defaultNextControl(ClinicalRecord $record, array $data): ?string
    {
        return null;
    }
}
