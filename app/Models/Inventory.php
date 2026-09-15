<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['outlet_id', 'product_id', 'quantity', 'reserved_quantity'])]
class Inventory extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
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

    public function available(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }

    public function stockStatus(): string
    {
        $quantity = (float) $this->quantity;
        $reorder = (float) ($this->product?->reorder_level ?? 0);

        if ($quantity <= 0) {
            return 'out';
        }

        if ($reorder > 0 && $quantity <= $reorder) {
            return 'low';
        }

        return 'ok';
    }

    public function statusLabel(): string
    {
        return match ($this->stockStatus()) {
            'out' => 'Habis',
            'low' => 'Menipis',
            default => 'Aman',
        };
    }

    public function statusTone(): string
    {
        return match ($this->stockStatus()) {
            'out' => 'red',
            'low' => 'orange',
            default => 'green',
        };
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['product.unit', 'product.category']);

        $unit = $this->product?->unit?->code ?? '';
        $qty = (float) $this->quantity;
        $reserved = (float) $this->reserved_quantity;
        $available = $this->available();
        $reorder = (float) ($this->product?->reorder_level ?? 0);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id ? (string) $this->product_id : '',
            'product_name' => $this->product?->name ?? '—',
            'sku' => $this->product?->sku ?? '',
            'category_label' => $this->product?->category?->name ?? '—',
            'unit_code' => $unit,
            'quantity' => $qty,
            'quantity_label' => $this->formatQty($qty, $unit),
            'reserved' => $reserved,
            'reserved_label' => $this->formatQty($reserved, $unit),
            'available' => $available,
            'available_label' => $this->formatQty($available, $unit),
            'reorder_level' => $reorder,
            'reorder_label' => $this->formatQty($reorder, $unit),
            'status' => $this->stockStatus(),
            'status_label' => $this->statusLabel(),
        ];
    }

    protected function formatQty(float $value, string $unit): string
    {
        $label = qty($value);

        return $unit !== '' ? $label.' '.$unit : $label;
    }
}
