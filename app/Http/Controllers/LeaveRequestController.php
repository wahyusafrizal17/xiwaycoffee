<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $this->employee($request);
        $today = now('Asia/Jakarta')->toDateString();
        $date = (string) $request->query('date', $today);
        if (! Carbon::hasFormat($date, 'Y-m-d') || $date < $today) {
            $date = $today;
        }

        $mine = WorkShift::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->first();

        $backups = collect();
        if ($mine?->starts_at) {
            $backups = WorkShift::query()
                ->with('employee.user')
                ->whereDate('work_date', $date)
                ->whereHas('employee', fn ($q) => $q->where('outlet_id', $employee->outlet_id)->where('is_active', true)->where('id', '!=', $employee->id))
                ->orderBy('employee_id')
                ->get()
                ->filter(fn (WorkShift $shift) => $shift->employee)
                ->map(fn (WorkShift $shift) => [
                    'employee' => $shift->employee,
                    'label' => $shift->label(),
                    'merged' => $this->mergedLabel($mine, $shift),
                ])
                ->values();
        }

        $incoming = LeaveRequest::query()
            ->with('employee.user')
            ->where('backup_employee_id', $employee->id)
            ->where('status', LeaveRequest::PENDING)
            ->whereDate('work_date', '>=', $today)
            ->orderBy('work_date')
            ->get()
            ->map(function (LeaveRequest $req) use ($employee) {
                $date = $req->work_date->toDateString();
                $leaveShift = WorkShift::query()
                    ->where('employee_id', $req->employee_id)
                    ->whereDate('work_date', $date)
                    ->first();
                $backupShift = WorkShift::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', $date)
                    ->first();

                return [
                    'request' => $req,
                    'merged_label' => ($leaveShift?->starts_at && $backupShift)
                        ? $this->mergedLabel($leaveShift, $backupShift)
                        : null,
                ];
            });

        return view('leave.index', [
            'date' => $date,
            'today' => $today,
            'shift' => $mine,
            'backups' => $backups,
            'incoming' => $incoming,
            'mine' => LeaveRequest::query()
                ->with('backup.user')
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', $today)
                ->orderByDesc('work_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);
        $today = now('Asia/Jakarta')->toDateString();
        $data = $request->validate([
            'work_date' => ['required', 'date', 'after_or_equal:'.$today],
            'backup_employee_id' => ['required', 'exists:employees,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $date = $data['work_date'];
        $shift = WorkShift::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->first();
        if (! $shift?->starts_at) {
            throw ValidationException::withMessages([
                'work_date' => 'Tanggal itu kamu OFF atau tidak ada jadwal.',
            ]);
        }

        $backupId = (int) $data['backup_employee_id'];
        if ($backupId === $employee->id) {
            throw ValidationException::withMessages([
                'backup_employee_id' => 'Pilih karyawan lain sebagai backup.',
            ]);
        }

        $backup = Employee::query()->where('id', $backupId)->where('outlet_id', $employee->outlet_id)->where('is_active', true)->first();
        if (! $backup) {
            throw ValidationException::withMessages([
                'backup_employee_id' => 'Backup tidak valid.',
            ]);
        }

        $backupShift = WorkShift::query()
            ->where('employee_id', $backup->id)
            ->whereDate('work_date', $date)
            ->first();
        if (! $backupShift) {
            throw ValidationException::withMessages([
                'backup_employee_id' => 'Backup belum punya jadwal di tanggal itu.',
            ]);
        }

        $exists = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->whereIn('status', [LeaveRequest::PENDING, LeaveRequest::APPROVED])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'work_date' => 'Sudah ada pengajuan izin untuk tanggal ini.',
            ]);
        }

        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'backup_employee_id' => $backup->id,
            'work_date' => $date,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveRequest::PENDING,
        ]);

        return redirect()
            ->route('leave.index', ['date' => $date])
            ->with('success', 'Pengajuan izin dikirim. Menunggu approve backup.');
    }

    public function decide(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ]);

        if ($leave->backup_employee_id !== $employee->id || $leave->status !== LeaveRequest::PENDING) {
            abort(403);
        }

        if ($data['decision'] === LeaveRequest::REJECTED) {
            $leave->update([
                'status' => LeaveRequest::REJECTED,
                'decided_at' => now(),
            ]);

            return back()->with('success', 'Pengajuan izin ditolak.');
        }

        DB::transaction(function () use ($leave) {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($leave->id);
            if ($locked->status !== LeaveRequest::PENDING) {
                throw ValidationException::withMessages([
                    'decision' => 'Pengajuan sudah diproses.',
                ]);
            }

            $date = $locked->work_date->toDateString();
            $mine = WorkShift::query()->where('employee_id', $locked->employee_id)->whereDate('work_date', $date)->lockForUpdate()->first();
            $backup = WorkShift::query()->where('employee_id', $locked->backup_employee_id)->whereDate('work_date', $date)->lockForUpdate()->first();
            if (! $mine?->starts_at || ! $backup) {
                throw ValidationException::withMessages([
                    'decision' => 'Jadwal sudah berubah. Tidak bisa approve.',
                ]);
            }

            [$starts, $ends] = $this->mergedWindow($mine, $backup);
            $mine->update(['starts_at' => null, 'ends_at' => null]);
            $backup->update(['starts_at' => $starts, 'ends_at' => $ends]);

            $locked->update([
                'status' => LeaveRequest::APPROVED,
                'decided_at' => now(),
            ]);
        });

        return back()->with('success', 'Izin disetujui. Backup menutupi shift tersebut.');
    }

    /** @return array{0: string, 1: string} */
    protected function mergedWindow(WorkShift $leaveShift, WorkShift $backupShift): array
    {
        if (! $backupShift->starts_at || ! $backupShift->ends_at) {
            return [$leaveShift->starts_at, $leaveShift->ends_at];
        }

        $starts = [$leaveShift->starts_at, $backupShift->starts_at];
        $ends = [$leaveShift->ends_at, $backupShift->ends_at];
        sort($starts);
        sort($ends);

        return [$starts[0], $ends[1]];
    }

    protected function mergedLabel(WorkShift $leaveShift, WorkShift $backupShift): string
    {
        [$starts, $ends] = $this->mergedWindow($leaveShift, $backupShift);

        return str_replace(':', '.', $starts).'-'.str_replace(':', '.', $ends);
    }

    protected function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $request->user()->hasPermission('attendance.clock') && ! $request->user()->hasPermission('attendance.manage'), 403);

        return $employee;
    }
}
