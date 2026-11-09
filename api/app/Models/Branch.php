<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Sede. Every clinic has at least one (created on registration).
 *
 * @property string $id
 * @property string $clinic_id
 * @property string $name
 * @property bool $is_active
 */
class Branch extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity;
    use StoresDatesInUtc;

    protected $fillable = ['name', 'address', 'municipality_code', 'phone', 'latitude', 'longitude', 'is_active'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('clinic_id')->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
