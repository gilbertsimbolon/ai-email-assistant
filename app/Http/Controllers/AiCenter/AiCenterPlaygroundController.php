<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\ChannelType;
use App\Enums\CustomerStatus;
use App\Enums\Priority;
use App\Enums\Sentiment;
use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Services\AiCenter\AiCenterPipeline;
use App\Services\AiCenter\Engines\IntentDetectionEngine;
use App\Services\AnalysisService;
use App\Enums\AiCenter\AiCenterLogSource;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

/**
 * "AI Playground" (claude.txt): an admin pastes a raw customer email/message,
 * the app runs it through the exact same pipeline production uses (Intent
 * Detection -> AiCenterPipeline: SOP Matching -> Rule Engine -> Workflow
 * Engine -> Knowledge Base -> Reply Template -> Prompt Builder -> AI Generate
 * Draft), and every stage's result is shown for testing/debugging. Uses an
 * ephemeral, unsaved Conversation/Analysis (never written to the
 * conversations/analyses tables) but still writes a real AiLog row tagged
 * AiCenterLogSource::Playground so the run shows up in AI Logs.
 */
class AiCenterPlaygroundController extends Controller
{
    public function __construct(
        protected AnalysisService $analysisService,
        protected IntentDetectionEngine $intentDetectionEngine,
        protected AiCenterPipeline $pipeline,
    ) {
    }

    public function index(): View
    {
        return view('ai-center.playground', [
            'result' => null,
        ]);
    }

    public function run(Request $request): View
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::enum(ChannelType::class)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'thread' => ['required', 'string'],
        ]);

        $conversation = new Conversation([
            'channel' => $validated['channel'],
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'subject' => $validated['subject'] ?? null,
        ]);

        $thread = $validated['thread'];

        $analysisJson = $this->analysisService->analyze($thread);
        $intent = $this->intentDetectionEngine->resolve($thread, $analysisJson);

        $analysis = new Analysis([
            'language' => $analysisJson['language'] ?? null,
            'summary' => $analysisJson['summary'] ?? '',
            'customer_intent' => $analysisJson['intent'] ?? null,
            'intent_id' => $intent?->id,
            'sentiment' => $this->normalize($analysisJson['sentiment'] ?? null) ?? Sentiment::Neutral->value,
            'customer_status' => $this->normalize($analysisJson['customer_status'] ?? null) ?? CustomerStatus::Unknown->value,
            'priority' => $this->normalize($analysisJson['priority'] ?? null) ?? Priority::Medium->value,
            'last_customer_request' => $analysisJson['last_customer_request'] ?? null,
            'recommended_action' => $analysisJson['recommended_action'] ?? null,
            'refund_requested' => (bool) ($analysisJson['refund_requested'] ?? false),
            'escalation_required' => (bool) ($analysisJson['needs_escalation'] ?? false),
            'confidence_score' => $analysisJson['confidence_score'] ?? null,
            'raw_json' => $analysisJson,
        ]);

        $result = $this->pipeline->run(
            $conversation,
            $analysis,
            $thread,
            AiCenterLogSource::Playground,
            auth()->id(),
        );

        return view('ai-center.playground', [
            'result' => $result,
            'old' => $validated,
        ]);
    }

    /**
     * Normalizes a free-text AI enum answer (e.g. "High") to its snake_case
     * backing value (e.g. "high"), same rule AnalysisService::normalize
     * applies before saving — duplicated here in miniature because the
     * Playground never persists through AnalysisService::save().
     */
    protected function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str_replace(' ', '_', strtolower(trim($value)));
    }
}
