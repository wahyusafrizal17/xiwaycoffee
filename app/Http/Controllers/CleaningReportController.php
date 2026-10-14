<?php

namespace App\Http\Controllers;

use App\Models\CleaningReport;
use App\Models\Employee;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CleaningReportController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $this->employee($request);
        $now = now('Asia/Jakarta');
        $shift = $this->shift($employee, $now);
        $slots = $shift ? CleaningReport::slots($shift, $now->toDateString()) : [];
        $plans = ($shift && $shift->starts_at) ? CleaningReport::plans($employee, $shift, $now->toDateString(), $slots) : [];
        $done = CleaningReport::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $now->toDateString())
            ->get()
            ->groupBy('slot');

        return view('cleaning.index', [
            'plans' => $plans,
            'done' => $done,
            'now' => $now->format('H:i'),
            'off' => $shift && ! $shift->starts_at,
            'shiftLabel' => $shift?->label($employee->position),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);
        $now = now('Asia/Jakarta');
        $shift = $this->shift($employee, $now);
        $slots = $shift ? CleaningReport::slots($shift, $now->toDateString()) : [];
        $slot = $request->validate(['slot' => ['required', 'string']])['slot'];
        if (! in_array($slot, $slots, true)) {
            throw ValidationException::withMessages([
                'slot' => 'Report ini bukan jadwal kamu.',
            ]);
        }

        $plan = collect(($shift && $shift->starts_at) ? CleaningReport::plans($employee, $shift, $now->toDateString(), $slots) : [])
            ->firstWhere('slot', $slot);
        $areas = $plan['areas'] ?? [];
        if ($areas === []) {
            throw ValidationException::withMessages([
                'slot' => 'Jam ini tidak ada bagianmu.',
            ]);
        }

        $checked = [];
        foreach ($areas as $key => $area) {
            $checked[$key] = collect($request->input('tasks.'.$key, []))
                ->map(fn ($index) => (int) $index)
                ->unique()
                ->filter(fn (int $index) => $index >= 0 && $index < count($area['items']))
                ->values()
                ->all();
        }
        if (collect($checked)->flatten()->isEmpty()) {
            throw ValidationException::withMessages([
                'slot' => 'Centang tugas yang sudah dikerjakan, lalu unggah fotonya.',
            ]);
        }

        $rules = [];
        foreach ($checked as $key => $indexes) {
            foreach ($indexes as $index) {
                $rules['photo.'.$key.'.'.$index] = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            }
        }

        $request->validate($rules, [
            'photo.*.*.required' => 'Setiap tugas yang dicentang wajib ada fotonya.',
            'photo.*.*.image' => 'Bukti harus berupa foto.',
            'photo.*.*.max' => 'Foto maksimal 5 MB.',
        ]);
        $data = ['slot' => $slot];

        $exists = CleaningReport::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $now->toDateString())
            ->where('slot', $data['slot'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'slot' => 'Report jam ini sudah dikirim.',
            ]);
        }

        $stored = [];
        try {
            foreach ($checked as $key => $indexes) {
                foreach ($indexes as $index) {
                    $file = $request->file('photo.'.$key.'.'.$index);
                    $path = $file->storeAs('evidence/report', $file->hashName(), 'biznet');
                    $stored[] = $path;
                    CleaningReport::query()->create([
                        'employee_id' => $employee->id,
                        'outlet_id' => $employee->outlet_id,
                        'work_date' => $now->toDateString(),
                        'slot' => $data['slot'],
                        'area' => $key,
                        'item' => $index,
                        'photo' => $path,
                        'unchecked' => 0,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Storage::disk('biznet')->delete($stored);
            throw $e;
        }

        return back()->with('success', 'Report '.$data['slot'].' tercatat.');
    }

    public function review(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);

        $date = (string) $request->query('date', now('Asia/Jakarta')->toDateString());
        if (! Carbon::hasFormat($date, 'Y-m-d')) {
            $date = now('Asia/Jakarta')->toDateString();
        }

        $reports = CleaningReport::query()
            ->with('employee.user')
            ->when(current_outlet_id(), fn ($query) => $query->where('outlet_id', current_outlet_id()))
            ->whereDate('work_date', $date)
            ->orderBy('employee_id')
            ->orderBy('slot')
            ->get()
            ->groupBy('employee_id');

        $shown = [];
        $boards = WorkShift::query()
            ->with('employee.user')
            ->when(current_outlet_id(), fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('outlet_id', current_outlet_id())))
            ->whereDate('work_date', $date)
            ->get()
            ->sortBy('employee_id')
            ->map(function (WorkShift $shift) use ($reports, $date, &$shown) {
                $employee = $shift->employee;
                if (! $employee) {
                    return null;
                }
                $shown[] = $employee->id;
                $rows = $reports->get($employee->id, collect());
                $slots = [];
                if ($shift->starts_at) {
                    foreach (CleaningReport::plans($employee, $shift, $date, CleaningReport::slots($shift, $date)) as $plan) {
                        $due = Carbon::parse($date.' '.$plan['slot'], 'Asia/Jakarta')->lte(now('Asia/Jakarta'));
                        $slotRows = $rows->where('slot', $plan['slot']);
                        $areas = [];
                        foreach ($plan['areas'] as $key => $area) {
                            $areaRows = $slotRows->where('area', $key);
                            $legacy = $areaRows->first(fn (CleaningReport $row) => $row->item === null);
                            $items = [];
                            foreach ($area['items'] as $index => $label) {
                                $photo = $areaRows->first(fn (CleaningReport $row) => $row->item !== null && (int) $row->item === $index);
                                $items[] = [
                                    'label' => $label,
                                    'done' => $photo !== null,
                                    'photo' => $photo?->photo,
                                    'status' => $photo ? 'Dikerjakan' : ($due ? 'Belum dikerjakan' : 'Belum waktunya'),
                                ];
                            }
                            $areas[] = [
                                'label' => $area['label'],
                                'items' => $legacy ? [] : $items,
                                'legacy_photo' => $legacy?->photo,
                                'legacy_unchecked' => $legacy ? (int) $legacy->unchecked : null,
                                'legacy_items' => $legacy ? $area['items'] : [],
                            ];
                        }
                        $slots[] = ['slot' => $plan['slot'], 'areas' => $areas];
                    }
                }

                return [
                    'name' => $employee->user?->name ?? 'Karyawan',
                    'shift' => $shift->label($employee->position),
                    'off' => ! $shift->starts_at,
                    'slots' => $slots,
                ];
            })
            ->filter()
            ->values();

        $orphans = $reports->reject(fn ($rows, $employeeId) => in_array((int) $employeeId, $shown, true));

        return view('cleaning.review', [
            'boards' => $boards,
            'orphans' => $orphans,
            'date' => $date,
            'areas' => config('cleaning.areas'),
        ]);
    }

    protected function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $request->user()->hasPermission('attendance.clock') && ! $request->user()->hasPermission('attendance.manage'), 403);

        return $employee;
    }

    protected function shift(Employee $employee, Carbon $now): ?WorkShift
    {
        return WorkShift::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $now->toDateString())
            ->first();
    }
}
