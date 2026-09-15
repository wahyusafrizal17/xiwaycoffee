<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'product_id', 'version', 'yield_percentage', 'waste_percentage',
    'active_from', 'active_until', 'is_active', 'notes',
])]
class Bom extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'yield_percentage' => 'decimal:2',
            'waste_percentage' => 'decimal:2',
            'active_from' => 'date',
            'active_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function recipeCost(): float
    {
        $this->loadMissing(['items.component.unit', 'items.unit']);

        $total = 0.0;
        foreach ($this->items as $item) {
            $qty = $item->requiredQuantity(1);
            $from = $item->unit ?? $item->component?->unit;
            $to = $item->component?->unit;
            if ($from && $to) {
                $qty = $from->convertTo($to, $qty);
            }
            $total += $qty * (float) ($item->component?->cost ?? 0);
        }

        $yield = max((float) $this->yield_percentage, 1) / 100;

        return round($total / $yield, 2);
    }

    public function applyToProduct(): void
    {
        $this->product()->update(['cost' => $this->recipeCost()]);
        $this->unsetRelation('product');
    }

    public static function syncMenusUsingComponent(Product $component): void
    {
        $bomIds = BomItem::query()->where('component_id', $component->id)->pluck('bom_id');

        static::query()
            ->whereIn('id', $bomIds)
            ->where('is_active', true)
            ->get()
            ->each(fn (Bom $bom) => $bom->applyToProduct());
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['product', 'items.component.unit', 'items.unit']);

        $items = $this->items
            ->values()
            ->map(function (BomItem $item) {
                $unit = $item->unit?->code ?? $item->component?->unit?->code ?? '';
                $qty = (float) $item->quantity;

                return [
                    'id' => $item->id,
                    'component_id' => (string) $item->component_id,
                    'unit_id' => (string) ($item->unit_id ?? ''),
                    'quantity' => (string) $qty,
                    'waste_percentage' => (string) (float) ($item->waste_percentage ?? 0),
                    'yield_percentage' => (string) (float) ($item->yield_percentage ?? 100),
                    'component_name' => $item->component?->name ?? '—',
                    'sku' => $item->component?->sku ?? '—',
                    'unit' => $unit !== '' ? $unit : '—',
                    'quantity_label' => $this->formatQty($qty, $unit),
                    'waste_label' => number_format((float) ($item->waste_percentage ?? 0), 1).'%',
                    'yield_label' => number_format((float) ($item->yield_percentage ?? 100), 1).'%',
                ];
            })
            ->all();

        $exploded = collect(app(\App\Services\ProductionService::class)->explode($this, 1))
            ->map(fn (array $row) => [
                'level' => (int) ($row['level'] ?? 1),
                'name' => $row['name'] ?? '—',
                'unit' => $row['unit'] ?? '—',
                'quantity_label' => $this->formatQty((float) ($row['quantity'] ?? 0), (string) ($row['unit'] ?? '')),
            ])
            ->values()
            ->all();

        $recipeCost = $this->recipeCost();

        return [
            'id' => $this->id,
            'product_id' => (string) $this->product_id,
            'product_name' => $this->product?->name ?? '—',
            'sku' => $this->product?->sku ?? '—',
            'version' => $this->version,
            'yield_percentage' => (string) (float) $this->yield_percentage,
            'yield_label' => number_format((float) $this->yield_percentage, 1).'%',
            'waste_percentage' => (string) (float) ($this->waste_percentage ?? 0),
            'waste_label' => number_format((float) ($this->waste_percentage ?? 0), 1).'%',
            'active_from' => $this->active_from?->format('Y-m-d') ?? '',
            'active_until' => $this->active_until?->format('Y-m-d') ?? '',
            'period_label' => $this->periodLabel(),
            'is_active' => (bool) $this->is_active,
            'status_label' => $this->is_active ? 'Aktif' : 'Nonaktif',
            'notes' => $this->notes ?: '',
            'notes_label' => $this->notes ?: '—',
            'items_count' => count($items),
            'items' => $items,
            'recipe_cost' => $recipeCost,
            'recipe_cost_label' => money($recipeCost),
            'exploded' => $exploded,
            'update_url' => route('boms.update', $this),
        ];
    }

    public function periodLabel(): string
    {
        if (! $this->active_from && ! $this->active_until) {
            return 'Tanpa periode';
        }

        return ($this->active_from?->format('d/m/Y') ?? '—').' – '.($this->active_until?->format('d/m/Y') ?? '—');
    }

    protected function formatQty(float $value, string $unit): string
    {
        $label = number_format($value, 4);

        return $unit !== '' && $unit !== '—' ? $label.' '.$unit : $label;
    }
}
