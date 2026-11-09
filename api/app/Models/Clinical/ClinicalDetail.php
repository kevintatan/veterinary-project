<?php

namespace App\Models\Clinical;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 detail of a clinical record: shares its id (docs/database.md §7).
 *
 * @property string $id
 */
abstract class ClinicalDetail extends Model
{
    use BelongsToClinic, StoresDatesInUtc;

    public $incrementing = false;

    protected $keyType = 'string';

    public function record(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class, 'id');
    }
}
