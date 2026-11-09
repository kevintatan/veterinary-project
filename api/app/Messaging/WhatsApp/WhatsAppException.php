<?php

namespace App\Messaging\WhatsApp;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    /** Errors worth retrying (rate limits, Meta outages) vs. permanent ones (bad number, template). */
    public function __construct(string $message, public readonly bool $retryable = true)
    {
        parent::__construct($message);
    }
}
