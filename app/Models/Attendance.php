<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id', 'outlet_id', 'work_date', 'clock_in_at', 'clock_out_at',
    'clock_in_lat', 'clock_in_lng', 'clock_in_distance_m', 'selfie_path', 'is_late',
])]
class Attendance extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'clock_in_lat' => 'decimal:7',
            'clock_in_lng' => 'decimal:7',
            'is_late' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function selfieUrl(): ?string
    {
        return $this->selfie_path ? asset('storage/'.$this->selfie_path) : null;
    }
}
