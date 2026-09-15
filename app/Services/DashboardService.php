<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStatus;
use App\Enums\StockMovementType;
use App\Enums\TableStatus;
use App\Enums\TransferStatus;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionOrder;
use App\Models\StockTransfer;
use App\Models\Waste;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function metrics(?int $outletId = null, ?Carbon $day = null): array
    {
        $day = $day ?? now();
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $orders = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', OrderStatus::Cancelled->value);

        $completed = (clone $orders)->where('payment_status', PaymentStatus::Paid->value);
        $sales = (float) (clone $completed)->sum('grand_total');
        $orderCount = (clone $completed)->count();

        $tables = DiningTable::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('is_active', true);

        $pendingKitchen = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereIn('status', [OrderStatus::New->value, OrderStatus::Processing->value, OrderStatus::Preparing->value])
            ->count();

        $pendingPickup = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('order_type', 'pickup')
            ->whereIn('status', [OrderStatus::New->value, OrderStatus::Processing->value, OrderStatus::Ready->value])
            ->count();

        $todayWaste = (float) Waste::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereBetween('created_at', [$from, $to])
            ->sum('quantity');

        $pendingTransfers = StockTransfer::query()
            ->when($outletId, fn ($q) => $q->where(function ($q) use ($outletId) {
                $q->where('source_outlet_id', $outletId)->orWhere('destination_outlet_id', $outletId);
            }))
            ->whereIn('status', [
                TransferStatus::Requested->value,
                TransferStatus::Approved->value,
                TransferStatus::Shipped->value,
            ])
            ->count();

        $productions = ProductionOrder::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereDate('production_date', $day->toDateString());

        $todayProductionQty = (float) (clone $productions)->where('status', ProductionStatus::Completed->value)->sum('quantity_produced');
        $pendingProduction = (clone $productions)->whereIn('status', [
            ProductionStatus::Draft->value,
            ProductionStatus::Planned->value,
            ProductionStatus::InProduction->value,
        ])->count();

        $avgYield = (float) (clone $productions)->where('status', ProductionStatus::Completed->value)->avg('yield_percentage');

        $lowStock = $this->inventory->lowStock($outletId);
        $outOfStock = $this->inventory->outOfStock($outletId);

        return [
            'sales' => $sales,
            'orders' => $orderCount,
            'aov' => $orderCount > 0 ? $sales / $orderCount : 0,
            'customers' => Customer::query()->count(),
            'active_tables' => (clone $tables)->where('status', '!=', TableStatus::Available->value)->count(),
            'available_tables' => (clone $tables)->where('status', TableStatus::Available->value)->count(),
            'occupied_tables' => (clone $tables)->where('status', TableStatus::Occupied->value)->count(),
            'total_tables' => (clone $tables)->count(),
            'pending_kitchen' => $pendingKitchen,
            'pending_pickup' => $pendingPickup,
            'low_stock' => $lowStock->count(),
            'out_of_stock' => $outOfStock->count(),
            'today_waste' => $todayWaste,
            'pending_transfers' => $pendingTransfers,
            'today_production' => $todayProductionQty,
            'pending_production' => $pendingProduction,
            'semi_finished' => $this->semiFinishedCount($outletId),
            'production_yield' => $avgYield,
            'low_stock_items' => $lowStock->take(6),
        ];
    }

    public function charts(?int $outletId = null): array
    {
        $from = now()->subDays(13)->startOfDay();

        $salesTrend = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, SUM(grand_total) as total, COUNT(*) as qty')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $byOutlet = Order::query()
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('outlets.name, SUM(orders.grand_total) as total')
            ->groupBy('outlets.name')
            ->get();

        $byCategory = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('categories.name, SUM(order_items.total) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('order_items.name, SUM(order_items.quantity) as qty, SUM(order_items.total) as total')
            ->groupBy('order_items.name')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        $channels = Order::query()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('created_at', '>=', $from)
            ->selectRaw('channel, COUNT(*) as qty')
            ->groupBy('channel')
            ->get();

        $payments = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->when($outletId, fn ($q) => $q->where('orders.outlet_id', $outletId))
            ->where('payments.created_at', '>=', $from)
            ->selectRaw('payments.method, SUM(payments.amount) as total')
            ->groupBy('payments.method')
            ->get();

        return [
            'sales_trend' => $salesTrend,
            'by_outlet' => $byOutlet,
            'by_category' => $byCategory,
            'top_products' => $topProducts,
            'channels' => $channels,
            'payments' => $payments,
        ];
    }

    protected function semiFinishedCount(?int $outletId): int
    {
        return Inventory::query()
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->when($outletId, fn ($q) => $q->where('inventories.outlet_id', $outletId))
            ->where('products.type', 'semi_finished')
            ->where('inventories.quantity', '>', 0)
            ->count();
    }
}
