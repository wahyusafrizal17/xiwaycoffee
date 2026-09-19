<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'sku', 'name', 'category_id', 'unit_id', 'type', 'bom_level', 'description', 'image',
    'price', 'cost', 'consignment_commission', 'is_sellable', 'is_stockable', 'is_active', 'is_recommended',
    'minimum_stock', 'reorder_level', 'maximum_stock', 'station', 'prep_minutes',
])]
class Product extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'consignment_commission' => 'decimal:2',
            'is_sellable' => 'boolean',
            'is_stockable' => 'boolean',
            'is_active' => 'boolean',
            'is_recommended' => 'boolean',
            'minimum_stock' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    public function activeBom(): ?Bom
    {
        return $this->boms()->where('is_active', true)->latest('id')->first();
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true)->where('is_active', true);
    }

    public function stockFor(?int $outletId): float
    {
        if (! $outletId) {
            return 0;
        }

        return (float) ($this->inventories()->where('outlet_id', $outletId)->value('quantity') ?? 0);
    }

    public function isLowStock(?int $outletId = null): bool
    {
        $outletId = $outletId ?? current_outlet_id();
        $stock = $this->stockFor($outletId);

        return $this->is_stockable && $this->reorder_level > 0 && $stock <= (float) $this->reorder_level;
    }

    public function imageUrl(): string
    {
        if ($this->image) {
            if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
                return $this->image;
            }

            return asset('storage/'.$this->image);
        }

        return asset('images/menu/placeholder.svg');
    }

    public function menuDescription(): string
    {
        $description = trim((string) $this->description);

        if ($description !== '' && strcasecmp($description, $this->name) !== 0) {
            return $description;
        }

        return $this->name;
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Aktif' : 'Nonaktif';
    }

    public function stationLabel(): string
    {
        return match ($this->station) {
            'kitchen' => 'Dapur',
            'bar' => 'Bar',
            'cashier' => 'Kasir',
            default => '—',
        };
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['category', 'unit', 'variants', 'optionGroups.options']);
        $recipe = $this->recipeLines();

        return [
            'id' => $this->id,
            'sku' => $this->sku ?? '',
            'name' => $this->name ?? '',
            'category_id' => $this->category_id ? (string) $this->category_id : '',
            'category_label' => $this->category?->name ?? '—',
            'unit_id' => $this->unit_id ? (string) $this->unit_id : '',
            'unit_label' => $this->unit ? $this->unit->name.' ('.$this->unit->code.')' : '—',
            'type' => $this->type?->value ?? ProductType::Finished->value,
            'type_label' => $this->type?->label() ?? '—',
            'bom_level' => (int) ($this->bom_level ?? 0),
            'description' => $this->description ?? '',
            'image' => $this->image ?? '',
            'image_url' => $this->imageUrl(),
            'price' => (float) $this->price,
            'price_label' => money($this->price),
            'cost' => (float) ($this->cost ?? 0),
            'cost_label' => money($this->cost),
            'consignment_commission' => (float) ($this->consignment_commission ?? 0),
            'recipe' => $recipe,
            'is_sellable' => (bool) $this->is_sellable,
            'is_stockable' => (bool) $this->is_stockable,
            'is_active' => (bool) $this->is_active,
            'is_recommended' => (bool) $this->is_recommended,
            'status_label' => $this->statusLabel(),
            'minimum_stock' => (float) ($this->minimum_stock ?? 0),
            'reorder_level' => (float) ($this->reorder_level ?? 0),
            'maximum_stock' => (float) ($this->maximum_stock ?? 0),
            'station' => $this->station ?? '',
            'station_label' => $this->stationLabel(),
            'prep_minutes' => (int) ($this->prep_minutes ?? 0),
            'variants' => $this->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku ?? '',
                'price_adjustment' => (float) $variant->price_adjustment,
                'price_adjustment_label' => money($variant->price_adjustment),
            ])->values()->all(),
            'option_groups' => $this->optionGroups->map(fn (ProductOptionGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'is_required' => (bool) $group->is_required,
                'min_select' => (int) $group->min_select,
                'max_select' => (int) $group->max_select,
                'options' => $group->options->map(fn (ProductOption $option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price_adjustment' => (float) $option->price_adjustment,
                    'is_active' => (bool) $option->is_active,
                ])->values()->all(),
            ])->values()->all(),
            'update_url' => route('products.update', $this),
            'delete_url' => route('products.destroy', $this),
        ];
    }

    public function recipeLines(): array
    {
        $bom = $this->activeBom();
        if (! $bom) {
            return [];
        }

        $bom->loadMissing(['items.component.unit', 'items.unit']);

        return $bom->items->map(function (BomItem $item) {
            $qty = $item->requiredQuantity(1);
            $from = $item->unit ?? $item->component?->unit;
            $to = $item->component?->unit;
            $costQty = ($from && $to) ? $from->convertTo($to, $qty) : $qty;
            $unit = $from?->code ?? $to?->code ?? '';

            return [
                'name' => $item->component?->name ?? '—',
                'quantity_label' => rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.').($unit !== '' ? ' '.$unit : ''),
                'cost_label' => money($costQty * (float) ($item->component?->cost ?? 0)),
            ];
        })->values()->all();
    }
}
