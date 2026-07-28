<?php

namespace App\Services;

use App\Enums\AiCenter\AiCenterLogSource;
use App\Enums\DraftStatus;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Models\Draft;
use App\Services\AI\AiConfigurationService;
use App\Services\AiCenter\AiCenterPipeline;

class DraftService
{
    public function __construct(
        protected AiCenterPipeline $pipeline,
        protected AiConfigurationService $aiConfig,
    ) {
    }

    /**
     * Generate reply draft by running the full AI Center pipeline (Intent
     * Detection has already resolved onto $analysis by AnalysisService;
     * from here it's SOP Matching -> Rule Engine -> Workflow Engine ->
     * Knowledge Base Retrieval -> Reply Template Selection -> Prompt
     * Builder -> AI Generate Draft). When nothing is configured yet,
     * PromptBuilder falls back to generic instructions so this behaves
     * exactly like the old hardcoded PromptService prompts.
     */
    public function generate(Conversation $conversation, Analysis $analysis, string $thread): string
    {
        $result = $this->pipeline->run(
            $conversation,
            $analysis,
            $thread,
            AiCenterLogSource::Production,
            auth()->id(),
        );

        return $result->draftContent;
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
    public function generateAndSave(Conversation $conversation, Analysis $analysis, string $thread, bool $asNewVersion = false): Draft
    {
        return $this->save($conversation, $this->generate($conversation, $analysis, $thread), $asNewVersion);
    }
}
