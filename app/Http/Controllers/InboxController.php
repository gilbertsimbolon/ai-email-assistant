<?php

namespace App\Http\Controllers;

use App\Enums\DraftStatus;
use App\Http\Requests\Inbox\UpdateConversationStatusRequest;
use App\Models\Conversation;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    /**
     * Menampilkan halaman Inbox utama (List Percakapan).
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending_review');

        $conversations = $this->conversationsByStatus($status);

        return view('inbox.index', compact('conversations', 'status'));
    }

    /**
     * Endpoint AJAX yang dipanggil berkala oleh halaman Inbox agar daftar
     * percakapan selalu terbaru tanpa reload halaman.
     */
    public function poll(Request $request)
    {
        $status = $request->get('status', 'pending_review');

        $conversations = $this->conversationsByStatus($status, (int) $request->get('page', 1));

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
    public function show(Conversation $conversation)
    {
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
        $conversation->update(['status' => $request->validated('status')]);

        return back()->with('success', 'Status percakapan berhasil diperbarui.');
    }

    protected function conversationsByStatus(?string $status, int $page = 1)
    {
        // Ambil percakapan beserta relasi analisis, draf, dan pesan terakhir
        return Conversation::with(['analysis', 'drafts', 'messages'])
            ->when($status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->latest('last_message_at')
            ->paginate(15, page: $page);
    }
}
