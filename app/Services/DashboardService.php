<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\MonthlyTarget;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function metrics(?int $outletId, array $range): array
    {
        $filters = [
            'outlet_id' => $outletId,
            'from' => $range['from'],
            'to' => $range['to'],
        ];

        $summary = $this->shares->summarize($filters);
        $food = $this->reports->foodSetoran($filters);
        $drinks = $this->drinkSales($filters);

        $orderCount = (int) Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereDate('created_at', '>=', $range['from'])
            ->whereDate('created_at', '<=', $range['to'])
            ->count();

        $pendingKitchen = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereIn('status', [OrderStatus::New->value, OrderStatus::Processing->value, OrderStatus::Preparing->value])
            ->count();

        $pendingPickup = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('order_type', 'pickup')
            ->whereIn('status', [OrderStatus::New->value, OrderStatus::Processing->value, OrderStatus::Ready->value])
            ->count();

        $tables = DiningTable::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('is_active', true);

        $target = $this->drinkTarget($outletId, $range);

        return [
            'period' => $range['period'],
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
            'gross' => (float) $summary['gross'],
            'drinks' => $drinks,
            'food_sales' => (float) $food['sales'],
            'food_cafe' => (float) $food['commission'],
            'food_setoran' => (float) $food['setoran'],
            'sales' => (float) $summary['sales'],
            'bop' => (float) $summary['bop'],
            'net' => (float) $summary['remainder'],
            'shares' => $summary['shares'],
            'target' => $target,
            'orders' => $orderCount,
            'aov' => $orderCount > 0 ? (float) $summary['gross'] / $orderCount : 0,
            'pending_kitchen' => $pendingKitchen,
            'pending_pickup' => $pendingPickup,
            'occupied_tables' => (clone $tables)->where('status', TableStatus::Occupied->value)->count(),
            'available_tables' => (clone $tables)->where('status', TableStatus::Available->value)->count(),
        ];
    }

    public function charts(?int $outletId, array $range): array
    {
        $trendFrom = now()->subDays(13)->startOfDay();

        $salesTrend = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('created_at', '>=', $trendFrom)
            ->selectRaw('DATE(created_at) as d, SUM(grand_total) as total, COUNT(*) as qty')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereDate('orders.created_at', '>=', $range['from'])
            ->whereDate('orders.created_at', '<=', $range['to'])
            ->selectRaw('order_items.name, SUM(order_items.quantity) as qty, SUM(order_items.total) as total')
            ->groupBy('order_items.name')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        $byCategory = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereDate('orders.created_at', '>=', $range['from'])
            ->whereDate('orders.created_at', '<=', $range['to'])
            ->selectRaw('categories.name, SUM(order_items.total) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $payments = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->whereDate('payments.created_at', '>=', $range['from'])
            ->whereDate('payments.created_at', '<=', $range['to'])
            ->selectRaw('payments.method, SUM(payments.amount) as total')
            ->groupBy('payments.method')
            ->get();

        return [
            'sales_trend' => $salesTrend,
            'top_products' => $topProducts,
            'by_category' => $byCategory,
            'payments' => $payments,
        ];
    }

    protected function drinkSales(array $filters): float
    {
        return (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereIn('categories.name', drink_category_names())
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->sum('order_items.total');
    }

    /**
     * Target omzet minuman vs realisasi bulan (sama konsep BOP / investors).
     *
     * @return array{amount: float, actual: float, progress: float, label: string, year: int, month: int}
     */
    protected function drinkTarget(?int $outletId, array $range): array
    {
        $anchor = Carbon::parse($range['month'] ?? $range['to'] ?? now()->toDateString());
        $year = (int) $anchor->year;
        $month = (int) $anchor->month;

        $stored = MonthlyTarget::query()
            ->where('outlet_id', $outletId)
            ->where('year', $year)
            ->where('month', $month)
            ->value('amount');

        $amount = (float) ($stored ?? monthly_bop());
        $from = $anchor->copy()->startOfMonth()->toDateString();
        $to = $anchor->copy()->endOfMonth();
        if ($to->isFuture()) {
            $to = Carbon::now()->startOfDay();
        }

        $actual = $this->drinkSales([
            'outlet_id' => $outletId,
            'from' => $from,
            'to' => $to->toDateString(),
        ]);
        $progress = $amount > 0 ? min(100, round($actual / $amount * 100, 1)) : 0.0;

        return [
            'amount' => $amount,
            'actual' => $actual,
            'progress' => $progress,
            'label' => $anchor->copy()->startOfMonth()->locale('id')->translatedFormat('F Y'),
            'year' => $year,
            'month' => $month,
        ];
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
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
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