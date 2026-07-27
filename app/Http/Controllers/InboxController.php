<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\DraftStatus;
use App\Http\Requests\Inbox\UpdateConversationStatusRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Gmail\GmailApiService;
use App\Services\Gmail\GmailAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InboxController extends Controller
{
    /**
     * Menampilkan halaman Inbox Email (dua panel: daftar percakapan di kiri,
     * pratinjau percakapan yang dipilih — via ?conversation= — di kanan).
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $conversations = $this->conversationsByFilter($request, $filter)
            ->appends(['filter' => $filter]);

        $hasGmailAccount = $request->user()->gmailAccounts()->exists();

        [$activeConversation, $activeDraft] = $this->resolveActiveConversation($request);

        return view('inbox.index', compact(
            'conversations',
            'filter',
            'hasGmailAccount',
            'activeConversation',
            'activeDraft',
        ));
    }

    /**
     * Endpoint AJAX yang dipanggil berkala oleh halaman Inbox agar daftar
     * percakapan selalu terbaru tanpa reload halaman.
     */
    public function poll(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $conversations = $this->conversationsByFilter($request, $filter, (int) $request->get('page', 1));

        // Pagination links must point back at the real inbox page, not this
        // JSON polling endpoint (which is what the current request's URL is).
        $conversations->withPath(route('inbox.index'))->appends(['filter' => $filter]);

        return response()->json([
            'html' => view('inbox.partials.list', compact('conversations', 'filter'))->render(),
            'checked_at' => now()->toIso8601String(),
        ]);
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
        $this->authorizeConversation($request, $message->conversation);

        $attachment = collect($message->attachments)->firstWhere('id', $attachmentId);

        abort_unless($attachment, 404);

        $account = $message->conversation->gmailAccount;

        abort_unless($account, 404);

        $account = $gmailAuth->ensureFreshToken($account);

        $data = $gmailApi->getAttachment($account->access_token, $message->gmail_message_id, $attachmentId);
        $bytes = base64_decode(strtr($data['data'] ?? '', '-_', '+/'));

        return response($bytes, 200, [
            'Content-Type' => $attachment['mime_type'] ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$attachment['filename'].'"',
        ]);
    }

    protected function conversationsByFilter(Request $request, ?string $filter, int $page = 1)
    {
        // Ambil percakapan beserta relasi analisis, draf, dan pesan terakhir —
        // dibatasi ke channel Email dan hanya akun Gmail milik user yang login.
        return Conversation::with(['analysis', 'drafts', 'messages'])
            ->whereHas('gmailAccount', fn ($query) => $query->where('user_id', $request->user()->id))
            ->where('channel', ChannelType::Email)
            ->when($filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->when($filter === 'starred', fn ($query) => $query->where('is_starred', true))
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

        $activeConversation = Conversation::with(['analysis', 'drafts', 'messages'])
            ->whereHas('gmailAccount', fn ($query) => $query->where('user_id', $request->user()->id))
            ->where('channel', ChannelType::Email)
            ->find($request->get('conversation'));

        if (! $activeConversation) {
            return [null, null];
        }

        if (! $activeConversation->is_read) {
            $activeConversation->update(['is_read' => true]);
        }

        $activeDraft = $activeConversation->drafts
            ->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])
            ->sortByDesc('created_at')
            ->first();

        return [$activeConversation, $activeDraft];
    }

    /**
     * Pastikan percakapan ini milik akun Gmail yang dimiliki user yang login.
     */
    protected function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->gmailAccount?->user_id === $request->user()->id, 403);
    }
}
