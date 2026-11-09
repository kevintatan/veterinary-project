<?php

namespace App\Clinical\Types;

use App\Clinical\Vocabulary;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Clinical\VitalSigns;
use App\Models\Pet;
use Illuminate\Validation\Rule;

/**
 * Vital signs shared by consultations and follow-ups.
 */
trait RecordsVitalSigns
{
    /**
     * @return array<string, mixed>
     */
    protected function vitalSignsRules(): array
    {
        return [
            'vital_signs' => ['sometimes', 'nullable', 'array'],
            'vital_signs.weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:2000'],
            'vital_signs.temperature_c' => ['nullable', 'numeric', 'between:25,45'],
            'vital_signs.heart_rate' => ['nullable', 'integer', 'between:1,500'],
            'vital_signs.respiratory_rate' => ['nullable', 'integer', 'between:1,300'],
            'vital_signs.capillary_refill_s' => ['nullable', 'numeric', 'between:0,20'],
            'vital_signs.spo2_pct' => ['nullable', 'integer', 'between:0,100'],
            'vital_signs.systolic_bp' => ['nullable', 'integer', 'between:20,400'],
            'vital_signs.diastolic_bp' => ['nullable', 'integer', 'between:10,300'],
            'vital_signs.glucose_mg_dl' => ['nullable', 'integer', 'between:1,2000'],
            'vital_signs.body_condition_score' => ['nullable', 'integer', 'between:1,9'],
            'vital_signs.mucous_membranes' => ['nullable', Rule::in(array_keys(Vocabulary::MUCOUS_MEMBRANES))],
            'vital_signs.hydration' => ['nullable', Rule::in(array_keys(Vocabulary::HYDRATION))],
            'vital_signs.pain_score' => ['nullable', 'integer', 'between:0,10'],
            'vital_signs.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveVitalSigns(ClinicalRecord $record, array $data): void
    {
        if (! array_key_exists('vital_signs', $data)) {
            return;
        }

        if (empty($data['vital_signs'])) {
            VitalSigns::where('clinical_record_id', $record->id)->delete();

            return;
        }

        VitalSigns::updateOrCreate(['clinical_record_id' => $record->id], $data['vital_signs']);

        // Keep the pet's current weight = weight of its most recent record.
        $weight = $data['vital_signs']['weight_kg'] ?? null;
        $isLatest = ! ClinicalRecord::where('pet_id', $record->pet_id)
            ->whereKeyNot($record->id)
            ->notVoided()
            ->where('occurred_at', '>', $record->occurred_at)
            ->whereHas('vitalSigns', fn ($q) => $q->whereNotNull('weight_kg'))
            ->exists();

        if ($weight !== null && $isLatest) {
            Pet::whereKey($record->pet_id)->update(['weight_kg' => $weight]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function presentVitalSigns(ClinicalRecord $record): ?array
    {
        $v = $record->vitalSigns;

        if ($v === null) {
            return null;
        }

        return [
            'weight_kg' => $v->weight_kg === null ? null : (float) $v->weight_kg,
            'temperature_c' => $v->temperature_c === null ? null : (float) $v->temperature_c,
            'heart_rate' => $v->heart_rate,
            'respiratory_rate' => $v->respiratory_rate,
            'capillary_refill_s' => $v->capillary_refill_s === null ? null : (float) $v->capillary_refill_s,
            'spo2_pct' => $v->spo2_pct,
            'systolic_bp' => $v->systolic_bp,
            'diastolic_bp' => $v->diastolic_bp,
            'glucose_mg_dl' => $v->glucose_mg_dl,
            'body_condition_score' => $v->body_condition_score,
            'mucous_membranes' => Vocabulary::label(Vocabulary::MUCOUS_MEMBRANES, $v->mucous_membranes),
            'hydration' => Vocabulary::label(Vocabulary::HYDRATION, $v->hydration),
            'pain_score' => $v->pain_score,
            'notes' => $v->notes,
        ];
    }
}
