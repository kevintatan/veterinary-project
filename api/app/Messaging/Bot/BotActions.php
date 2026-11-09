<?php

namespace App\Messaging\Bot;

/**
 * Button payloads understood by the bot: "{action}:{message id}[:{extra}]".
 * The message id is our outbound message (the reminder) the owner is answering.
 */
final class BotActions
{
    public const CONFIRM = 'confirm';

    public const RESCHEDULE = 'reschedule';

    public const CANCEL = 'cancel';

    public const CANCEL_YES = 'cancel_yes';

    public const CANCEL_NO = 'cancel_no';

    public const SLOT = 'slot';

    /** Quick-reply buttons of the appointment reminder template, in order (docs/whatsapp.md §3). */
    public const REMINDER_BUTTONS = [self::CONFIRM, self::RESCHEDULE, self::CANCEL];

    /** @return array{0: string, 1: string, 2: ?string}|null [action, message id, extra] */
    public static function parse(?string $payload): ?array
    {
        if ($payload === null || ! preg_match('/^([a-z_]+):([0-9a-f-]{36})(?::(.+))?$/', $payload, $m)) {
            return null;
        }

        return [$m[1], $m[2], $m[3] ?? null];
    }
}
