<?php

namespace App\Services;

use App\Enums\DraftStatus;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\Analysis;
use App\Services\AI\AiConfigurationService;
use App\Services\AI\Contracts\AiClientInterface;

class DraftService
{
    public function __construct(
        protected PromptService $promptService,
        protected AiClientInterface $aiClient,
        protected AiConfigurationService $aiConfig
    ) {
    }

    /**
     * Generate reply draft using AI.
     */
    public function generate(Conversation $conversation, Analysis $analysis): string
    {
        $prompt = $this->promptService
            ->buildDraftPrompt($conversation, $analysis);

        $response = $this->aiClient->chat($prompt);

        return $response['content'];
    }

    /**
     * Save draft result. Exactly one draft per conversation is ever
     * "Active" at a time. If one already exists, either replace it in place
     * (default — "Replace Draft") or supersede it (status -> Regenerated,
     * kept as read-only history) and create a new version (asNewVersion —
     * "Create New Version").
     */
    public function save(
        Conversation $conversation,
        string $content,
        bool $asNewVersion = false
    ): Draft {
        // Ambil nilai string dari channel conversation dengan aman
        $channelValue = $conversation->channel instanceof \BackedEnum
            ? $conversation->channel->value
            : (string) $conversation->channel;

        $payload = [
            'content' => [
                'subject' => $conversation->subject ?: 'Re: percakapan Anda',
                'body' => $content,
                'tone' => null,
                'confidence' => null,
            ],
            'type' => strtolower($channelValue), // Pastikan nilai berupa string backing value
            'provider' => $this->aiConfig->getProvider()->value,
        ];

        $current = $conversation->drafts()
            ->where('status', DraftStatus::Active)
            ->first();

        if ($current && !$asNewVersion) {
            $current->update($payload + ['status' => DraftStatus::Active]);

            return $current->fresh();
        }

        if ($current) {
            $current->update(['status' => DraftStatus::Regenerated]);
        }

        return Draft::create($payload + [
            'conversation_id' => $conversation->id,
            'version' => $current ? $current->version + 1 : 1,
            'status' => DraftStatus::Active,
        ]);
    }

    /**
     * Generate the reply draft and persist it in a single call.
     */
    public function generateAndSave(Conversation $conversation, Analysis $analysis, bool $asNewVersion = false): Draft
    {
        return $this->save($conversation, $this->generate($conversation, $analysis), $asNewVersion);
    }
}
