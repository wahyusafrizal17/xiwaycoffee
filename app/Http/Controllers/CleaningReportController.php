<?php

namespace App\Http\Controllers;

use App\Models\CleaningReport;
use App\Models\Employee;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $rules = [];
        foreach ($areas as $key => $area) {
            $rules['photo.'.$key] = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $rules['tasks.'.$key] = ['nullable', 'array'];
        }

        $request->validate($rules, [
            'photo.*.required' => 'Foto bukti wajib diunggah.',
            'photo.*.image' => 'Bukti harus berupa foto.',
            'photo.*.max' => 'Foto maksimal 5 MB.',
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

        $directory = public_path('evidence/report');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stored = [];
        try {
            foreach (array_keys($areas) as $key) {
                $file = $request->file('photo.'.$key);
                $name = $file->hashName();
                $file->move($directory, $name);
                $path = 'evidence/report/'.$name;
                $stored[] = $path;
                $checked = collect($request->input('tasks.'.$key, []))
                    ->map(fn ($index) => (int) $index)
                    ->unique()
                    ->filter(fn (int $index) => $index >= 0 && $index < count($areas[$key]['items']))
                    ->count();
                CleaningReport::query()->create([
                    'employee_id' => $employee->id,
                    'outlet_id' => $employee->outlet_id,
                    'work_date' => $now->toDateString(),
                    'slot' => $data['slot'],
                    'area' => $key,
                    'photo' => $path,
                    'unchecked' => count($areas[$key]['items']) - $checked,
                ]);
            }
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                $file = public_path($path);
                if (is_file($file)) {
                    unlink($file);
                }
            }
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

        return view('cleaning.review', [
            'reports' => $reports,
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
