<?php

namespace App\Models\Clinical;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Examen por sistema: no evaluado / normal / anormal + observación.
 */
class PhysicalExamFinding extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $table = 'physical_exam_findings';

    protected $fillable = ['clinical_record_id', 'system', 'status', 'notes'];

    protected function casts(): array
    {
        return [];
    }
}
