<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $foodOnly = ! $request->user()->hasPermission('dashboard.view');
        abort_unless(! $foodOnly || $request->user()->hasPermission('reports.food'), 403);

        $outletId = $foodOnly ? current_outlet_id() : ($request->integer('outlet_id') ?: current_outlet_id());
        if (! $foodOnly && $request->filled('outlet_id') && $request->user()->canAccessOutlet($outletId)) {
            session(['current_outlet_id' => $outletId]);
        }

        $period = $request->string('period')->toString();
        if ($period === 'today') {
            $period = 'day';
        }
        if (! in_array($period, ['day', 'month', 'year', 'range'], true)) {
            $period = 'day';
        }

        $range = $dashboard->resolveRange([
            'period' => $period,
            'date' => $request->string('date')->toString() ?: null,
            'month' => $request->string('month')->toString() ?: null,
            'year' => $request->string('year')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ]);

        $tab = $request->string('tab')->toString();
        if ($foodOnly) {
            $tab = 'food';
        } elseif ($tab !== 'food') {
            $tab = 'cafe';
        }

        return view('dashboard.index', [
            'metrics' => $dashboard->metrics($outletId, $range),
            'charts' => $dashboard->charts($outletId, $range, $tab),
            'outletId' => $outletId,
            'tab' => $tab,
            'foodOnly' => $foodOnly,
            'period' => $range['period'],
            'range' => $range,
        ]);
    }
}