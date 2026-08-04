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

/**
 * The Inbox is 100% GHL now (claude.txt) — conversations/messages/contacts
 * are always fetched live from GoHighLevelApiService, never read from
 * MySQL. The only local writes are the anchor Conversation row (lazily
 * created — see GhlConversationAnchorService) and the workflow-only
 * is_read/is_starred/status columns on it. Legacy Gmail conversations live
 * at GmailInboxController/gmail-inbox.* instead (see claude.txt: this
 * migration only covers GHL).
 */
class InboxController extends Controller
{
    use AuthorizesConversationAccess;

    /**
     * How many conversations to request per GHL page. The "Load More"
     * button (conversation-list.blade.php + inbox-navigation.js) cursors
     * forward through GHL's own /conversations/search pagination
     * (startAfterDate/startAfter) one page at a time — never fetched all at
     * once, and never just a bigger single limit (claude.txt Task 2).
     */
    protected const PAGE_SIZE = 20;

    /**
     * Page size used only while walking every unread page for
     * resolveUnreadCount() — a practical upper bound for GHL's search
     * endpoint, not an "unrealistic number" (claude.txt Task 2): still real
     * cursor pagination underneath, just fewer round trips per page.
     */
    protected const UNREAD_COUNT_PAGE_SIZE = 100;

    /**
     * Safety valve so a runaway/misbehaving cursor can never turn this into
     * an unbounded loop — caps at 50 pages (~5,000 conversations). Hitting
     * it logs a warning; the badge becomes a floor rather than wrong.
     */
    protected const UNREAD_COUNT_MAX_PAGES = 50;

    protected const UNREAD_COUNT_CACHE_SECONDS = 30;

    /**
     * Local-only concepts (starred/workflow status/AI draft presence) that
     * GHL has no server-side filter for. These filters can only surface
     * conversations an agent has already opened at least once (i.e. that
     * have a local anchor row) — there's no local mirror of every GHL
     * conversation to search through instead.
     */
    protected const LOCAL_ONLY_FILTERS = ['recent', 'starred', 'waiting_agent', 'waiting_customer', 'ai_draft', 'closed'];

    public function __construct(
        protected GoHighLevelApiService $ghlApi,
        protected GhlParserService $ghlParser,
        protected GhlConversationAnchorService $anchors,
        protected GhlThreadLoader $threadLoader,
    ) {
    }

    /**
     * Menampilkan halaman Inbox GHL (3 kolom: list, thread, contact
     * details). Klik conversation di list & polling realtime dilakukan
     * lewat AJAX (lihat inbox-navigation.js & inbox-polling.js) — hanya
     * navigasi filter/search/"load more" yang full page load.
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->string('q')->toString();

        $list = $this->conversationsByFilter($request, $filter, $search);

        [$activeConversation, $activeDraft] = $this->resolveActiveConversation($request);

        $contactDetails = $this->loadContactDetails($activeConversation);

        $ghlConfigured = filled(config('ghl.api_key')) && filled(config('ghl.location_id'));

        // Badge next to the Unread filter icon (claude.txt Task 4): must
        // reflect GHL's real unread total, not just whatever page happens
        // to be loaded — see resolveUnreadCount().
        $unreadCount = $ghlConfigured ? $this->resolveUnreadCount() : 0;

        // Polling list (no ?conversation=): each row is pre-rendered server
        // side (same partial the initial page load uses) so the browser
        // only needs to patch/append <li> elements it doesn't already have
        // — never a full page reload (claude.txt section 11-12).
        if ($request->wantsJson() && ! $request->filled('conversation')) {
            return response()->json([
                'items' => $list['items']->map(fn (GhlConversationListItem $item) => [
                    'id' => $item->ghlConversationId,
                    'is_read' => $item->isRead,
                    'html' => view('inbox.components.conversation-item', [
                        'item' => $item,
                        'filter' => $filter,
                        'search' => $search,
                        'isActive' => false,
                    ])->render(),
                ])->values(),
                // Same GHL cursor conversationsFromGhl() computes for the
                // full page load — lets inbox-navigation.js's "Load More"
                // button page forward through this same JSON endpoint
                // instead of a second, separate pagination code path.
                'nextCursor' => $list['nextCursor'],
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'thread_html' => view('inbox.components.conversation-thread', compact('activeConversation', 'activeDraft'))->render(),
                'ai_panel_html' => view('inbox.components.ai-panel', compact('activeConversation', 'contactDetails'))->render(),
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

    /**
     * Permalink lama ke detail percakapan. `/inbox/{id}` sekarang cukup
     * jadi passthrough ke panel kanan halaman index — kecuali id tersebut
     * ternyata conversation Gmail lama (bookmark dari sebelum migrasi),
     * yang diarahkan ke gmail-inbox.
     */
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

