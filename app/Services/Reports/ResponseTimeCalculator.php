<?php

namespace App\Services\Reports;

use App\Enums\SenderType;
use App\Models\Conversation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Response time isn't stored anywhere — it's derived from the existing
 * `messages` timestamps: the gap between a conversation's first customer
 * message and the first agent message that follows it.
 */
class ResponseTimeCalculator
{
    /**
     * @return Collection<int, float> one sample (minutes) per conversation
     */
    public function minutes(?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $query = Conversation::query()
            ->whereHas('messages', fn ($q) => $q->where('sender_type', SenderType::Agent))
            ->with(['messages' => fn ($q) => $q->orderBy('sent_at')->select('id', 'conversation_id', 'sender_type', 'sent_at')]);

        if ($start && $end) {
            $query->whereHas(
                'messages',
                fn ($q) => $q->where('sender_type', SenderType::Customer)->whereBetween('sent_at', [$start, $end])
            );
        }

        return $query->get()
            ->map(function (Conversation $conversation) {
                $customerMessage = $conversation->messages->firstWhere('sender_type', SenderType::Customer);

                if (!$customerMessage) {
                    return null;
                }

                $agentMessage = $conversation->messages->first(
                    fn ($m) => $m->sender_type === SenderType::Agent && $m->sent_at->greaterThan($customerMessage->sent_at)
                );

                if (!$agentMessage) {
                    return null;
                }

                return $customerMessage->sent_at->diffInMinutes($agentMessage->sent_at, true);
            })
            ->filter(fn ($minutes) => $minutes !== null)
            ->values();
    }

    /**
     * @return array{average: float, median: float, fastest: float, slowest: float, count: int}
     */
    public function stats(?Carbon $start = null, ?Carbon $end = null): array
    {
        $minutes = $this->minutes($start, $end);

        if ($minutes->isEmpty()) {
            return ['average' => 0.0, 'median' => 0.0, 'fastest' => 0.0, 'slowest' => 0.0, 'count' => 0];
        }

        $sorted = $minutes->sort()->values();
        $count = $sorted->count();
        $mid = intdiv($count, 2);
        $median = $count % 2 === 0 ? ($sorted[$mid - 1] + $sorted[$mid]) / 2 : $sorted[$mid];

        return [
            'average' => round($minutes->avg(), 1),
            'median' => round($median, 1),
            'fastest' => round($sorted->first(), 1),
            'slowest' => round($sorted->last(), 1),
            'count' => $count,
        ];
    }
}
