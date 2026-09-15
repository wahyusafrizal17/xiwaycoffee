<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['outlet_id', 'key', 'value', 'group'])]
class Setting extends Model
{
    use AppliesFillableAttribute;

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