    /**
     * Polling thread aktif (claude.txt section 13-14): mengembalikan bubble
     * HTML setiap message saat ini dari GHL beserta id-nya, biar JS bisa
     * append hanya yang belum ada di DOM (dedup by data-message-id) —
     * bukan render ulang seluruh thread.
     */
    public function messages(string $conversation)
    {
        try {
            $messages = $this->threadLoader->messages($conversation);
        } catch (Throwable $e) {
            Log::error('Failed to poll GHL conversation messages', [
                'ghl_conversation_id' => $conversation,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Unable to load messages from GHL.'], 502);
        }

        $activeConversation = $this->anchors->find($conversation) ?? new Conversation();

        return response()->json([
            'success' => true,
            'messages' => $messages->map(fn (Message $message) => [
                'id' => $message->ghl_message_id,
                'html' => view('inbox.components.message-bubble', [
                    'message' => $message,
                    'activeConversation' => $activeConversation,
                ])->render(),
            ])->values(),
        ]);
    }

    /**
     * Mengubah status percakapan (pending_review / replied / closed) dari
     * dropdown pada halaman detail. Selalu terhadap anchor lokal — anchor
     * dijamin sudah ada karena hanya bisa diakses lewat thread yang terbuka.
     */
    public function updateStatus(UpdateConversationStatusRequest $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['status' => $request->validated('status')]);

        return back()->with('success', 'Status percakapan berhasil diperbarui.');
    }

    /**
     * Toggle bintang (starred) — lokal saja, GHL tidak punya konsep ini.
     */
    public function toggleStar(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['is_starred' => ! $conversation->is_starred]);

        return response()->json(['is_starred' => $conversation->is_starred]);
    }

    /**
     * Toggle status baca — SATU-SATUNYA jalur yang boleh mengubah is_read.
     * Selalu manual (klik tombol "Mark Read"/"Tandai belum dibaca" di
     * toolbar) — membuka/melihat/polling percakapan tidak pernah memanggil
     * ini secara otomatis (claude.txt Task 3).
     */
    public function toggleRead(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['is_read' => ! $conversation->is_read]);

