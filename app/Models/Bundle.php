<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['name', 'sku', 'product_id', 'price', 'start_date', 'end_date', 'start_time', 'end_time', 'is_active'])]
class Bundle extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class);
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

    public function quantityLabel(mixed $quantity): string
    {
        $value = (float) $quantity;
        $formatted = number_format($value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['outlets', 'items.product']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku ?: '—',
            'price_label' => money($this->price),
            'items_count' => $this->items->count(),
            'items_count_label' => $this->items->count().' item',
            'period_label' => $this->periodLabel(),
            'time_label' => $this->timeLabel(),
            'status_label' => $this->statusLabel(),
            'is_active' => $this->is_active,
            'outlets_label' => $this->outlets->pluck('name')->filter()->join(', ') ?: 'Semua outlet',
            'items' => $this->items->map(fn (BundleItem $item) => [
                'name' => $item->product?->name ?? '—',
                'quantity' => $this->quantityLabel($item->quantity),
            ])->values()->all(),
            'toggle_url' => route('marketing.bundles.toggle', $this),
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
