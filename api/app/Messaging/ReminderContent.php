<?php

namespace App\Messaging;

/** A reminder ready to send: the readable text and the template variables. */
final class ReminderContent
{
    /** @param list<string> $parameters */
    public function __construct(
        public readonly string $purpose,
        public readonly string $body,
        public readonly array $parameters,
    ) {}
}
