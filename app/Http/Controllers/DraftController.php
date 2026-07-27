<?php

namespace App\Http\Controllers;

use App\Enums\DraftStatus;
use App\Http\Requests\Inbox\UpdateDraftRequest;
use App\Models\Draft;
use App\Services\Gmail\GmailSendService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DraftController extends Controller
{
    /**
     * Simpan perubahan manual pada draft AI (subjek/isi) sebelum dikirim.
     */
    public function update(UpdateDraftRequest $request, Draft $draft)
    {
        $draft->update([
            'content' => array_merge($draft->content ?? [], [
                'subject' => $request->validated('subject'),
                'body' => $request->validated('body'),
            ]),
        ]);

        return back()->with('success', 'Draft berhasil disimpan.');
    }

    /**
     * Approve draft dan kirim balasan melalui Gmail API (in-thread reply).
     */
    public function approve(Draft $draft, GmailSendService $gmailSendService)
    {
        try {
            $gmailSendService->sendDraft($draft);
        } catch (Throwable $e) {
            Log::error('Failed to send draft', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['draft' => 'Gagal mengirim balasan: '.$e->getMessage()]);
        }

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    /**
     * Tolak draft AI tanpa mengirim balasan.
     */
    public function reject(Draft $draft)
    {
        $draft->update(['status' => DraftStatus::Discarded]);

        return back()->with('success', 'Draft ditolak.');
    }
}
