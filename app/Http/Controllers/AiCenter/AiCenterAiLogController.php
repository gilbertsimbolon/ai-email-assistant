<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\AiCenterLogSource;
use App\Enums\AiCenter\AiCenterLogStatus;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\AiLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiCenterAiLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AiLog::query()
            ->with(['conversation', 'intent', 'sop', 'workflow'])
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->date('date')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('ai-center.ai-logs.index', [
            'logs' => $logs,
            'sources' => AiCenterLogSource::cases(),
            'statuses' => AiCenterLogStatus::cases(),
        ]);
    }

    public function show(AiLog $aiLog): View
    {
        $aiLog->load(['conversation', 'intent', 'sop', 'workflow', 'replyTemplate', 'aiModel', 'triggeredByUser']);

        return view('ai-center.ai-logs.show', [
            'aiLog' => $aiLog,
        ]);
    }
}
