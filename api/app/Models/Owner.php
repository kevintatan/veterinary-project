<?php

namespace App\Models;

use App\Enums\ConsentType;
use App\Models\Concerns\StoresDatesInUtc;
use App\Support\SearchText;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tutor (dueño) of one or more pets. Isolated per clinic.
 *
 * @property string $id
 * @property string $clinic_id
 * @property string $full_name
 * @property string|null $email
 * @property string|null $mobile_phone
 */
class Owner extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity, SoftDeletes;
    use StoresDatesInUtc;

    protected $fillable = [
        'document_type', 'document_number', 'full_name', 'email', 'mobile_phone', 'phone', 'address',
        'municipality_code', 'neighborhood', 'alt_contact_name', 'alt_contact_phone', 'referral_source',
        'important_notes', 'created_by',
    ];

    protected $hidden = ['name_search'];

    protected static function booted(): void
    {
        static::saving(function (Owner $owner): void {
            $owner->name_search = SearchText::normalize($owner->full_name);
        });
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(OwnerConsent::class);
    }

    /**
     * Current consent state per type (latest record wins).
     *
     * @return array<string, bool>
     */
    public function currentConsents(): array
    {
        $latest = $this->consents()
            ->orderBy('created_at')
            ->orderBy('id') // UUIDv7: time-ordered tie-breaker within the same second
            ->get(['type', 'granted'])
            ->keyBy('type');

        return collect(ConsentType::cases())
            ->mapWithKeys(fn (ConsentType $type) => [$type->value => (bool) ($latest[$type->value]->granted ?? false)])
            ->all();
    }

    public function hasConsent(ConsentType $type): bool
    {
        return $this->currentConsents()[$type->value];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
