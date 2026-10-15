<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['employee_id', 'outlet_id', 'work_date', 'slot', 'area', 'item', 'photo', 'unchecked'])]
class CleaningReport extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'item' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return list<string>
     */
    public static function slots(WorkShift $shift, string $workDate): array
    {
        if (! $shift->starts_at || ! $shift->ends_at) {
            return [];
        }

        $start = Carbon::parse($workDate.' '.$shift->starts_at, 'Asia/Jakarta')->subMinutes(30);
        $end = Carbon::parse($workDate.' '.$shift->ends_at, 'Asia/Jakarta');
        if ($end->lessThanOrEqualTo(Carbon::parse($workDate.' '.$shift->starts_at, 'Asia/Jakarta'))) {
            $end->addDay();
        }

        $slots = [];
        for ($cursor = $start->copy(); $cursor->lt($end); $cursor->addHours(2)) {
            $slots[] = $cursor->format('H:i');
        }

        return $slots;
    }

    public static function accepts(string $workDate, string $slot, ?Carbon $now = null): bool
    {
        $now ??= now('Asia/Jakarta');

        return ! $now->greaterThan(Carbon::parse($workDate.' '.$slot, 'Asia/Jakarta')->addHour());
    }

    /**
     * @param  list<string>  $slots
     * @param  \Illuminate\Support\Collection<int, WorkShift>|null  $dayShifts
     * @return list<array{slot: string, areas: array<string, array{label: string, items: list<string>}>, roster: list<array{name: string, labels: list<string>, mine: bool}>}>
     */
    public static function plans(Employee $employee, WorkShift $shift, string $workDate, array $slots, $dayShifts = null): array
    {
        $definitions = config('cleaning.areas');
        $dayShifts ??= WorkShift::query()
            ->with('employee.user')
            ->whereDate('work_date', $workDate)
            ->whereNotNull('starts_at')
            ->whereHas('employee', fn ($query) => $query->where('outlet_id', $employee->outlet_id))
            ->get();
        $dayShifts = $dayShifts
            ->filter(fn (WorkShift $row) => $row->starts_at && (int) $row->employee?->outlet_id === (int) $employee->outlet_id)
            ->sortBy('employee_id')
            ->values();
        if ($dayShifts->isEmpty()) {
            $shift->setRelation('employee', $employee->loadMissing('user'));
            $dayShifts = collect([$shift]);
        }

        $present = [];
        foreach ($dayShifts as $row) {
            foreach (self::slots($row, $workDate) as $time) {
                $present[$time][] = $row;
            }
        }

        // ponytail: pasangan saling melengkapi. Yang bertugas di jam yang sama, meski panjang shift beda, tidak boleh dapat area yang sama.
        $offset = Carbon::parse($workDate)->dayOfYear;
        $matchings = [
            [['lantai1', 'meeting'], ['halaman', 'lantai2']],
            [['lantai1', 'lantai2'], ['halaman', 'meeting']],
            [['halaman', 'lantai1'], ['lantai2', 'meeting']],
        ];
        $plans = [];
        foreach ($slots as $slotIndex => $slot) {
            $crew = $present[$slot] ?? [];
            $matching = $matchings[($offset + $slotIndex) % count($matchings)];
            $pairs = [
                $matching[($offset + $slotIndex) % 2],
                $matching[($offset + $slotIndex + 1) % 2],
            ];
            $count = count($crew);
            $assigned = [];
            if ($count <= 2) {
                for ($index = 0; $index < $count; $index++) {
                    $assigned[] = $pairs[$index];
                }
            } else {
                $assigned = array_fill(0, $count, []);
                foreach (array_merge($pairs[0], $pairs[1]) as $index => $key) {
                    $assigned[$index % $count][] = $key;
                }
            }
            $roster = [];
            $mine = [];
            foreach ($crew as $index => $row) {
                $personKeys = $assigned[$index];
                $roster[] = [
                    'name' => $row->employee?->user?->name ?? 'Karyawan',
                    'labels' => array_map(fn (string $key) => $definitions[$key]['label'], $personKeys),
                    'mine' => $row->employee_id === $employee->id,
                ];
                if ($row->employee_id === $employee->id) {
                    foreach ($personKeys as $key) {
                        $mine[$key] = $definitions[$key];
                    }
                }
            }
            $plans[] = ['slot' => $slot, 'areas' => $mine, 'roster' => $roster];
        }

        return $plans;
    }
}
