<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ContentAnalyticsService;
use App\Services\Reports\ReportPeriodResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsContentController extends Controller
{
    public function __construct(
        protected ReportPeriodResolver $periodResolver,
        protected ContentAnalyticsService $content,
    ) {
    }

    public function index(Request $request): View
    {
        $period = $this->periodResolver->resolve($request);

        $data = $this->content->compute($period['start'], $period['end']);

        return view('reports.content', $data + [
            'period' => $period,
        ]);
    }
}
