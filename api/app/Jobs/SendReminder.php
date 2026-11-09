<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\ConsentType;
use App\Messaging\Bot\BotActions;
use App\Messaging\ReminderContent;
use App\Messaging\Reminders\ReminderSettings;
use App\Messaging\ReminderTexts;
use App\Messaging\WhatsApp\OutgoingMessage;
use App\Messaging\WhatsApp\WhatsAppException;
use App\Messaging\WhatsApp\WhatsAppProvider;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Message;
use App\Models\Owner;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Tenancy\Jobs\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sends one automatic reminder (Fase 4c).
 *
 * Never twice: the row is locked and moved queued → sending inside a
 * transaction before calling the provider. Everything that could have changed
 * since planning (consent, the pet's death, a newer vaccine, the plan) is
 * checked again here. Retries are ours (attempts + backoff), not the queue's.
 */
class SendReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 1;

    /** Id of the message being sent (generated before sending: it travels in the button payloads). */
    private ?string $messageId = null;

    /** Minutes to wait after the 1st, 2nd… failed attempt. */
    private const BACKOFF = [5, 30];

    public function __construct(public readonly string $reminderId)
    {
        $this->captureClinic();
    }

    public function handle(WhatsAppProvider $provider, ReminderTexts $texts): void
    {
        $clinic = Clinic::findOrFail($this->clinicId);

        /** @var array{0: ?Reminder, 1: ?OutgoingMessage} $claim */
        $claim = DB::transaction(function () use ($clinic, $texts) {
            $reminder = Reminder::whereKey($this->reminderId)->lockForUpdate()->first();

            if ($reminder === null || $reminder->status !== Reminder::QUEUED) {
                return [null, null]; // already handled by another worker, or cancelled meanwhile
            }

            $settings = ReminderSettings::for($clinic);
            if (! $settings->isOpen(now())) {
                $reminder->update(['status' => Reminder::PENDING, 'scheduled_for' => $settings->next(now())]);

                return [null, null];
            }

            [$verdict, $owner, $content] = $this->check($reminder, $clinic, $texts);

            if ($verdict !== null) {
                $reminder->update(['status' => $verdict]);

                return [null, null];
            }

            $template = config("whatsapp.templates.{$reminder->purpose}");
            $reminder->update(['status' => Reminder::SENDING, 'attempts' => $reminder->attempts + 1, 'owner_id' => $owner->id]);

            // The message id goes in the buttons' payloads: a tap tells the bot which appointment and
            // clinic it is about, even with the shared number (docs/whatsapp.md §6).
            $this->messageId = (string) Str::uuid7();
            $buttons = $reminder->purpose === 'appointment_reminder'
                ? array_map(fn (string $action) => "{$action}:{$this->messageId}", BotActions::REMINDER_BUTTONS)
                : [];

            return [$reminder, new OutgoingMessage($owner->mobile_phone, $template['name'], $template['language'], $content->parameters, $content->body, $buttons)];
        });

        [$reminder, $outgoing] = $claim;

        if ($reminder === null) {
            return;
        }

        try {
            $result = $provider->send($outgoing);
        } catch (WhatsAppException $e) {
            $retry = $e->retryable && $reminder->attempts < Reminder::MAX_ATTEMPTS;
            $reminder->update([
                'status' => $retry ? Reminder::PENDING : Reminder::FAILED,
                'scheduled_for' => $retry ? now()->addMinutes(self::BACKOFF[$reminder->attempts - 1] ?? 60) : $reminder->scheduled_for,
                'last_error' => $e->getMessage(),
            ]);

            return;
        }

        // forceFill: the id was chosen before sending (it is inside the button payloads).
        $message = (new Message)->forceFill(['id' => $this->messageId])->fill([
            'owner_id' => $reminder->owner_id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'provider' => $provider->name(),
            'purpose' => $reminder->purpose,
            'to_phone' => $outgoing->to,
            'body' => $outgoing->body,
            'payload' => ['template' => $outgoing->template, 'parameters' => $outgoing->parameters, 'buttons' => $outgoing->buttonPayloads, 'response' => $result->payload],
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->status,
            'related_type' => $reminder->remindable_type,
            'related_id' => $reminder->remindable_id,
            'sent_at' => now(),
        ]);
        $message->save();

        $reminder->update(['status' => Reminder::SENT, 'message_id' => $message->id, 'last_error' => null]);
        UsageCounter::add($clinic->id, 'whatsapp_utility');
    }

    /**
     * Why it must not be sent (a status), or the owner and text to send.
     *
     * @return array{0: ?string, 1: ?Owner, 2: ?ReminderContent}
     */
    private function check(Reminder $reminder, Clinic $clinic, ReminderTexts $texts): array
    {
        $subject = $reminder->remindable;

        // The event changed or disappeared: the planner already made (or will make) a new reminder.
        if ($subject instanceof Appointment) {
            if (! in_array($subject->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true) || ! $subject->starts_at->equalTo($reminder->event_at)) {
                return [Reminder::CANCELLED, null, null];
            }
            $pet = $subject->pet;
            $owner = $subject->owner;
        } elseif ($subject instanceof ClinicalRecord) {
            if ($subject->voided_at !== null || $subject->next_control_at === null) {
                return [Reminder::CANCELLED, null, null];
            }
            if (! ClinicalRecord::whereKey($subject->id)->unanswered()->exists()) {
                return ['skipped_answered', null, null];
            }
            // "Ask them to book" makes no sense if they already booked: the appointment reminder covers it.
            if (Appointment::where('pet_id', $subject->pet_id)->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])->where('starts_at', '>', now())->exists()) {
                return ['skipped_has_appointment', null, null];
            }
            $pet = $subject->pet;
            $owner = $pet?->owner;
        } else {
            return [Reminder::CANCELLED, null, null];
        }

        $subscription = Subscription::current();
        $limit = $subscription?->plan->limits['whatsapp_messages'] ?? 0;

        return match (true) {
            $owner === null || $pet === null => [Reminder::CANCELLED, null, null],
            $pet->deceased_at !== null => ['skipped_deceased', null, null],
            $subscription === null || ! $subscription->allowsWrites() || ! ($subscription->plan->features['whatsapp_bot'] ?? false) => ['skipped_plan', null, null],
            $limit !== null && UsageCounter::used('whatsapp_utility') >= $limit => ['skipped_limit', null, null],
            blank($owner->mobile_phone) => ['skipped_no_phone', null, null],
            ! $owner->hasConsent(ConsentType::ChannelWhatsapp) => ['skipped_no_consent', null, null],
            default => [null, $owner, $subject instanceof Appointment ? $texts->appointment($subject, $clinic, withButtons: true) : $texts->nextControl($subject, $clinic)],
        };
    }
}
