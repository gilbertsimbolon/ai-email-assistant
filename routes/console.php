<?php

use App\Jobs\SyncGmailAccountJob;
use App\Models\GmailAccount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Efficient polling fallback for when Gmail push notifications (Pub/Sub)
// aren't configured: one queued, unique-per-account sync job every 30
// seconds, each incrementally pulling only what changed since its stored
// historyId (see GmailSyncService). withoutOverlapping so a slow run never
// stacks a duplicate scheduler tick behind it.
Schedule::call(function () {
    GmailAccount::query()->pluck('id')->each(
        fn (int $gmailAccountId) => SyncGmailAccountJob::dispatch($gmailAccountId)
    );
})
->name('gmail-sync-dispatch')
->everyThirtySeconds()
->withoutOverlapping();

// GHL is never mirrored into the database (see claude.txt) — Inbox reads
// conversations/messages live from GoHighLevelApiService on every request,
// so there is no GHL scheduler here. Only the Gmail fallback poll above
// remains.
