<?php

namespace App\Console\Commands;

use App\Jobs\SyncGhlConversationsJob;
use Illuminate\Console\Command;

class SyncConversationsCommand extends Command
{
    protected $signature = 'ghl:sync';

    protected $description = 'Dispatch a job to pull new/updated email conversations and messages from GoHighLevel';

    public function handle(): int
    {
        SyncGhlConversationsJob::dispatch();

        $this->info('GHL sync job dispatched to the queue.');

        return self::SUCCESS;
    }
}
