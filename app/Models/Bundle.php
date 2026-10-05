<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'campaign', 'sku', 'product_id', 'price', 'start_date', 'end_date', 'start_time', 'end_time', 'weekdays_only', 'requirement', 'is_active'])]
class Bundle extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'weekdays_only' => 'boolean',
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

        $today = now('Asia/Jakarta')->toDateString();

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
            'campaign' => $this->campaign,
            'sku' => $this->sku ?: '—',
            'price_label' => money($this->price),
            'normal_label' => $this->normalLabel(),
            'items_count' => $this->items->count(),
            'items_count_label' => $this->items->count().' item',
            'period_label' => $this->periodLabel(),
            'time_label' => $this->timeLabel(),
            'weekdays_label' => $this->weekdays_only ? 'Hari kerja' : 'Setiap hari',
            'requirement' => $this->requirement,
            'status_label' => $this->statusLabel(),
            'is_active' => $this->is_active,
            'outlets_label' => $this->outlets->pluck('name')->filter()->join(', ') ?: 'Semua outlet',
            'items' => $this->items->map(fn (BundleItem $item) => [
                'name' => $item->product?->name ?? '—',
                'quantity' => $this->quantityLabel($item->quantity),
                'choice_group' => $item->choice_group,
            ])->values()->all(),
            'toggle_url' => route('marketing.bundles.toggle', $this),
        ];
    }

    public function isCurrentlyActive(?int $outletId = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now('Asia/Jakarta');
        $today = $now->toDateString();
        $time = $now->format('H:i:s');

        if ($this->start_date && $today < $this->start_date->toDateString()) {
            return false;
        }
        if ($this->end_date && $today > $this->end_date->toDateString()) {
            return false;
        }
        $start = $this->clockSeconds($this->start_time);
        $end = $this->clockSeconds($this->end_time);
        if ($start && $time < $start) {
            return false;
        }
        if ($end && $time > $end) {
            return false;
        }
        if ($this->weekdays_only && $now->isWeekend()) {
            return false;
        }

        if ($outletId && $this->outlets()->exists()) {
            return $this->outlets()->where('outlets.id', $outletId)->exists();
        }

        return true;
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function normalBounds(): array
    {
        $this->loadMissing('items.product');
        $fixed = 0.0;
        $groups = [];

        foreach ($this->items as $item) {
            $line = (float) ($item->product?->price ?? 0) * (float) $item->quantity;
            if (! filled($item->choice_group)) {
                $fixed += $line;

                continue;
            }
            $groups[$item->choice_group][] = $line;
        }

        $min = $fixed;
        $max = $fixed;
        foreach ($groups as $prices) {
            $min += min($prices);
            $max += max($prices);
        }

        return [round($min, 2), round($max, 2)];
    }

    public function normalLabel(): string
    {
        [$min, $max] = $this->normalBounds();

        return $max > $min ? money($min).' – '.money($max) : money($min);
    }

    public function toPosArray(): array
    {
        $this->loadMissing('items.product');
        $fixed = $this->items->first(fn (BundleItem $item) => ! filled($item->choice_group));
        $groups = $this->items
            ->filter(fn (BundleItem $item) => filled($item->choice_group))
            ->groupBy('choice_group')
            ->map(fn ($items, $name) => [
                'name' => (string) $name,
                'options' => $items->map(fn (BundleItem $item) => [
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name ?? '—',
                ])->values()->all(),
            ])->values()->all();
        $anchor = $fixed?->product_id
            ?? ($groups[0]['options'][0]['product_id'] ?? $this->items->first()?->product_id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'campaign' => $this->campaign,
            'price' => (float) $this->price,
            'product_id' => $anchor,
            'requirement' => $this->requirement,
            'groups' => $groups,
        ];
    }

    private function clockSeconds(mixed $time): ?string
    {
        $label = $this->clockLabel($time);

        return $label === null ? null : $label.':00';
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
