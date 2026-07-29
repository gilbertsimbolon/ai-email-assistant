<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ActivityTimelineService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsTimelineController extends Controller
{
    public function __construct(
        protected ActivityTimelineService $timeline,
    ) {
    }

    public function index(Request $request): View
    {
        $page = max(1, $request->integer('page', 1));

        return view('reports.timeline', [
            'events' => $this->timeline->paginate(30, $page),
        ]);
    }
}
