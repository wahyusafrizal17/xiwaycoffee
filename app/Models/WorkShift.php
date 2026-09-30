<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'work_date', 'starts_at', 'ends_at'])]
class WorkShift extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function label(string $position = ''): string
    {
        if (! $this->starts_at || ! $this->ends_at) {
            return 'OFF';
        }

        $text = str_replace(':', '.', $this->starts_at).'-'.str_replace(':', '.', $this->ends_at);
        if (str_starts_with($position, 'Barista') && $this->starts_at === '09:00' && $this->ends_at === '22:00') {
            $text .= '*';
        }

        return $text;
    }
}
