<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['code', 'name', 'city', 'address', 'phone', 'is_central_kitchen', 'is_active', 'opens_at', 'closes_at'])]
class Outlet extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_central_kitchen' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'outlet_users')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Aktif' : 'Nonaktif';
    }

    public function hoursLabel(): string
    {
        $open = $this->opens_at ? substr((string) $this->opens_at, 0, 5) : null;
        $close = $this->closes_at ? substr((string) $this->closes_at, 0, 5) : null;

        if ($open && $close) {
            return "{$open} – {$close}";
        }

        return $open ?: ($close ?: '—');
    }

    public function toModalArray(): array
    {
        $this->loadCount('users');

        return [
            'id' => $this->id,
            'code' => $this->code ?? '',
            'name' => $this->name ?? '',
            'city' => $this->city ?? '',
            'address' => $this->address ?? '',
            'phone' => $this->phone ?? '',
            'opens_at' => $this->opens_at ? substr((string) $this->opens_at, 0, 5) : '',
            'closes_at' => $this->closes_at ? substr((string) $this->closes_at, 0, 5) : '',
            'hours_label' => $this->hoursLabel(),
            'is_central_kitchen' => (bool) $this->is_central_kitchen,
            'is_active' => (bool) $this->is_active,
            'status_label' => $this->statusLabel(),
            'users_count' => (int) ($this->users_count ?? 0),
            'update_url' => route('outlets.update', $this),
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'outlet_product')
            ->withPivot(['price', 'is_available']);
    }
}
