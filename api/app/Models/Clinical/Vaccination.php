<?php

namespace App\Models\Clinical;

/**
 * Vacunación: marca y lote se registran aquí.
 */
class Vaccination extends ClinicalDetail
{
    protected $table = 'vaccinations';

    protected $fillable = ['id', 'vaccine_id', 'vaccine_name', 'manufacturer', 'lot_number', 'lot_expires_on', 'dose_number', 'route'];

    protected function casts(): array
    {
        return ['lot_expires_on' => 'date', 'dose_number' => 'integer'];
    }
}
