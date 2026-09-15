<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['printer_id', 'category_id', 'station'])]
class PrinterRoute extends Model
{
    use AppliesFillableAttribute;

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
