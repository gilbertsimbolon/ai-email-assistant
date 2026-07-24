<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Enums\DraftStatus;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Models\Draft;
use Illuminate\Support\Arr;

class DraftService
{
    public function __construct(
        protected PromptService $promptService,
        protected OpenAIService $openAIService
    ) {
    }

    /**
     * Generate an AI reply draft for the conversation and persist it.
     *
     * The draft is never sent automatically — it is stored as `active`
     * and must be reviewed/approved by an agent in the Inbox.
     */
    public function generate(Conversation $conversation, string $thread, Analysis $analysis): Draft
    {
        $analysisData = $analysis->raw_json ?? Arr::only($analysis->toArray(), [
            'language', 'customer_intent', 'priority', 'sentiment',
            'customer_status', 'escalation_required', 'refund_requested',
            'summary', 'last_customer_request', 'recommended_action', 'confidence_score',
        ]);

        $prompt = $conversation->channel === ChannelType::WhatsApp
            ? $this->promptService->buildWhatsAppPrompt($thread, $analysisData)
            : $this->promptService->buildEmailPrompt($thread, $analysisData);

        $body = $this->openAIService->text($prompt);

        return $this->save($conversation, [
            'subject' => $this->buildSubject($conversation),
            'body' => $body,
            'tone' => 'professional',
            'confidence' => $analysis->confidence_score !== null ? (float) $analysis->confidence_score : null,
        ]);
    }

    /**
     * Persist a draft as the new active version, regenerating the previous one.
     */
    public function save(Conversation $conversation, array $draft): Draft
    {
        $nextVersion = $conversation->drafts()->max('version') + 1;

        $conversation->drafts()
            ->where('status', DraftStatus::Active)
            ->update(['status' => DraftStatus::Regenerated]);

        return $conversation->drafts()->create([
            'type' => $conversation->channel->value,
            'provider' => 'openai',
            'content' => [
                'subject' => $draft['subject'] ?? $this->buildSubject($conversation),
                'body' => $draft['body'],
                'tone' => $draft['tone'] ?? 'professional',
                'confidence' => $draft['confidence'] ?? null,
            ],
            'version' => $nextVersion,
            'status' => DraftStatus::Active,
        ]);
    }

    protected function buildSubject(Conversation $conversation): string
    {
        return $conversation->subject
            ? 'Re: '.$conversation->subject
            : 'Re: Your inquiry';
    }
}
