<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The owner's portal account (Fase 4e). Platform model: NOT tenant scoped.
 * Its tokens carry the "tutor" ability and no clinic, so they never pass
 * the staff routes (ResolveClinicFromToken requires a clinic token).
 */
class TutorAccount extends Authenticatable
{
    use HasApiTokens, HasUuids, StoresDatesInUtc;

    public const ABILITY = 'tutor';

    /** Sessions last long: the owner verifies once (docs/fase-4.md "Acceso del tutor"). */
    public const TOKEN_DAYS = 180;

    protected $fillable = ['name', 'email', 'phone', 'password', 'last_login_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function issueToken(string $device): string
    {
        return $this->createToken($device, [self::ABILITY], now()->addDays(self::TOKEN_DAYS))->plainTextToken;
    }
}
