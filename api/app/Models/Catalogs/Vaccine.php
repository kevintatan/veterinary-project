<?php

namespace App\Models\Catalogs;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\IsClinicCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $clinic_id
 * @property string $name
 * @property list<string> $species_ids
 * @property int|null $default_booster_days
 */
class Vaccine extends Model
{
    use HasUuids, IsClinicCatalog;
    use StoresDatesInUtc;

    protected $fillable = ['name', 'manufacturer', 'protects_against', 'species_ids', 'default_booster_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'species_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForSpecies(Builder $query, string $speciesId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereJsonContains('species_ids', $speciesId)
            ->orWhereJsonLength('species_ids', 0));
    }
}
