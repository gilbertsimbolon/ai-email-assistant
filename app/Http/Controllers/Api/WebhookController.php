<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EmailWebhookRequest;
use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
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

        $this->conversationService->triggerAiReply($conversation);

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
