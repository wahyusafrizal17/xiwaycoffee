<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'backup_employee_id', 'work_date', 'reason', 'status', 'decided_at'])]
class LeaveRequest extends Model
{
    use AppliesFillableAttribute;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'backup_employee_id');
    }
}
