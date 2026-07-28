<?php

namespace App\Services;

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
     * Save draft result.
     */
    public function save(
        Conversation $conversation,
        string $content
    ): Draft {
        // Ambil nilai string dari channel conversation dengan aman
        $channelValue = $conversation->channel instanceof \BackedEnum 
            ? $conversation->channel->value 
            : (string) $conversation->channel;

        return Draft::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'content' => [
                    'subject' => $conversation->subject ?: 'Re: percakapan Anda',
                    'body' => $content,
                    'tone' => null,
                    'confidence' => null,
                ],
                'type' => strtolower($channelValue), // Pastikan nilai berupa string backing value
                'status' => 'active',
                'provider' => $this->aiConfig->getProvider()->value,
            ]
        );
    }

    /**
     * Generate the reply draft and persist it in a single call.
     */
    public function generateAndSave(Conversation $conversation, Analysis $analysis): Draft
    {
        return $this->save($conversation, $this->generate($conversation, $analysis));
    }
}
