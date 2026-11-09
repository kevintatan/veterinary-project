<?php

namespace App\Models;

use App\Models\Catalogs\Breed;
use App\Models\Catalogs\Species;
use App\Models\Concerns\StoresDatesInUtc;
use App\Support\SearchText;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $clinic_id
 * @property string $owner_id
 * @property string $name
 * @property Carbon|null $birth_date
 * @property Carbon|null $deceased_at
 * @property string $public_token
 */
class Pet extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity, SoftDeletes;
    use StoresDatesInUtc;

    protected $fillable = [
        'owner_id', 'name', 'species_id', 'breed_id', 'breed_other', 'sex', 'birth_date', 'birth_date_is_estimated',
        'color', 'size', 'reproductive_status', 'temperament', 'diet', 'microchip', 'weight_kg', 'photo_path',
        'is_service_animal', 'is_emotional_support', 'deceased_at', 'deceased_reason', 'important_notes',
        'public_profile_enabled', 'public_contact_phone', 'created_by',
    ];

    protected $hidden = ['name_search'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'birth_date_is_estimated' => 'boolean',
            'weight_kg' => 'decimal:3',
            'is_service_animal' => 'boolean',
            'is_emotional_support' => 'boolean',
            'deceased_at' => 'date',
            'public_profile_enabled' => 'boolean',
            'lost_mode_enabled_at' => 'datetime',
            'card_design' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Pet $pet): void {
            $pet->public_token ??= self::newPublicToken();
        });

        static::saving(function (Pet $pet): void {
            $pet->name_search = SearchText::normalize($pet->name);
        });
    }

    /** Page behind the card's QR (web/src/app/m/[token]). */
    public function publicUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/m/'.$this->public_token;
    }

    public static function newPublicToken(): string
    {
        return Str::random(32);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PetAlert::class);
    }

    public function lifestyle(): HasOne
    {
        return $this->hasOne(PetLifestyle::class);
    }

    public function isDeceased(): bool
    {
        return $this->deceased_at !== null;
    }

    /**
     * Human age in Spanish ("4 años y 3 meses"), null without birth date.
     */
    public function ageLabel(?Carbon $at = null): ?string
    {
        if ($this->birth_date === null) {
            return null;
        }

        $diff = $this->birth_date->diff($at ?? $this->deceased_at ?? now());
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y.' '.($diff->y === 1 ? 'año' : 'años');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m.' '.($diff->m === 1 ? 'mes' : 'meses');
        }
        if ($parts === []) {
            $parts[] = $diff->d.' '.($diff->d === 1 ? 'día' : 'días');
        }

        return implode(' y ', $parts);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
