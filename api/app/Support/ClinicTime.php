<?php

namespace App\Support;

use App\Models\Clinic;
use Illuminate\Support\Carbon;

/**
 * Business time helpers. Storage is UTC; clinics think in their timezone.
 */
final class ClinicTime
{
    /**
     * "2026-10-05T09:00" (no offset) is read in the clinic timezone;
     * "2026-10-05T14:00:00Z" / "+00:00" keep their offset. Result is UTC.
     */
    public static function parse(string $value, Clinic $clinic): Carbon
    {
        $hasOffset = preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', trim($value)) === 1;

        return ($hasOffset ? Carbon::parse($value) : Carbon::parse($value, $clinic->timezone))->utc();
    }

    public static function toClinic(Carbon $utc, Clinic $clinic): Carbon
    {
        return $utc->copy()->setTimezone($clinic->timezone);
    }

    public static function iso(?Carbon $utc, Clinic $clinic): ?string
    {
        return $utc === null ? null : self::toClinic($utc, $clinic)->toIso8601String();
    }
}
