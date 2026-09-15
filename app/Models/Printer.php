<?php

namespace App\Models;

use App\Enums\PrinterStation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['outlet_id', 'name', 'station', 'ip_address', 'port', 'is_active'])]
class Printer extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'station' => PrinterStation::class,
            'is_active' => 'boolean',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(PrinterRoute::class);
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['routes.category', 'outlet']);

        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'station' => $this->station?->value ?? '',
            'station_label' => $this->station?->label() ?? '—',
            'ip_address' => $this->ip_address ?? '',
            'port' => (int) ($this->port ?? 9100),
            'is_active' => (bool) $this->is_active,
            'status_label' => $this->is_active ? 'Aktif' : 'Nonaktif',
            'categories_label' => $this->routes->pluck('category.name')->filter()->join(', ') ?: 'Belum ada routing',
            'category_ids' => $this->routes->pluck('category_id')->filter()->map(fn ($id) => (string) $id)->values()->all(),
            'outlet_label' => $this->outlet?->name ?? '—',
            'update_url' => route('printers.update', $this),
            'delete_url' => route('printers.destroy', $this),
        ];
    }
}
