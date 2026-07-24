<?php

namespace App\Console\Commands;

use App\Services\GhlSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncGhlConversations extends Command
{
    protected $signature = 'ghl:sync';

    protected $description = 'Tarik conversations & messages dari GoHighLevel, lalu generate draft balasan AI untuk pesan customer baru.';

    public function handle(GhlSyncService $ghlSyncService): int
    {
        $this->info('Memulai sync conversations dari GoHighLevel...');

        try {
            $ghlSyncService->sync();
        } catch (Throwable $e) {
            $this->error('Sync gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sync selesai.');

        return self::SUCCESS;
    }
}
