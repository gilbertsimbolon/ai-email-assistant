<?php

namespace App\Services\AiCenter;

use App\DataTransferObjects\ParsedGhlContactData;
use App\Enums\AiCenter\AiCenterLogSource;
use App\Enums\AiCenter\AiCenterLogStatus;
use App\Models\AiCenter\AiLog;
use App\Models\Conversation;
use App\Services\AI\Contracts\AiClientInterface;
use App\Services\AiCenter\Engines\IntentDetectionEngine;
use App\Services\AiCenter\Engines\SopMatchingEngine;
use App\Services\AiCenter\Support\ConversationThreadFormatter;
use App\Services\AiCenter\Support\InboxToolPromptFactory;
use App\Services\Ghl\GhlParserService;
use App\Services\Ghl\GhlThreadLoader;
use App\Services\Ghl\GoHighLevelApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backs the 5 manual Inbox toolbar actions (Summarize/Translate/Detect
 * Intent/Extract Info/Sentiment — claude.txt Task 3). Every call is
 * user-initiated (never automatic, same rule AiGenerationService follows
 * for draft generation) and cached for an hour per conversation+thread
 * content so re-opening a modal doesn't re-spend OpenAI tokens. The
 * exception is Extract Info (claude.txt: "Remove AI from Extract Info") —
 * it never touches aiClient at all, it reads straight from GHL.
 */
class InboxToolsService
{
    protected const CACHE_TTL_MINUTES = 60;

    protected const EXTRACT_INFO_CACHE_TTL_MINUTES = 5;

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
        protected GhlThreadLoader $ghlThreadLoader,
        protected GoHighLevelApiService $ghlApi,
        protected GhlParserService $ghlParser,
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
     * NOT an AI call (claude.txt Task 1: "Remove AI from Extract Info").
     * Every field here comes straight from GHL's own contact/conversation
     * data — the same source InboxController's Contact Details panel
     * reads from — never inferred by a model. Cached briefly per
     * conversation purely to avoid re-hitting GHL on every modal open;
     * force_refresh bypasses that cache the same way the AI tools' own
     * force-refresh does.
     *
     * @return array<string, mixed>
     */
    public function extractInformation(Conversation $conversation, bool $forceRefresh = false): array
    {
        $key = 'inbox-extract-info:'.$conversation->id;

        if (! $forceRefresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $contact = $this->fetchContact($conversation->contact_id);

        $result = [
            'customer_name' => $contact?->fullName() ?: $conversation->contact_name,
            'email' => $contact?->email ?: $conversation->contact_email,
            'phone' => $contact?->phone ?: $conversation->contact_phone,
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->ghl_conversation_id,
            'channel' => $conversation->channel,
            'company_name' => $contact?->companyName,
            'tags' => $contact?->tags ?? [],
            'custom_fields' => $contact?->customFields ?? [],
        ];

        Cache::put($key, $result, now()->addMinutes(self::EXTRACT_INFO_CACHE_TTL_MINUTES));

        return $result;
    }

    /**
     * Same on-demand, defensive-read GHL contact fetch InboxController uses
     * for the Contact Details panel — a failed/missing fetch returns null
     * rather than fabricating data, so Extract Info can fall back to
     * whatever the local anchor already knows (contact_name/email/phone
     * seeded from GHL when the anchor was created).
     */
    protected function fetchContact(?string $contactId): ?ParsedGhlContactData
    {
        if (blank($contactId)) {
            return null;
        }

        try {
            $response = $this->ghlApi->getContact($contactId);

            return $this->ghlParser->contactFromApi($response['contact'] ?? $response);
        } catch (Throwable $e) {
            Log::warning('Failed to fetch GHL contact for Extract Info', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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
        $thread = $this->threadFormatter->format($this->messagesFor($conversation));

        $key = 'inbox-tool:'.$tool.':'.$conversation->id.':'.md5($thread);

        if (! $forceRefresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $result = $compute($thread);

        Cache::put($key, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $result;
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
