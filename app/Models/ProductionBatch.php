<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'batch_number', 'production_order_id', 'product_id', 'outlet_id',
    'quantity', 'yield_quantity', 'remaining_quantity', 'produced_at', 'expires_at', 'destination_outlet_id',
])]
class ProductionBatch extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'yield_quantity' => 'decimal:3',
            'remaining_quantity' => 'decimal:3',
            'produced_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function destinationOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'destination_outlet_id');
    }

    public function salesItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'batch_id');
    }

    public function toModalArray(): array
    {
        $this->loadMissing([
            'product.unit',
            'outlet',
            'destinationOutlet',
            'productionOrder.items.product.unit',
            'salesItems.order',
            'salesItems.product',
        ]);

        $unit = $this->product?->unit?->code ?? '';
        $qty = (float) $this->quantity;
        $yield = (float) ($this->yield_quantity ?? 0);
        $remaining = (float) ($this->remaining_quantity ?? 0);
        $today = now()->toDateString();
        $expired = $this->expires_at && $this->expires_at->toDateString() < $today;
        $status = $remaining <= 0 ? 'depleted' : ($expired ? 'expired' : 'available');

        $materials = ($this->productionOrder?->items ?? collect())
            ->values()
            ->map(function ($item) {
                $itemUnit = $item->product?->unit?->code ?? '';
                $used = (float) ($item->quantity_used ?? $item->quantity_required ?? 0);

                return [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? '—',
                    'sku' => $item->product?->sku ?? '—',
                    'unit' => $itemUnit !== '' ? $itemUnit : '—',
                    'quantity_label' => $this->formatQty($used, $itemUnit),
                ];
            })
            ->all();

        $sales = $this->salesItems
            ->values()
            ->map(function (OrderItem $item) use ($unit) {
                return [
                    'id' => $item->id,
                    'order_number' => $item->order?->order_number ?? '—',
                    'order_url' => $item->order ? route('orders.show', $item->order) : '',
                    'name' => $item->name ?: ($item->product?->name ?? '—'),
                    'quantity_label' => $this->formatQty((float) $item->quantity, $unit),
                ];
            })
            ->all();

        return [
            'id' => $this->id,
            'batch_number' => $this->batch_number,
            'product_name' => $this->product?->name ?? '—',
            'sku' => $this->product?->sku ?? '—',
            'outlet_name' => $this->outlet?->name ?? '—',
            'destination_name' => $this->destinationOutlet?->name ?? $this->outlet?->name ?? '—',
            'quantity_label' => $this->formatQty($qty, $unit),
            'yield_label' => $this->formatQty($yield, $unit),
            'remaining_label' => $this->formatQty($remaining, $unit),
            'produced_label' => $this->produced_at?->format('d/m/Y') ?? '—',
            'expires_label' => $this->expires_at?->format('d/m/Y') ?? '—',
            'status' => $status,
            'status_label' => match ($status) {
                'depleted' => 'Habis',
                'expired' => 'Kadaluarsa',
                default => 'Tersedia',
            },
            'status_color' => match ($status) {
                'depleted' => 'gray',
                'expired' => 'red',
                default => 'green',
            },
            'production_number' => $this->productionOrder?->number ?? '—',
            'production_url' => $this->productionOrder
                ? route('production.index', ['modal' => 'view', 'production' => $this->productionOrder->id])
                : '',
            'materials' => $materials,
            'sales' => $sales,
            'sales_count' => count($sales),
        ];
    }

    protected function formatQty(float $value, string $unit): string
    {
        $label = number_format($value, 2);

        return $unit !== '' ? $label.' '.$unit : $label;
    }
}
