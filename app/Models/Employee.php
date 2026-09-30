<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'outlet_id', 'position', 'primary_position', 'salary', 'base_salary', 'job_allowance', 'transport_allowance', 'cleanliness_allowance', 'sales_bonus', 'deduction', 'is_active'])]
class Employee extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'base_salary' => 'decimal:2',
            'job_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'cleanliness_allowance' => 'decimal:2',
            'sales_bonus' => 'decimal:2',
            'deduction' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Prorata applies to pokok and tunjangan. Bonus and potongan stay as entered.
     *
     * @return array{lines: array<string, int>, bonus: int, monthly: int, earnings: int, deduction: int, received: int}
     */
    public function pay(int $worked): array
    {
        $parts = [
            'Gaji Pokok' => (int) round((float) $this->base_salary),
            'Tunjangan Job' => (int) round((float) $this->job_allowance),
            'Tunjangan Transportasi' => (int) round((float) $this->transport_allowance),
            'Tunjangan Kebersihan' => (int) round((float) $this->cleanliness_allowance),
        ];
        if (array_sum($parts) === 0) {
            $parts['Gaji Pokok'] = (int) round((float) $this->salary);
        }

        $monthly = array_sum($parts);
        $lines = [];
        foreach ($parts as $label => $amount) {
            $lines[$label] = (int) round($amount * $worked / 26);
        }
        $prorated = (int) round($monthly * $worked / 26);
        $bump = array_key_first($lines);
        foreach ($lines as $label => $amount) {
            if ($amount >= $lines[$bump]) {
                $bump = $label;
            }
        }
        $lines[$bump] += $prorated - array_sum($lines);

        $bonus = (int) round((float) $this->sales_bonus);
        $deduction = (int) round((float) $this->deduction);

        return [
            'lines' => $lines,
            'bonus' => $bonus,
            'monthly' => $monthly,
            'earnings' => $prorated + $bonus,
            'deduction' => $deduction,
            'received' => $prorated + $bonus - $deduction,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkShift::class);
    }
}
