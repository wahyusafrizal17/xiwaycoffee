<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['name', 'description', 'points_required', 'value', 'is_active'])]
class Reward extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function toModalArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description ?: '—',
            'points_label' => number_format((int) $this->points_required).' poin',
            'value_label' => $this->value !== null && (float) $this->value > 0 ? money($this->value) : '—',
            'status_label' => $this->is_active ? 'Aktif' : 'Nonaktif',
            'is_active' => $this->is_active,
            'toggle_url' => route('loyalty.rewards.toggle', $this),
        ];
    }
}
