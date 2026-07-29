<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AiCenter\AiModel;
use App\Services\Reports\AiUsageReportService;
use App\Services\Reports\ReportPeriodResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsAiUsageController extends Controller
{
    public function __construct(
        protected ReportPeriodResolver $periodResolver,
        protected AiUsageReportService $aiUsage,
    ) {
    }

    public function index(Request $request): View
    {
        $period = $this->periodResolver->resolve($request);
        $aiModelId = $request->integer('ai_model_id') ?: null;

        $data = $this->aiUsage->compute($period['start'], $period['end'], $aiModelId);

        return view('reports.ai-usage', $data + [
            'period' => $period,
            'aiModels' => AiModel::query()->orderBy('name')->get(['id', 'name']),
            'selectedAiModelId' => $aiModelId,
        ]);
    }
}
