<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Habeas Data evidence. Append-only: never updated or deleted;
 * revoking is inserting granted = false.
 *
 * @property string $type
 * @property bool $granted
 */
class OwnerConsent extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    public const UPDATED_AT = null;

    protected $fillable = ['owner_id', 'type', 'granted', 'policy_version', 'source', 'recorded_by', 'evidence'];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'evidence' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $readOnly = fn () => throw new LogicException('Consent records are append-only.');

        static::updating($readOnly);
        static::deleting($readOnly);
    }
}
