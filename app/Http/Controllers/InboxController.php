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
     * How many conversations to request per GHL page. "Load more" cursors
     * forward through GHL's own /conversations/search pagination
     * (startAfterDate/startAfter) — never fetched all at once.
     */
    protected const PAGE_SIZE = 20;

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
     * Toggle status baca — dipakai tombol "Mark Read" di toolbar untuk
     * menandai balik sebagai belum dibaca (membuka percakapan otomatis
     * menandai terbaca lewat resolveActiveConversation()).
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
            isRead: $anchor ? $anchor->is_read : ! ($live?->isUnread() ?? false),
            isStarred: $anchor?->is_starred ?? false,
            status: $anchor?->status ?? ConversationStatus::PendingReview,
            hasDraft: (bool) ($anchor?->has_draft ?? false),
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
        $anchor = $this->anchors->findOrCreate($parsed);

        if (! $anchor->is_read) {
            $anchor->update(['is_read' => true]);
        }

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
