<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollDay;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);

        $employees = Employee::query()
            ->with('user')
            ->when(current_outlet_id(), fn ($q) => $q->where('outlet_id', current_outlet_id()))
            ->orderBy('position')
            ->get();

        return view('employees.index', [
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);

        $data = $request->validate([
            'position' => ['required', 'string', 'max:80'],
            'primary_position' => ['nullable', 'string', 'max:80'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'job_allowance' => ['required', 'numeric', 'min:0'],
            'transport_allowance' => ['required', 'numeric', 'min:0'],
            'cleanliness_allowance' => ['required', 'numeric', 'min:0'],
            'sales_bonus' => ['required', 'numeric', 'min:0'],
            'deduction' => ['required', 'numeric', 'min:0'],
        ]);
        $data['salary'] = $data['base_salary'] + $data['job_allowance'] + $data['transport_allowance'] + $data['cleanliness_allowance'];

        $employee->update($data);

        return redirect()->route('employees.index')->with('success', 'Data karyawan diperbarui.');
    }

    public function updateDays(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);

        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'days' => ['required', 'integer', 'min:0', 'max:31'],
        ]);

        PayrollDay::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'month' => $data['month']],
            ['days' => $data['days']],
        );

        return redirect()->route('employees.payroll', ['month' => $data['month']])->with('success', 'Hari masuk disimpan.');
    }

    public function recap(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);

        [$month, $rows] = $this->rows($request);

        return view('employees.recap', [
            'month' => $month,
            'rows' => $rows,
        ]);
    }

    public function payroll(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);

        [$month, $rows] = $this->rows($request);

        return view('employees.payroll', [
            'month' => $month,
            'rows' => $rows,
            'label' => Carbon::createFromFormat('Y-m', $month)->locale('id')->translatedFormat('F Y'),
        ]);
    }

    public function mine(Request $request): View
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404);

        if ($request->filled('month')) {
            return $this->slip($request, $employee);
        }

        $rows = $this->slipMonths($employee)->map(function (string $month) use ($request, $employee) {
            $probe = Request::create('/', 'GET', ['month' => $month]);
            $probe->setUserResolver(fn () => $request->user());
            $row = $this->rows($probe, $employee)[1]->first();

            return [
                'month' => $month,
                'label' => Carbon::createFromFormat('Y-m', $month)->locale('id')->translatedFormat('F Y'),
                'worked' => $row['worked'] ?? 0,
                'net' => $row['net'] ?? 0,
            ];
        });

        return view('employees.slips', [
            'rows' => $rows,
        ]);
    }

    public function slip(Request $request, Employee $employee): View
    {
        $user = $request->user();
        $own = $user->employee?->is($employee);
        abort_unless($user->hasPermission('attendance.manage') || ($own && $user->hasPermission('attendance.clock')), 403);

        $month = $this->month($request);
        $row = $this->rows($request, $employee)[1]->first();
        abort_unless($row, 404);

        return view('employees.slip', [
            'row' => $row,
            'month' => $month,
            'label' => Carbon::createFromFormat('Y-m', $month)->locale('id')->translatedFormat('F Y'),
            'outlet' => $employee->outlet,
        ]);
    }

    /**
     * @return array{0: string, 1: Collection<int, array<string, mixed>>}
     */
    protected function rows(Request $request, ?Employee $only = null): array
    {
        $month = $this->month($request);
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::query()
            ->with(['user', 'outlet'])
            ->when($only, fn ($q) => $q->whereKey($only->id))
            ->when(! $only && current_outlet_id(), fn ($q) => $q->where('outlet_id', current_outlet_id()))
            ->orderBy('position')
            ->get();

        $ids = $employees->pluck('id');
        $shifts = WorkShift::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get()
            ->groupBy('employee_id');
        $present = Attendance::query()
            ->whereIn('employee_id', $ids)
            ->whereNotNull('clock_in_at')
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get()
            ->groupBy('employee_id');
        $overrides = PayrollDay::query()
            ->whereIn('employee_id', $ids)
            ->where('month', $month)
            ->pluck('days', 'employee_id');

        $rows = $employees->map(function (Employee $employee) use ($shifts, $present, $overrides) {
            $mine = $shifts->get($employee->id, collect());
            $days = $present->get($employee->id, collect());
            $scheduled = $mine->filter(fn ($shift) => filled($shift->starts_at))->count();
            $off = $mine->filter(fn ($shift) => ! filled($shift->starts_at))->count();
            $attended = $days->count();
            $worked = $overrides->has($employee->id) ? (int) $overrides[$employee->id] : $attended;
            $late = $days->where('is_late', true)->count();
            $pay = $employee->pay($worked);
            $cells = $days->keyBy(fn ($attendance) => $attendance->work_date->day)->map(fn ($attendance) => [
                'in' => $attendance->clock_in_at?->timezone('Asia/Jakarta')->format('H:i'),
                'out' => $attendance->clock_out_at?->timezone('Asia/Jakarta')->format('H:i'),
                'late' => (bool) $attendance->is_late,
            ]);
            $offDays = $mine->filter(fn ($shift) => ! filled($shift->starts_at))->map(fn ($shift) => $shift->work_date->day)->all();
            $dueDays = $mine->filter(fn ($shift) => filled($shift->starts_at))->map(fn ($shift) => $shift->work_date->day)->all();

            return [
                'employee' => $employee,
                'scheduled' => $scheduled,
                'off' => $off,
                'attended' => $attended,
                'worked' => $worked,
                'late' => $late,
                'cells' => $cells,
                'off_days' => $offDays,
                'due_days' => $dueDays,
                'salary' => $pay['monthly'],
                'lines' => $pay['lines'],
                'bonus' => $pay['bonus'],
                'deduction' => $pay['deduction'],
                'earnings' => $pay['earnings'],
                'net' => $pay['received'],
            ];
        });

        return [$month, $rows];
    }

    protected function month(Request $request): string
    {
        $month = (string) $request->query('month', now()->format('Y-m'));

        return Carbon::hasFormat($month, 'Y-m') ? $month : now()->format('Y-m');
    }

    /**
     * @return Collection<int, string>
     */
    protected function slipMonths(Employee $employee): Collection
    {
        $start = ($employee->created_at ?? now())->copy()->startOfMonth();
        $firstDay = Attendance::query()->where('employee_id', $employee->id)->min('work_date');
        if ($firstDay) {
            $attendanceStart = Carbon::parse($firstDay)->startOfMonth();
            if ($attendanceStart->lt($start)) {
                $start = $attendanceStart;
            }
        }
        $firstOverride = PayrollDay::query()->where('employee_id', $employee->id)->min('month');
        if (is_string($firstOverride) && Carbon::hasFormat($firstOverride, 'Y-m')) {
            $overrideStart = Carbon::createFromFormat('Y-m', $firstOverride)->startOfMonth();
            if ($overrideStart->lt($start)) {
                $start = $overrideStart;
            }
        }

        $cursor = now()->startOfMonth();
        if ($start->gt($cursor)) {
            $start = $cursor->copy();
        }

        // ponytail: 24 months, paginate if a staff record runs longer than that
        $months = collect();
        while ($cursor->greaterThanOrEqualTo($start) && $months->count() < 24) {
            $months->push($cursor->format('Y-m'));
            $cursor = $cursor->copy()->subMonth();
        }

        return $months;
    }
}
