<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * File attached to a clinical record (or pet/owner). Stored under
 * clinics/{clinic_id}/… on the configured disk; never publicly reachable.
 *
 * @property string $id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 */
class Attachment extends Model
{
    use BelongsToClinic, HasUuids, SoftDeletes, StoresDatesInUtc;

    public const CATEGORIES = ['image', 'lab_result', 'imaging', 'document', 'other'];

    public const MAX_KILOBYTES = 20480;

    public const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'dcm'];

    protected $fillable = [
        'attachable_type', 'attachable_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
        'category', 'visible_to_owner', 'uploaded_by',
    ];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'visible_to_owner' => 'boolean',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
