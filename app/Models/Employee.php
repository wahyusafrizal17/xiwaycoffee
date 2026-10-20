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
     * Late cut from 15 Oct 2026. Clock-in after cutoff (shift start − 20 menit) is per second of the shift.
     * No clock-in before miss-by (start + 3 jam, pagi 12:00): late runs from cutoff; after that = tidak masuk, bukan denda telat.
     */
    public function latePenalty(iterable $attendances, iterable $shifts): int
    {
        $monthly = array_sum($this->payParts());
        if ($monthly <= 0) {
            return 0;
        }

        $clocked = [];
        foreach ($attendances as $attendance) {
            if ($attendance->clock_in_at) {
                $clocked[$attendance->work_date->toDateString()] = $attendance;
            }
        }

        $now = now('Asia/Jakarta');
        $today = $now->toDateString();
        $fraction = 0.0;
        foreach ($shifts as $shift) {
            $date = $shift->work_date->toDateString();
            if ($date < '2026-10-15' || ! $shift->starts_at || ! $shift->ends_at) {
                continue;
            }

            $start = Carbon::parse($date.' '.$shift->starts_at, 'Asia/Jakarta');
            $end = Carbon::parse($date.' '.$shift->ends_at, 'Asia/Jakarta');
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $shiftSeconds = $end->getTimestamp() - $start->getTimestamp();
            if ($shiftSeconds <= 0) {
                continue;
            }

            $cutoff = $start->copy()->subMinutes(20);
            $missBy = $start->copy()->addHours(3);

            $attendance = $clocked[$date] ?? null;
            if ($attendance) {
                $late = $attendance->clock_in_at->copy()->timezone('Asia/Jakarta')->getTimestamp() - $cutoff->getTimestamp();
                if ($late > 0) {
                    $fraction += min(1, $late / $shiftSeconds);
                }
                continue;
            }

            // Belum absen: setelah jam 12 (atau hari sudah lewat) = tidak masuk, bukan potongan telat.
            if ($date < $today || $now->greaterThanOrEqualTo($missBy) || $now->lessThan($cutoff)) {
                continue;
            }

            $window = $missBy->getTimestamp() - $cutoff->getTimestamp();
            if ($window > 0) {
                $fraction += ($now->getTimestamp() - $cutoff->getTimestamp()) / $window;
            }
        }

        return (int) round($monthly / 26 * $fraction);
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

    /** 50% of one day rate per absent day from 16 Oct 2026. */
    public function absentPenalty(iterable $shifts, iterable $attendances): int
    {
        $monthly = array_sum($this->payParts());
        if ($monthly <= 0) {
            return 0;
        }

        $clocked = [];
        foreach ($attendances as $attendance) {
            if ($attendance->clock_in_at) {
                $clocked[$attendance->work_date->toDateString()] = true;
            }
        }

        $now = now('Asia/Jakarta');
        $days = 0;
        foreach ($shifts as $shift) {
            $date = $shift->work_date->toDateString();
            if ($date < '2026-10-16' || ! $shift->starts_at || isset($clocked[$date])) {
                continue;
            }
            $missBy = Carbon::parse($date.' '.$shift->starts_at, 'Asia/Jakarta')->addHours(3);
            if ($now->greaterThanOrEqualTo($missBy)) {
                $days++;
            }
        }

        return (int) round($days * $monthly / 26 * 0.5);
    }

    public function reportPenalty(iterable $shifts, iterable $attendances = []): int
    {
        // OFF / izin (starts_at kosong): tidak kena. Ada jadwal (hadir atau tidak masuk tanpa izin): kena untuk slot yang sudah lewat.
        $shifts = collect($shifts)->filter(fn (WorkShift $shift) => $shift->starts_at && $shift->work_date->toDateString() >= '2026-10-16');
        if ($shifts->isEmpty()) {
            return 0;
        }

        $now = now('Asia/Jakarta');
        $reports = CleaningReport::query()
            ->where('employee_id', $this->id)
            ->whereDate('work_date', '>=', $shifts->min(fn (WorkShift $shift) => $shift->work_date->toDateString()))
            ->whereDate('work_date', '<=', $shifts->max(fn (WorkShift $shift) => $shift->work_date->toDateString()))
            ->get();
        $missed = 0;
        foreach ($shifts as $shift) {
            $date = $shift->work_date->toDateString();
            foreach (CleaningReport::plans($this, $shift, $date, CleaningReport::slots($shift, $date)) as $plan) {
                if (CleaningReport::accepts($date, $plan['slot'], $now)) {
                    continue;
                }
                foreach ($plan['areas'] as $key => $area) {
                    $areaRows = $reports->filter(fn (CleaningReport $report) => $report->work_date->toDateString() === $date && $report->slot === $plan['slot'] && $report->area === $key);
                    if ($areaRows->isEmpty()) {
                        $missed += count($area['items']);
                    } elseif ($areaRows->every(fn (CleaningReport $report) => $report->item === null)) {
                        $missed += (int) $areaRows->sum('unchecked');
                    } else {
                        $missed += max(0, count($area['items']) - $areaRows->pluck('item')->filter(fn ($item) => $item !== null)->unique()->count());
                    }
                }
            }
        }

        return $missed * 100;
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
