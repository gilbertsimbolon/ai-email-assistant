<?php

namespace App\Http\Controllers;

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
     * Menampilkan halaman Inbox utama (List Percakapan).
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending_review');

        $conversations = $this->conversationsByStatus($request, $status);
        $hasGmailAccount = $request->user()->gmailAccounts()->exists();

        return view('inbox.index', compact('conversations', 'status', 'hasGmailAccount'));
    }

    /**
     * Endpoint AJAX yang dipanggil berkala oleh halaman Inbox agar daftar
     * percakapan selalu terbaru tanpa reload halaman.
     */
    public function poll(Request $request)
    {
        $status = $request->get('status', 'pending_review');

        $conversations = $this->conversationsByStatus($request, $status, (int) $request->get('page', 1));

        // Pagination links must point back at the real inbox page, not this
        // JSON polling endpoint (which is what the current request's URL is).
        $conversations->withPath(route('inbox.index'))->appends(['status' => $status]);

        return response()->json([
            'html' => view('inbox.partials.list', compact('conversations'))->render(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Menampilkan detail percakapan yang dipilih dari daftar.
     */
    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->load(['analysis', 'drafts', 'messages']);

        $activeDraft = $conversation->drafts
            ->whereIn('status', [DraftStatus::Active, DraftStatus::Regenerated])
            ->sortByDesc('created_at')
            ->first();

        return view('inbox.show', compact('conversation', 'activeDraft'));
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

    protected function conversationsByStatus(Request $request, ?string $status, int $page = 1)
    {
        // Ambil percakapan beserta relasi analisis, draf, dan pesan terakhir —
        // dibatasi hanya ke akun Gmail milik user yang login.
        return Conversation::with(['analysis', 'drafts', 'messages'])
            ->whereHas('gmailAccount', fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->latest('last_message_at')
            ->paginate(15, page: $page);
    }

    /**
     * Pastikan percakapan ini milik akun Gmail yang dimiliki user yang login.
     */
    protected function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->gmailAccount?->user_id === $request->user()->id, 403);
    }
}
