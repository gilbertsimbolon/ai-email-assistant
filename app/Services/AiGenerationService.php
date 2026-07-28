<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Draft;
use App\Services\AiCenter\Support\ConversationThreadFormatter;
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
    ) {
    }

    /**
     * Analisis thread terbaru dan generate draft balasan AI.
     */
    public function generateReply(Conversation $conversation, bool $asNewVersion = false): Draft
    {
        if ($conversation->messages()->count() === 0) {
            throw new RuntimeException('Belum ada pesan pada percakapan ini untuk dianalisis.');
        }

        $thread = $this->threadFormatter->format(
            $conversation->messages()->orderBy('sent_at')->get()
        );

        $analysis = $this->analysisService->analyzeAndSave($conversation, $thread);

        return $this->draftService->generateAndSave($conversation, $analysis, $thread, $asNewVersion);
    }
}
