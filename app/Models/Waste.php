<?php

namespace App\Models;

use App\Enums\WasteReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['number', 'outlet_id', 'product_id', 'unit_id', 'user_id', 'quantity', 'reason', 'notes', 'status'])]
class Waste extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'reason' => WasteReason::class,
            'quantity' => 'decimal:3',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['product.unit', 'unit', 'user', 'outlet']);

        $unit = $this->product?->unit?->code ?? $this->unit?->code ?? '';
        $qty = (float) $this->quantity;

        return [
            'id' => $this->id,
            'number' => $this->number ?: '—',
            'product_id' => $this->product_id ? (string) $this->product_id : '',
            'product_name' => $this->product?->name ?? '—',
            'sku' => $this->product?->sku ?? '—',
            'quantity' => $qty,
            'quantity_label' => $this->formatQty($qty, $unit),
            'reason_label' => $this->reason?->label() ?? '—',
            'reason_color' => $this->reason?->color() ?? 'gray',
            'notes' => $this->notes ?: '—',
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
}
