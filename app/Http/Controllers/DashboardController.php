<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        abort_unless($request->user()->hasPermission('dashboard.view'), 403);

        $outletId = $request->integer('outlet_id') ?: current_outlet_id();
        if ($request->filled('outlet_id') && $request->user()->canAccessOutlet($outletId)) {
            session(['current_outlet_id' => $outletId]);
        }

        $period = $request->string('period')->toString() === 'month' ? 'month' : 'today';

        return view('dashboard.index', [
            'metrics' => $dashboard->metrics($outletId, $period),
            'charts' => $dashboard->charts($outletId, $period),
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(),
            'outletId' => $outletId,
            'period' => $period,
        ]);
    }
}
