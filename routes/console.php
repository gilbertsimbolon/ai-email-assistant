<?php

use App\Jobs\SyncGhlConversationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Efficient polling fallback for when a GHL webhook isn't configured (see
// GhlSyncService for the incremental/dedup logic). Runs as a queued job so a
// slow GHL/OpenAI call never blocks the scheduler process.
Schedule::job(new SyncGhlConversationsJob)->everyThirtySeconds()->withoutOverlapping();
