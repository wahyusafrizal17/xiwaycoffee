<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['discount_id', 'product_id', 'category_id'])]
class DiscountItem extends Model
{
    use AppliesFillableAttribute;

    public $timestamps = false;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
