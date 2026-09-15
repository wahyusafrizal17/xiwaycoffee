<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['outlet_id', 'code', 'name', 'capacity', 'status', 'pos_x', 'pos_y', 'shape', 'zone', 'is_active'])]
class DiningTable extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected $table = 'tables';

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'table_id');
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(TableSession::class, 'table_id')->whereNull('closed_at')->latestOfMany();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(TableReservation::class, 'table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function currentOrder(): ?Order
    {
        return $this->orders()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest('id')
            ->first();
    }
}
