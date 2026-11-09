<?php

namespace App\Clinical\Types;

use App\Clinical\RecordType;
use App\Models\Clinical\ClinicalNote;
use App\Models\Clinical\ClinicalRecord;
use Illuminate\Support\Str;

final class NoteType implements RecordType
{
    public function key(): string
    {
        return 'note';
    }

    public function rules(bool $creating): array
    {
        return [
            'note' => [$creating ? 'required' : 'sometimes', 'array'],
            'note.body' => ['required_with:note', 'string', 'max:10000'],
        ];
    }

    public function save(ClinicalRecord $record, array $data, bool $creating): void
    {
        if (isset($data['note'])) {
            ClinicalNote::updateOrCreate(['id' => $record->id], $data['note']);
        }
    }

    public function relations(): array
    {
        return ['note'];
    }

    public function present(ClinicalRecord $record): array
    {
        return ['note' => $record->note?->only(['body'])];
    }

    public function summary(ClinicalRecord $record): string
    {
        return Str::limit((string) $record->note?->body, 80);
    }

    public function defaultNextControl(ClinicalRecord $record, array $data): ?string
    {
        return null;
    }
}
