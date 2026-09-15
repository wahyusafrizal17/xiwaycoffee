<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'name', 'code', 'type', 'scope', 'value', 'minimum_transaction', 'maximum_discount',
    'start_date', 'end_date', 'start_time', 'end_time', 'is_active',
])]
class Discount extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'minimum_transaction' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DiscountItem::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            DiscountType::Percentage => 'Persentase',
            DiscountType::Nominal => 'Nominal',
            default => '—',
        };
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            'order' => 'Pesanan',
            'item' => 'Produk',
            'category' => 'Kategori',
            default => $this->scope ?: '—',
        };
    }

    public function valueLabel(): string
    {
        return $this->type === DiscountType::Percentage
            ? rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',').'%'
            : money($this->value);
    }

    public function periodLabel(): string
    {
        if (! $this->start_date && ! $this->end_date) {
            return 'Tanpa periode';
        }

        return ($this->start_date?->format('d/m/Y') ?? '—').' – '.($this->end_date?->format('d/m/Y') ?? '—');
    }

    public function timeLabel(): string
    {
        $start = $this->clockLabel($this->start_time);
        $end = $this->clockLabel($this->end_time);

        if (! $start && ! $end) {
            return 'Sepanjang hari';
        }

        return ($start ?? '—').' – '.($end ?? '—');
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Nonaktif';
        }

        $today = now()->toDateString();

        if ($this->start_date && $today < $this->start_date->toDateString()) {
            return 'Terjadwal';
        }

        if ($this->end_date && $today > $this->end_date->toDateString()) {
            return 'Berakhir';
        }

        return 'Aktif';
    }

    public function statusColor(): string
    {
        return match ($this->statusLabel()) {
            'Aktif' => 'green',
            'Terjadwal' => 'orange',
            'Berakhir' => 'red',
            default => 'gray',
        };
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['outlets', 'items.product', 'items.category']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code ?? '',
            'type' => $this->type instanceof DiscountType ? $this->type->value : (string) $this->type,
            'scope' => $this->scope,
            'value' => (float) $this->value,
            'minimum_transaction' => (float) $this->minimum_transaction ?: '',
            'maximum_discount' => $this->maximum_discount !== null ? (float) $this->maximum_discount : '',
            'start_date' => $this->start_date?->format('Y-m-d') ?? '',
            'end_date' => $this->end_date?->format('Y-m-d') ?? '',
            'start_time' => $this->clockLabel($this->start_time) ?? '',
            'end_time' => $this->clockLabel($this->end_time) ?? '',
            'outlet_ids' => $this->outlets->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'product_ids' => $this->items->pluck('product_id')->filter()->map(fn ($id) => (string) $id)->values()->all(),
            'category_ids' => $this->items->pluck('category_id')->filter()->map(fn ($id) => (string) $id)->values()->all(),
            'type_label' => $this->typeLabel(),
            'scope_label' => $this->scopeLabel(),
            'value_label' => $this->valueLabel(),
            'minimum_label' => (float) $this->minimum_transaction > 0 ? money($this->minimum_transaction) : 'Tidak ada',
            'maximum_label' => $this->maximum_discount !== null ? money($this->maximum_discount) : 'Tidak dibatasi',
            'period_label' => $this->periodLabel(),
            'time_label' => $this->timeLabel(),
            'status_label' => $this->statusLabel(),
            'is_active' => $this->is_active,
            'outlets_label' => $this->outlets->pluck('name')->filter()->join(', ') ?: 'Semua outlet',
            'products_label' => $this->items->pluck('product.name')->filter()->join(', ') ?: '—',
            'categories_label' => $this->items->pluck('category.name')->filter()->join(', ') ?: '—',
            'toggle_url' => route('marketing.discounts.toggle', $this),
            'update_url' => route('marketing.discounts.update', $this),
            'delete_url' => route('marketing.discounts.destroy', $this),
        ];
    }

    public function isCurrentlyActive(?int $outletId = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->toDateString();
        $time = now()->format('H:i:s');

        if ($this->start_date && $today < $this->start_date->toDateString()) {
            return false;
        }
        if ($this->end_date && $today > $this->end_date->toDateString()) {
            return false;
        }
        if ($this->start_time && $time < $this->start_time) {
            return false;
        }
        if ($this->end_time && $time > $this->end_time) {
            return false;
        }

        if ($outletId && $this->outlets()->exists()) {
            return $this->outlets()->where('outlets.id', $outletId)->exists();
        }

        return true;
    }

    private function clockLabel(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        return substr((string) $time, 0, 5);
    }
}
