<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A clinic's reminder rule: "24 h before every appointment",
 * "7 days before the next vaccine/control" (Fase 4c).
 */
class ReminderRule extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    public const DEFAULTS = [
        ['trigger' => 'appointment', 'record_type' => null, 'offset_minutes' => -1440],   // 24 h before
        ['trigger' => 'next_control', 'record_type' => null, 'offset_minutes' => -10080], // 7 days before
    ];

    protected $fillable = ['trigger', 'record_type', 'offset_minutes', 'channel', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'offset_minutes' => 'integer'];
    }

    /** Creates the default rules for the clinic in context (new clinics). */
    public static function createDefaults(): void
    {
        foreach (self::DEFAULTS as $rule) {
            self::create([...$rule, 'channel' => 'whatsapp', 'is_active' => true]);
        }
    }
}
