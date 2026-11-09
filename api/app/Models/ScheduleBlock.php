<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Time off. NULL branch = every branch; NULL user = everyone.
 *
 * @property string|null $branch_id
 * @property string|null $user_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
class ScheduleBlock extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    protected $fillable = ['branch_id', 'user_id', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    /** Blocks that apply to a professional working in a branch. */
    public function scopeAffecting(Builder $query, string $branchId, ?string $userId): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where(fn (Builder $q) => $q->whereNull('user_id')->when($userId, fn (Builder $q) => $q->orWhere('user_id', $userId)));
    }
}
