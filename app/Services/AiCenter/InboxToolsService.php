<?php

namespace App\Services\AiCenter;

use App\Enums\AiCenter\AiCenterLogSource;
use App\Enums\AiCenter\AiCenterLogStatus;
use App\Models\AiCenter\AiLog;
use App\Models\Conversation;
use App\Services\AI\Contracts\AiClientInterface;
use App\Services\AiCenter\Engines\IntentDetectionEngine;
use App\Services\AiCenter\Engines\SopMatchingEngine;
use App\Services\AiCenter\Support\ConversationThreadFormatter;
use App\Services\AiCenter\Support\InboxToolPromptFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Backs the 5 manual Inbox toolbar actions (Summarize/Translate/Detect
 * Intent/Extract Info/Sentiment — claude.txt Task 3). Every call is
 * user-initiated (never automatic, same rule AiGenerationService follows
 * for draft generation) and cached for an hour per conversation+thread
 * content so re-opening a modal doesn't re-spend OpenAI tokens.
 */
class InboxToolsService
{
    protected const CACHE_TTL_MINUTES = 60;

    protected const LANGUAGES = [
        'en' => 'English',
        'id' => 'Indonesian',
        'ja' => 'Japanese',
        'zh' => 'Chinese',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
    ];

    public function __construct(
        protected AiClientInterface $aiClient,
        protected ConversationThreadFormatter $threadFormatter,
        protected InboxToolPromptFactory $promptFactory,
        protected IntentDetectionEngine $intentDetectionEngine,
        protected SopMatchingEngine $sopMatchingEngine,
        protected KnowledgeResolver $knowledgeResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(Conversation $conversation, bool $forceRefresh = false): array
    {
        return $this->remember('summarize', $conversation, $forceRefresh, function (string $thread) use ($conversation) {
            $result = $this->aiClient->json($this->promptFactory->summarize($thread));
            $this->logCall($conversation, $thread, $result);

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function translate(Conversation $conversation, string $language, bool $forceRefresh = false): array
    {
        $languageLabel = self::LANGUAGES[$language] ?? $language;

        return $this->remember("translate:{$language}", $conversation, $forceRefresh, function (string $thread) use ($conversation, $languageLabel) {
            $result = $this->aiClient->json($this->promptFactory->translate($thread, $languageLabel));
            $this->logCall($conversation, $thread, $result);

            return $result;
        });
    }

    /**
     * Reuses IntentDetectionEngine/SopMatchingEngine/KnowledgeResolver — the
     * same matching stages AiCenterPipeline runs for Generate/Regenerate —
     * so "Matched SOP"/"Matched Knowledge"/"Matched Template" reflect
     * exactly what a real draft generation would pick up.
     *
     * @return array<string, mixed>
     */
    public function detectIntent(Conversation $conversation, bool $forceRefresh = false): array
    {
        return $this->remember('detect-intent', $conversation, $forceRefresh, function (string $thread) use ($conversation) {
            $knownIntentNames = $this->intentDetectionEngine->shortlist($thread)->pluck('name')->all();
            $classification = $this->aiClient->json($this->promptFactory->detectIntent($thread, $knownIntentNames));

            $intent = $this->intentDetectionEngine->resolve($thread, $classification);
            $sopMatch = $this->sopMatchingEngine->match($conversation, $intent, $thread);
            $sop = $sopMatch->sop;
            $knowledgeBases = $this->knowledgeResolver->resolve($sop);

            $result = [
                'intent' => $classification['intent'] ?? $intent?->name,
                'confidence_score' => $classification['confidence_score'] ?? null,
                'reasoning' => $classification['reasoning'] ?? '',
                'matched_sop' => $sop?->name,
                'matched_knowledge' => $knowledgeBases->pluck('title')->values()->all(),
                'matched_template' => $sop?->replyTemplate?->name,
            ];

            $this->logCall($conversation, $thread, $result, intentId: $intent?->id, sopId: $sop?->id);

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function extractInformation(Conversation $conversation, bool $forceRefresh = false): array
    {
        return $this->remember('extract-info', $conversation, $forceRefresh, function (string $thread) use ($conversation) {
            $result = $this->aiClient->json($this->promptFactory->extractInformation($thread));
            $this->logCall($conversation, $thread, $result);

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function sentiment(Conversation $conversation, bool $forceRefresh = false): array
    {
        return $this->remember('sentiment', $conversation, $forceRefresh, function (string $thread) use ($conversation) {
            $result = $this->aiClient->json($this->promptFactory->sentiment($thread));
            $this->logCall($conversation, $thread, $result);

            return $result;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function remember(string $tool, Conversation $conversation, bool $forceRefresh, \Closure $compute): array
    {
        $thread = $this->threadFormatter->format(
            $conversation->messages()->orderBy('sent_at')->get()
        );

        $key = 'inbox-tool:'.$tool.':'.$conversation->id.':'.md5($thread);

        if (! $forceRefresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $result = $compute($thread);

        Cache::put($key, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function logCall(Conversation $conversation, string $thread, array $result, ?int $intentId = null, ?int $sopId = null): void
    {
        AiLog::create([
            'source' => AiCenterLogSource::InboxTool,
            'conversation_id' => $conversation->exists ? $conversation->id : null,
            'intent_id' => $intentId,
            'sop_id' => $sopId,
            'triggered_by' => auth()->id(),
            'prompt' => $thread,
            'response' => json_encode($result),
            'status' => AiCenterLogStatus::Success,
        ]);
    }
}
