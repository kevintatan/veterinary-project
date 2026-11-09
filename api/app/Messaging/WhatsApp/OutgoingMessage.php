<?php

namespace App\Messaging\WhatsApp;

/**
 * A template message to send. `body` is the rendered text (what the owner
 * reads; stored in `messages`), `template` + `parameters` is what Meta needs.
 */
final class OutgoingMessage
{
    /**
     * @param  list<string>  $parameters  values for {{1}}, {{2}}… of the template body
     * @param  list<string>  $buttonPayloads  what Meta sends back when the owner taps each button
     */
    public function __construct(
        public readonly string $to,          // E.164, e.g. +573123334455
        public readonly string $template,    // approved template name
        public readonly string $language,    // es_CO
        public readonly array $parameters,
        public readonly string $body,
        public readonly array $buttonPayloads = [], // quick-reply payloads of the template buttons, in order
    ) {}
}
