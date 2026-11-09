<?php

namespace App\Models\Clinical;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Diagnóstico presuntivo, diferencial o definitivo.
 */
class ConsultationDiagnosis extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $table = 'consultation_diagnoses';

    protected $fillable = ['clinical_record_id', 'kind', 'description', 'code', 'sort'];

    protected function casts(): array
    {
        return [];
    }
}
