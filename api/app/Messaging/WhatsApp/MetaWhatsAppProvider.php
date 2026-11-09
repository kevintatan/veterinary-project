<?php

namespace App\Messaging\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * WhatsApp Business Cloud API (Meta), shared Zooetia number.
 * Docs: developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaWhatsAppProvider implements WhatsAppProvider
{
    public function send(OutgoingMessage $message): SendResult
    {
        $components = [[
            'type' => 'body',
            'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], $message->parameters),
        ]];

        // Quick-reply buttons defined in the approved template: we only fill their payloads.
        foreach ($message->buttonPayloads as $index => $payload) {
            $components[] = ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => (string) $index, 'parameters' => [['type' => 'payload', 'payload' => $payload]]];
        }

        return $this->post($message->to, [
            'type' => 'template',
            'template' => [
                'name' => $message->template,
                'language' => ['code' => $message->language],
                'components' => $components,
            ],
        ]);
    }

    public function sendText(string $to, string $body): SendResult
    {
        return $this->post($to, ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $body]]);
    }

    public function sendButtons(string $to, string $body, array $buttons): SendResult
    {
        return $this->post($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => array_map(fn (array $b) => [
                    'type' => 'reply',
                    'reply' => ['id' => $b[0], 'title' => Str::limit($b[1], 20, '')], // Meta: title ≤ 20 chars
                ], array_slice($buttons, 0, 3))],
            ],
        ]);
    }

    public function name(): string
    {
        return 'meta';
    }

    /** @param array<string, mixed> $content */
    private function post(string $to, array $content): SendResult
    {
        $config = config('whatsapp.meta');

        if (blank($config['phone_number_id']) || blank($config['access_token'])) {
            throw new WhatsAppException('WhatsApp (Meta) no está configurado: faltan WHATSAPP_PHONE_NUMBER_ID o WHATSAPP_ACCESS_TOKEN.', retryable: false);
        }

        try {
            $response = Http::withToken($config['access_token'])
                ->acceptJson()
                ->timeout(15)
                ->post("https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($to, '+'),
                    ...$content,
                ]);
        } catch (ConnectionException $e) {
            throw new WhatsAppException('Sin conexión con Meta: '.$e->getMessage());
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? [];
            // 4xx are usually permanent (invalid number, template not approved); 429/5xx are worth retrying.
            $retryable = $response->status() === 429 || $response->serverError();

            throw new WhatsAppException(sprintf('Meta %d: %s', $response->status(), $error['message'] ?? $response->body()), $retryable);
        }

        return new SendResult((string) $response->json('messages.0.id'), 'sent', (array) $response->json());
    }
}
