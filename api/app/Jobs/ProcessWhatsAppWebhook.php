<?php

namespace App\Jobs;

use App\Messaging\Bot\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Platform-level job (no clinic yet): WebhookProcessor routes each message
 * to its clinic and runs inside it.
 */
class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @param array<string, mixed> $payload */
    public function __construct(public readonly array $payload) {}

    public function handle(WebhookProcessor $processor): void
    {
        $processor->process($this->payload);
    }
}
