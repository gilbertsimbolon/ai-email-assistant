<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\GmailAnalyticsService;
use Illuminate\View\View;

class ReportsGmailController extends Controller
{
    public function __construct(
        protected GmailAnalyticsService $gmail,
    ) {
    }

    public function index(): View
    {
        return view('reports.gmail-accounts', [
            'accounts' => $this->gmail->all(),
        ]);
    }
}
