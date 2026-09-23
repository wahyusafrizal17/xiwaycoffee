<?php

namespace App\Services;

use App\Enums\BankMovementType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\FoodSettlement;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\OperatingExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Waste;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function sales(array $filters)
    {
        return $this->paidOrders($filters, withColumnFilters: true)
            ->with(['outlet', 'customer', 'user'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function salesStats(array $filters): array
    {
        $agg = $this->paidOrders($filters, withColumnFilters: false)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total), 0) as total')
            ->first();

        $count = (int) ($agg->cnt ?? 0);
        $total = (float) ($agg->total ?? 0);

        return [
            'count' => $count,
            'total' => $total,
            'average' => $count > 0 ? $total / $count : 0,
        ];
    }

    /**
     * Laba rugi sederhana dari data existing (sales, product cost, BOP).
     *
     * @return array{
     *   sales: float,
     *   hpp: float,
     *   hpp_available: bool,
     *   gross: float,
     *   bop: float,
     *   net: float,
     *   gross_margin: float,
     *   net_margin: float,
     *   status: string,
     *   bop_rows: list<array{category: ?ExpenseCategory, label: string, total: float}>,
     *   from: string,
     *   to: string,
     *   label: string
     * }
     */
    public function profitLoss(array $filters): array
    {
        $sales = (float) $this->salesStats($filters)['total'];

        $hpp = (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->selectRaw('COALESCE(SUM(order_items.quantity * COALESCE(products.cost, 0)), 0) as total')
            ->value('total');

        $bopQuery = OperatingExpense::query()
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('spent_on', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('spent_on', '<=', $filters['to']));

        $bop = (float) (clone $bopQuery)->sum('amount');
        $bopRows = (clone $bopQuery)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $category = $row->category instanceof ExpenseCategory
                    ? $row->category
                    : ExpenseCategory::tryFrom((string) $row->category);

                return [
                    'category' => $category,
                    'label' => $category?->label() ?? (string) $row->category,
                    'total' => (float) $row->total,
                ];
            })
            ->all();

        $gross = round($sales - $hpp, 2);
        $net = round($gross - $bop, 2);
        $grossMargin = $sales > 0 ? round($gross / $sales * 100, 1) : 0.0;
        $netMargin = $sales > 0 ? round($net / $sales * 100, 1) : 0.0;
        $status = $net > 0 ? 'Laba' : ($net < 0 ? 'Rugi' : 'Impas');

        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $label = Carbon::parse($from)->locale('id')->translatedFormat('F Y');
        if ($from !== Carbon::parse($from)->startOfMonth()->toDateString()
            || $to !== min(Carbon::parse($from)->endOfMonth()->toDateString(), now()->toDateString())) {
            $label = Carbon::parse($from)->locale('id')->translatedFormat('d M Y')
                .' – '.Carbon::parse($to)->locale('id')->translatedFormat('d M Y');
        }

        return [
            'sales' => $sales,
            'hpp' => $hpp,
            'hpp_available' => true,
            'gross' => $gross,
            'bop' => $bop,
            'net' => $net,
            'gross_margin' => $grossMargin,
            'net_margin' => $netMargin,
            'status' => $status,
            'bop_rows' => $bopRows,
            'from' => $from,
            'to' => $to,
            'label' => $label,
        ];
    }

    public function productSales(array $filters)
    {
        return $this->productSalesBase($filters, withColumnFilters: true)
            ->selectRaw('order_items.product_id, order_items.name, SUM(order_items.quantity) as qty, SUM(order_items.total) as total')
            ->groupBy('order_items.product_id', 'order_items.name')
            ->orderByDesc('qty')
            ->paginate(20)
            ->withQueryString();
    }

    public function productSalesStats(array $filters): array
    {
        $agg = $this->productSalesBase($filters, withColumnFilters: false)
            ->selectRaw('COUNT(DISTINCT order_items.product_id) as products, COALESCE(SUM(order_items.quantity), 0) as qty, COALESCE(SUM(order_items.total), 0) as total')
            ->first();

        return [
            'products' => (int) ($agg->products ?? 0),
            'qty' => (float) ($agg->qty ?? 0),
            'total' => (float) ($agg->total ?? 0),
        ];
    }

    public function inventoryValuation(?int $outletId = null)
    {
        return Inventory::query()
            ->with(['product.unit', 'outlet'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereHas('product', fn ($q) => $q->where('is_stockable', true))
            ->orderBy('product_id')
            ->paginate(30)
            ->withQueryString();
    }

    public function movements(array $filters)
    {
        return InventoryMovement::query()
            ->with(['product', 'outlet', 'user', 'unit'])
            ->when($filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('outlet_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('reference_number', 'like', "%{$s}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%"));
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    public function waste(array $filters)
    {
        return Waste::query()
            ->with(['product', 'outlet', 'user'])
            ->when($filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('outlet_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function transfers(array $filters)
    {
        return StockTransfer::query()
            ->with(['sourceOutlet', 'destinationOutlet', 'items'])
            ->when($filters['outlet_id'] ?? null, fn ($q, $id) => $q->where(function ($q) use ($id) {
                $q->where('source_outlet_id', $id)->orWhere('destination_outlet_id', $id);
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function opnames(array $filters)
    {
        return StockOpname::query()
            ->with(['outlet', 'creator'])
            ->when($filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('outlet_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function production(array $filters)
    {
        return ProductionOrder::query()
            ->with(['product', 'outlet'])
            ->when($filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('outlet_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('production_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('production_date', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function batches(array $filters)
    {
        return ProductionBatch::query()
            ->with(['product', 'outlet', 'productionOrder'])
            ->when($filters['outlet_id'] ?? null, fn ($q, $id) => $q->where('outlet_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function customers(array $filters)
    {
        return Customer::query()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%");
            }))
            ->orderByDesc('total_transaction')
            ->paginate(20)
            ->withQueryString();
    }

    public function categorySales(array $filters)
    {
        return $this->categorySalesBase($filters, withColumnFilters: true)
            ->selectRaw('categories.id as category_id, COALESCE(categories.name, "Tanpa kategori") as name, SUM(order_items.quantity) as qty, SUM(order_items.total) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->paginate(20)
            ->withQueryString();
    }

    public function categorySalesStats(array $filters): array
    {
        $agg = $this->categorySalesBase($filters, withColumnFilters: false)
            ->selectRaw('COUNT(DISTINCT COALESCE(categories.id, -1)) as categories, COALESCE(SUM(order_items.quantity), 0) as qty, COALESCE(SUM(order_items.total), 0) as total')
            ->first();

        return [
            'categories' => (int) ($agg->categories ?? 0),
            'qty' => (float) ($agg->qty ?? 0),
            'total' => (float) ($agg->total ?? 0),
        ];
    }

    public function promoPerformance(array $filters): array
    {
        return [
            'discounts' => $this->promoDiscounts($filters),
            'bundles' => $this->promoBundles($filters),
        ];
    }

    public function promoStats(array $filters): array
    {
        $discounts = Order::query()
            ->join('discounts', 'discounts.id', '=', 'orders.discount_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->selectRaw('COUNT(orders.id) as usage_count, COALESCE(SUM(orders.discount_amount), 0) as discount, COALESCE(SUM(orders.grand_total), 0) as sales')
            ->first();

        $bundles = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('bundles', 'bundles.id', '=', 'order_items.bundle_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereNotNull('order_items.bundle_id')
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as qty, COALESCE(SUM(order_items.total), 0) as sales')
            ->first();

        return [
            'discount_usage' => (int) ($discounts->usage_count ?? 0),
            'discount_total' => (float) ($discounts->discount ?? 0),
            'bundle_sales' => (float) ($bundles->sales ?? 0),
        ];
    }

    public function promoDiscounts(array $filters)
    {
        return $this->promoDiscountsBase($filters, withColumnFilters: true)
            ->selectRaw('discounts.id, discounts.name, discounts.type, COUNT(orders.id) as usage_count, SUM(orders.discount_amount) as discount_total, SUM(orders.grand_total) as sales_total')
            ->groupBy('discounts.id', 'discounts.name', 'discounts.type')
            ->orderByDesc('usage_count')
            ->get();
    }

    public function promoBundles(array $filters)
    {
        return $this->promoBundlesBase($filters, withColumnFilters: true)
            ->selectRaw('bundles.id, bundles.name, SUM(order_items.quantity) as qty, SUM(order_items.total) as sales_total')
            ->groupBy('bundles.id', 'bundles.name')
            ->orderByDesc('qty')
            ->get();
    }

    protected function promoDiscountsBase(array $filters, bool $withColumnFilters = true)
    {
        $name = $filters['discount'] ?? null;

        return Order::query()
            ->join('discounts', 'discounts.id', '=', 'orders.discount_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->when($withColumnFilters && filled($name), fn ($q) => $q->where('discounts.name', 'like', '%'.$name.'%'));
    }

    protected function promoBundlesBase(array $filters, bool $withColumnFilters = true)
    {
        $name = $filters['bundle'] ?? null;

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('bundles', 'bundles.id', '=', 'order_items.bundle_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereNotNull('order_items.bundle_id')
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->when($withColumnFilters && filled($name), fn ($q) => $q->where('bundles.name', 'like', '%'.$name.'%'));
    }

    protected function categorySalesBase(array $filters, bool $withColumnFilters = true)
    {
        $name = $filters['name'] ?? $filters['search'] ?? null;

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->when($withColumnFilters && filled($name), fn ($q) => $q->whereRaw('COALESCE(categories.name, "Tanpa kategori") LIKE ?', ['%'.$name.'%']));
    }

    public function foodSetoran(array $filters): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->where('order_items.consignment_commission', '>', 0)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->selectRaw('order_items.name as name')
            ->selectRaw('SUM(order_items.quantity) as qty')
            ->selectRaw('SUM(order_items.total) as sales')
            ->selectRaw('SUM(order_items.consignment_commission * order_items.quantity) as commission')
            ->groupBy('order_items.name')
            ->orderBy('order_items.name')
            ->get()
            ->map(function ($row) {
                $row->setoran = (float) $row->sales - (float) $row->commission;

                return $row;
            });

        $sales = (float) $rows->sum('sales');
        $commission = (float) $rows->sum('commission');

        return [
            'rows' => $rows,
            'sales' => $sales,
            'commission' => $commission,
            'setoran' => round($sales - $commission, 2),
        ];
    }

    /**
     * @return array{accrued: float, settled: float, outstanding: float, settlements: Collection}
     */
    public function foodSetoranBalance(?int $outletId = null): array
    {
        $accrued = (float) $this->foodSetoran(['outlet_id' => $outletId])['setoran'];
        $settlements = FoodSettlement::query()
            ->with('user')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('settled_on')
            ->orderByDesc('id')
            ->get();
        $settled = (float) $settlements->sum('amount');
        $outstanding = round(max(0, $accrued - $settled), 2);

        return [
            'accrued' => $accrued,
            'settled' => round($settled, 2),
            'outstanding' => $outstanding,
            'settlements' => $settlements,
        ];
    }

    public function settleFoodSetoran(int $outletId, int $userId, string $settledOn, ?string $notes = null): FoodSettlement
    {
        $outstanding = $this->foodSetoranBalance($outletId)['outstanding'];

        if ($outstanding <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Tidak ada setoran yang belum dibayar.',
            ]);
        }

        $settlement = FoodSettlement::query()->create([
            'outlet_id' => $outletId,
            'user_id' => $userId,
            'settled_on' => $settledOn,
            'amount' => $outstanding,
            'notes' => $notes,
        ]);

        app(BankAccountService::class)->debit(
            $outletId,
            $outstanding,
            BankMovementType::FoodSettlement,
            $settlement,
            $notes ?: 'Setoran makanan mitra',
            $settledOn,
            $userId,
        );

        return $settlement;
    }

    protected function productSalesBase(array $filters, bool $withColumnFilters = true)
    {
        $name = $filters['name'] ?? $filters['search'] ?? null;

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('orders.outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('orders.created_at', '<=', $filters['to']))
            ->when($withColumnFilters && filled($name), fn ($q) => $q->where('order_items.name', 'like', '%'.$name.'%'));
    }

    protected function paidOrders(array $filters, bool $withColumnFilters = true)
    {
        $number = $filters['number'] ?? $filters['search'] ?? null;

        return Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->when(filled($filters['outlet_id'] ?? null), fn ($q) => $q->where('outlet_id', $filters['outlet_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($withColumnFilters && filled($number), fn ($q) => $q->where('order_number', 'like', '%'.$number.'%'))
            ->when($withColumnFilters && filled($filters['customer'] ?? null), function ($q) use ($filters) {
                $q->whereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$filters['customer'].'%'));
            })
            ->when($withColumnFilters && filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']));
    }

    public function range(?string $from, ?string $to, ?string $period = null): array
    {
        [$fromDate, $toDate] = match ($period) {
            'today' => [Carbon::now()->toDateString(), Carbon::now()->toDateString()],
            'week' => [Carbon::now()->startOfWeek()->toDateString(), Carbon::now()->toDateString()],
            'month' => [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->toDateString()],
            default => [
                $from ?: Carbon::now()->startOfMonth()->toDateString(),
                $to ?: Carbon::now()->toDateString(),
            ],
        };

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'period' => $period,
        ];
    }
}
