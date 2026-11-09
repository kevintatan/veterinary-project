<?php

namespace App\Clinical;

use App\Models\Clinical\ClinicalRecord;

/**
 * One kind of clinical record (consultation, vaccination…): its payload
 * validation, persistence and presentation. Registered in RecordTypes.
 */
interface RecordType
{
    public function key(): string;

    /**
     * Validation rules for the type-specific payload (merged with the common ones).
     *
     * @return array<string, mixed>
     */
    public function rules(bool $creating): array;

    /**
     * Persist the type-specific payload. $data is the validated request.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(ClinicalRecord $record, array $data, bool $creating): void;

    /**
     * Relations to eager load before present()/summary().
     *
     * @return list<string>
     */
    public function relations(): array;

    /**
     * @return array<string, mixed>
     */
    public function present(ClinicalRecord $record): array;

    /** One line for the timeline ("Otitis externa", "Rabia · lote A123"). */
    public function summary(ClinicalRecord $record): string;

    /**
     * Suggested next control when the user does not set one.
     *
     * @param  array<string, mixed>  $data
     */
    public function defaultNextControl(ClinicalRecord $record, array $data): ?string;
}
