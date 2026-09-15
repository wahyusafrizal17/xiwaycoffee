<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable([
    'user_id', 'action', 'module', 'auditable_type', 'auditable_id',
    'old_values', 'new_values', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function auditableLabel(): string
    {
        if (! $this->auditable_type) {
            return '—';
        }

        return class_basename($this->auditable_type).' #'.$this->auditable_id;
    }

    public function toModalArray(): array
    {
        $this->loadMissing('user');

        return [
            'id' => $this->id,
            'action' => $this->action ?? '',
            'module' => $this->module ?? '',
            'user_label' => $this->user?->name ?? 'Sistem',
            'auditable_label' => $this->auditableLabel(),
            'ip_address' => $this->ip_address ?? '—',
            'user_agent' => $this->user_agent ?? '—',
            'created_at' => $this->created_at?->format('d/m/Y H:i') ?? '—',
            'old_json' => json_encode($this->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}',
            'new_json' => json_encode($this->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
    }
}
