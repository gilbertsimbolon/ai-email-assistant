<?php

namespace App\Services\Reports;

use App\Models\GmailAccount;

class GmailAnalyticsService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return GmailAccount::query()
            ->withCount('conversations')
            ->with('user:id,name,email')
            ->orderByDesc('last_synced_at')
            ->get()
            ->map(fn (GmailAccount $account) => [
                'email' => $account->email,
                'owner' => $account->user?->name,
                'conversation_count' => $account->conversations_count,
                'last_synced_at' => $account->last_synced_at,
                'status' => $account->status,
                'history_id' => $account->history_id,
                'last_error' => $account->last_error,
            ])
            ->values()
            ->all();
    }
}
