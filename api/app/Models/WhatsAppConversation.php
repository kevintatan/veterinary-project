<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Bot state per phone (platform table: not tenant scoped). Always filter by
 * active_clinic_id explicitly when showing it to a clinic.
 */
class WhatsAppConversation extends Model
{
    use HasUuids, StoresDatesInUtc;

    protected $table = 'whatsapp_conversations';

    public const IDLE = 'idle';

    public const HANDOFF = 'handoff_human';

    protected $fillable = ['phone', 'active_clinic_id', 'owner_id', 'state', 'context', 'last_inbound_at', 'needs_attention_since', 'assigned_user_id'];

    protected function casts(): array
    {
        return ['context' => 'array', 'last_inbound_at' => 'datetime', 'needs_attention_since' => 'datetime'];
    }

    /** Meta allows free-form messages only within 24 h of the owner's last message. */
    public function windowOpen(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subDay());
    }
}
