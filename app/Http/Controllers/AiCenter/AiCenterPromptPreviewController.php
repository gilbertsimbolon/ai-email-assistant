<?php

namespace App\Http\Controllers\AiCenter;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AiCenter\AiCenterPipeline;
use App\Services\AiCenter\PromptBuilder;
use App\Services\AiCenter\Support\ConversationThreadFormatter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only view of the exact prompt AiCenterPipeline would build for a
 * given (real, persisted) conversation — admins can inspect but never edit
 * it, per claude.txt's "Prompt Preview" spec. No AI call, no AiLog row.
 */
class AiCenterPromptPreviewController extends Controller
{
    public function __construct(
        protected AiCenterPipeline $pipeline,
        protected PromptBuilder $promptBuilder,
        protected ConversationThreadFormatter $threadFormatter,
    ) {
    }

    public function index(Request $request): View
    {
        $conversations = Conversation::query()
            ->whereHas('messages')
            ->latest('last_message_at')
            ->limit(50)
            ->get();

        $conversation = null;
        $sections = null;

        $selectedId = $request->integer('conversation');

        if ($selectedId && ($conversation = Conversation::find($selectedId))) {
            $thread = $this->threadFormatter->format(
                $conversation->messages()->orderBy('sent_at')->get()
            );

            $context = $this->pipeline->preview($conversation, $conversation->analysis, $thread);
            $sections = $this->promptBuilder->build($context)->sections;
        }

        return view('ai-center.prompt-preview', [
            'conversations' => $conversations,
            'conversation' => $conversation,
            'sections' => $sections,
        ]);
    }
}
