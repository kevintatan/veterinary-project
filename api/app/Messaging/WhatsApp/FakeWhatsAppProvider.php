<?php

namespace App\Messaging\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development / pre-WABA provider: nothing leaves the server. Each message is
 * written to the log (storage/logs/laravel.log, "whatsapp.fake") and returns a
 * fake id, so the whole flow (scheduling, sending, bot, log, usage) can be
 * tested. The owner's replies are simulated with POST /dev/whatsapp/simulate.
 */
class FakeWhatsAppProvider implements WhatsAppProvider
{
    public function send(OutgoingMessage $message): SendResult
    {
        return $this->log('template', $message->to, $message->body, [
            'template' => $message->template,
            'parameters' => $message->parameters,
            'buttons' => $message->buttonPayloads,
        ]);
    }

    public function sendText(string $to, string $body): SendResult
    {
        return $this->log('text', $to, $body);
    }

    public function sendButtons(string $to, string $body, array $buttons): SendResult
    {
        return $this->log('buttons', $to, $body, ['buttons' => $buttons]);
    }

    public function name(): string
    {
        return 'fake';
    }

    /** @param array<string, mixed> $extra */
    private function log(string $kind, string $to, string $body, array $extra = []): SendResult
    {
        $id = 'fake.'.Str::uuid7();
        Log::info('whatsapp.fake', ['id' => $id, 'kind' => $kind, 'to' => $to, 'body' => $body, ...$extra]);

        return new SendResult($id, 'sent', ['fake' => true, 'kind' => $kind, ...$extra]);
    }
}
