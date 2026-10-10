<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Investor;
use App\Models\InvestorTopup;
use App\Models\MonthlyTarget;
use App\Models\OrderItem;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('bundles', 'bundles.id', '=', 'order_items.bundle_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->whereYear('orders.created_at', $year)
            ->whereMonth('orders.created_at', $month)
            ->selectRaw('COALESCE(SUM('.order_item_drink_amount_sql().'), 0) as sales')
            ->value('sales');
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

    public function storeBop(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'period' => ['required', 'in:month,year'],
            'amount' => ['required', 'numeric', 'min:0'],
            'index' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Nama biaya wajib diisi.',
            'period.in' => 'Periode harus bulanan atau tahunan.',
            'amount.required' => 'Nominal wajib diisi.',
        ]);

        $items = bop_plan_source();
        $index = $request->filled('index') ? (int) $data['index'] : null;
        if ($index !== null && ! isset($items[$index])) {
            throw ValidationException::withMessages(['index' => 'Biaya tidak ditemukan.']);
        }

        $row = [
            'name' => trim($data['name']),
            'category' => $index !== null
                ? (string) ($items[$index]['category'] ?? (Str::slug(trim($data['name'])) ?: 'lainnya'))
                : (Str::slug(trim($data['name'])) ?: 'lainnya'),
            'amount' => round((float) $data['amount'], 2),
            'period' => $data['period'],
        ];

        if ($index === null) {
            $items[] = $row;
        } else {
            $items[$index] = $row;
        }

        $this->saveBopPlan($items);

        return redirect()
            ->route('investors.index')
            ->with('success', $index === null ? 'Biaya ditambahkan.' : 'Biaya diperbarui.');
    }

    public function destroyBop(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
        ]);

        $items = bop_plan_source();
        if (! isset($items[(int) $data['index']])) {
            throw ValidationException::withMessages(['index' => 'Biaya tidak ditemukan.']);
        }

        array_splice($items, (int) $data['index'], 1);
        $this->saveBopPlan($items);

        return redirect()
            ->route('investors.index')
            ->with('success', 'Biaya dihapus.');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function saveBopPlan(array $items): void
    {
        Setting::query()->updateOrCreate(
            ['outlet_id' => null, 'key' => 'bop_plan'],
            ['value' => json_encode(array_values($items), JSON_UNESCAPED_UNICODE), 'group' => 'bop'],
        );

        Cache::forget('setting..bop_plan');
        Cache::forget('setting.'.current_outlet_id().'.bop_plan');
    }
}
