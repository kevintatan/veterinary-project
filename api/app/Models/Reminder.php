<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One scheduled reminder (Fase 4c). Created by ReminderPlanner when an
 * appointment or a clinical record changes; sent by SendReminder.
 */
class Reminder extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    public const PENDING = 'pending';

    public const QUEUED = 'queued';     // picked by the dispatcher, waiting for a worker

    public const SENDING = 'sending';   // handed to the provider (a crash here is reviewed by hand, never re-sent)

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    /** Why a reminder was not sent (shown to the clinic). */
    public const SKIPPED = [
        'skipped_no_consent' => 'El tutor no autorizó WhatsApp',
        'skipped_no_phone' => 'El tutor no tiene celular',
        'skipped_deceased' => 'La mascota falleció',
        'skipped_answered' => 'Ya se resolvió (cita nueva o vacuna aplicada)',
        'skipped_has_appointment' => 'La mascota ya tiene una cita agendada',
        'skipped_limit' => 'Se alcanzó el límite de mensajes del plan este mes',
        'skipped_plan' => 'El plan no incluye recordatorios automáticos',
    ];

    public const MAX_ATTEMPTS = 3;

    protected $fillable = ['remindable_type', 'remindable_id', 'owner_id', 'rule_id', 'channel', 'purpose', 'event_at', 'scheduled_for', 'status', 'message_id', 'attempts', 'last_error'];

    protected function casts(): array
    {
        return ['event_at' => 'datetime', 'scheduled_for' => 'datetime', 'attempts' => 'integer'];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING, self::QUEUED => 'Programado',
            self::SENDING => 'Enviando',
            self::SENT => 'Enviado',
            self::FAILED => 'Falló',
            self::CANCELLED => 'Cancelado',
            default => self::SKIPPED[$this->status] ?? $this->status,
        };
    }
}
