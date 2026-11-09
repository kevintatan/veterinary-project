<?php

namespace App\Models\Clinical;

/**
 * Cirugía o procedimiento.
 */
class Procedure extends ClinicalDetail
{
    protected $table = 'procedures';

    protected $fillable = ['id', 'procedure_type_id', 'name', 'description', 'premedication', 'anesthesia', 'complications', 'outcome'];

    protected function casts(): array
    {
        return [];
    }
}
