<?php

namespace App\Clinical\Types;

use App\Clinical\RecordType;
use App\Models\Catalogs\ProcedureType as ProcedureCatalog;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Clinical\Procedure;

/**
 * Cirugía o procedimiento.
 */
final class ProcedureType implements RecordType
{
    public function key(): string
    {
        return 'procedure';
    }

    public function rules(bool $creating): array
    {
        $text = ['nullable', 'string', 'max:10000'];

        return [
            'procedure' => [$creating ? 'required' : 'sometimes', 'array'],
            'procedure.procedure_type_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => ProcedureCatalog::whereKey($v)->exists() ?: $fail('El procedimiento no existe en el catálogo.')],
            'procedure.name' => ['required_without:procedure.procedure_type_id', 'nullable', 'string', 'max:150'],
            'procedure.description' => $text,
            'procedure.premedication' => $text,
            'procedure.anesthesia' => $text,
            'procedure.complications' => $text,
            'procedure.outcome' => $text,
        ];
    }

    public function save(ClinicalRecord $record, array $data, bool $creating): void
    {
        if (! isset($data['procedure'])) {
            return;
        }

        $payload = $data['procedure'];

        if (! empty($payload['procedure_type_id']) && empty($payload['name'])) {
            $payload['name'] = ProcedureCatalog::findOrFail($payload['procedure_type_id'])->name;
        }

        Procedure::updateOrCreate(['id' => $record->id], $payload);
    }

    public function relations(): array
    {
        return ['procedure'];
    }

    public function present(ClinicalRecord $record): array
    {
        return ['procedure' => $record->procedure?->only([
            'procedure_type_id', 'name', 'description', 'premedication', 'anesthesia', 'complications', 'outcome',
        ])];
    }

    public function summary(ClinicalRecord $record): string
    {
        return $record->procedure?->name ?? 'Procedimiento';
    }

    public function defaultNextControl(ClinicalRecord $record, array $data): ?string
    {
        return null;
    }
}
