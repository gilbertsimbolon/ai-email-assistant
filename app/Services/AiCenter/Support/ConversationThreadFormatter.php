<?php

namespace App\Services\AiCenter\Support;

use App\Enums\SenderType;
use App\Models\Message;
use Illuminate\Support\Collection;

/**
 * Formats a conversation's messages into a single thread string, shared by
 * AiGenerationService (production, persisted Message rows) and the
 * Playground (an ephemeral, unsaved Message built from pasted email text).
 */
class ConversationThreadFormatter
{
    /**
     * @param  Collection<int, Message>  $messages
     */
    public function format(Collection $messages): string
    {
        return $messages
            ->map(function (Message $message) {
                $sender = $message->sender_type === SenderType::Customer ? 'Customer' : 'Agent';

                return "{$sender}: {$message->body}";
            })
            ->implode("\n\n");
    }
}
