<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use Database\Factories\ClinicFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The tenant. Not scoped itself: every other business table points here.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property array<string, mixed> $settings
 */
class Clinic extends Model
{
    /** @use HasFactory<ClinicFactory> */
    use HasFactory, HasUuids;

    use StoresDatesInUtc;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** Defaults for settings (docs/database.md §3). */
    public const DEFAULT_SETTINGS = [
        'edit_window_hours' => 24,
        'prevent_overlaps' => true,
        'default_appointment_minutes' => 30,
        'week_starts_on' => 1,
        'privacy_policy_version' => '1', // stored with every Habeas Data consent
        // Online booking (docs/reservas.md). Requests always wait for the clinic's approval.
        'online_booking' => [
            'enabled' => false,
            'min_notice_hours' => 2,
            'max_days_ahead' => 30,
            'choose_professional' => true,
            'message' => null, // shown on the public page (e.g. "Urgencias: llama al 607…")
        ],
    ];

    protected $fillable = ['name', 'legal_name', 'tax_id', 'slug', 'email', 'phone', 'address', 'website', 'logo_path', 'timezone', 'currency', 'status', 'settings'];

    protected $attributes = [
        'settings' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function setting(string $key): mixed
    {
        return $this->settings[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClinicMembership::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereIn('status', Subscription::CURRENT_STATUSES)->latestOfMany();
    }
}
