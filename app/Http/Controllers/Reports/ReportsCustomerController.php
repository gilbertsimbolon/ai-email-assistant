<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CustomerAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsCustomerController extends Controller
{
    public function __construct(
        protected CustomerAnalyticsService $customers,
    ) {
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value() ?: null;

        return view('reports.customers', [
            'customers' => $this->customers->paginate($search, 20),
            'search' => $search,
        ]);
    }
}
