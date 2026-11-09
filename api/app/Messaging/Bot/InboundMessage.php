<?php

namespace App\Messaging\Bot;

/** An owner's message as received from Meta's webhook, normalized. */
final class InboundMessage
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $from,            // E.164
        public readonly string $wamid,           // Meta message id (idempotency)
        public readonly string $type,            // button | text | other
        public readonly ?string $payload,        // button payload / reply id
        public readonly ?string $text,           // typed text or the button's label
        public readonly ?string $contextWamid,   // the message being answered, if quoted
        public readonly ?string $profileName,
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $message one item of value.messages[] */
    public static function fromMeta(array $message, ?string $profileName): self
    {
        $type = $message['type'] ?? 'other';

        [$kind, $payload, $text] = match ($type) {
            'text' => ['text', null, $message['text']['body'] ?? ''],
            // Template quick-reply buttons
            'button' => ['button', $message['button']['payload'] ?? null, $message['button']['text'] ?? null],
            // Interactive reply buttons / list rows (bot messages)
            'interactive' => ['button', $message['interactive']['button_reply']['id'] ?? $message['interactive']['list_reply']['id'] ?? null, $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? null],
            default => ['other', null, null],
        };

        return new self('+'.ltrim((string) ($message['from'] ?? ''), '+'), (string) ($message['id'] ?? ''), $kind, $payload, $text, $message['context']['id'] ?? null, $profileName, $message);
    }
}
