<?php

namespace App\Services;

use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\Message;
use Illuminate\Support\Collection;
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

        $thread = $this->buildPromptFromMessages(
            $conversation->messages()->orderBy('sent_at')->get()
        );

        $analysis = $this->analysisService->analyzeAndSave($conversation, $thread);

        return $this->draftService->generateAndSave($conversation, $analysis, $asNewVersion);
    }

    /**
     * Mengubah kumpulan Message (Eloquent) menjadi format thread untuk AI.
     *
     * @param  Collection<int, Message>  $messages
     */
    protected function buildPromptFromMessages(Collection $messages): string
    {
        return $messages
            ->map(function (Message $message) {
                $sender = $message->sender_type === SenderType::Customer ? 'Customer' : 'Agent';

                return "{$sender}: {$message->body}";
            })
            ->implode("\n\n");
    }
}
