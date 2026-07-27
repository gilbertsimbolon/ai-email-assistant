<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inbox\UpdateDraftRequest;
use App\Models\Draft;
use App\Services\EmailService;
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
     * Approve draft dan kirim balasan melalui GoHighLevel.
     */
    public function approve(Draft $draft, EmailService $emailService)
    {
        try {
            $emailService->sendDraft($draft);
        } catch (Throwable $e) {
            Log::error('Failed to send draft', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['draft' => 'Gagal mengirim balasan: '.$e->getMessage()]);
        }

        return back()->with('success', 'Balasan berhasil dikirim.');
    }
}
