<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.clock') || $request->user()->hasPermission('attendance.manage'), 403);

        $employee = Employee::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->with('outlet')
            ->first();

        $today = $employee
            ? Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', now()->toDateString())
                ->first()
            : null;

        $todayShift = $employee
            ? WorkShift::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', now()->toDateString())
                ->first()
            : null;

        $history = $employee
            ? Attendance::query()
                ->where('employee_id', $employee->id)
                ->latest('work_date')
                ->limit(14)
                ->get()
            : collect();

        $team = null;
        if ($request->user()->hasPermission('attendance.manage')) {
            $team = Attendance::query()
                ->with(['employee.user'])
                ->when(current_outlet_id(), fn ($q) => $q->where('outlet_id', current_outlet_id()))
                ->whereDate('work_date', now()->toDateString())
                ->orderByDesc('clock_in_at')
                ->get();
        }

        return view('attendance.index', [
            'employee' => $employee,
            'today' => $today,
            'todayShift' => $todayShift,
            'history' => $history,
            'team' => $team,
            'lateAfter' => config('pos.attendance.late_after', '08:30'),
            'outlet' => $employee?->outlet,
        ]);
    }

    public function clockIn(Request $request, AttendanceService $attendance): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('attendance.clock'), 403);

        $employee = Employee::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->firstOrFail();

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'selfie' => ['required', 'string'],
        ]);

        $attendance->clockIn($employee, (float) $data['latitude'], (float) $data['longitude'], $data['selfie']);

        return back()->with('success', 'Absen masuk tercatat.');
    }

    public function clockOut(Request $request, AttendanceService $attendance): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('attendance.clock'), 403);

        $employee = Employee::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->firstOrFail();

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $attendance->clockOut($employee, (float) $data['latitude'], (float) $data['longitude']);

        return back()->with('success', 'Absen pulang tercatat.');
    }

    public function schedule(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.clock') || $request->user()->hasPermission('attendance.manage'), 403);

        $order = [
            'zakki@xiway.local' => 0,
            'ergina@xiway.local' => 1,
            'naurah@xiway.local' => 2,
            'jimmy@xiway.local' => 3,
        ];

        $shifts = WorkShift::query()
            ->with(['employee.user'])
            ->when(current_outlet_id(), fn ($q) => $q->whereHas('employee', fn ($employee) => $employee->where('outlet_id', current_outlet_id())))
            ->orderBy('work_date')
            ->get();

        $employees = $shifts
            ->pluck('employee')
            ->unique('id')
            ->sortBy(fn (Employee $employee) => $order[$employee->user?->email] ?? 9)
            ->values();

        $byDate = $shifts->groupBy(fn (WorkShift $shift) => $shift->work_date->toDateString());

        return view('attendance.schedule', [
            'employees' => $employees,
            'byDate' => $byDate,
            'meId' => Employee::query()->where('user_id', $request->user()->id)->value('id'),
        ]);
    }
}
