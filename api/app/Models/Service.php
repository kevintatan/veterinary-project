<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Service offered by the clinic: gives appointments their type, duration and price.
 *
 * @property string $id
 * @property string $name
 * @property int $duration_minutes
 */
class Service extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity;
    use StoresDatesInUtc;

    public const CATEGORIES = [
        'consultation' => 'Consulta',
        'vaccination' => 'Vacunación',
        'deworming' => 'Desparasitación',
        'grooming' => 'Peluquería y spa',
        'surgery' => 'Cirugía',
        'lab' => 'Laboratorio',
        'imaging' => 'Imágenes diagnósticas',
        'hospitalization' => 'Hospitalización',
        'other' => 'Otro',
    ];

    protected $fillable = ['name', 'category', 'duration_minutes', 'price', 'tax_rate', 'is_bookable_online', 'color', 'is_active'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_bookable_online' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
