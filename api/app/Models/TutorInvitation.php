<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One-use link a clinic sends to an owner to activate the portal. Only the
 * sha256 of the token is stored: a database leak doesn't leak usable links.
 */
class TutorInvitation extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    public const DAYS = 7;

    protected $fillable = ['owner_id', 'token_hash', 'expires_at', 'accepted_at', 'tutor_account_id', 'created_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
