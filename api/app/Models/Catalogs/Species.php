<?php

namespace App\Models\Catalogs;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\IsClinicCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $clinic_id
 * @property string|null $code
 * @property string $name
 */
class Species extends Model
{
    use HasUuids, IsClinicCatalog;
    use StoresDatesInUtc;

    protected $table = 'species';

    protected $fillable = ['code', 'name', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }
}
