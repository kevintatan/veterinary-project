<?php

namespace App\Models\Clinical;

/**
 * Consulta (formato SOIP).
 */
class Consultation extends ClinicalDetail
{
    protected $table = 'consultations';

    protected $fillable = ['id', 'reason', 'subjective', 'objective', 'assessment', 'plan_treatment', 'plan_diagnostic'];

    protected function casts(): array
    {
        return [];
    }
}
