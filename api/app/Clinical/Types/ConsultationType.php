<?php

namespace App\Clinical\Types;

use App\Clinical\RecordType;
use App\Clinical\Vocabulary;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Clinical\Consultation;
use App\Models\Clinical\ConsultationDiagnosis;
use App\Models\Clinical\PhysicalExamFinding;
use Illuminate\Validation\Rule;

/**
 * Consulta en formato SOIP + signos vitales + examen por sistemas + diagnósticos.
 */
final class ConsultationType implements RecordType
{
    use RecordsVitalSigns;

    public function key(): string
    {
        return 'consultation';
    }

    public function rules(bool $creating): array
    {
        $text = ['nullable', 'string', 'max:10000'];

        return [
            'consultation' => [$creating ? 'required' : 'sometimes', 'array'],
            'consultation.reason' => ['required_with:consultation', 'string', 'max:255'],
            'consultation.subjective' => $text,
            'consultation.objective' => $text,
            'consultation.assessment' => $text,
            'consultation.plan_treatment' => $text,
            'consultation.plan_diagnostic' => $text,

            'findings' => ['sometimes', 'array', 'max:'.count(Vocabulary::EXAM_SYSTEMS)],
            'findings.*.system' => ['required', 'distinct', Rule::in(array_keys(Vocabulary::EXAM_SYSTEMS))],
            'findings.*.status' => ['required', Rule::in(array_keys(Vocabulary::EXAM_STATUS))],
            'findings.*.notes' => ['nullable', 'string', 'max:1000'],

            'diagnoses' => ['sometimes', 'array', 'max:20'],
            'diagnoses.*.kind' => ['required', Rule::in(array_keys(Vocabulary::DIAGNOSIS_KIND))],
            'diagnoses.*.description' => ['required', 'string', 'max:255'],
            'diagnoses.*.code' => ['nullable', 'string', 'max:30'],
        ] + $this->vitalSignsRules();
    }

    public function save(ClinicalRecord $record, array $data, bool $creating): void
    {
        if (isset($data['consultation'])) {
            Consultation::updateOrCreate(['id' => $record->id], $data['consultation']);
        }

        $this->saveVitalSigns($record, $data);

        if (array_key_exists('findings', $data)) {
            PhysicalExamFinding::where('clinical_record_id', $record->id)->delete();

            // "Not evaluated" rows are implicit: only evaluated systems are stored.
            foreach ($data['findings'] as $finding) {
                if ($finding['status'] !== 'not_evaluated') {
                    PhysicalExamFinding::create(['clinical_record_id' => $record->id] + $finding);
                }
            }
        }

        if (array_key_exists('diagnoses', $data)) {
            ConsultationDiagnosis::where('clinical_record_id', $record->id)->delete();

            foreach (array_values($data['diagnoses']) as $i => $diagnosis) {
                ConsultationDiagnosis::create(['clinical_record_id' => $record->id, 'sort' => $i] + $diagnosis);
            }
        }
    }

    public function relations(): array
    {
        return ['consultation', 'vitalSigns', 'findings', 'diagnoses'];
    }

    public function present(ClinicalRecord $record): array
    {
        $c = $record->consultation;

        return [
            'consultation' => $c?->only(['reason', 'subjective', 'objective', 'assessment', 'plan_treatment', 'plan_diagnostic']),
            'vital_signs' => $this->presentVitalSigns($record),
            'findings' => $record->findings->map(fn (PhysicalExamFinding $f) => [
                'system' => Vocabulary::label(Vocabulary::EXAM_SYSTEMS, $f->system),
                'status' => Vocabulary::label(Vocabulary::EXAM_STATUS, $f->status),
                'notes' => $f->notes,
            ])->values(),
            'diagnoses' => $record->diagnoses->map(fn (ConsultationDiagnosis $d) => [
                'kind' => Vocabulary::label(Vocabulary::DIAGNOSIS_KIND, $d->kind),
                'description' => $d->description,
                'code' => $d->code,
            ])->values(),
        ];
    }

    public function summary(ClinicalRecord $record): string
    {
        $diagnosis = $record->diagnoses->firstWhere('kind', 'definitive') ?? $record->diagnoses->first();

        return $diagnosis?->description ?? $record->consultation?->reason ?? 'Consulta';
    }

    public function defaultNextControl(ClinicalRecord $record, array $data): ?string
    {
        return null;
    }
}
