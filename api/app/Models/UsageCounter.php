<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Monthly usage per clinic and metric (docs/database.md §10): plan limits,
 * overage billing and the real margin per clinic.
 */
class UsageCounter extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $fillable = ['period', 'metric', 'quantity', 'cost'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'cost' => 'decimal:4'];
    }

    /** Atomic increment (concurrent jobs can't lose counts). */
    public static function add(string $clinicId, string $metric, int $quantity = 1, float $cost = 0, ?string $period = null): void
    {
        DB::statement(
            'INSERT INTO usage_counters (id, clinic_id, period, metric, quantity, cost, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, now(), now())
             ON CONFLICT (clinic_id, period, metric)
             DO UPDATE SET quantity = usage_counters.quantity + EXCLUDED.quantity, cost = usage_counters.cost + EXCLUDED.cost, updated_at = now()',
            [(string) Str::uuid7(), $clinicId, $period ?? now()->format('Y-m'), $metric, $quantity, $cost],
        );
    }

    public static function used(string $metric, ?string $period = null): int
    {
        return (int) self::where('period', $period ?? now()->format('Y-m'))->where('metric', $metric)->value('quantity');
    }
}
