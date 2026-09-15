<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'reference_number', 'outlet_id', 'product_id', 'unit_id', 'user_id',
    'type', 'quantity', 'before_stock', 'after_stock', 'reason', 'batch_id',
    'reference_type', 'reference_id',
])]
class InventoryMovement extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'before_stock' => 'decimal:3',
            'after_stock' => 'decimal:3',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['product.unit', 'unit', 'user', 'outlet']);

        $unit = $this->product?->unit?->code ?? $this->unit?->code ?? '';
        $qty = (float) $this->quantity;
        $before = (float) $this->before_stock;
        $after = (float) $this->after_stock;

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number ?: '—',
            'product_name' => $this->product?->name ?? '—',
            'sku' => $this->product?->sku ?? '—',
            'type_label' => $this->type?->label() ?? '—',
            'type_color' => $this->type?->color() ?? 'gray',
            'quantity' => $qty,
            'quantity_label' => $this->formatSignedQty($qty, $unit),
            'quantity_tone' => $qty > 0 ? 'plus' : ($qty < 0 ? 'minus' : 'zero'),
            'before_label' => $this->formatQty($before, $unit),
            'after_label' => $this->formatQty($after, $unit),
            'reason' => $this->reason ?: '—',
            'user_name' => $this->user?->name ?? '—',
            'outlet_name' => $this->outlet?->name ?? '—',
            'created_label' => $this->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    protected function formatQty(float $value, string $unit): string
    {
        $label = qty($value);

        return $unit !== '' ? $label.' '.$unit : $label;
    }

    protected function formatSignedQty(float $value, string $unit): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.$this->formatQty($value, $unit);
    }
}
