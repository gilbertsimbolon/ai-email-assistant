<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Models\Conversation;
use App\Models\Draft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxController extends Controller
{
    /**
     * Daftar percakapan untuk agent, dengan filter status opsional.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $conversations = Conversation::query()
            ->with('analysis')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('inbox.index', [
            'conversations' => $conversations,
            'status' => $status,
        ]);
    }

    /**
     * Detail percakapan: pesan, hasil analisis AI, dan draft aktif.
     */
    public function show(Conversation $conversation): View
    {
        $conversation->load([
            'messages' => fn ($query) => $query->orderBy('sent_at'),
            'analysis',
            'drafts' => fn ($query) => $query->orderByDesc('version'),
        ]);

        $activeDraft = $conversation->drafts->firstWhere('status', DraftStatus::Active);

        return view('inbox.show', [
            'conversation' => $conversation,
            'activeDraft' => $activeDraft,
        ]);
    }

    /**
     * Simpan perubahan agent pada draft (subject/body) sebelum dikirim.
     */
    public function updateDraft(Request $request, Draft $draft): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $draft->update([
            'content' => array_merge($draft->content ?? [], [
                'subject' => $data['subject'],
                'body' => $data['body'],
            ]),
        ]);

        return back()->with('success', 'Draft berhasil diperbarui.');
    }

    /**
     * Approve draft agar siap dikirim.
     */
    public function approveDraft(Draft $draft): RedirectResponse
    {
        $draft->update(['status' => DraftStatus::Approved]);

        return back()->with('success', 'Draft disetujui.');
    }

    /**
     * Ubah status percakapan (pending_review, replied, closed).
     */
    public function updateStatus(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(ConversationStatus::cases(), 'value'))],
        ]);

        $conversation->update(['status' => $data['status']]);

        return back()->with('success', 'Status percakapan diperbarui.');
    }
}
