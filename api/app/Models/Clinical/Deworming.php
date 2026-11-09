<?php

namespace App\Models\Clinical;

/**
 * Desparasitación.
 */
class Deworming extends ClinicalDetail
{
    protected $table = 'dewormings';

    protected $fillable = ['id', 'kind', 'product_name', 'dose'];

    protected function casts(): array
    {
        return [];
    }
}
