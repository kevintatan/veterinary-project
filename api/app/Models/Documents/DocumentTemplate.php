<?php

namespace App\Models\Documents;

use App\Models\Concerns\StoresDatesInUtc;
use App\Models\User;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The clinic's own wording of a consent type (D4). */
class DocumentTemplate extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $fillable = ['type', 'body', 'version', 'base_version', 'updated_by'];

    protected function casts(): array
    {
        return ['body' => 'array'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
