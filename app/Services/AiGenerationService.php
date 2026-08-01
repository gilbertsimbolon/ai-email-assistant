<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Draft;
use App\Services\AiCenter\Support\ConversationThreadFormatter;
use App\Services\Ghl\GhlThreadLoader;
use RuntimeException;

/**
 * The only entry point in the app allowed to spend AI tokens for a
 * conversation. Called exclusively from an authenticated, user-initiated
 * HTTP request (the "Generate AI Reply" button) — never from Gmail
 * sync/webhooks/queues.
 */
class AiGenerationService
{
    public function __construct(
        protected AnalysisService $analysisService,
        protected DraftService $draftService,
        protected ConversationThreadFormatter $threadFormatter,
        protected GhlThreadLoader $ghlThreadLoader,
    ) {
    }

    /**
     * Analisis thread terbaru dan generate draft balasan AI.
     */
    public function generateReply(Conversation $conversation, bool $asNewVersion = false): Draft
    {
        $messages = $this->messagesFor($conversation);

        if ($messages->isEmpty()) {
            throw new RuntimeException('Belum ada pesan pada percakapan ini untuk dianalisis.');
        }

        $thread = $this->threadFormatter->format($messages);

        $analysis = $this->analysisService->analyzeAndSave($conversation, $thread);

        return $this->draftService->generateAndSave($conversation, $analysis, $thread, $asNewVersion);
    }

    /**
     * GHL-sourced conversations are never mirrored into the messages table
     * (claude.txt) — their thread is fetched live on every call. Gmail-
     * sourced ones keep reading the real, persisted relation.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Message>
     */
    protected function messagesFor(Conversation $conversation)
    {
        if (filled($conversation->ghl_conversation_id)) {
            return $this->ghlThreadLoader->messages($conversation->ghl_conversation_id);
        }

        return $conversation->messages()->orderBy('sent_at')->get();
    }
}
