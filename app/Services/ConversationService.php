<?php

namespace App\Services;

use App\Enums\SenderType;
use App\Models\Message;
use Illuminate\Support\Collection;

class ConversationService
{
    public function __construct(
        protected GoHighLevelService $ghl
    ) {
    }

    /**
     * Mengambil seluruh thread dari GoHighLevel.
     */
    public function getThread(string $conversationId): array
    {
        return $this->ghl->getConversationMessages($conversationId);
    }

    /**
     * Mengurutkan pesan dari yang paling lama ke yang terbaru.
     */
    public function normalize(array $messages): array
    {
        return collect($messages)
            ->sortBy('dateAdded')
            ->values()
            ->all();
    }

    /**
     * Mengubah thread menjadi format yang mudah dipahami AI.
     */
    public function buildPrompt(array $messages): string
    {
        return collect($messages)
            ->map(function ($message) {

                $sender = ($message['direction'] ?? '') === 'inbound'
                    ? 'Customer'
                    : 'Agent';

                $body = trim($message['body'] ?? '');

                return "{$sender}: {$body}";
            })
            ->implode("\n\n");
    }

    /**
     * Mengubah kumpulan Message (Eloquent) menjadi format thread untuk AI.
     *
     * @param Collection<int, Message> $messages
     */
    public function buildPromptFromMessages(Collection $messages): string
    {
        return $messages
            ->map(function (Message $message) {
                $sender = $message->sender_type === SenderType::Customer ? 'Customer' : 'Agent';

                return "{$sender}: {$message->body}";
            })
            ->implode("\n\n");
    }
}