<?php

namespace App\Models\Catalogs;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\IsClinicCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $clinic_id
 * @property string $species_id
 * @property string $name
 */
class Breed extends Model
{
    use HasUuids, IsClinicCatalog;
    use StoresDatesInUtc;

    protected $fillable = ['species_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }
}
