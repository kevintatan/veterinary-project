<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum token bound to one clinic: the only source of the tenant for a request.
 *
 * @property string|null $clinic_id
 */
class PersonalAccessToken extends SanctumToken
{
    public const ABILITY_CLINIC = 'clinic';

    public const ABILITY_SELECT_CLINIC = 'select-clinic';

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
