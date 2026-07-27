<?php

namespace App\Console\Commands;

use App\Jobs\SyncGmailAccountJob;
use App\Models\GmailAccount;
use Illuminate\Console\Command;

class SyncGmailCommand extends Command
{
    protected $signature = 'gmail:sync';

    protected $description = 'Dispatch a sync job for every connected Gmail account';

    public function handle(): int
    {
        $accountIds = GmailAccount::query()->pluck('id');

        foreach ($accountIds as $accountId) {
            SyncGmailAccountJob::dispatch($accountId);
        }

        $this->info("Dispatched Gmail sync for {$accountIds->count()} account(s).");

        return self::SUCCESS;
    }
}
