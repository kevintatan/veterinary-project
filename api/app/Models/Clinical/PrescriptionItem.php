<?php

namespace App\Models\Clinical;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Medicamento de una fórmula.
 */
class PrescriptionItem extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $table = 'prescription_items';

    protected $fillable = ['clinical_record_id', 'medication', 'presentation', 'dose', 'frequency', 'duration', 'route', 'quantity', 'notes', 'sort'];

    protected function casts(): array
    {
        return [];
    }
}
