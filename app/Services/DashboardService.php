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
        $foodCut = $this->foodHalfPriceCut($filters);
        $drinkCut = $this->drinkPriceCut($filters);
        $cafeRate = (float) config('pos.food_cafe_percent', 10) / 100;
        $food['sales'] = round((float) $food['sales'] - $foodCut, 2);
        $food['commission'] = round((float) $food['commission'] - ($foodCut * $cafeRate), 2);
        $food['setoran'] = round((float) $food['setoran'] - ($foodCut * (1 - $cafeRate)), 2);
        $drinks = round($drinks - $drinkCut, 2);
        $summary['gross'] = round((float) $summary['gross'] - $foodCut - $drinkCut, 2);
        $summary['sales'] = round((float) $summary['sales'] - ($foodCut * $cafeRate) - $drinkCut, 2);
        $summary['remainder'] = round((float) $summary['sales'] - (float) $summary['bop'], 2);
        $summary['shares'] = $this->shares->split($summary['remainder'], $this->shares->partners());

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
        $paymentTotals = $this->paymentTotals($outletId, $range);

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
            'cash' => $paymentTotals['cash'],
            'qris' => $paymentTotals['qris'],
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

        return [
            'sales_trend' => $salesTrend,
            'top_products' => $topProducts,
        ];
    }

    /**
     * @return array{cash: float, qris: float}
     */
    protected function paymentTotals(?int $outletId, array $range): array
    {
        $rows = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->whereDate('payments.created_at', '>=', $range['from'])
            ->whereDate('payments.created_at', '<=', $range['to'])
            ->whereIn('payments.method', ['cash', 'qris'])
            ->selectRaw('payments.method, SUM(payments.amount) as total')
            ->groupBy('payments.method')
            ->pluck('total', 'method');

        return [
            'cash' => (float) ($rows['cash'] ?? 0),
            'qris' => (float) ($rows['qris'] ?? 0),
        ];
    }

    /**
     * 20 Sep 2026: semua makanan mitra dihitung setengah harga.
     * ponytail: satu tanggal tetap, pindah ke diskon order kalau promo jadi rutin.
     */
    protected function foodHalfPriceCut(array $filters): float
    {
        $day = '2026-09-20';
        if ((filled($filters['from'] ?? null) && $filters['from'] > $day) || (filled($filters['to'] ?? null) && $filters['to'] < $day)) {
            return 0.0;
        }

        $sales = (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('order_items.consignment_commission', '>', 0)
            ->whereDate('orders.created_at', $day)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->sum('order_items.total');

        return round($sales / 2, 2);
    }

    /**
     * Minuman: 20 Sep 2026 harga 0, 21–27 Sep 2026 setengah harga.
     * ponytail: tanggal tetap, pindah ke diskon order kalau promo jadi rutin.
     */
    protected function drinkPriceCut(array $filters): float
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        if ((filled($from) && $from > '2026-09-27') || (filled($to) && $to < '2026-09-20')) {
            return 0.0;
        }

        $cut = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereIn('categories.name', drink_category_names())
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($from), fn ($q) => $q->whereDate('orders.created_at', '>=', $from))
            ->when(filled($to), fn ($q) => $q->whereDate('orders.created_at', '<=', $to))
            ->selectRaw("SUM(CASE WHEN DATE(orders.created_at) = '2026-09-20' THEN order_items.total WHEN DATE(orders.created_at) BETWEEN '2026-09-21' AND '2026-09-27' THEN order_items.total * 0.5 ELSE 0 END) as cut")
            ->value('cut');

        return round((float) $cut, 2);
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

        $monthFilters = [
            'outlet_id' => $outletId,
            'from' => $from,
            'to' => $to->toDateString(),
        ];
        $actual = round($this->drinkSales($monthFilters) - $this->drinkPriceCut($monthFilters), 2);
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
