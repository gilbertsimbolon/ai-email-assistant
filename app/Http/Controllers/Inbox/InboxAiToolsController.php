<?php

namespace App\Http\Controllers\Inbox;

use App\Exceptions\AiNotConfiguredException;
use App\Http\Controllers\Concerns\AuthorizesConversationAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\TranslateThreadRequest;
use App\Models\Conversation;
use App\Services\AiCenter\InboxToolsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backs the AI toolbar above the Inbox composer (claude.txt Task 3):
 * Summarize Thread, Translate, Detect Intent, Extract Customer Information,
 * Sentiment Analysis. Every action here is a manual button click — none of
 * these run automatically, same rule DraftController::generate follows.
 */
class InboxAiToolsController extends Controller
{
    use AuthorizesConversationAccess;

    public function __construct(protected InboxToolsService $tools)
    {
    }

    public function summarize(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->run($request, $conversation, fn (bool $forceRefresh) => $this->tools->summarize($conversation, $forceRefresh));
    }

    public function translate(TranslateThreadRequest $request, Conversation $conversation): JsonResponse
    {
        return $this->run($request, $conversation, fn (bool $forceRefresh) => $this->tools->translate(
            $conversation,
            $request->validated('language'),
            $forceRefresh,
        ));
    }

    public function detectIntent(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->run($request, $conversation, fn (bool $forceRefresh) => $this->tools->detectIntent($conversation, $forceRefresh));
    }

    public function extractInfo(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->run($request, $conversation, fn (bool $forceRefresh) => $this->tools->extractInformation($conversation, $forceRefresh));
    }

    public function sentiment(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->run($request, $conversation, fn (bool $forceRefresh) => $this->tools->sentiment($conversation, $forceRefresh));
    }

    protected function run(Request $request, Conversation $conversation, \Closure $action): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        try {
            $result = $action($request->boolean('force_refresh'));
        } catch (AiNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('Inbox AI tool failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Gagal memproses permintaan AI. Silakan coba lagi.'], 500);
        }

        return response()->json(['result' => $result]);
    }
}
