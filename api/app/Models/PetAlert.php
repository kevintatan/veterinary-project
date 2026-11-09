<?php

namespace App\Models;

use App\Models\Catalogs\ClinicalAlert;
use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Always-visible warning on a pet (allergy, chronic/infectious condition, behavior).
 * Not a diagnosis.
 */
class PetAlert extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    protected $fillable = ['pet_id', 'type', 'clinical_alert_id', 'description', 'severity', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function catalogEntry(): BelongsTo
    {
        return $this->belongsTo(ClinicalAlert::class, 'clinical_alert_id');
    }
}
