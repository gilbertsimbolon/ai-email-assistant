<?php

namespace App\Http\Controllers;

use App\Enums\DraftStatus;
use App\Exceptions\AiNotConfiguredException;
use App\Http\Controllers\Concerns\AuthorizesConversationAccess;
use App\Http\Requests\Inbox\UpdateDraftRequest;
use App\Models\Conversation;
use App\Models\Draft;
use App\Services\AiGenerationService;
use App\Services\Ghl\GhlSendService;
use App\Services\Gmail\GmailSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class DraftController extends Controller
{
    use AuthorizesConversationAccess;

    /**
     * Simpan perubahan manual pada draft AI (subjek/isi) sebelum dikirim.
     * Juga dipakai oleh autosave composer (fetch dengan Accept: application/json).
     */
    public function update(UpdateDraftRequest $request, Draft $draft)
    {
        $this->authorizeConversation($request, $draft->conversation);

        $draft->update([
            'content' => array_merge($draft->content ?? [], [
                'subject' => $request->validated('subject'),
                'body' => $request->validated('body'),
            ]),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['saved_at' => $draft->fresh()->updated_at->toIso8601String()]);
        }

        return back()->with('success', 'Draft berhasil disimpan.');
    }

    /**
     * Approve draft dan kirim balasan — via GHL jika percakapan berasal dari
     * GHL (shared inbox), atau via Gmail API (in-thread reply) untuk
     * percakapan lama yang masih dari Gmail.
     */
    public function approve(Request $request, Draft $draft, GmailSendService $gmailSendService, GhlSendService $ghlSendService)
    {
        $this->authorizeConversation($request, $draft->conversation);

        try {
            $this->dispatchSend($draft, $gmailSendService, $ghlSendService);
        } catch (Throwable $e) {
            Log::error('Failed to send draft', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Gagal mengirim balasan: '.$e->getMessage()], 422);
            }

            return back()->withErrors(['draft' => 'Gagal mengirim balasan: '.$e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'sent']);
        }

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    /**
     * Tolak draft AI tanpa mengirim balasan.
     */
    public function reject(Request $request, Draft $draft)
    {
        $this->authorizeConversation($request, $draft->conversation);

        $draft->update(['status' => DraftStatus::Discarded]);

        return back()->with('success', 'Draft ditolak.');
    }

    /**
     * Tombol "Generate AI Reply" — satu-satunya tempat AI benar-benar
     * dipanggil. Sinkron (bukan queue) karena user sedang menunggu di
     * composer dengan indikator loading, dan tidak ada infrastruktur
     * broadcasting untuk mengirim hasil job secara real-time.
     */
    public function generate(Request $request, Conversation $conversation, AiGenerationService $aiGeneration)
    {
        $this->authorizeConversation($request, $conversation);

        try {
            $draft = $aiGeneration->generateReply($conversation, $request->boolean('as_new_version'));
        } catch (AiNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('AI draft generation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Gagal membuat draf AI. Silakan coba lagi.'], 500);
        }

        $analysis = $conversation->refresh()->analysis;

        return response()->json([
            'draft' => [
                'id' => $draft->id,
                'subject' => $draft->content['subject'] ?? '',
                'body' => $draft->content['body'] ?? '',
                'version' => $draft->version,
                'status' => $draft->status->value,
            ],
            'analysis' => $analysis
                ? Arr::only($analysis->toArray(), ['customer_intent', 'sentiment', 'summary', 'priority', 'customer_status'])
                : null,
        ]);
    }

    /**
     * Tombol "Send" pada composer. Selalu bisa diklik walau belum pernah
     * Generate — jika belum ada draft aktif, buat draft manual dari isi
     * textarea saat ini lalu langsung kirim.
     */
    public function send(Request $request, Conversation $conversation, GmailSendService $gmailSendService, GhlSendService $ghlSendService)
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            // Message-specific Reply (claude.txt task 1): populated only
            // when the agent clicked Reply on a bubble in the thread
            // (message-bubble.blade.php `.js-msg-reply` → composer.js
            // setReplyTarget). GHL's /conversations/messages send API has
            // no reply-to-message-id field, so the reference is carried as
            // quoted text prepended to the outgoing body instead — see
            // GhlSendService::sendDraft().
            'reply_to_sender' => ['nullable', 'string', 'max:190'],
            'reply_to_snippet' => ['nullable', 'string', 'max:500'],
        ]);

        $quoted = filled($validated['reply_to_snippet'] ?? null) ? [
            'sender' => $validated['reply_to_sender'] ?? null,
            'snippet' => $validated['reply_to_snippet'],
        ] : null;

        $draft = $conversation->drafts()->where('status', DraftStatus::Active)->first();

        if ($draft) {
            $draft->update([
                'content' => array_merge($draft->content ?? [], [
                    'subject' => $validated['subject'] ?? null,
                    'body' => $validated['body'],
                    'quoted' => $quoted,
                ]),
            ]);
        } else {
            $channelValue = (string) $conversation->channelValue();

            $draft = Draft::create([
                'conversation_id' => $conversation->id,
                'type' => strtolower($channelValue),
                'provider' => 'manual',
                'content' => [
                    'subject' => $validated['subject'] ?? ($conversation->subject ?: 'Re: percakapan Anda'),
                    'body' => $validated['body'],
                    'tone' => null,
                    'confidence' => null,
                    'quoted' => $quoted,
                ],
                'version' => 1,
                'status' => DraftStatus::Active,
            ]);
        }

        try {
            $this->dispatchSend($draft, $gmailSendService, $ghlSendService);
        } catch (Throwable $e) {
            Log::error('Failed to send draft', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Gagal mengirim balasan: '.$e->getMessage()], 422);
        }

        return response()->json(['status' => 'sent']);
    }

    /**
     * Pick the delivery channel by where the conversation actually came
     * from: GHL-sourced conversations send through GHL, legacy Gmail-synced
     * ones keep sending through Gmail.
     */
    protected function dispatchSend(Draft $draft, GmailSendService $gmailSendService, GhlSendService $ghlSendService): void
    {
        if (filled($draft->conversation->ghl_conversation_id)) {
            $ghlSendService->sendDraft($draft);

            return;
        }

        $gmailSendService->sendDraft($draft);
    }
}
