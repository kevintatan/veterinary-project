<?php

namespace App\Models\Clinical;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Signos vitales (1 por consulta o seguimiento). Peso en kg y temperatura en °C.
 */
class VitalSigns extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $table = 'vital_signs';

    protected $fillable = ['clinical_record_id', 'weight_kg', 'temperature_c', 'heart_rate', 'respiratory_rate', 'capillary_refill_s', 'spo2_pct', 'systolic_bp', 'diastolic_bp', 'glucose_mg_dl', 'body_condition_score', 'mucous_membranes', 'hydration', 'pain_score', 'notes'];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:3', 'temperature_c' => 'decimal:1', 'capillary_refill_s' => 'decimal:1'];
    }
}
