<?php

namespace App\Enums\AiCenter;

/**
 * Purely descriptive catalog of channels the omnichannel roadmap names
 * (claude.txt). Deliberately decoupled from App\Enums\ChannelType, which
 * only lists channels actually wired into Conversation today — SopMatchingEngine
 * filters against the string value, so a SOP can be tagged for a channel
 * before that channel's integration exists.
 */
enum SupportedChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case FacebookMessenger = 'facebook_messenger';
    case Telegram = 'telegram';
    case LiveChat = 'live_chat';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Gmail',
            self::WhatsApp => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::FacebookMessenger => 'Facebook Messenger',
            self::Telegram => 'Telegram',
            self::LiveChat => 'Live Chat',
        };
    }
}
