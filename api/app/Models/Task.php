<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $title
 * @property string|null $assignee_id
 * @property string $created_by
 * @property string $status
 * @property Carbon|null $due_at
 */
class Task extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity, StoresDatesInUtc;

    public const STATUSES = ['pending' => 'Pendiente', 'done' => 'Finalizada', 'discarded' => 'Descartada'];

    protected $fillable = ['title', 'description', 'owner_id', 'pet_id', 'assignee_id', 'created_by', 'due_at', 'source_type', 'source_id'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_at !== null && $this->due_at->isPast();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'assignee_id', 'due_at', 'status'])->logOnlyDirty();
    }
}
