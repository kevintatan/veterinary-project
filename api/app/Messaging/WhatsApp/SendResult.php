<?php

namespace App\Messaging\WhatsApp;

final class SendResult
{
    /**
     * @param  array<string, mixed>  $payload  raw provider response (stored in messages.payload)
     */
    public function __construct(
        public readonly string $providerMessageId, // Meta "wamid"; matches the status webhooks (4d)
        public readonly string $status,            // sent | queued
        public readonly array $payload = [],
    ) {}
}
