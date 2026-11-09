<?php

namespace App\Models\Documents;

use App\Models\Concerns\StoresDatesInUtc;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\User;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** A consent / authorization for one pet (Documentos y firmas). */
class PetDocument extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity, StoresDatesInUtc;

    public const DRAFT = 'draft';

    public const SIGNING = 'signing'; // at least one signature: content locked

    public const SIGNED = 'signed';

    public const VOIDED = 'voided';

    public const STATUSES = [self::DRAFT => 'Borrador', self::SIGNING => 'Firmando', self::SIGNED => 'Firmado', self::VOIDED => 'Anulado'];

    protected $attributes = ['status' => self::DRAFT];

    protected $fillable = ['pet_id', 'owner_id', 'type', 'template_version', 'title', 'data', 'snapshot', 'status', 'vet_id', 'content_hash', 'pdf_path', 'pdf_sha256', 'signed_at', 'voided_at', 'voided_by', 'void_reason', 'created_by'];

    protected function casts(): array
    {
        return ['data' => 'array', 'snapshot' => 'array', 'signed_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function vet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vet_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(PetDocumentSignature::class);
    }

    public function isEditable(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'type', 'void_reason'])->logOnlyDirty();
    }
}
