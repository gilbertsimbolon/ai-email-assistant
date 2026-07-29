<?php

namespace App\Http\Controllers\AiCenter;

use App\Http\Controllers\Controller;
use App\Services\AiCenter\DashboardStatsService;
use Illuminate\View\View;

class AiCenterDashboardController extends Controller
{
    public function __construct(
        protected DashboardStatsService $stats,
    ) {
    }

    public function index(): View
    {
        return view('ai-center.dashboard', [
            'stats' => $this->stats->compute(),
        ]);
    }
}
