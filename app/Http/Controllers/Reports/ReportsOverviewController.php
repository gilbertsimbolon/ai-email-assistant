<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GmailAccount;
use App\Services\Reports\DashboardOverviewService;
use App\Services\Reports\ReportPeriodResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsOverviewController extends Controller
{
    public function __construct(
        protected ReportPeriodResolver $periodResolver,
        protected DashboardOverviewService $overview,
    ) {
    }

    public function index(Request $request): View
    {
        $period = $this->periodResolver->resolve($request);
        $gmailAccountId = $request->integer('gmail_account_id') ?: null;

        $data = $this->overview->compute($period['start'], $period['end'], $period['bucket'], $gmailAccountId);

        return view('reports.index', $data + [
            'period' => $period,
            'gmailAccounts' => GmailAccount::query()->orderBy('email')->get(['id', 'email']),
            'selectedGmailAccountId' => $gmailAccountId,
        ]);
    }
}
