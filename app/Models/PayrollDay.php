<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'month', 'days'])]
class PayrollDay extends Model
{
    use AppliesFillableAttribute;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
