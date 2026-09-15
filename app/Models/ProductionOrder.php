<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'number', 'outlet_id', 'product_id', 'bom_id', 'user_id', 'quantity_planned',
    'quantity_produced', 'yield_percentage', 'production_date', 'batch_number',
    'status', 'notes', 'started_at', 'completed_at',
])]
class ProductionOrder extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProductionStatus::class,
            'quantity_planned' => 'decimal:3',
            'quantity_produced' => 'decimal:3',
            'yield_percentage' => 'decimal:2',
            'production_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function batch(): HasOne
    {
        return $this->hasOne(ProductionBatch::class);
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['outlet', 'product.unit', 'bom.product', 'user', 'batch', 'items.product.unit', 'items.unit']);

        $unit = $this->product?->unit?->code ?? '';
        $planned = (float) $this->quantity_planned;
        $produced = (float) ($this->quantity_produced ?? 0);
        $status = $this->status ?? ProductionStatus::Draft;

        $items = $this->items
            ->sortBy(fn (ProductionOrderItem $item) => $item->product?->name ?? '')
            ->values()
            ->map(function (ProductionOrderItem $item) {
                $itemUnit = $item->product?->unit?->code ?? $item->unit?->code ?? '';
                $required = (float) $item->quantity_required;
                $used = (float) ($item->quantity_used ?? 0);

                return [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? '—',
                    'sku' => $item->product?->sku ?? '—',
                    'unit' => $itemUnit,
                    'required_label' => $this->formatQty($required, $itemUnit),
                    'used_label' => $this->formatQty($used, $itemUnit),
                ];
            })
            ->all();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'product_name' => $this->product?->name ?? '—',
            'sku' => $this->product?->sku ?? '—',
            'outlet_name' => $this->outlet?->name ?? '—',
            'bom_label' => $this->bom ? (($this->bom->product?->name ?? 'BOM').' · v'.$this->bom->version) : 'Otomatis',
            'notes' => $this->notes ?: '—',
            'user_name' => $this->user?->name ?? '—',
            'planned' => $planned,
            'planned_label' => $this->formatQty($planned, $unit),
            'produced_label' => $this->formatQty($produced, $unit),
            'produced_input' => (string) ($produced > 0 ? $produced : $planned),
            'yield_label' => number_format((float) ($this->yield_percentage ?? 0), 1).'%',
            'batch_number' => $this->batch?->batch_number ?? $this->batch_number ?: '—',
            'batch_url' => $this->batch ? route('batches.show', $this->batch) : '',
            'production_date_label' => $this->production_date?->format('d/m/Y') ?? '—',
            'started_label' => $this->started_at?->format('d/m/Y H:i') ?? '—',
            'completed_label' => $this->completed_at?->format('d/m/Y H:i') ?? '—',
            'items_count' => count($items),
            'items' => $items,
            'start_url' => route('production.start', $this),
            'complete_url' => route('production.complete', $this),
            'cancel_url' => route('production.cancel', $this),
        ];
    }

    protected function formatQty(float $value, string $unit): string
    {
        $label = number_format($value, 2);

        return $unit !== '' ? $label.' '.$unit : $label;
    }
}
