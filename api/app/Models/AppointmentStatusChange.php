<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only history of appointment statuses.
 */
class AppointmentStatusChange extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    public const UPDATED_AT = null;

    protected $fillable = ['appointment_id', 'from_status', 'to_status', 'changed_by', 'channel', 'note'];

    protected static function booted(): void
    {
        $readOnly = fn () => throw new LogicException('Appointment status history is append-only.');

        static::updating($readOnly);
        static::deleting($readOnly);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
