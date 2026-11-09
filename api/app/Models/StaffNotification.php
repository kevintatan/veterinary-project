<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A notification in the bell of one person of the clinic (docs/notificaciones.md).
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 */
class StaffNotification extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
