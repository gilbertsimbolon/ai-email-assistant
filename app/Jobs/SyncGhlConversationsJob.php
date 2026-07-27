<?php

namespace App\Jobs;

use App\Services\GhlSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Efficient polling fallback for when a GHL webhook isn't wired up: pulls only
 * conversations/messages that changed since the last sync (see GhlSyncService).
 */
class SyncGhlConversationsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Keep the unique lock alive for a bit longer than the 30s schedule interval
     * so a slow run doesn't let a duplicate stack up behind it.
     */
    public int $uniqueFor = 120;

    public function uniqueId(): string
    {
        return 'ghl-sync';
    }

    public function handle(GhlSyncService $ghlSyncService): void
    {
        $ghlSyncService->sync();
    }
}
