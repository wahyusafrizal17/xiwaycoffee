<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['name', 'slug', 'station', 'color', 'sort_order', 'is_active'])]
class Category extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
        $this->loadCount('products');

        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'slug' => $this->slug ?? '',
            'station' => $this->station ?? '',
            'station_label' => $this->stationLabel(),
            'color' => $this->color ?? '',
            'sort_order' => (int) ($this->sort_order ?? 0),
            'is_active' => (bool) $this->is_active,
            'status_label' => $this->statusLabel(),
            'products_count' => (int) ($this->products_count ?? 0),
            'update_url' => route('categories.update', $this),
            'delete_url' => route('categories.destroy', $this),
        ];
    }
}
