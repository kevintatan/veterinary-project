<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Opening hours for a branch (user_id NULL) or a professional in a branch.
 *
 * @property string $branch_id
 * @property string|null $user_id
 * @property int $weekday ISO-8601 (1 = Monday)
 * @property string $start_time HH:MM:SS
 * @property string $end_time HH:MM:SS
 */
class Schedule extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    protected $fillable = ['branch_id', 'user_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
