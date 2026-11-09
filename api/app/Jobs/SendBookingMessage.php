<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Messaging\Bot\BotActions;
use App\Messaging\ReminderTexts;
use App\Messaging\WhatsApp\OutgoingMessage;
use App\Messaging\WhatsApp\WhatsAppException;
use App\Messaging\WhatsApp\WhatsAppProvider;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Message;
use App\Models\UsageCounter;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsApp to the person who asked for an online booking (docs/reservas.md, B2):
 * request received / accepted / rejected. Templates, because we write first.
 *
 * They answer the tutor's own request, so they are sent at any hour and are
 * not stopped by the monthly reminder limit (they are counted in it).
 */
class SendBookingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public const RECEIVED = 'booking_received';

    public const ACCEPTED = 'booking_accepted';

    public const REJECTED = 'booking_rejected';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** @param self::RECEIVED|self::ACCEPTED|self::REJECTED $purpose */
    public function __construct(public readonly string $appointmentId, public readonly string $purpose)
    {
        $this->captureClinic();
    }

    public function handle(WhatsAppProvider $provider, ReminderTexts $texts): void
    {
        $clinic = Clinic::findOrFail($this->clinicId);
        $appointment = Appointment::with('owner')->find($this->appointmentId);

        if ($appointment === null || ! $this->stillTrue($appointment)) {
            return; // the situation changed before sending (e.g. accepted right after being received)
        }

        // Accepted: the tutor's phone (possibly just updated); before that, the one typed in the form.
        $to = $appointment->owner?->mobile_phone ?? ($appointment->booking_details['phone'] ?? null);
        if ($to === null) {
            return;
        }

        $messageId = (string) Str::uuid7();
        $buttons = match ($this->purpose) {
            self::RECEIVED => [BotActions::CANCEL.':'.$messageId],                                  // "Cancelar solicitud"
            self::ACCEPTED => [BotActions::RESCHEDULE.':'.$messageId, BotActions::CANCEL.':'.$messageId], // "Reprogramar", "Cancelar"
            default => [],
        };
        $content = $texts->booking($this->purpose, $appointment, $clinic);
        $template = config("whatsapp.templates.{$this->purpose}");

        try {
            $result = $provider->send(new OutgoingMessage($to, $template['name'], $template['language'], $content->parameters, $content->body, $buttons));
        } catch (WhatsAppException $e) {
            Log::warning('whatsapp.booking_failed', ['appointment' => $appointment->id, 'purpose' => $this->purpose, 'error' => $e->getMessage()]);

            if ($e->retryable) {
                throw $e; // the queue retries (tries/backoff)
            }

            return;
        }

        // The id travels in the buttons: a tap tells the bot which request it is about.
        (new Message)->forceFill(['id' => $messageId])->fill([
            'owner_id' => $appointment->owner_id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'provider' => $provider->name(),
            'purpose' => $this->purpose,
            'to_phone' => $to,
            'body' => $content->body,
            'payload' => ['template' => $template['name'], 'parameters' => $content->parameters, 'buttons' => $buttons, 'response' => $result->payload],
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->status,
            'related_type' => $appointment->getMorphClass(),
            'related_id' => $appointment->id,
            'sent_at' => now(),
        ])->save();

        UsageCounter::add($clinic->id, 'whatsapp_utility');
    }

    private function stillTrue(Appointment $appointment): bool
    {
        return match ($this->purpose) {
            self::RECEIVED => $appointment->status === AppointmentStatus::Requested,
            self::ACCEPTED => in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true),
            self::REJECTED => $appointment->status === AppointmentStatus::Cancelled,
            default => false,
        };
    }
}
