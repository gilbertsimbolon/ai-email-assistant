<?php

namespace App\Services\Ghl;

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Models\Conversation;
use App\Models\Draft;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends an approved AI/manual draft back out through GHL — the only caller
 * of GoHighLevelApiService::sendMessage(). This is how "Send" on the
 * Laravel composer actually reaches the customer: the agent never opens
 * GHL (see claude.txt section 4). Channel-aware (claude.txt section 16) —
 * a GHL conversation can be Email, SMS, WhatsApp, FB, IG, GMB, Live Chat,
 * etc, so this always checks the conversation's *live* channel from GHL
 * before deciding how to shape the payload, instead of assuming Email.
 */
class GhlSendService
{
    public function __construct(
        protected GoHighLevelApiService $api,
    ) {
    }

    public function sendDraft(Draft $draft): void
    {
        $conversation = $draft->conversation;

        if (blank($conversation?->ghl_conversation_id)) {
            throw new RuntimeException('Percakapan ini belum terhubung dengan GoHighLevel, tidak bisa mengirim balasan.');
        }

        $body = $this->withQuotedReply($draft->content['body'] ?? '', $draft->content['quoted'] ?? null);
        $channel = $this->liveChannel($conversation->ghl_conversation_id) ?? $conversation->channel;

        $payload = array_merge(
            [
                'conversationId' => $conversation->ghl_conversation_id,
                'contactId' => $conversation->contact_id,
                'message' => $body,
            ],
            $this->channelPayload($channel, $draft, $conversation, $body)
        );

        $this->api->sendMessage($payload);

        $draft->update(['status' => DraftStatus::Sent]);
        $conversation->update(['status' => ConversationStatus::Replied]);

        Log::info('Draft sent via GHL', [
            'conversation_id' => $conversation->id,
            'draft_id' => $draft->id,
            'channel' => $channel,
        ]);
    }

    /**
     * Prepends a quoted reference to whichever specific message the agent
     * clicked "Reply" on (claude.txt task 1). GHL's /conversations/messages
     * send API is conversation-scoped only — there is no reply-to-message-id
     * field to set — so the reference travels as visible quoted text in the
     * body itself, the same way GHL's own UI represents a "reply" on
     * non-email channels that don't support real message threading.
     *
     * @param  ?array{sender: ?string, snippet: string}  $quoted
     */
    protected function withQuotedReply(string $body, ?array $quoted): string
    {
        if (blank($quoted['snippet'] ?? null)) {
            return $body;
        }

        $sender = $quoted['sender'] ?: 'pesan sebelumnya';

        return "> {$sender}: {$quoted['snippet']}\n\n{$body}";
    }

    /**
     * A conversation's channel can change/not be known locally (the anchor
     * row only ever caches it once) — always confirm against GHL right
     * before sending rather than trusting stale local data.
     */
    protected function liveChannel(string $ghlConversationId): ?string
    {
        try {
            $response = $this->api->getConversation($ghlConversationId);
            $raw = data_get($response, 'conversation', $response);

            return $raw['lastMessageType'] ?? $raw['type'] ?? null;
        } catch (Throwable $e) {
            Log::warning('Could not confirm live GHL channel before sending, falling back to cached channel', [
                'ghl_conversation_id' => $ghlConversationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Maps GHL's conversation channel string to the extra fields GHL's
     * /conversations/messages endpoint expects for that channel's `type`.
     * Best-effort against GHL's documented channel strings — Email is the
     * only channel with subject/html; everything else is plain text.
     *
     * @return array<string, mixed>
     */
    protected function channelPayload(?string $channel, Draft $draft, Conversation $conversation, string $body): array
    {
        $normalized = strtoupper((string) $channel);

        if (str_contains($normalized, 'EMAIL')) {
            $subject = $draft->content['subject'] ?? ('Re: '.($conversation->subject ?? 'percakapan Anda'));

            return [
                'type' => 'Email',
                'subject' => $subject,
                'html' => nl2br(e($body)),
            ];
        }

        return match (true) {
            str_contains($normalized, 'WHATSAPP') => ['type' => 'WhatsApp'],
            str_contains($normalized, 'SMS') => ['type' => 'SMS'],
            str_contains($normalized, 'FACEBOOK') || $normalized === 'TYPE_FB' => ['type' => 'FB'],
            str_contains($normalized, 'INSTAGRAM') || $normalized === 'TYPE_IG' => ['type' => 'IG'],
            str_contains($normalized, 'GMB') => ['type' => 'GMB'],
            str_contains($normalized, 'LIVE_CHAT') => ['type' => 'Live_Chat'],
            default => ['type' => 'Custom'],
        };
    }
}
