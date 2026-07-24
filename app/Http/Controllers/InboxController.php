<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Draft;
use App\Services\GoHighLevelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class InboxController extends Controller
{
    public function __construct(
        protected GoHighLevelService $ghl,
    ) {
    }

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
     * Approve draft dan langsung kirim balasan ke GoHighLevel.
     */
    public function approveDraft(Draft $draft): RedirectResponse
    {
        $draft->load('conversation');
        $conversation = $draft->conversation;

        if ($draft->status !== DraftStatus::Active) {
            return back()->with('error', 'Draft ini sudah diproses sebelumnya.');
        }

        if (!$conversation->ghl_conversation_id) {
            return back()->with('error', 'Percakapan ini tidak terhubung ke GoHighLevel, tidak bisa dikirim otomatis.');
        }

        try {
            $result = $this->ghl->sendEmailMessage(
                $conversation->ghl_conversation_id,
                $conversation->contact_id,
                $draft->content['subject'] ?? 'Re: Your inquiry',
                nl2br(e($draft->content['body'] ?? '')),
                $draft->content['body'] ?? '',
            );
        } catch (Throwable $e) {
            Log::error('Failed to send GHL message', ['draft_id' => $draft->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Gagal mengirim pesan ke GoHighLevel: '.$e->getMessage());
        }

        $conversation->messages()->create([
            'ghl_message_id' => $result['messageId'] ?? $result['id'] ?? 'sent-'.Str::uuid(),
            'sender_type' => SenderType::Agent,
            'message_type' => MessageType::Email,
            'body' => $draft->content['body'] ?? '',
            'sent_at' => now(),
        ]);

        $draft->update(['status' => DraftStatus::Sent]);
        $conversation->update(['status' => ConversationStatus::Replied, 'last_message_at' => now()]);

        return back()->with('success', 'Balasan berhasil dikirim.');
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
