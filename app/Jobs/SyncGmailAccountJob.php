<?php

namespace App\Jobs;

use App\Models\GmailAccount;
use App\Services\Gmail\GmailSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Efficient polling fallback (Gmail push notifications need a Google Cloud
 * Pub/Sub project we're not assuming is set up): syncs one GmailAccount's
 * inbox via the History API cursor in GmailSyncService.
 */
class SyncGmailAccountJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Keep the unique lock alive a bit longer than the scheduler interval so
     * a slow run doesn't let a duplicate stack up behind it.
     */
    public int $uniqueFor = 120;

    public function __construct(
        protected int $gmailAccountId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'gmail-sync-'.$this->gmailAccountId;
    }

    public function handle(GmailSyncService $gmailSyncService): void
    {
        $account = GmailAccount::find($this->gmailAccountId);

        if (!$account) {
            return;
        }

        $gmailSyncService->sync($account);
    }
}
