<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_end
 */
class Subscription extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    public const TRIAL_DAYS = 15;

    /** Days a paid plan keeps working after its period ends without payment (past_due). */
    public const GRACE_DAYS = 5;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    /** Statuses that count as "the clinic's current subscription". */
    public const CURRENT_STATUSES = [self::STATUS_TRIALING, self::STATUS_ACTIVE, self::STATUS_PAST_DUE];

    protected $fillable = [
        'plan_id', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end',
        'canceled_at', 'billing_provider', 'billing_reference',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Can the clinic write data? Expired trials fall back to read-only
     * even before the nightly job flips their status.
     */
    public function allowsWrites(): bool
    {
        return match ($this->status) {
            self::STATUS_TRIALING => $this->onTrial(),
            self::STATUS_ACTIVE => true,
            self::STATUS_PAST_DUE => $this->current_period_end === null || $this->graceEndsAt()->isFuture(),
            default => false,
        };
    }

    /** Last day a past_due plan still allows writes. */
    public function graceEndsAt(): ?Carbon
    {
        return $this->current_period_end?->copy()->addDays(self::GRACE_DAYS);
    }

    /** Current subscription or, when none, the last one (an expired trial or plan): what the clinic sees. */
    public static function latestForClinic(): ?self
    {
        return self::current() ?? self::with('plan')->latest()->orderByDesc('id')->first();
    }

    /** The clinic's current subscription (inside the tenant context). */
    public static function current(): ?self
    {
        // id (ordered UUID) breaks ties: a plan activated in the same second as the trial it replaces.
        return self::with('plan')->whereIn('status', self::CURRENT_STATUSES)->latest()->orderByDesc('id')->first();
    }

    /** Does the current plan include this feature (and is the clinic not read-only)? */
    public static function allows(string $feature): bool
    {
        $subscription = self::current();

        return $subscription !== null && $subscription->allowsWrites() && $subscription->plan->hasFeature($feature);
    }

    public function trialDaysLeft(): ?int
    {
        if (! $this->onTrial()) {
            return null;
        }

        return (int) ceil(now()->diffInHours($this->trial_ends_at) / 24);
    }
}
