<?php

use App\Jobs\SyncGhlConversationsJob;
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

// Inbox conversations/messages now come from GHL (see claude.txt). One
// shared Private Integration per location — no per-account loop needed,
// just a single unique job. Skipped entirely until GHL is configured, so a
// blank GHL_API_KEY in dev doesn't spam failed API calls every 30 seconds.
Schedule::call(function () {
    if (filled(config('ghl.api_key')) && filled(config('ghl.location_id'))) {
        SyncGhlConversationsJob::dispatch();
    }
})
->name('ghl-sync-dispatch')
->everyThirtySeconds()
->withoutOverlapping();
