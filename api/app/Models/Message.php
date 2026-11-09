<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One message sent or received, on any channel (docs/database.md §10).
 */
class Message extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    public const PROVIDER_MANUAL = 'manual';

    public const STATUS_OPENED = 'opened'; // manual: the WhatsApp link was opened by staff

    protected $fillable = [
        'clinic_id', 'owner_id', 'channel', 'direction', 'provider', 'purpose', 'to_phone', 'from_phone',
        'template_id', 'body', 'payload', 'provider_message_id', 'status', 'pricing_category', 'cost_usd',
        'error', 'related_type', 'related_id', 'sent_by', 'sent_at', 'delivered_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
