<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\TableStatus;
use App\Models\Bundle;
use App\Models\DiningTable;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected NumberGenerator $numbers,
        protected InventoryService $inventory,
        protected DiscountService $discounts,
        protected LoyaltyService $loyalty,
        protected AuditService $audit,
        protected TableService $tables,
    ) {}

    public function createDraft(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::query()->create([
                'order_number' => $this->numbers->next(config('pos.order_prefix', 'ORD'), 'orders', 'order_number'),
                'outlet_id' => $data['outlet_id'],
                'user_id' => Auth::id(),
                'customer_id' => $data['customer_id'] ?? null,
                'table_id' => $data['table_id'] ?? null,
                'channel' => $data['channel'] ?? $this->channelFromType($data['order_type'] ?? null),
                'order_type' => $data['order_type'] ?? OrderType::DineIn,
                'status' => OrderStatus::Draft,
                'payment_status' => PaymentStatus::Unpaid,
                'tax_rate' => tax_rate(),
                'guest_count' => $data['guest_count'] ?? 1,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recordHistory($order, null, OrderStatus::Draft->value, 'Order dibuat');

            if (! empty($data['table_id'])) {
                $this->tables->occupy((int) $data['table_id'], $order);
            }

            return $order->fresh(['items']);
        });
    }

    public function addItem(Order $order, array $payload): OrderItem
    {
        $this->assertMutable($order);

        return DB::transaction(function () use ($order, $payload) {
            $product = Product::query()->findOrFail($payload['product_id']);
            $variant = ! empty($payload['product_variant_id'])
                ? ProductVariant::query()->find($payload['product_variant_id'])
                : null;
            $bundle = ! empty($payload['bundle_id'])
                ? Bundle::query()->find($payload['bundle_id'])
                : null;

            $qty = (float) ($payload['quantity'] ?? 1);
            $price = $bundle
                ? (float) $bundle->price
                : (float) $product->price + (float) ($variant?->price_adjustment ?? 0);

            $item = $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'bundle_id' => $bundle?->id,
                'name' => $variant ? "{$product->name} ({$variant->name})" : ($bundle?->name ?? $product->name),
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => $price * $qty,
                'consignment_commission' => (float) ($product->consignment_commission ?? 0),
                'notes' => $payload['notes'] ?? null,
                'station' => $product->station ?? $product->category?->station,
                'status' => 'new',
            ]);

            $this->recalculate($order->fresh(['items', 'discount']));

            return $item->fresh();
        });
    }

    public function updateItem(OrderItem $item, array $payload): Order
    {
        $order = $item->order;
        $this->assertMutable($order);

        $item->fill([
            'quantity' => $payload['quantity'] ?? $item->quantity,
            'notes' => $payload['notes'] ?? $item->notes,
        ]);
        $item->total = ((float) $item->unit_price * (float) $item->quantity) - (float) $item->discount_amount;
        $item->save();

        return $this->recalculate($order->fresh(['items', 'discount']));
    }

    public function removeItem(OrderItem $item): Order
    {
        $order = $item->order;
        $this->assertMutable($order);
        $item->delete();

        return $this->recalculate($order->fresh(['items', 'discount']));
    }

    public function applyDiscount(Order $order, ?int $discountId, float $manual = 0): Order
    {
        $this->assertMutable($order);
        $order->discount_id = $discountId;
        $order->discount_amount = $discountId ? $order->discount_amount : max(0, $manual);
        $order->save();

        return $this->recalculate($order->fresh(['items', 'discount.items']));
    }

    public function applyPoints(Order $order, int $points): Order
    {
        $this->assertMutable($order);

        if (! $order->customer_id) {
            throw ValidationException::withMessages(['customer_id' => 'Pilih pelanggan untuk menukar poin.']);
        }

        $order->loadMissing('customer');

        if ($points > 0 && (int) $order->customer->points < $points) {
            throw ValidationException::withMessages(['points' => 'Poin pelanggan tidak mencukupi.']);
        }

        $value = $this->loyalty->redeemValue($points);
        $order->points_redeemed = $points;
        $order->points_value = $value;
        $order->save();

        return $this->recalculate($order->fresh(['items', 'discount']));
    }

    public function recalculate(Order $order): Order
    {
        $order->loadMissing(['items', 'discount.items']);
        $subtotal = (float) $order->items->sum(fn ($item) => (float) $item->unit_price * (float) $item->quantity);
        $discount = (float) $order->discount_amount;

        if ($order->discount_id && $order->discount) {
            $discount = $this->discounts->calculate(
                $order->discount,
                $subtotal,
                $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all(),
            );
        }

        $taxable = max(0, $subtotal - $discount);
        $tax = round($taxable * ((float) $order->tax_rate / 100), 2);
        $pointsValue = (float) $order->points_value;
        $grand = max(0, $taxable + $tax + (float) $order->service_charge - $pointsValue);

        $maxPrep = $order->items->loadMissing('product')->max(fn ($item) => $item->product?->prep_minutes ?? 10);

        $order->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'grand_total' => $grand,
            'estimated_ready_at' => now()->addMinutes((int) $maxPrep),
        ]);

        return $order->fresh(['items.product', 'customer', 'table', 'payments', 'discount']);
    }

    public function hold(Order $order): Order
    {
        $this->assertMutable($order);

        return $this->transition($order, OrderStatus::Held, 'Order ditahan');
    }

    public function submit(Order $order, array $data = []): Order
    {
        $this->assertMutable($order);

        if ($order->items()->count() === 0) {
            throw ValidationException::withMessages(['items' => 'Order belum memiliki item.']);
        }

        if (! empty($data['order_type'])) {
            $order->order_type = $data['order_type'];
            $order->channel = $this->channelFromType($data['order_type']);
        }

        if (! empty($data['table_id'])) {
            $order = $this->assignTable($order, (int) $data['table_id']);
        }

        $order->save();
        $order = $order->fresh(['items', 'discount']);

        $this->recalculate($order);

        $order = $this->transition($order, OrderStatus::New, 'Order dikirim ke dapur');

        $orderId = $order->id;
        dispatch(function () use ($orderId) {
            $fresh = Order::query()->find($orderId);
            if ($fresh) {
                app(PrinterRoutingService::class)->dispatchNetworkTickets($fresh);
            }
        })->afterResponse();

        return $order;
    }

    public function transition(Order $order, OrderStatus $status, ?string $notes = null): Order
    {
        $from = $order->status?->value;

        $order->status = $status;
        if ($status === OrderStatus::Held) {
            $order->held_at = now();
        }
        if ($status === OrderStatus::Cancelled) {
            $order->cancelled_at = now();
            $order->cancel_reason = $notes;
        }
        $order->save();

        $this->recordHistory($order, $from, $status->value, $notes);

        return $order->fresh(['items', 'histories', 'payments']);
    }

    public function checkout(Order $order, array $payment): Order
    {
        return DB::transaction(function () use ($order, $payment) {
            $order = $order->fresh(['items.product', 'discount', 'customer']);
            $this->assertMutable($order);

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Tidak bisa checkout order kosong.']);
            }

            $this->recalculate($order);

            if (in_array($order->status, [OrderStatus::Draft, OrderStatus::Held], true)) {
                $order = $this->submit($order, $payment);
            }

            $amount = (float) ($payment['amount'] ?? $order->grand_total);
            $tendered = (float) ($payment['tendered'] ?? $amount);
            $method = PaymentMethod::from($payment['method'] ?? 'cash');

            Payment::query()->create([
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'method' => $method,
                'amount' => min($amount, (float) $order->grand_total),
                'tendered' => $tendered,
                'change_amount' => max(0, $tendered - (float) $order->grand_total),
                'reference' => $payment['reference'] ?? null,
                'status' => 'paid',
            ]);

            $paid = $order->paidTotal();
            $order->payment_status = $paid + 0.009 >= (float) $order->grand_total
                ? PaymentStatus::Paid
                : ($paid > 0 ? PaymentStatus::Partial : PaymentStatus::Unpaid);
            $order->save();

            return $order->fresh(['items', 'payments', 'customer', 'table']);
        });
    }

    public function complete(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $order->fresh(['items.product', 'customer']);

            foreach ($order->items as $item) {
                if ($item->product?->is_stockable) {
                    $batchId = $this->inventory->consumeBatches(
                        $order->outlet_id,
                        $item->product_id,
                        (float) $item->quantity,
                    );

                    if ($batchId) {
                        $item->update(['batch_id' => $batchId]);
                    }

                    $this->inventory->decrease(
                        $order->outlet_id,
                        $item->product_id,
                        (float) $item->quantity,
                        StockMovementType::Sale,
                        'Penjualan '.$order->order_number,
                        $order,
                        $order->order_number,
                        $batchId,
                    );
                }

                if ($item->bundle_id) {
                    $item->loadMissing('bundle.items');
                    foreach ($item->bundle?->items ?? [] as $bundleItem) {
                        if ($bundleItem->product?->is_stockable) {
                            $this->inventory->decrease(
                                $order->outlet_id,
                                $bundleItem->product_id,
                                (float) $bundleItem->quantity * (float) $item->quantity,
                                StockMovementType::Sale,
                                'Bundle '.$order->order_number,
                                $order,
                                $order->order_number,
                            );
                        }
                    }
                }
            }

            if ($order->points_redeemed > 0 && $order->customer) {
                $this->loyalty->redeem($order->customer, (int) $order->points_redeemed, $order, 'Tukar poin '.$order->order_number);
            }

            if ($order->customer) {
                $order->customer->increment('total_transaction', (float) $order->grand_total);
                $order->customer->update(['last_transaction_at' => now()]);
                $this->loyalty->earnFromOrder($order);
                $order->customer->refreshMembership();
            }

            $order->completed_at = now();
            $order->save();
            $this->transition($order, OrderStatus::Completed, 'Order selesai');

            if ($order->table_id) {
                $this->tables->release((int) $order->table_id);
            }

            $this->audit->log('completed', 'orders', $order, null, ['total' => $order->grand_total]);

            return $order->fresh(['items', 'payments', 'customer']);
        });
    }

    public function cancel(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            if ($order->status === OrderStatus::Completed) {
                throw ValidationException::withMessages(['status' => 'Order selesai tidak dapat dibatalkan.']);
            }

            $this->transition($order, OrderStatus::Cancelled, $reason);

            if ($order->table_id) {
                $this->tables->release((int) $order->table_id);
            }

            $this->audit->log('cancelled', 'orders', $order, ['status' => $order->status?->value], ['reason' => $reason]);

            return $order->fresh();
        });
    }

    public function assignTable(Order $order, int $tableId): Order
    {
        $this->assertMutable($order);

        if ($order->table_id && $order->table_id !== $tableId) {
            $this->tables->release((int) $order->table_id);
        }

        $this->tables->occupy($tableId, $order);
        $order->update(['table_id' => $tableId, 'order_type' => OrderType::DineIn]);

        return $order->fresh(['table']);
    }

    public function assignCustomer(Order $order, ?int $customerId): Order
    {
        $this->assertMutable($order);
        $order->update(['customer_id' => $customerId]);

        return $order->fresh(['items.product', 'customer', 'table', 'discount']);
    }

    public function updateItemStatus(OrderItem $item, string $status): Order
    {
        if (! in_array($status, ['new', 'preparing', 'ready', 'served'], true)) {
            throw ValidationException::withMessages(['status' => 'Status item tidak valid.']);
        }

        $item->update([
            'status' => $status,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        $this->audit->log('checked', 'orders', $item->order, ['item' => $item->id], [
            'item' => $item->name,
            'status' => $status,
        ]);

        return $this->syncOrderFromItems($item->order->fresh(['items']));
    }

    public function syncOrderFromItems(Order $order): Order
    {
        if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Draft, OrderStatus::Held], true)) {
            return $order;
        }

        $statuses = $order->items->pluck('status');

        if ($statuses->isEmpty()) {
            return $order;
        }

        if ($statuses->every(fn ($status) => $status === 'served')) {
            $order = $order->fresh(['items.product', 'customer', 'payments']);
            if ($order->payment_status === PaymentStatus::Paid) {
                return $this->complete($order);
            }

            return $this->transition($order, OrderStatus::Ready, 'Semua item dikonfirmasi checker');
        }

        if ($statuses->contains('preparing') || $statuses->contains('ready') || $statuses->contains('served')) {
            if (in_array($order->status, [OrderStatus::New], true)) {
                return $this->transition($order, OrderStatus::Preparing, 'Checker memproses item');
            }
        }

        return $order->fresh(['items.product', 'customer', 'table']);
    }

    protected function channelFromType(mixed $type): OrderChannel
    {
        $value = $type instanceof OrderType ? $type->value : (string) $type;

        return match ($value) {
            'pickup' => OrderChannel::Pickup,
            'online' => OrderChannel::Online,
            default => OrderChannel::Pos,
        };
    }

    protected function recordHistory(Order $order, ?string $from, string $to, ?string $notes): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
        ]);
    }

    protected function assertMutable(Order $order): void
    {
        if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => 'Order ini sudah ditutup dan tidak dapat diubah.',
            ]);
        }
    }
}
