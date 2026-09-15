<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['number', 'outlet_id', 'category_id', 'created_by', 'approved_by', 'status', 'notes', 'finalized_at'])]
class StockOpname extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'finalized' || filled($this->finalized_at);
    }

    public function statusLabel(): string
    {
        return $this->isLocked() ? 'Selesai' : 'Draft';
    }

    public function statusTone(): string
    {
        return $this->isLocked() ? 'green' : 'orange';
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['outlet', 'creator', 'approver', 'category', 'items.product.unit']);

        $items = $this->items
            ->sortBy(fn (StockOpnameItem $item) => $item->product?->name ?? '')
            ->values()
            ->map(function (StockOpnameItem $item) {
                $unit = $item->product?->unit?->code ?? '';
                $system = (float) $item->system_qty;
                $physical = (float) $item->physical_qty;
                $difference = (float) $item->difference;

                return [
                    'id' => $item->id,
                    'product_name' => $item->product?->name ?? '—',
                    'sku' => $item->product?->sku ?? '—',
                    'unit' => $unit,
                    'system_qty' => $system,
                    'system_label' => $this->formatQty($system, $unit),
                    'physical_qty' => (string) (float) $item->physical_qty,
                    'physical_label' => $this->formatQty($physical, $unit),
                    'difference' => $difference,
                    'difference_label' => $this->formatQty($difference, $unit, true),
                    'difference_tone' => $difference > 0 ? 'plus' : ($difference < 0 ? 'minus' : 'zero'),
                    'reason' => $item->reason ?? '',
                ];
            })
            ->all();

        $varianceCount = collect($items)->where('difference_tone', '!=', 'zero')->count();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'locked' => $this->isLocked(),
            'category_label' => $this->category?->name ?? 'Semua produk',
            'notes' => $this->notes ?: '—',
            'outlet_name' => $this->outlet?->name ?? '—',
            'creator_name' => $this->creator?->name ?? '—',
            'approver_name' => $this->approver?->name ?? '—',
            'created_label' => $this->created_at?->format('d/m/Y H:i') ?? '—',
            'finalized_label' => $this->finalized_at?->format('d/m/Y H:i') ?? '—',
            'items_count' => count($items),
            'variance_count' => $varianceCount,
            'items' => $items,
            'update_url' => route('opnames.update', $this),
            'finalize_url' => route('opnames.finalize', $this),
        ];
    }

    protected function formatQty(float $value, string $unit, bool $signed = false): string
    {
        $label = qty($value);
        if ($signed && $value > 0) {
            $label = '+'.$label;
        }

        return $unit !== '' ? $label.' '.$unit : $label;
    }
}
