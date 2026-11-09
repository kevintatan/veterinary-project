<?php

namespace App\Clinical;

/**
 * Controlled clinical vocabulary with Spanish labels. Keys match the
 * database CHECK constraints (migration 2026_10_03_000001).
 */
final class Vocabulary
{
    public const EXAM_SYSTEMS = [
        'attitude' => 'Actitud',
        'hydration' => 'Hidratación',
        'nutrition' => 'Estado nutricional',
        'lymph_nodes' => 'Ganglios linfáticos',
        'mucous_membranes' => 'Mucosas',
        'cardiovascular' => 'Sistema cardiovascular',
        'respiratory' => 'Sistema respiratorio',
        'digestive' => 'Sistema digestivo',
        'urinary' => 'Sistema urinario',
        'reproductive' => 'Sistema reproductivo',
        'musculoskeletal' => 'Sistema musculoesquelético',
        'nervous' => 'Sistema nervioso',
        'skin' => 'Piel y anexos',
        'eyes' => 'Ojos',
        'ears' => 'Oídos',
    ];

    public const EXAM_STATUS = ['not_evaluated' => 'No evaluado', 'normal' => 'Normal', 'abnormal' => 'Anormal'];

    public const DIAGNOSIS_KIND = ['presumptive' => 'Presuntivo', 'differential' => 'Diferencial', 'definitive' => 'Definitivo'];

    public const MUCOUS_MEMBRANES = ['pink' => 'Rosadas', 'pale' => 'Pálidas', 'cyanotic' => 'Cianóticas', 'icteric' => 'Ictéricas', 'hyperemic' => 'Hiperémicas'];

    public const HYDRATION = ['normal' => 'Normal', 'mild' => 'Deshidratación leve', 'moderate' => 'Deshidratación moderada', 'severe' => 'Deshidratación severa'];

    public const DEWORMING_KIND = ['internal' => 'Interna', 'external' => 'Externa', 'both' => 'Interna y externa'];

    public const FOLLOW_UP_KIND = ['phone_call' => 'Llamada', 'in_person' => 'Presencial', 'post_op' => 'Postoperatorio', 'other' => 'Otro'];

    /**
     * @param  array<string, string>  $values
     * @return array{value: string, label: string}|null
     */
    public static function label(array $values, ?string $key): ?array
    {
        return $key === null ? null : ['value' => $key, 'label' => $values[$key] ?? $key];
    }
}
