<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A user's membership in a clinic (table clinic_user).
 *
 * @property string $id
 * @property string $clinic_id
 * @property string $user_id
 * @property string $status
 * @property bool $shows_in_agenda
 * @property Carbon|null $joined_at
 */
class ClinicMembership extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity;
    use StoresDatesInUtc;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $table = 'clinic_user';

    protected $fillable = ['user_id', 'status', 'shows_in_agenda', 'agenda_color', 'joined_at'];

    protected function casts(): array
    {
        return [
            'shows_in_agenda' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'shows_in_agenda', 'agenda_color'])->logOnlyDirty();
    }
}
