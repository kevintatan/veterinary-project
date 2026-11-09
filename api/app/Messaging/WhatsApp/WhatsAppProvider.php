<?php

namespace App\Messaging\WhatsApp;

/**
 * Sends WhatsApp messages. Chosen with WHATSAPP_DRIVER (config/whatsapp.php):
 * FakeWhatsAppProvider in development, MetaWhatsAppProvider in production.
 * Connecting the real WABA means configuring Meta and switching the driver:
 * nothing else changes (docs/whatsapp.md).
 */
interface WhatsAppProvider
{
    /** @throws WhatsAppException when the provider rejects the message */
    public function send(OutgoingMessage $message): SendResult;

    /**
     * Free-form text. Only allowed inside Meta's 24 h window after the owner wrote
     * (bot replies, staff replies from the inbox).
     */
    public function sendText(string $to, string $body): SendResult;

    /**
     * Text with up to 3 reply buttons ([id, title ≤ 20 chars]), same 24 h window.
     *
     * @param  list<array{0: string, 1: string}>  $buttons
     */
    public function sendButtons(string $to, string $body, array $buttons): SendResult;

    /** Short name stored in messages.provider: fake | meta */
    public function name(): string;
}
