<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\ParsedGhlContactData;
use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Http\Controllers\Concerns\AuthorizesConversationAccess;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Ghl\GhlParserService;
use App\Services\Ghl\GoHighLevelApiService;
use App\Services\Gmail\GmailApiService;
use App\Services\Gmail\GmailAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Legacy Gmail Inbox — split out of the (now GHL-only) InboxController so
 * the GHL migration in claude.txt doesn't touch Gmail's still-DB-backed
 * flow. Behavior is unchanged from before the split: conversations/messages
 * are still read from the local `conversations`/`messages` tables, synced
 * by GmailSyncService. Status/star/read toggle and draft/AI-tool actions
 * are NOT duplicated here — they stay on InboxController's shared
 * inbox.status.update/inbox.star/inbox.read.toggle/inbox.drafts.
 * inbox.ai-tools.* routes, which already work generically against any local
 * Conversation row regardless of source.
 */
class GmailInboxController extends Controller
{
    use AuthorizesConversationAccess;

    public function index(Request $request, GoHighLevelApiService $ghlApi, GhlParserService $ghlParser)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->string('q')->toString();

        $conversations = $this->conversationsByFilter($request, $filter)
            ->appends(['filter' => $filter, 'q' => $search]);

        [$activeConversation, $activeDraft] = $this->resolveActiveConversation($request);

        $contactDetails = $this->loadContactDetails($activeConversation, $ghlApi, $ghlParser);

        if ($request->wantsJson()) {
            return response()->json([
                'thread_html' => view('inbox.components.conversation-thread', compact('activeConversation', 'activeDraft'))->render(),
                'ai_panel_html' => view('inbox.components.ai-panel', compact('activeConversation', 'contactDetails'))->render(),
                'conversation_id' => $activeConversation?->id,
                'is_read' => $activeConversation?->is_read,
            ]);
        }

        return view('gmail-inbox.index', compact(
            'conversations',
            'filter',
            'search',
            'activeConversation',
            'activeDraft',
            'contactDetails',
        ));
    }

    /**
     * Permalink lama ke detail percakapan Gmail.
     */
    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        return redirect()->route('gmail-inbox.index', ['conversation' => $conversation->id]);
    }

    public function downloadAttachment(
        Request $request,
        Message $message,
        string $attachmentId,
        GmailApiService $gmailApi,
        GmailAuthService $gmailAuth,
    ): Response {
        [$attachment, $bytes] = $this->fetchAttachment($request, $message, $attachmentId, $gmailApi, $gmailAuth);

        return response($bytes, 200, [
            'Content-Type' => $attachment['mime_type'] ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$attachment['filename'].'"',
        ]);
    }

    public function previewAttachment(
        Request $request,
        Message $message,
        string $attachmentId,
        GmailApiService $gmailApi,
        GmailAuthService $gmailAuth,
    ): Response {
        [$attachment, $bytes] = $this->fetchAttachment($request, $message, $attachmentId, $gmailApi, $gmailAuth);

        return response($bytes, 200, [
            'Content-Type' => $attachment['mime_type'] ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$attachment['filename'].'"',
        ]);
    }

    /**
     * @return array{0: array, 1: string} [attachment metadata, raw file bytes]
     */
    protected function fetchAttachment(
        Request $request,
        Message $message,
        string $attachmentId,
        GmailApiService $gmailApi,
        GmailAuthService $gmailAuth,
    ): array {
        $this->authorizeConversation($request, $message->conversation);

        $attachment = collect($message->attachments)->firstWhere('id', $attachmentId);

        abort_unless($attachment, 404);

        $account = $message->conversation->gmailAccount;

        abort_unless($account, 404);

        $account = $gmailAuth->ensureFreshToken($account);

        $data = $gmailApi->getAttachment($account->access_token, $message->gmail_message_id, $attachmentId);
        $bytes = base64_decode(strtr($data['data'] ?? '', '-_', '+/'));

        return [$attachment, $bytes];
    }

    protected function conversationsByFilter(Request $request, ?string $filter, int $page = 1)
    {
        return Conversation::with(['analysis', 'latestMessage'])
            ->withExists(['drafts as has_draft' => fn ($query) => $query->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])])
            ->whereHas('gmailAccount', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->when($filter === 'starred', fn ($query) => $query->where('is_starred', true))
            ->when($filter === 'recent', fn ($query) => $query->where('last_message_at', '>=', now()->subDay()))
            ->when($filter === 'waiting_agent', fn ($query) => $query->where('status', ConversationStatus::PendingReview))
            ->when($filter === 'waiting_customer', fn ($query) => $query->where('status', ConversationStatus::Replied))
            ->when($filter === 'ai_draft', fn ($query) => $query->whereHas('drafts', fn ($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])))
            ->when($filter === 'closed', fn ($query) => $query->where('status', ConversationStatus::Closed))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';

                $query->where(fn ($q) => $q->where('contact_name', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('contact_phone', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhereHas('latestMessage', fn ($mq) => $mq->where('body', 'like', $term)));
            })
            ->latest('last_message_at')
            ->paginate(15, page: $page);
    }

    protected function resolveActiveConversation(Request $request): array
    {
        if (! $request->filled('conversation')) {
            return [null, null];
        }

        $activeConversation = Conversation::with([
                'analysis',
                'drafts',
                'messages' => fn ($query) => $query->orderBy('sent_at'),
            ])
            ->whereHas('gmailAccount', fn ($q) => $q->where('user_id', $request->user()->id))
            ->find($request->get('conversation'));

        if (! $activeConversation) {
            return [null, null];
        }

        if (! $activeConversation->is_read) {
            $activeConversation->update(['is_read' => true]);
        }

        $activeDraft = $activeConversation->drafts
            ->firstWhere('status', DraftStatus::Active);

        return [$activeConversation, $activeDraft];
    }

    /**
     * Same on-demand GHL contact lookup the main Inbox uses — a Gmail
     * conversation can still carry a matched GHL contact_id, so the Contact
     * Details panel stays consistent between both inboxes.
     */
    protected function loadContactDetails(
        ?Conversation $conversation,
        GoHighLevelApiService $ghlApi,
        GhlParserService $ghlParser,
    ): ?ParsedGhlContactData {
        if (! $conversation || blank($conversation->contact_id)) {
            return null;
        }

        try {
            return Cache::remember(
                "ghl_contact_{$conversation->contact_id}",
                300,
                function () use ($conversation, $ghlApi, $ghlParser) {
                    $response = $ghlApi->getContact($conversation->contact_id);

                    return $ghlParser->contactFromApi($response['contact'] ?? $response);
                }
            );
        } catch (Throwable $e) {
            return null;
        }
    }
}
