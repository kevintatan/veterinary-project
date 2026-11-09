<?php

namespace App\Messaging\Bot;

use App\Messaging\WhatsApp\WhatsAppException;
use App\Messaging\WhatsApp\WhatsAppProvider;
use App\Models\Message;
use App\Models\UsageCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Sends a free-form reply inside Meta's 24 h window (bot or staff) and logs it
 * in `messages`. Must run inside the clinic's tenant context.
 */
class WhatsAppReplier
{
    public function __construct(private readonly WhatsAppProvider $provider) {}

    /**
     * @param  list<array{0: string, 1: string}>  $buttons
     */
    public function reply(string $clinicId, string $to, ?string $ownerId, string $body, array $buttons = [], ?Model $related = null, ?string $sentBy = null, string $purpose = 'bot_reply'): ?Message
    {
        try {
            $result = $buttons === [] ? $this->provider->sendText($to, $body) : $this->provider->sendButtons($to, $body, $buttons);
        } catch (WhatsAppException $e) {
            Log::warning('whatsapp.reply_failed', ['to' => $to, 'error' => $e->getMessage()]);

            return null;
        }

        UsageCounter::add($clinicId, 'whatsapp_service'); // free inside the window (Meta), counted to watch volume

        return Message::create([
            'owner_id' => $ownerId,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'provider' => $this->provider->name(),
            'purpose' => $purpose,
            'to_phone' => $to,
            'body' => $body,
            'payload' => ['buttons' => $buttons, 'response' => $result->payload],
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->status,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'sent_by' => $sentBy,
            'sent_at' => now(),
        ]);
    }
}
