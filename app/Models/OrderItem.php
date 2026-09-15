<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'order_id', 'product_id', 'product_variant_id', 'bundle_id', 'batch_id', 'name',
    'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'total', 'consignment_commission',
    'notes', 'station', 'status', 'confirmed_by', 'confirmed_at',
])]
class OrderItem extends Model
{
    use AppliesFillableAttribute;

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'consignment_commission' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->product?->imageUrl() ?? asset('images/menu/placeholder.svg'));
    }
}
