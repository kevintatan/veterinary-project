<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * "Datos generales": housing, feeding and grooming habits.
 */
class PetLifestyle extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    protected $fillable = ['pet_id', 'housing', 'food_amount', 'feeding_frequency', 'bath_frequency', 'other_pets', 'last_heat_date', 'notes'];

    protected function casts(): array
    {
        return ['last_heat_date' => 'date'];
    }
}
