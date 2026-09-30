<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\DiningTable;
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
        $food = $this->excludeCancelledFood($this->reports->foodSetoran($filters), $filters);
        $drinks = $this->drinkSales($filters);
        $gross = round((float) (clone $this->validOrders($outletId, $range))->sum('grand_total'), 2);
        $setoran = (float) $food['setoran'];
        $sales = round($gross - $setoran, 2);
        $bop = (float) $summary['bop'];
        $net = round($gross - $bop, 2);
        $shareBase = round($sales - $bop, 2);

        $orderCount = (int) (clone $this->validOrders($outletId, $range))->count();

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

        $paymentTotals = $this->paymentTotals($outletId, $range);
        $paymentGap = round($paymentTotals['total'] - $gross, 2);

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
            'sales' => $sales,
            'bop' => $bop,
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

        return [
            'sales_trend' => $salesTrend,
            'top_products' => $topProducts,
        ];
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
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.status', OrderStatus::Cancelled->value)
            ->where('order_items.consignment_commission', '>', 0)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->sum('order_items.total');

        if ($cancelled <= 0) {
            return $food;
        }

        $rate = (float) config('pos.food_cafe_percent', 10) / 100;
        $food['sales'] = round((float) $food['sales'] - $cancelled, 2);
        $food['commission'] = round((float) $food['commission'] - ($cancelled * $rate), 2);
        $food['setoran'] = round($food['sales'] - $food['commission'], 2);

        return $food;
    }

    protected function drinkSales(array $filters): float
    {
        return (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereIn('categories.name', drink_category_names())
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->sum('order_items.total');
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
