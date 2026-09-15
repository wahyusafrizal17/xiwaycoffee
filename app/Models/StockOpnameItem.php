<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['stock_opname_id', 'product_id', 'system_qty', 'physical_qty', 'difference', 'reason'])]
class StockOpnameItem extends Model
{
    use AppliesFillableAttribute;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'physical_qty' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
