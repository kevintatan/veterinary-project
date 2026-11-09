<?php

namespace App\Clinical\Types;

use App\Clinical\RecordType;
use App\Models\Catalogs\Vaccine;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Clinical\Vaccination;
use Illuminate\Support\Arr;

/**
 * Vacunación. Brand (manufacturer) and lot are recorded here; the next dose
 * defaults to the catalog's booster interval.
 */
final class VaccinationType implements RecordType
{
    public function key(): string
    {
        return 'vaccination';
    }

    public function rules(bool $creating): array
    {
        return [
            'vaccination' => [$creating ? 'required' : 'sometimes', 'array'],
            'vaccination.vaccine_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => Vaccine::whereKey($v)->exists() ?: $fail('La vacuna no existe en el catálogo.')],
            'vaccination.vaccine_name' => ['required_without:vaccination.vaccine_id', 'nullable', 'string', 'max:120'],
            'vaccination.manufacturer' => ['nullable', 'string', 'max:80'],
            'vaccination.lot_number' => ['nullable', 'string', 'max:60'],
            'vaccination.lot_expires_on' => ['nullable', 'date'],
            'vaccination.dose_number' => ['nullable', 'integer', 'between:1,20'],
            'vaccination.route' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function save(ClinicalRecord $record, array $data, bool $creating): void
    {
        if (! isset($data['vaccination'])) {
            return;
        }

        $payload = $data['vaccination'];

        // Snapshot of the catalog name (D11): renaming the catalog never rewrites history.
        if (! empty($payload['vaccine_id']) && empty($payload['vaccine_name'])) {
            $payload['vaccine_name'] = Vaccine::findOrFail($payload['vaccine_id'])->name;
        }

        Vaccination::updateOrCreate(['id' => $record->id], $payload);
    }

    public function relations(): array
    {
        return ['vaccination'];
    }

    public function present(ClinicalRecord $record): array
    {
        $v = $record->vaccination;

        return ['vaccination' => $v === null ? null : [
            'vaccine_id' => $v->vaccine_id,
            'vaccine_name' => $v->vaccine_name,
            'manufacturer' => $v->manufacturer,
            'lot_number' => $v->lot_number,
            'lot_expires_on' => $v->lot_expires_on?->toDateString(),
            'dose_number' => $v->dose_number,
            'route' => $v->route,
        ]];
    }

    public function summary(ClinicalRecord $record): string
    {
        $v = $record->vaccination;

        return implode(' · ', array_filter([$v?->vaccine_name, $v?->manufacturer, $v?->lot_number ? "lote {$v->lot_number}" : null]));
    }

    public function defaultNextControl(ClinicalRecord $record, array $data): ?string
    {
        $vaccineId = Arr::get($data, 'vaccination.vaccine_id');
        $days = $vaccineId ? Vaccine::find($vaccineId)?->default_booster_days : null;

        return $days ? $record->occurred_at->copy()->addDays($days)->toDateString() : null;
    }
}
