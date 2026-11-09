<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Audit log entry stamped with the clinic in context (docs/database.md D10).
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            $activity->clinic_id ??= app(TenantContext::class)->id();
        });
    }
}
