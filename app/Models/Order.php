<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'order_number', 'outlet_id', 'user_id', 'customer_id', 'table_id', 'discount_id',
    'channel', 'order_type', 'status', 'payment_status', 'subtotal', 'discount_amount',
    'tax_amount', 'tax_rate', 'service_charge', 'points_redeemed', 'points_value',
    'grand_total', 'guest_count', 'notes', 'estimated_ready_at', 'held_at',
    'completed_at', 'cancelled_at', 'cancel_reason',
    'kitchen_printed_at', 'bar_printed_at',
])]
class Order extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'channel' => OrderChannel::class,
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'points_value' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'estimated_ready_at' => 'datetime',
            'held_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'kitchen_printed_at' => 'datetime',
            'bar_printed_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function paidTotal(): float
    {
        return (float) $this->payments()->where('status', 'paid')->sum('amount');
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->grand_total - $this->paidTotal());
    }

    public function allItemsReady(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->isNotEmpty()
            && $items->every(fn ($item) => in_array($item->status, ['ready', 'served'], true));
    }
}
