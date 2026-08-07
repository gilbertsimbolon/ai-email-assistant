<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\GhlConversationListItem;
use App\DataTransferObjects\ParsedGhlContactData;
use App\DataTransferObjects\ParsedGhlConversationData;
use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Http\Controllers\Concerns\AuthorizesConversationAccess;
use App\Http\Requests\Inbox\UpdateConversationStatusRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Ghl\GhlConversationAnchorService;
use App\Services\Ghl\GhlParserService;
use App\Services\Ghl\GhlThreadLoader;
use App\Services\Ghl\GoHighLevelApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class InboxController extends Controller
{
    use AuthorizesConversationAccess;

    protected const PAGE_SIZE = 20;
    protected const UNREAD_COUNT_PAGE_SIZE = 100;
    protected const UNREAD_COUNT_MAX_PAGES = 50;
    protected const UNREAD_COUNT_CACHE_SECONDS = 30;
    protected const LOCAL_ONLY_FILTERS = ['recent', 'starred', 'waiting_agent', 'waiting_customer', 'ai_draft', 'closed'];

    public function __construct(
        protected GoHighLevelApiService $ghlApi,
        protected GhlParserService $ghlParser,
        protected GhlConversationAnchorService $anchors,
        protected GhlThreadLoader $threadLoader,
    ) {}

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->string('q')->toString();

        $list = $this->conversationsByFilter($request, $filter, $search);

        [$activeConversation, $activeDraft] = $this->resolveActiveConversation($request);

        $contactDetails = $this->loadContactDetails($activeConversation);

        $ghlConfigured = filled(config('ghl.api_key')) && filled(config('ghl.location_id'));

        $unreadCount = $ghlConfigured ? $this->resolveUnreadCount() : 0;

        if ($request->wantsJson() && ! $request->filled('conversation')) {
            return response()->json([
                'items' => $list['items']->map(fn(GhlConversationListItem $item) => [
                    'id' => $item->ghlConversationId,
                    'is_read' => $item->isRead,
                    'html' => view('inbox.components.conversation-item', [
                        'item' => $item,
                        'filter' => $filter,
                        'search' => $search,
                        'isActive' => false,
                    ])->render(),
                ])->values(),
                'nextCursor' => $list['nextCursor'],
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'thread_html' => view('inbox.components.conversation-thread', [
                    'activeConversation' => $activeConversation,
                    'activeDraft' => $activeDraft,
                    'contactDetails' => $contactDetails,
                ])->render(),

                'ai_panel_html' => view('inbox.components.ai-panel', [
                    'activeConversation' => $activeConversation,
                    'contactDetails' => $contactDetails,
                ])->render(),

                'conversation_id' => $activeConversation?->ghl_conversation_id,
                'is_read' => $activeConversation?->is_read,
            ]);
        }

        return view('inbox.index', [
            'conversations' => $list['items'],
            'nextCursor' => $list['nextCursor'],
            'localPaginator' => $list['localPaginator'],
            'filter' => $filter,
            'search' => $search,
            'ghlConfigured' => $ghlConfigured,
            'ghlError' => $list['error'] ?? false,
            'unreadCount' => $unreadCount,
            'activeConversation' => $activeConversation,
            'activeDraft' => $activeDraft,
            'contactDetails' => $contactDetails,
        ]);
    }

    public function show(Request $request, string $conversation)
    {
        if (ctype_digit($conversation)) {
            $gmailConversation = Conversation::whereKey($conversation)->whereNotNull('gmail_account_id')->first();

            if ($gmailConversation) {
                $this->authorizeConversation($request, $gmailConversation);

                return redirect()->route('gmail-inbox.index', ['conversation' => $gmailConversation->id]);
            }
        }

        return redirect()->route('inbox.index', ['conversation' => $conversation]);
    }

    public function messages(string $conversation)
    {
        try {
            $messages = $this->threadLoader->messages($conversation);
        } catch (Throwable $e) {
            Log::error('Failed to poll GHL conversation messages', [
                'ghl_conversation_id' => $conversation,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load messages from GHL.',
            ], 502);
        }

        $activeConversation = $this->anchors->find($conversation) ?? new Conversation();

        $contactDetails = $this->loadContactDetails($activeConversation);

        return response()->json([
            'success' => true,

            'messages' => $messages->map(function (Message $message) use (
                $activeConversation,
                $contactDetails
            ) {
                return [
                    'id' => $message->ghl_message_id,

                    'html' => view('inbox.components.message-bubble', [
                        'message' => $message,
                        'activeConversation' => $activeConversation,
                        'contactDetails' => $contactDetails,
                    ])->render(),
                ];
            })->values(),
        ]);
    }

    public function updateStatus(UpdateConversationStatusRequest $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['status' => $request->validated('status')]);

        return back()->with('success', 'Status percakapan berhasil diperbarui.');
    }

    public function toggleStar(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['is_starred' => ! $conversation->is_starred]);

        return response()->json(['is_starred' => $conversation->is_starred]);
    }

    public function toggleRead(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['is_read' => ! $conversation->is_read]);

        return response()->json(['is_read' => $conversation->is_read]);
    }

    protected function conversationsByFilter(Request $request, string $filter, string $search): array
    {
        if (in_array($filter, self::LOCAL_ONLY_FILTERS, true)) {
            return $this->conversationsFromLocalAnchors($filter);
        }

        return $this->conversationsFromGhl($request, $filter, $search);
    }

    protected function conversationsFromGhl(Request $request, string $filter, string $search): array
    {
        $params = ['limit' => self::PAGE_SIZE];

        if ($search !== '') {
            $params['query'] = $search;
        }

        if ($filter === 'unread') {
            $params['status'] = 'unread';
        }

        if ($request->filled('startAfterDate') && $request->filled('startAfter')) {
            $params['startAfterDate'] = $request->get('startAfterDate');
            $params['startAfter'] = $request->get('startAfter');
        }

        try {
            $result = $this->ghlApi->getConversations($params);
        } catch (Throwable $e) {
            Log::error('Failed to load GHL conversations', ['error' => $e->getMessage()]);

            return ['items' => collect(), 'nextCursor' => null, 'localPaginator' => null, 'error' => true];
        }

        $raw = $result['conversations'] ?? [];

        $parsed = collect($raw)->map(fn(array $r) => $this->ghlParser->conversationFromSearchApi($r));

        if ($filter === 'unread') {
            $parsed = $parsed->filter(fn(ParsedGhlConversationData $p) => $p->isUnread())->values();
        }

        $anchors = Conversation::whereIn('ghl_conversation_id', $parsed->pluck('ghlConversationId'))
            ->withExists(['drafts as has_draft' => fn($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])])
            ->get()
            ->keyBy('ghl_conversation_id');

        $items = $parsed->map(fn(ParsedGhlConversationData $p) => $this->buildListItem($p, $anchors->get($p->ghlConversationId)))
            ->values();

        $nextCursor = null;

        if (count($raw) >= self::PAGE_SIZE) {
            $last = end($raw);

            if ($last) {
                $nextCursor = [
                    'startAfterDate' => $last['dateUpdated'] ?? null,
                    'startAfter' => $last['id'] ?? null,
                ];
            }
        }

        return ['items' => $items, 'nextCursor' => $nextCursor, 'localPaginator' => null];
    }

    protected function resolveUnreadCount(): int
    {
        return Cache::remember('ghl_unread_conversations_total', self::UNREAD_COUNT_CACHE_SECONDS, function () {
            return $this->countAllUnreadConversations();
        });
    }

    protected function countAllUnreadConversations(): int
    {
        $total = 0;
        $seen = [];
        $cursor = [];

        for ($page = 0; $page < self::UNREAD_COUNT_MAX_PAGES; $page++) {
            $params = array_merge([
                'limit' => self::UNREAD_COUNT_PAGE_SIZE,
                'status' => 'unread',
            ], $cursor);

            try {
                $result = $this->ghlApi->getConversations($params);
            } catch (Throwable $e) {
                Log::error('Failed to page through GHL unread conversations for the unread count', [
                    'error' => $e->getMessage(),
                    'page' => $page,
                ]);

                break;
            }

            $raw = $result['conversations'] ?? [];

            if ($raw === []) {
                break;
            }

            foreach ($raw as $r) {
                $id = $r['id'] ?? null;

                if ($id === null || isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $total += (int) ($r['unreadCount'] ?? 0);
            }

            if (count($raw) < self::UNREAD_COUNT_PAGE_SIZE) {
                break;
            }

            $last = end($raw);
            $cursor = [
                'startAfterDate' => $last['dateUpdated'] ?? null,
                'startAfter' => $last['id'] ?? null,
            ];

            if (blank($cursor['startAfterDate']) || blank($cursor['startAfter'])) {
                break;
            }

            if ($page === self::UNREAD_COUNT_MAX_PAGES - 1) {
                Log::warning('Hit the safety cap while counting GHL unread conversations; badge is a floor, not exact', [
                    'pages_fetched' => $page + 1,
                    'conversations_seen' => count($seen),
                ]);
            }
        }

        return $total;
    }

    protected function conversationsFromLocalAnchors(string $filter): array
    {
        $query = Conversation::whereNotNull('ghl_conversation_id')
            ->withExists(['drafts as has_draft' => fn($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])]);

        match ($filter) {
            'recent' => $query->where('updated_at', '>=', now()->subDay()),
            'starred' => $query->where('is_starred', true),
            'waiting_agent' => $query->where('status', ConversationStatus::PendingReview),
            'waiting_customer' => $query->where('status', ConversationStatus::Replied),
            'ai_draft' => $query->whereHas('drafts', fn($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])),
            'closed' => $query->where('status', ConversationStatus::Closed),
            default => null,
        };

        $paginator = $query->latest('updated_at')->paginate(15);

        $items = collect($paginator->items())->map(function (Conversation $anchor) {
            $live = $this->fetchLiveSummary($anchor->ghl_conversation_id);

            return $this->buildListItem($live, $anchor);
        });

        return ['items' => $items, 'nextCursor' => null, 'localPaginator' => $paginator];
    }

    protected function buildListItem(?ParsedGhlConversationData $live, ?Conversation $anchor): GhlConversationListItem
    {
        return new GhlConversationListItem(
            ghlConversationId: $live?->ghlConversationId ?? $anchor?->ghl_conversation_id,
            contactName: $live?->contactName ?? $anchor?->contact_name,
            contactEmail: $live?->contactEmail ?? $anchor?->contact_email,
            contactPhone: $live?->contactPhone ?? $anchor?->contact_phone,
            channelLabel: $live?->channelLabel() ?? 'Conversation',
            preview: $live?->subject,
            lastActivityAt: $live?->lastActivityAt,
            isRead: $live !== null ? ! $live->isUnread() : ($anchor?->is_read ?? true),
            isStarred: $anchor?->is_starred ?? false,
            status: $anchor?->status ?? ConversationStatus::PendingReview,
            hasDraft: (bool) ($anchor?->has_draft ?? false),
            unreadCount: $live?->unreadCount ?? 0,
        );
    }

    protected function fetchLiveSummary(string $ghlConversationId): ?ParsedGhlConversationData
    {
        try {
            $response = $this->ghlApi->getConversation($ghlConversationId);
            $raw = data_get($response, 'conversation', $response);
            $raw['id'] = $raw['id'] ?? $ghlConversationId;

            return $this->ghlParser->conversationFromSearchApi($raw);
        } catch (Throwable $e) {
            Log::warning('Failed to live-refresh a GHL conversation for a local filter', [
                'ghl_conversation_id' => $ghlConversationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function resolveActiveConversation(Request $request): array
    {
        if (! $request->filled('conversation')) {
            return [null, null];
        }

        $ghlConversationId = $request->string('conversation')->toString();

        try {
            $response = $this->ghlApi->getConversation($ghlConversationId);
        } catch (Throwable $e) {
            Log::error('Failed to load active GHL conversation', [
                'ghl_conversation_id' => $ghlConversationId,
                'error' => $e->getMessage(),
            ]);

            return [null, null];
        }

        $raw = data_get($response, 'conversation', $response);
        $raw['id'] = $raw['id'] ?? $ghlConversationId;

        $parsed = $this->ghlParser->conversationFromSearchApi($raw);
        $anchor = $this->anchors->findOrCreate($parsed);

        $anchor->setRelation('messages', $this->threadLoader->messages($ghlConversationId));
        $anchor->load('drafts');

        $activeDraft = $anchor->drafts->firstWhere('status', DraftStatus::Active);

        return [$anchor, $activeDraft];
    }

    protected function loadContactDetails(?Conversation $conversation): ?ParsedGhlContactData
    {
        if (! $conversation || blank($conversation->contact_id)) {
            return null;
        }

        try {
            return Cache::remember(
                "ghl_contact_{$conversation->contact_id}",
                300,
                function () use ($conversation) {
                    $response = $this->ghlApi->getContact($conversation->contact_id);

                    return $this->ghlParser->contactFromApi($response['contact'] ?? $response);
                }
            );
        } catch (Throwable $e) {
            Log::warning('Failed to fetch GHL contact details', [
                'contact_id' => $conversation->contact_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}