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

    public function metrics(?int $outletId = null, string $period = 'today'): array
    {
        $range = $this->periodRange($period);
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

        return [
            'period' => $period,
            'from' => $range['from'],
            'to' => $range['to'],
            'gross' => (float) $summary['gross'],
            'drinks' => $drinks,
            'food_sales' => (float) $food['sales'],
            'food_cafe' => (float) $food['commission'],
            'food_setoran' => (float) $food['setoran'],
            'sales' => (float) $summary['sales'],
            'bop' => (float) $summary['bop'],
            'net' => (float) $summary['remainder'],
            'shares' => $summary['shares'],
            'orders' => $orderCount,
            'aov' => $orderCount > 0 ? (float) $summary['gross'] / $orderCount : 0,
            'pending_kitchen' => $pendingKitchen,
            'pending_pickup' => $pendingPickup,
            'occupied_tables' => (clone $tables)->where('status', TableStatus::Occupied->value)->count(),
            'available_tables' => (clone $tables)->where('status', TableStatus::Available->value)->count(),
        ];
    }

    public function charts(?int $outletId = null, string $period = 'today'): array
    {
        $range = $this->periodRange($period);
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
     * @return array{from: string, to: string}
     */
    protected function periodRange(string $period): array
    {
        $today = Carbon::now()->toDateString();

        return match ($period) {
            'month' => [
                'from' => Carbon::now()->startOfMonth()->toDateString(),
                'to' => $today,
            ],
            default => [
                'from' => $today,
                'to' => $today,
            ],
        };
    }
}
