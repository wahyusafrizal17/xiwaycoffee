<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'outlet_id', 'position', 'primary_position', 'salary', 'base_salary', 'job_allowance', 'transport_allowance', 'cleanliness_allowance', 'sales_bonus', 'deduction', 'is_active', 'contract_signed_at'])]
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
            'contract_signed_at' => 'datetime',
        ];
    }

    public function contractSignature(): ?string
    {
        if (! $this->contract_signed_at) {
            return null;
        }

        return substr(hash_hmac(
            'sha256',
            $this->id.'|'.$this->contract_signed_at->getTimestamp(),
            (string) config('app.key'),
        ), 0, 20);
    }

    /**
     * @return array<string, int>
     */
    public function payParts(): array
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

        return $parts;
    }

    /**
     * Scheduled days with no clock-in after miss-by (shift start + 3 jam). OFF / approved leave (swap to OFF) not counted.
     */
    public function absentCount(iterable $shifts, iterable $attendances): int
    {
        $clocked = [];
        foreach ($attendances as $attendance) {
            if ($attendance->clock_in_at) {
                $clocked[$attendance->work_date->toDateString()] = true;
            }
        }

        $now = now('Asia/Jakarta');
        $count = 0;
        foreach ($shifts as $shift) {
            if (! $shift->starts_at || isset($clocked[$shift->work_date->toDateString()])) {
                continue;
            }
            $missBy = Carbon::parse($shift->work_date->toDateString().' '.$shift->starts_at, 'Asia/Jakarta')->addHours(3);
            if ($now->greaterThanOrEqualTo($missBy)) {
                $count++;
            }
        }

        return $count;
    }

    public function overtimePay(iterable $attendances, iterable $shifts): int
    {
        $present = [];
        foreach ($attendances as $attendance) {
            if ($attendance->clock_in_at) {
                $present[$attendance->work_date->toDateString()] = true;
            }
        }

        $days = 0;
        foreach ($shifts as $shift) {
            if ($shift->work_date->toDateString() < '2026-10-15' || $shift->starts_at !== '09:00' || $shift->ends_at !== '22:00') {
                continue;
            }
            if (isset($present[$shift->work_date->toDateString()])) {
                $days++;
            }
        }

        // Flat sama rata: Rp 65.000 per hari lembur 09.00–22.00.
        return $days * 65_000;
    }

    /**
     * Prorata applies to pokok and tunjangan. Bonus and potongan stay as entered.
     *
     * @return array{lines: array<string, int>, bonus: int, monthly: int, earnings: int, deduction: int, received: int}
     */
    public function pay(int $worked): array
    {
        $parts = $this->payParts();
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
