<?php

namespace App\Models\Documents;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A link sent to the owner to sign a document from their phone (D3). */
class PetDocumentRequest extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    public const HOURS = 48;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['pet_document_id', 'role', 'token_hash', 'channel', 'expires_at', 'attempts', 'used_at', 'revoked_at', 'created_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PetDocument::class, 'pet_document_id');
    }

    public function isActive(): bool
    {
        return $this->used_at === null && $this->revoked_at === null && $this->expires_at->isFuture() && $this->attempts < self::MAX_ATTEMPTS;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
