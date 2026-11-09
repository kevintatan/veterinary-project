<?php

namespace App\Models\Catalogs;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\IsClinicCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog of conditions shown as alerts on a pet (leishmaniosis, FIV, diabetes…).
 *
 * @property string $id
 * @property string|null $clinic_id
 * @property string $name
 * @property string $type
 * @property list<string> $species_ids
 */
class ClinicalAlert extends Model
{
    use HasUuids, IsClinicCatalog;
    use StoresDatesInUtc;

    public const TYPES = ['infectious_disease', 'chronic_condition', 'allergy', 'behavior', 'other'];

    protected $fillable = ['name', 'type', 'species_ids', 'is_active'];

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
