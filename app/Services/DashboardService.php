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
            ...$this->dailyAverage($outletId, $range, $sales, $drinks),
            'occupied_tables' => (clone $tables)->where('status', TableStatus::Occupied->value)->count(),
            'available_tables' => (clone $tables)->where('status', TableStatus::Available->value)->count(),
        ];
    }

    /**
     * Laba rugi bulanan dengan dasar yang sama seperti dashboard:
     * omzet cafe (tanpa bagian vendor makanan) − HPP − BOP − gaji tetap − sewa ruko.
     */
    public function incomeStatement(?int $outletId, string $month, bool $withPrevious = true): array
    {
        $range = $this->monthRange($month);
        $filters = ['outlet_id' => $outletId, 'from' => $range['from'], 'to' => $range['to']];
        $rate = (float) config('pos.food_cafe_percent', 10) / 100;

        $orders = $this->validOrders($outletId, $range);
        $gross = round((float) (clone $orders)->sum('grand_total'), 2);
        $service = round((float) (clone $orders)->sum('tax_amount'), 2);
        $discount = round((float) (clone $orders)->sum('discount_amount'), 2);
        $orderCount = (int) (clone $orders)->count();
        [$drinks, $cups] = $this->drinkSales($filters);
        $food = $this->excludeCancelledFood($this->reports->foodSetoran($filters), $filters);
        $other = round($gross - $drinks - (float) $food['sales'] - $service, 2);
        $setoran = (float) $food['setoran'];
        $omzet = round($gross - $setoran, 2);

        $net = order_item_net_total_sql();
        $foodAmount = order_item_food_amount_sql();
        $categories = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('bundles', 'bundles.id', '=', 'order_items.bundle_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->whereDate('orders.created_at', '>=', $range['from'])
            ->whereDate('orders.created_at', '<=', $range['to'])
            ->selectRaw("COALESCE(categories.name, 'Lainnya') as name")
            ->selectRaw('SUM(order_items.quantity) as qty')
            ->selectRaw("SUM({$net}) as sales")
            ->selectRaw('SUM(order_items.quantity * COALESCE(products.cost, 0)) as hpp')
            ->selectRaw("SUM({$foodAmount}) as food")
            ->groupBy('categories.name')
            ->get()
            ->map(function ($row) use ($rate) {
                $sales = (float) $row->sales;
                $hpp = (float) $row->hpp;
                // Makanan titipan: bagian cafe hanya komisinya, sisanya disetor ke vendor.
                $vendor = (float) $row->food * (1 - $rate);
                $profit = $sales - $hpp - $vendor;

                return [
                    'name' => (string) $row->name,
                    'qty' => (float) $row->qty,
                    'sales' => round($sales, 2),
                    'hpp' => round($hpp, 2),
                    'consignment' => (float) $row->food > $sales / 2,
                    'profit' => round($profit, 2),
                    'margin' => $sales > 0 ? round($profit / $sales * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('sales')
            ->values()
            ->all();

        $hpp = round(array_sum(array_column($categories, 'hpp')), 2);
        $grossProfit = round($omzet - $hpp, 2);

        $expenses = $this->shares->summarize($filters)['expenses'];
        $fixedCategories = [ExpenseCategory::Salary, ExpenseCategory::Rent];
        $bopRows = $expenses
            ->reject(fn ($expense) => in_array($expense->category, $fixedCategories, true))
            ->groupBy(fn ($expense) => $expense->category?->label() ?? 'Lainnya')
            ->map(fn ($rows, $label) => ['label' => (string) $label, 'total' => round((float) $rows->sum('amount'), 2), 'count' => $rows->count()])
            ->sortByDesc('total')
            ->values()
            ->all();
        $bop = round(array_sum(array_column($bopRows, 'total')), 2);
        $skippedFixed = round((float) $expenses->filter(fn ($expense) => in_array($expense->category, $fixedCategories, true))->sum('amount'), 2);
        $salary = $this->fixedSalary($outletId, $range);
        $rent = round((float) setting('rent_yearly', self::RENT_YEARLY) / 12, 2);
        $operational = round($bop + $salary + $rent, 2);
        $netProfit = round($grossProfit - $operational, 2);

        $previous = null;
        if ($withPrevious) {
            $prevMonth = Carbon::createFromFormat('!Y-m', $range['month'])->subMonth()->format('Y-m');
            $previous = $this->incomeStatement($outletId, $prevMonth, false);
        }

        return [
            'label' => $range['label'],
            'month' => $range['month'],
            'from' => $range['from'],
            'to' => $range['to'],
            'drinks' => round($drinks, 2),
            'food_sales' => (float) $food['sales'],
            'service' => $service,
            'other' => abs($other) < 1 ? 0.0 : $other,
            'gross' => $gross,
            'discount' => $discount,
            'setoran' => $setoran,
            'commission' => (float) $food['commission'],
            'omzet' => $omzet,
            'categories' => $categories,
            'hpp' => $hpp,
            'gross_profit' => $grossProfit,
            'gross_margin' => $omzet > 0 ? round($grossProfit / $omzet * 100, 1) : 0.0,
            'bop_rows' => $bopRows,
            'bop' => $bop,
            'skipped_fixed' => $skippedFixed,
            'salary' => $salary,
            'rent' => $rent,
            'operational' => $operational,
            'net' => $netProfit,
            'net_margin' => $omzet > 0 ? round($netProfit / $omzet * 100, 1) : 0.0,
            'status' => $netProfit > 0 ? 'Laba' : ($netProfit < 0 ? 'Rugi' : 'Impas'),
            'orders' => $orderCount,
            'aov' => $orderCount > 0 ? round($gross / $orderCount, 2) : 0.0,
            'cups' => $cups,
            'previous' => $previous,
        ];
    }

    /**
     * Omzet rata-rata per hari. Filter harian memakai awal bulan s.d. tanggal itu, supaya ada pembanding.
     */
    protected function dailyAverage(?int $outletId, array $range, float $sales, float $drinks): array
    {
        $avgRange = $range;
        if ($range['period'] === 'day') {
            $avgRange = [
                'period' => 'range',
                'from' => Carbon::parse($range['to'])->startOfMonth()->toDateString(),
                'to' => $range['to'],
            ];
            $sales = $this->cafeSales($outletId, $avgRange);
            [$drinks] = $this->drinkSales(['outlet_id' => $outletId, 'from' => $avgRange['from'], 'to' => $avgRange['to']]);
        }
        $days = $this->days($avgRange);

        return [
            'avg_days' => $days,
            'avg_from' => $avgRange['from'],
            'avg_to' => $avgRange['to'],
            'avg_sales' => round($sales / $days, 2),
            'avg_drinks' => round($drinks / $days, 2),
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

    /** Saldo rekening saat ini, sama dengan halaman Keuangan → Rekening. */
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
            'arrival_hours' => $this->arrivalHours($outletId, $range),
        ];
    }

    /**
     * Rata-rata order lunas per jam (WIB) per hari pada periode, plus jam paling ramai dan sepi.
     *
     * @return array{rows: list<array{hour: int, label: string, avg: float}>, days: int, peak: ?array, quiet: ?array}
     */
    protected function arrivalHours(?int $outletId, array $range): array
    {
        $counts = array_fill(0, 24, 0);
        foreach ((clone $this->validOrders($outletId, $range))->pluck('created_at') as $at) {
            $counts[(int) Carbon::parse($at)->timezone('Asia/Jakarta')->format('G')]++;
        }

        $days = $this->days($range);
        $open = array_keys(array_filter($counts, fn ($count) => round($count / $days, 1) > 0));
        // Tampilkan jam buka (09-22) dan jam lain yang rutin ada order, bukan order nyasar tengah malam.
        $first = min([9, ...$open]);
        $last = max([21, ...$open]);

        $rows = [];
        for ($hour = $first; $hour <= $last; $hour++) {
            $rows[] = [
                'hour' => $hour,
                'label' => sprintf('%02d.00', $hour),
                'avg' => round($counts[$hour] / $days, 1),
            ];
        }

        $busy = collect($rows)->filter(fn ($row) => $row['avg'] > 0);
        $window = collect($rows)->filter(fn ($row) => $row['hour'] >= 9 && $row['hour'] <= 21);

        return [
            'rows' => $rows,
            'days' => $days,
            'peak' => $busy->sortByDesc('avg')->first(),
            'quiet' => $busy->isEmpty() ? null : $window->sortBy('avg')->first(),
        ];
    }

    protected function days(array $range): int
    {
        return max(1, (int) Carbon::parse($range['from'])->startOfDay()->diffInDays(Carbon::parse($range['to'])->startOfDay()) + 1);
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
