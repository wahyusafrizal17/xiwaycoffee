<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['employee_id', 'outlet_id', 'work_date', 'slot', 'area', 'photo', 'unchecked'])]
class CleaningReport extends Model
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

    /**
     * @param  list<string>  $slots
     * @return list<array{slot: string, areas: array<string, array{label: string, items: list<string>}>, roster: list<array{name: string, labels: list<string>, mine: bool}>}>
     */
    public static function plans(Employee $employee, WorkShift $shift, string $workDate, array $slots): array
    {
        $definitions = config('cleaning.areas');
        $crew = WorkShift::query()
            ->with('employee.user')
            ->whereDate('work_date', $workDate)
            ->where('starts_at', $shift->starts_at)
            ->where('ends_at', $shift->ends_at)
            ->whereHas('employee', fn ($query) => $query->where('outlet_id', $employee->outlet_id))
            ->get()
            ->sortBy('employee_id')
            ->values();

        if ($crew->isEmpty()) {
            $crew = collect([(object) ['employee_id' => $employee->id, 'employee' => $employee->loadMissing('user')]]);
        }

        // ponytail: 6 pasangan digeser tiap jam dan tiap orang. Orang dalam shift yang sama tidak pernah dapat pasangan yang sama. Tidak menyimpan histori.
        $offset = Carbon::parse($workDate)->dayOfYear;
        $pairs = [
            ['lantai1', 'meeting'],
            ['halaman', 'lantai2'],
            ['lantai1', 'lantai2'],
            ['halaman', 'meeting'],
            ['halaman', 'lantai1'],
            ['lantai2', 'meeting'],
        ];
        $plans = [];
        foreach ($slots as $slotIndex => $slot) {
            $roster = [];
            $mine = [];
            foreach ($crew as $index => $row) {
                $personKeys = $pairs[($offset + $slotIndex + $index) % count($pairs)];
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
