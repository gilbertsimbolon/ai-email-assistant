<?php

namespace App\Services\Reports;

use App\Enums\SenderType;
use App\Models\AiCenter\AiLog;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\Message;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * There is no dedicated activity/audit-log table, so the timeline is
 * synthesized from timestamps already sitting on conversations, analyses,
 * ai_logs, drafts and messages — one merged, time-sorted feed.
 */
class ActivityTimelineService
{
    public function recent(int $limit = 10): array
    {
        return $this->events($limit)->take($limit)->all();
    }

    /**
     * Bounded, flat list for export buttons.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forExport(int $limit = 500): Collection
    {
        return $this->events($limit)->take($limit)->values();
    }

    public function paginate(int $perPage = 30, int $page = 1): LengthAwarePaginator
    {
        $total = Conversation::query()->count()
            + Analysis::query()->count()
            + AiLog::query()->count()
            + Draft::query()->whereNotNull('original_content')->whereColumn('updated_at', '!=', 'created_at')->count()
            + Message::query()->where('sender_type', SenderType::Agent)->count();

        $needed = $perPage * $page;
        $events = $this->events($needed);
        $slice = $events->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * @return Collection<int, array{time: \Carbon\Carbon, icon: string, title: string, description: string, conversation_id: ?int}>
     */
    protected function events(int $limit): Collection
    {
        $events = collect();

        Conversation::query()
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'created_at', 'contact_name', 'contact_email'])
            ->each(fn (Conversation $conversation) => $events->push([
                'time' => $conversation->created_at,
                'icon' => 'bx-envelope',
                'title' => 'Email diterima',
                'description' => $conversation->contact_name ?? $conversation->contact_email ?? 'Pelanggan',
                'conversation_id' => $conversation->id,
            ]));

        Analysis::query()
            ->with('intent:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'conversation_id', 'created_at', 'customer_intent', 'intent_id'])
            ->each(fn (Analysis $analysis) => $events->push([
                'time' => $analysis->created_at,
                'icon' => 'bx-target-lock',
                'title' => 'Intent dikenali',
                'description' => $analysis->intent?->name ?? $analysis->customer_intent ?? '-',
                'conversation_id' => $analysis->conversation_id,
            ]));

        AiLog::query()
            ->with(['sop:id,name', 'workflow:id,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'conversation_id', 'created_at', 'sop_id', 'workflow_id', 'status'])
            ->each(fn (AiLog $log) => $events->push([
                'time' => $log->created_at,
                'icon' => 'bx-git-branch',
                'title' => $log->workflow_id ? 'Workflow dijalankan' : 'AI membuat draft',
                'description' => collect([$log->sop?->name, $log->workflow?->name])->filter()->implode(' → ') ?: 'AI Center pipeline',
                'conversation_id' => $log->conversation_id,
            ]));

        Draft::query()
            ->whereNotNull('original_content')
            ->whereColumn('updated_at', '!=', 'created_at')
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'conversation_id', 'updated_at', 'content', 'original_content'])
            ->each(function (Draft $draft) use ($events) {
                if (!$draft->wasEditedByAgent()) {
                    return;
                }

                $events->push([
                    'time' => $draft->updated_at,
                    'icon' => 'bx-edit',
                    'title' => 'Agent mengedit draft',
                    'description' => 'Draft #'.$draft->id,
                    'conversation_id' => $draft->conversation_id,
                ]);
            });

        Message::query()
            ->where('sender_type', SenderType::Agent)
            ->latest('sent_at')
            ->limit($limit)
            ->get(['id', 'conversation_id', 'sent_at'])
            ->each(fn (Message $message) => $events->push([
                'time' => $message->sent_at,
                'icon' => 'bx-send',
                'title' => 'Email dikirim',
                'description' => 'Balasan agent',
                'conversation_id' => $message->conversation_id,
            ]));

        return $events->sortByDesc('time')->values();
    }
}
