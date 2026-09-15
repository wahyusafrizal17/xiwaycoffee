<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['name', 'label', 'module'])]
class Permission extends Model
{
    use AppliesFillableAttribute;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