        return response()->json(['is_read' => $conversation->is_read]);
    }

    /**
     * @return array{items: \Illuminate\Support\Collection<int, GhlConversationListItem>, nextCursor: ?array, localPaginator: ?\Illuminate\Pagination\LengthAwarePaginator, error?: bool}
     */
    protected function conversationsByFilter(Request $request, string $filter, string $search): array
    {
        if (in_array($filter, self::LOCAL_ONLY_FILTERS, true)) {
            return $this->conversationsFromLocalAnchors($filter);
        }

        return $this->conversationsFromGhl($request, $filter, $search);
    }

    /**
     * The default/fast path: "all", "unread" and search all go straight to
     * GHL's /conversations/search — no local database involved at all
     * (claude.txt section 7, 32).
     */
    protected function conversationsFromGhl(Request $request, string $filter, string $search): array
    {
        $params = ['limit' => self::PAGE_SIZE];

        if ($search !== '') {
            $params['query'] = $search;
        }

        // Delegate the Unread tab's filtering to GHL itself rather than
        // fetching one generic page and throwing away whatever on it isn't
        // unread — that's the root cause behind "GHL has 1.4K unread but
        // Laravel only shows ~4" (claude.txt Task 2): with a plain page,
        // most of the 20 latest conversations by activity aren't unread at
        // all, so only a handful survived the old client-side filter.
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

        $parsed = collect($raw)->map(fn (array $r) => $this->ghlParser->conversationFromSearchApi($r));

        // Safety net, not the primary filter: keeps the tab correct even if
        // GHL's `status=unread` isn't honored for some reason. Real
        // pagination through the *unread* set happens via the cursor below,
        // which walks GHL's own filtered pages — never a single page.
        if ($filter === 'unread') {
            $parsed = $parsed->filter(fn (ParsedGhlConversationData $p) => $p->isUnread())->values();
        }

        $anchors = Conversation::whereIn('ghl_conversation_id', $parsed->pluck('ghlConversationId'))
            ->withExists(['drafts as has_draft' => fn ($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])])
            ->get()
            ->keyBy('ghl_conversation_id');

        $items = $parsed->map(fn (ParsedGhlConversationData $p) => $this->buildListItem($p, $anchors->get($p->ghlConversationId)))
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

    /**
     * The real, location-wide unread total (claude.txt Task 4) — walks
     * every page GHL has for status=unread, not just the first one, so the
     * badge can't be capped at whatever a single page happened to load.
     * Cached briefly since the list-poll hits index() every few seconds
     * (inbox-polling.js) and this would otherwise re-page through GHL on
     * every single tick.
     */
    protected function resolveUnreadCount(): int
    {
        return Cache::remember('ghl_unread_conversations_total', self::UNREAD_COUNT_CACHE_SECONDS, function () {
            return $this->countAllUnreadConversations();
        });
    }

    /**
     * Pages through GHL's /conversations/search filtered server-side to
     * status=unread, summing each conversation's unreadCount, until GHL
     * returns a short page (no more data) or the safety cap is hit.
     * Conversations are deduped by id in case a page ever overlaps the
     * previous one.
     */
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

            // Short page: this was the last one.
            if (count($raw) < self::UNREAD_COUNT_PAGE_SIZE) {
                break;
            }

            $last = end($raw);
            $cursor = [
                'startAfterDate' => $last['dateUpdated'] ?? null,
                'startAfter' => $last['id'] ?? null,
            ];

            // GHL didn't give us anything to cursor forward with — treat as
            // the last page rather than risk re-requesting the same one.
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

    /**
     * Local-only filters (starred/workflow status/AI draft/recent) only
     * make sense for conversations an agent already opened — paginate the
     * local anchor table for candidates, then live-refresh each one's
     * display data from GHL so nothing shown is stale (claude.txt section 9).
     */
    protected function conversationsFromLocalAnchors(string $filter): array
    {
        $query = Conversation::whereNotNull('ghl_conversation_id')
            ->withExists(['drafts as has_draft' => fn ($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])]);

        match ($filter) {
            'recent' => $query->where('updated_at', '>=', now()->subDay()),
            'starred' => $query->where('is_starred', true),
            'waiting_agent' => $query->where('status', ConversationStatus::PendingReview),
            'waiting_customer' => $query->where('status', ConversationStatus::Replied),
            'ai_draft' => $query->whereHas('drafts', fn ($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])),
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
            // GHL is the source of truth for read/unread (claude.txt Task
            // 3-4): whenever a live GHL summary was fetched, its unread
            // state always wins over the local anchor's is_read — the local
            // flag can only reflect state as of whenever it was last
            // written and would otherwise silently drift from GHL (e.g. a
            // conversation the agent already opened getting a new unread
            // message later). Only fall back to the anchor when GHL
            // couldn't be reached at all.
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

    /**
     * Muat percakapan yang sedang dipilih (?conversation=<ghlConversationId>)
     * untuk panel tengah/kanan — selalu dari GHL, tidak pernah dari mirror
     * lokal. Anchor lokal baru dibuat di sini (agent benar-benar membuka
     * percakapan ini), bukan proaktif untuk seluruh list.
     */
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
        // Opening/viewing a conversation must NEVER mark it read (claude.txt
        // Task 3) — the anchor's is_read only ever changes from the
        // explicit "Mark Read" toggle (toggleRead()) or when it's first
        // seeded from GHL's own unread state in findOrCreate(). No update()
        // call here on purpose.
        $anchor = $this->anchors->findOrCreate($parsed);

        $anchor->setRelation('messages', $this->threadLoader->messages($ghlConversationId));
        $anchor->load('drafts');

        $activeDraft = $anchor->drafts->firstWhere('status', DraftStatus::Active);

        return [$anchor, $activeDraft];
    }

    /**
     * Ambil detail contact (tags, custom fields, DND, dll) langsung dari GHL
     * untuk panel Contact Details — bukan disimpan lokal, di-cache singkat
     * per contact supaya tidak memanggil GHL berulang kali saat agent
     * bolak-balik membuka percakapan yang sama. Gagal fetch (mis. GHL down,
     * contact_id kosong) tidak boleh menjatuhkan halaman.
     */
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
