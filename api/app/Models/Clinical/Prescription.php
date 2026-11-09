<?php

namespace App\Models\Clinical;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fórmula médica (encabezado; los medicamentos están en prescription_items).
 */
class Prescription extends ClinicalDetail
{
    protected $table = 'prescriptions';

    protected $fillable = ['id', 'diagnosis', 'instructions'];

    protected function casts(): array
    {
        return [];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class, 'clinical_record_id')->orderBy('sort');
    }
}
