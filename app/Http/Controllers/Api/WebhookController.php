<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EmailWebhookRequest;
use App\Models\Conversation;
use App\Services\AnalysisService;
use App\Services\ConversationService;
use App\Services\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected AnalysisService $analysisService,
        protected DraftService $draftService,
    ) {
    }

    /**
     * Receive an inbound email, store it against a conversation, then
     * trigger AI analysis and draft generation.
     */
    public function email(EmailWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();

        Log::info('Webhook email received', ['from' => $data['from']]);

        $conversation = $this->findOrCreateConversation($data);

        $message = $conversation->messages()->create([
            'ghl_message_id' => 'webhook-'.Str::uuid(),
            'sender_type' => SenderType::Customer,
            'message_type' => MessageType::Email,
            'body' => $data['message'],
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => $message->sent_at]);

        try {
            $thread = $this->conversationService->buildPromptFromMessages(
                $conversation->messages()->orderBy('sent_at')->get()
            );

            $analysis = $this->analysisService->analyzeAndSave($conversation, $thread);
            $this->draftService->generate($conversation, $thread, $analysis);
        } catch (Throwable $e) {
            // The message is already stored — AI failures shouldn't fail
            // the webhook response and risk the sender retrying/duplicating it.
            Log::error('Webhook AI processing failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'received',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ], 202);
    }

    /**
     * @param array{from:string, subject:?string, message:string} $data
     */
    protected function findOrCreateConversation(array $data): Conversation
    {
        $conversation = Conversation::where('contact_email', $data['from'])
            ->where('channel', ChannelType::Email)
            ->where('status', '!=', ConversationStatus::Closed)
            ->latest('last_message_at')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return Conversation::create([
            'contact_email' => $data['from'],
            'contact_name' => $data['from'],
            'channel' => ChannelType::Email,
            'subject' => $data['subject'] ?? null,
            'status' => ConversationStatus::PendingReview,
        ]);
    }
}
