<?php

namespace App\Models\Catalogs;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\IsClinicCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cirugías, procedimientos y ayudas diagnósticas.
 *
 * @property string $id
 * @property string|null $clinic_id
 * @property string $name
 * @property string $category
 */
class ProcedureType extends Model
{
    use HasUuids, IsClinicCatalog;
    use StoresDatesInUtc;

    public const CATEGORIES = ['surgery', 'procedure', 'diagnostic'];

    protected $fillable = ['name', 'category', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
