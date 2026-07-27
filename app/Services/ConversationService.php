<?php

namespace App\Services;

use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversationService
{
    public function __construct(
        protected AnalysisService $analysisService,
        protected DraftService $draftService,
    ) {
    }

    /**
     * Analisis thread terbaru dan generate draft balasan AI.
     *
     * Kegagalan AI tidak boleh menggagalkan proses pemanggil (webhook/sync)
     * karena pesan/percakapan itu sendiri sudah berhasil tersimpan.
     */
    public function triggerAiReply(Conversation $conversation): void
    {
        try {
            $thread = $this->buildPromptFromMessages(
                $conversation->messages()->orderBy('sent_at')->get()
            );

            $analysis = $this->analysisService->analyzeAndSave($conversation, $thread);
            $this->draftService->generateAndSave($conversation, $analysis);
        } catch (Throwable $e) {
            Log::error('AI processing failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
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