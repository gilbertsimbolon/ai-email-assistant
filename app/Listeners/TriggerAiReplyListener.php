<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Services\ConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Queued so a slow OpenAI call never blocks the Gmail sync job that
 * dispatched MessageReceived.
 */
class TriggerAiReplyListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ConversationService $conversationService,
    ) {
    }

    public function handle(MessageReceived $event): void
    {
        $this->conversationService->triggerAiReply($event->conversation);
    }
}
