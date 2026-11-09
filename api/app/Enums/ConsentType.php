<?php

namespace App\Enums;

/**
 * Habeas Data (Ley 1581): processing authorization + opt-in per channel.
 */
enum ConsentType: string
{
    case DataProcessing = 'data_processing';
    case ChannelWhatsapp = 'channel_whatsapp';
    case ChannelSms = 'channel_sms';
    case ChannelEmail = 'channel_email';
    case Marketing = 'marketing';

    /**
     * @return list<self>
     */
    public static function channels(): array
    {
        return [self::ChannelWhatsapp, self::ChannelSms, self::ChannelEmail];
    }

    /** Request/response key: channel_whatsapp => whatsapp. */
    public function key(): string
    {
        return str_replace('channel_', '', $this->value);
    }
}
