<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Http\Controllers\Concerns\AuthorizesConversationAccess;
use App\Http\Requests\Inbox\UpdateConversationStatusRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Gmail\GmailApiService;
use App\Services\Gmail\GmailAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InboxController extends Controller
{
    use AuthorizesConversationAccess;

    /**
     * Menampilkan halaman Inbox Email (dua panel: daftar percakapan di kiri,
     * pratinjau percakapan yang dipilih — via ?conversation= — di kanan).
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->string('q')->toString();

        $conversations = $this->conversationsByFilter($request, $filter)
            ->appends(['filter' => $filter, 'q' => $search]);

        $hasGmailAccount = $request->user()->gmailAccounts()->exists();

        [$activeConversation, $activeDraft] = $this->resolveActiveConversation($request);

        // Klik conversation di list dilakukan lewat AJAX (lihat inbox-navigation.js)
        // agar tidak perlu reload seluruh halaman — cukup kirim balik markup thread
        // & AI panel yang sudah dirender, JS yang menukar innerHTML-nya. Navigasi
        // filter/search/pagination tetap full page load seperti biasa.
        if ($request->wantsJson()) {
            return response()->json([
                'thread_html' => view('inbox.components.conversation-thread', compact('activeConversation', 'activeDraft'))->render(),
                'ai_panel_html' => view('inbox.components.ai-panel', compact('activeConversation'))->render(),
                'conversation_id' => $activeConversation?->id,
                'is_read' => $activeConversation?->is_read,
            ]);
        }

        return view('inbox.index', compact(
            'conversations',
            'filter',
            'search',
            'hasGmailAccount',
            'activeConversation',
            'activeDraft',
        ));
    }

    /**
     * Permalink lama ke detail percakapan — sekarang detailnya tampil sebagai
     * panel kanan pada halaman Inbox, jadi cukup arahkan ke sana.
     */
    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        return redirect()->route('inbox.index', ['conversation' => $conversation->id]);
    }

    /**
     * Mengubah status percakapan (pending_review / replied / closed) dari
     * dropdown pada halaman detail.
     */
    public function updateStatus(UpdateConversationStatusRequest $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['status' => $request->validated('status')]);

        return back()->with('success', 'Status percakapan berhasil diperbarui.');
    }

    /**
     * Toggle bintang (starred) pada percakapan dari daftar Inbox.
     */
    public function toggleStar(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['is_starred' => ! $conversation->is_starred]);

        return response()->json(['is_starred' => $conversation->is_starred]);
    }

    /**
     * Toggle status baca (dipakai tombol "Mark Read" di toolbar untuk menandai
     * balik sebagai belum dibaca — membuka percakapan sudah otomatis menandai
     * terbaca lewat resolveActiveConversation()).
     */
    public function toggleRead(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['is_read' => ! $conversation->is_read]);

        return response()->json(['is_read' => $conversation->is_read]);
    }

    /**
     * Unduh satu attachment sesuai permintaan (fetch on-demand dari Gmail
     * API) — isi file tidak disimpan saat sync, hanya metadata-nya.
     */
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

    /**
     * Tampilkan satu attachment inline (thumbnail gambar / preview PDF di
     * bubble chat), sama seperti downloadAttachment tapi tanpa memaksa
     * unduhan.
     */
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
        // Ambil percakapan beserta analisis dan pesan TERAKHIR saja (bukan
        // seluruh thread — daftar hanya butuh satu baris pratinjau per item),
        // dibatasi ke channel Email dan hanya akun Gmail milik user yang login.
        return Conversation::with(['analysis', 'latestMessage'])
            ->withExists(['drafts as has_draft' => fn ($query) => $query->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])])
            ->whereHas('gmailAccount', fn ($query) => $query->where('user_id', $request->user()->id))
            ->where('channel', ChannelType::Email)
            ->when($filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->when($filter === 'starred', fn ($query) => $query->where('is_starred', true))
            // "Waiting Agent" / "Waiting Customer" tidak ada di skema DB — di-mapping
            // ke ConversationStatus yang sudah ada: pending_review = customer baru
            // kirim & agent belum balas, replied = agent sudah balas & menunggu
            // customer.
            ->when($filter === 'waiting_agent', fn ($query) => $query->where('status', ConversationStatus::PendingReview))
            ->when($filter === 'waiting_customer', fn ($query) => $query->where('status', ConversationStatus::Replied))
            ->when($filter === 'ai_draft', fn ($query) => $query->whereHas('drafts', fn ($q) => $q->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])))
            ->when($filter === 'closed', fn ($query) => $query->where('status', ConversationStatus::Closed))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';

                $query->where(fn ($q) => $q->where('contact_name', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('subject', 'like', $term));
            })
            ->latest('last_message_at')
            ->paginate(15, page: $page);
    }

    /**
     * Muat percakapan yang sedang dipilih (?conversation=) untuk panel kanan,
     * lalu tandai sudah dibaca begitu dibuka.
     */
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
            ->whereHas('gmailAccount', fn ($query) => $query->where('user_id', $request->user()->id))
            ->where('channel', ChannelType::Email)
            ->find($request->get('conversation'));

        if (! $activeConversation) {
            return [null, null];
        }

        if (! $activeConversation->is_read) {
            $activeConversation->update(['is_read' => true]);
        }

        // Tepat satu draft berstatus Active per percakapan (lihat
        // DraftService::save) — draft lama otomatis jadi Regenerated saat
        // versi baru dibuat, jadi tidak perlu sortBy di sini.
        $activeDraft = $activeConversation->drafts
            ->firstWhere('status', DraftStatus::Active);

        return [$activeConversation, $activeDraft];
    }
}
