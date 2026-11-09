<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\StoresDatesInUtc;
use App\Observers\AppointmentReminderObserver;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $clinic_id
 * @property string $branch_id
 * @property string|null $owner_id
 * @property string|null $pet_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property AppointmentStatus $status
 * @property bool $is_time_unset
 */
#[ObservedBy(AppointmentReminderObserver::class)]
class Appointment extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity, SoftDeletes;
    use StoresDatesInUtc;

    protected $fillable = [
        'branch_id', 'owner_id', 'pet_id', 'service_id', 'starts_at', 'ends_at', 'is_time_unset',
        'is_home_visit', 'home_address', 'status', 'source', 'notes_for_owner', 'internal_notes',
        'cancellation_reason', 'created_by', 'booking_details',
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'source' => 'staff',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_time_unset' => 'boolean',
            'is_home_visit' => 'boolean',
            'booking_details' => 'array',
            'status' => AppointmentStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['clinic_id', 'role']);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(AppointmentStatusChange::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function scopeBlockingTime(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::timeBlockingValues())->where('is_time_unset', false);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['branch_id', 'pet_id', 'service_id', 'starts_at', 'ends_at', 'is_home_visit', 'notes_for_owner', 'internal_notes'])
            ->logOnlyDirty();
    }
}
