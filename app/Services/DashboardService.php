<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\BankAccount;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected ProfitShareService $shares,
        protected ReportService $reports,
    ) {}

    /**
     * @param  array{period?: string, date?: string, month?: string, year?: string, from?: string, to?: string}  $params
     */
    public function resolveRange(array $params): array
    {
        $period = $params['period'] ?? 'day';
        if ($period === 'today') {
            $period = 'day';
        }

        $today = Carbon::now();

        return match ($period) {
            'month' => $this->monthRange($params['month'] ?? $today->format('Y-m')),
            'year' => $this->yearRange($params['year'] ?? $today->format('Y')),
            'range' => $this->customRange($params['from'] ?? null, $params['to'] ?? null),
            default => $this->dayRange($params['date'] ?? $today->toDateString()),
        };
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function periodFilters(Request $request): array
    {
        $period = $request->string('period')->toString();
        if ($period === 'today') {
            $period = 'day';
        }
        if ($period === 'week') {
            $period = 'range';
            $request->merge([
                'from' => $request->from ?: now()->startOfWeek()->toDateString(),
                'to' => $request->to ?: now()->toDateString(),
            ]);
        }
        if (! in_array($period, ['day', 'month', 'year', 'range'], true)) {
            $period = ($request->filled('from') && $request->filled('to')) ? 'range' : 'day';
        }

        $range = $this->resolveRange([
            'period' => $period,
            'date' => $request->string('date')->toString() ?: null,
            'month' => $request->string('month')->toString() ?: null,
            'year' => $request->string('year')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ]);
        $filters = $request->all();
        $filters['from'] = $range['from'];
        $filters['to'] = $range['to'];
        $filters['period'] = $range['period'];

        return [$filters, $range];
    }

    public function metrics(?int $outletId, array $range): array
    {
        $filters = [
            'outlet_id' => $outletId,
            'from' => $range['from'],
            'to' => $range['to'],
        ];

        $summary = $this->shares->summarize($filters);
        $food = $this->excludeCancelledFood($this->reports->foodSetoran($filters), $filters);
        [$drinks, $drinkCups] = $this->drinkSales($filters);
        $gross = round((float) (clone $this->validOrders($outletId, $range))->sum('grand_total'), 2);
        $setoran = (float) $food['setoran'];
        $setoranPaid = $outletId
            ? $this->reports->foodSetoranPaidWithin($outletId, $range['from'], $range['to'])
            : 0.0;
        $sales = round($gross - $setoran, 2);
        $bop = (float) $summary['bop'];

        // Operasional dan omzet bersih selalu per bulan: filter harian/range memakai bulan tanggal akhirnya.
        $opsRange = in_array($range['period'], ['month', 'year'], true)
            ? $range
            : $this->monthRange(Carbon::parse($range['to'])->format('Y-m'));
        $opsSummary = $opsRange === $range
            ? $summary
            : $this->shares->summarize(['outlet_id' => $outletId, 'from' => $opsRange['from'], 'to' => $opsRange['to']]);
        $opsSales = $opsRange === $range ? $sales : $this->cafeSales($outletId, $opsRange);

        // Gaji dan sewa pakai nilai tetap, jadi BOP kategori Gaji dan Sewa tidak dihitung lagi di operasional.
        $salary = $this->fixedSalary($outletId, $opsRange);
        $rent = round((float) setting('rent_yearly', self::RENT_YEARLY) / 12 * $this->months($opsRange), 2);
        $otherExpenses = $opsSummary['expenses']->reject(fn ($expense) => in_array($expense->category, [ExpenseCategory::Salary, ExpenseCategory::Rent], true));
        $bopOther = round((float) $otherExpenses->sum('amount'), 2);
        $operational = round($bopOther + $salary + $rent, 2);
        $serviceFee = round((float) (clone $this->validOrders($outletId, $range))->sum('tax_amount'), 2);
        $net = round($opsSales - $operational, 2);
        $shareBase = round($sales - $serviceFee - $bop, 2);

        $orderCount = (int) (clone $this->validOrders($outletId, $range))->count();

        $tables = DiningTable::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('is_active', true);

        $paymentTotals = $this->paymentTotals($outletId, $range);
        $paymentGap = round($paymentTotals['total'] - $gross, 2);
        $drinkDailyTarget = (int) config('pos.drink_daily_target', 50);
        $days = max(1, (int) Carbon::parse($range['from'])->startOfDay()->diffInDays(Carbon::parse($range['to'])->startOfDay()) + 1);
        $drinkTarget = $drinkDailyTarget * $days;

        return [
            'period' => $range['period'],
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
            'gross' => $gross,
            'drinks' => $drinks,
            'food_sales' => (float) $food['sales'],
            'food_cafe' => (float) $food['commission'],
            'food_setoran' => $setoran,
            'food_setoran_paid' => $setoranPaid,
            'food_setoran_remaining' => round(max(0, $setoran - $setoranPaid), 2),
            'sales' => $sales,
            'ops_label' => $opsRange['label'],
            'ops_sales' => $opsSales,
            'bank_balance' => $this->bankBalance($outletId),
            'bop' => $bop,
            'salary' => $salary,
            'rent' => $rent,
            'bop_other' => $bopOther,
            'operational' => $operational,
            'expense_groups' => $this->expenseGroups($otherExpenses, $salary, $rent, $operational),
            'net' => $net,
            'share_base' => $shareBase,
            'shares' => $this->shares->split($shareBase, $this->shares->partners()),
            'cash' => $paymentTotals['cash'],
            'qris' => $paymentTotals['qris'],
            'other' => $paymentTotals['other'],
            'payment_total' => $paymentTotals['total'],
            'payment_gap' => $paymentGap,
            'payments_match' => abs($paymentGap) < 1,
            'orders' => $orderCount,
            'aov' => $orderCount > 0 ? $gross / $orderCount : 0,
            'service_fee' => $serviceFee,
            'drink_cups' => $drinkCups,
            'drink_daily_target' => $drinkDailyTarget,
            'drink_target' => $drinkTarget,
            'drink_target_percent' => $drinkTarget > 0 ? (int) round($drinkCups / $drinkTarget * 100) : 0,
            'occupied_tables' => (clone $tables)->where('status', TableStatus::Occupied->value)->count(),
            'available_tables' => (clone $tables)->where('status', TableStatus::Available->value)->count(),
        ];
    }

    /** Omzet cafe: semua order lunas dikurangi setoran makanan. */
    protected function cafeSales(?int $outletId, array $range): float
    {
        $filters = ['outlet_id' => $outletId, 'from' => $range['from'], 'to' => $range['to']];
        $food = $this->excludeCancelledFood($this->reports->foodSetoran($filters), $filters);
        $gross = (float) (clone $this->validOrders($outletId, $range))->sum('grand_total');

        return round($gross - (float) $food['setoran'], 2);
    }

    protected function bankBalance(?int $outletId): float
    {
        $bank = app(BankAccountService::class);
        $outlets = $outletId ? [$outletId] : BankAccount::query()->pluck('outlet_id')->all();

        return round(array_sum(array_map(fn ($id) => $bank->balance((int) $id), $outlets)), 2);
    }

    /** Sewa ruko per tahun bila belum diatur di Settings. */
    public const RENT_YEARLY = 45_000_000;

    /**
     * Gaji bulanan karyawan aktif.
     */
    protected function fixedSalary(?int $outletId, array $range): float
    {
        $monthly = Employee::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('is_active', true)
            ->get()
            ->sum(fn (Employee $employee) => array_sum($employee->payParts()));

        return round($monthly * $this->months($range), 2);
    }

    /**
     * Jumlah bulan biaya tetap: bulanan penuh, tahunan per bulan berjalan, harian/range prorata kalender.
     */
    protected function months(array $range): float
    {
        $from = Carbon::parse($range['from']);
        $to = Carbon::parse($range['to']);

        return match ($range['period']) {
            'month' => 1,
            'year' => $to->month,
            default => ($from->diffInDays($to) + 1) / $from->daysInMonth,
        };
    }

    /**
     * @return list<array{label: string, amount: float, percent: int}>
     */
    protected function expenseGroups($expenses, float $salary, float $rent, float $total): array
    {
        $groups = $expenses
            ->groupBy(fn ($expense) => $expense->category?->label() ?? 'Lainnya')
            ->map(fn ($rows) => (float) $rows->sum('amount'));
        if ($salary > 0) {
            $groups['Gaji karyawan'] = $salary;
        }
        if ($rent > 0) {
            $groups['Sewa ruko'] = $rent;
        }

        return $groups
            ->map(fn (float $amount, $label) => [
                'label' => (string) $label,
                'amount' => round($amount, 2),
                'percent' => $total > 0 ? (int) round($amount / $total * 100) : 0,
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    public function charts(?int $outletId, array $range, string $tab = 'cafe'): array
    {
        $trendFrom = now()->subDays(13)->startOfDay();

        return [
            'sales_trend' => $this->kindTrend($outletId, $trendFrom, $tab === 'food' ? 'food' : 'drink'),
            'top_drinks' => $this->topProducts($outletId, $range, 'drink'),
            'top_food' => $this->topProducts($outletId, $range, 'food'),
            'arrival_sources' => $this->arrivalSources($outletId, $range),
        ];
    }

    /**
     * Omzet harian minuman atau makanan, setelah diskon, sama dengan kartu pendapatan.
     */
    protected function kindTrend(?int $outletId, Carbon $from, string $kind)
    {
        $amount = $kind === 'drink' ? order_item_drink_amount_sql() : order_item_food_amount_sql();

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('bundles', 'bundles.id', '=', 'order_items.bundle_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', $from)
            ->selectRaw("DATE(orders.created_at) as d, SUM({$amount}) as total")
            ->groupBy('d')
            ->orderBy('d')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{name: string, qty: float|int, total: float|int, profit: float|int}>
     */
    protected function topProducts(?int $outletId, array $range, string $kind)
    {
        $net = order_item_net_total_sql();
        $drinkNames = collect(drink_category_names())
            ->map(fn (string $name) => "'".str_replace("'", "''", $name)."'")
            ->implode(', ');

        $lines = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereDate('orders.created_at', '>=', $range['from'])
            ->whereDate('orders.created_at', '<=', $range['to'])
            ->when($kind === 'drink', fn ($q) => $q->whereRaw("categories.name IN ({$drinkNames})"))
            ->when($kind === 'food', fn ($q) => $q->where(function ($q) use ($drinkNames) {
                $q->whereNull('categories.name')->orWhereRaw("categories.name NOT IN ({$drinkNames})");
            }))
            ->selectRaw("order_items.name as name, order_items.quantity as qty, ({$net}) as line_total, order_items.quantity * COALESCE(products.cost, 0) as line_cost");

        return DB::query()
            ->fromSub($lines, 'lines')
            ->selectRaw('name, SUM(qty) as qty, SUM(line_total) as total, SUM(line_total) - SUM(line_cost) as profit')
            ->groupBy('name')
            ->orderByDesc('qty')
            ->get();
    }

    /**
     * @return list<array{key: string, label: string, qty: int, percent: int}>
     */
    protected function arrivalSources(?int $outletId, array $range): array
    {
        $rows = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereDate('created_at', '>=', $range['from'])
            ->whereDate('created_at', '<=', $range['to'])
            ->whereNotNull('arrival_source')
            ->where('arrival_source', '!=', '')
            ->selectRaw('arrival_source, COUNT(*) as qty')
            ->groupBy('arrival_source')
            ->orderByDesc('qty')
            ->orderBy('arrival_source')
            ->get();

        $total = (int) $rows->sum('qty');
        $labels = config('pos.arrival_sources', []);

        return $rows->map(fn ($row) => [
            'key' => (string) $row->arrival_source,
            'label' => $labels[$row->arrival_source] ?? (string) $row->arrival_source,
            'qty' => (int) $row->qty,
            'percent' => $total > 0 ? (int) round($row->qty / $total * 100) : 0,
        ])->all();
    }

    /**
     * @return array{cash: float, qris: float, other: float, total: float}
     */
    protected function paymentTotals(?int $outletId, array $range): array
    {
        $rows = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereDate('orders.created_at', '>=', $range['from'])
            ->whereDate('orders.created_at', '<=', $range['to'])
            ->selectRaw('payments.method, SUM(payments.amount) as total')
            ->groupBy('payments.method')
            ->pluck('total', 'method');

        $cash = (float) ($rows['cash'] ?? 0);
        $qris = (float) ($rows['qris'] ?? 0);
        $total = round((float) $rows->sum(), 2);

        return [
            'cash' => $cash,
            'qris' => $qris,
            'other' => round($total - $cash - $qris, 2),
            'total' => $total,
        ];
    }

    /**
     * Order lunas yang tidak dibatalkan, pada tanggal order yang sama dengan kartu lain.
     */
    protected function validOrders(?int $outletId, array $range)
    {
        return Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereDate('created_at', '>=', $range['from'])
            ->whereDate('created_at', '<=', $range['to']);
    }

    /**
     * @param  array{sales: float, commission: float, setoran: float, rows: mixed}  $food
     * @return array{sales: float, commission: float, setoran: float, rows: mixed}
     */
    protected function excludeCancelledFood(array $food, array $filters): array
    {
        $cancelled = (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('bundles', 'bundles.id', '=', 'order_items.bundle_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.status', OrderStatus::Cancelled->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->selectRaw('COALESCE(SUM('.order_item_food_amount_sql().'), 0) as sales')
            ->value('sales');

        if ($cancelled <= 0) {
            return $food;
        }

        $rate = (float) config('pos.food_cafe_percent', 10) / 100;
        $food['sales'] = round((float) $food['sales'] - $cancelled, 2);
        $food['commission'] = round((float) $food['commission'] - ($cancelled * $rate), 2);
        $food['setoran'] = round($food['sales'] - $food['commission'], 2);

        return $food;
    }

    /**
     * @return array{0: float, 1: float}
     */
    protected function drinkSales(array $filters): array
    {
        $row = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('bundles', 'bundles.id', '=', 'order_items.bundle_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->selectRaw('COALESCE(SUM('.order_item_drink_amount_sql().'), 0) as sales, COALESCE(SUM('.order_item_drink_cups_sql().'), 0) as cups')
            ->first();

        return [(float) $row->sales, (float) $row->cups];
    }

    /**
     * @return array{period: string, from: string, to: string, label: string, date?: string, month?: string, year?: string}
     */
    protected function dayRange(string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();

        return [
            'period' => 'day',
            'from' => $day->toDateString(),
            'to' => $day->toDateString(),
            'date' => $day->toDateString(),
            'label' => $day->locale('id')->translatedFormat('d F Y'),
        ];
    }

    /**
     * @return array{period: string, from: string, to: string, label: string, month: string}
     */
    protected function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        if ($end->isFuture()) {
            $end = Carbon::now()->startOfDay();
        }

        return [
            'period' => 'month',
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'month' => $start->format('Y-m'),
            'label' => $start->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * @return array{period: string, from: string, to: string, label: string, year: string}
     */
    protected function yearRange(string $year): array
    {
        $y = (int) $year;
        $start = Carbon::create($y, 1, 1)->startOfDay();
        $end = Carbon::create($y, 12, 31)->startOfDay();
        if ($end->isFuture()) {
            $end = Carbon::now()->startOfDay();
        }

        return [
            'period' => 'year',
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'year' => (string) $y,
            'label' => 'Tahun '.$y,
        ];
    }

    /**
     * @return array{period: string, from: string, to: string, label: string}
     */
    protected function customRange(?string $from, ?string $to): array
    {
        $fromDate = Carbon::parse($from ?: now()->startOfMonth()->toDateString())->startOfDay();
        $toDate = Carbon::parse($to ?: now()->toDateString())->startOfDay();
        if ($toDate->lt($fromDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'period' => 'range',
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'label' => $fromDate->locale('id')->translatedFormat('d M Y').' – '.$toDate->locale('id')->translatedFormat('d M Y'),
        ];
    }
}
