<?php

namespace App\Models\Clinical;

/**
 * Nota clínica libre.
 */
class ClinicalNote extends ClinicalDetail
{
    protected $table = 'clinical_notes';

    protected $fillable = ['id', 'body'];

    protected function casts(): array
    {
        return [];
    }
}
