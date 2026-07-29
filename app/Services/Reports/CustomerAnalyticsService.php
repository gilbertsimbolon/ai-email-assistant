<?php

namespace App\Services\Reports;

use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Top Customer analytics — there is no dedicated Customer table, customer
 * identity lives on `conversations.contact_email`/`contact_name`, so this
 * groups conversations (+ their messages) by contact email.
 */
class CustomerAnalyticsService
{
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return Conversation::query()
            ->selectRaw('conversations.contact_email as contact_email')
            ->selectRaw('MAX(conversations.contact_name) as contact_name')
            ->selectRaw('COUNT(DISTINCT conversations.id) as ticket_count')
            ->selectRaw('COUNT(messages.id) as email_count')
            ->selectRaw('MAX(conversations.last_message_at) as last_contact')
            ->leftJoin('messages', 'messages.conversation_id', '=', 'conversations.id')
            ->whereNotNull('conversations.contact_email')
            ->when($search, fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('conversations.contact_email', 'like', "%{$search}%")
                    ->orWhere('conversations.contact_name', 'like', "%{$search}%");
            }))
            ->groupBy('conversations.contact_email')
            ->orderByDesc('last_contact')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Bounded, non-paginated variant for export buttons.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forExport(?string $search = null, int $limit = 2000): Collection
    {
        return $this->paginate($search, $limit)->getCollection();
    }
}
