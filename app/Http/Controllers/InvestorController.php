<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Investor;
use App\Models\InvestorTopup;
use App\Models\MonthlyTarget;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvestorController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $investors = Investor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $totalCapital = (float) $investors->sum('capital');
        $outletId = current_outlet_id();
        $year = (int) now()->year;
        $month = (int) now()->month;

        $target = MonthlyTarget::query()
            ->where('outlet_id', $outletId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $bopMonthly = monthly_bop();
        $targetAmount = (float) ($target?->amount ?? $bopMonthly);
        $actual = $this->drinkSalesForMonth($outletId, $year, $month);
        $progress = $targetAmount > 0 ? min(100, round($actual / $targetAmount * 100, 1)) : 0;

        return view('investors.index', [
            'investors' => $investors,
            'totalCapital' => $totalCapital,
            'topups' => InvestorTopup::query()
                ->with(['investor', 'user'])
                ->orderByDesc('topped_up_on')
                ->orderByDesc('id')
                ->paginate(20),
            'target' => $target,
            'targetAmount' => $targetAmount,
            'actualSales' => $actual,
            'targetProgress' => $progress,
            'targetYear' => $year,
            'targetMonth' => $month,
            'bopItems' => bop_items(),
            'bopMonthly' => $bopMonthly,
        ]);
    }

    protected function drinkSalesForMonth(?int $outletId, int $year, int $month): float
    {
        return (float) OrderItem::query()
            ->whereHas('order', function ($q) use ($outletId, $year, $month) {
                $q->where('payment_status', PaymentStatus::Paid->value)
                    ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month);
            })
            ->whereHas('product.category', fn ($q) => $q->whereIn('name', drink_category_names()))
            ->sum('total');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'capital' => ['required', 'numeric', 'min:0'],
        ]);

        Investor::query()->create([
            'name' => $data['name'],
            'capital' => $data['capital'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('investors.index')
            ->with('success', 'Investor ditambahkan.');
    }

    public function topup(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'investor_id' => ['required', 'exists:investors,id'],
            'topped_up_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $investor = Investor::query()->lockForUpdate()->findOrFail($data['investor_id']);

            InvestorTopup::query()->create([
                ...$data,
                'user_id' => $request->user()->id,
            ]);

            $investor->update([
                'capital' => (float) $investor->capital + (float) $data['amount'],
            ]);
        });

        return redirect()
            ->route('investors.index')
            ->with('success', 'Topup modal tercatat.');
    }

    public function storeTarget(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        MonthlyTarget::query()->updateOrCreate(
            [
                'outlet_id' => current_outlet_id(),
                'year' => $data['year'],
                'month' => $data['month'],
            ],
            ['amount' => $data['amount']],
        );

        return redirect()
            ->route('investors.index')
            ->with('success', 'Target bulanan disimpan.');
    }
}
