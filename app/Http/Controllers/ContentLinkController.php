<?php

namespace App\Http\Controllers;

use App\Models\ContentLink;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentLinkController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $this->employee($request);
        $today = now('Asia/Jakarta')->toDateString();

        return view('content.index', [
            'links' => $this->dayLinks($employee->outlet_id, $today),
            'today' => $today,
            'limit' => ContentLink::DAILY,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);
        $today = now('Asia/Jakarta')->toDateString();
        $url = ContentLink::normalize((string) $request->input('url'));
        if ($url === null) {
            throw ValidationException::withMessages([
                'url' => 'Tempel link video Instagram.',
            ]);
        }

        DB::transaction(function () use ($employee, $today, $url) {
            $count = ContentLink::query()
                ->where('outlet_id', $employee->outlet_id)
                ->whereDate('work_date', $today)
                ->lockForUpdate()
                ->count();
            if ($count >= ContentLink::DAILY) {
                throw ValidationException::withMessages([
                    'url' => 'Video hari ini sudah 3. Itu batas maksimal.',
                ]);
            }
            $exists = ContentLink::query()
                ->where('outlet_id', $employee->outlet_id)
                ->whereDate('work_date', $today)
                ->where('url', $url)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'url' => 'Link ini sudah dikirim hari ini.',
                ]);
            }
            ContentLink::query()->create([
                'employee_id' => $employee->id,
                'outlet_id' => $employee->outlet_id,
                'work_date' => $today,
                'url' => $url,
            ]);
        });

        return back()->with('success', 'Link video tercatat.');
    }

    public function review(Request $request): View
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);

        $date = (string) $request->query('date', now('Asia/Jakarta')->toDateString());
        if (! Carbon::hasFormat($date, 'Y-m-d')) {
            $date = now('Asia/Jakarta')->toDateString();
        }
        $outletId = current_outlet_id();
        $links = $this->dayLinks($outletId, $date);
        $heads = Employee::query()->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))->where('is_active', true)->count();
        $missed = max(0, ContentLink::DAILY - $links->count());
        $closed = $date >= ContentLink::START && $date < now('Asia/Jakarta')->toDateString();

        return view('content.review', [
            'links' => $links,
            'date' => $date,
            'missed' => $missed,
            'closed' => $closed,
            'fine' => $closed ? $missed * ContentLink::FINE : 0,
            'share' => $closed && $heads > 0 ? intdiv($missed * ContentLink::FINE, $heads) : 0,
            'limit' => ContentLink::DAILY,
        ]);
    }

    protected function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $request->user()->hasPermission('attendance.clock') && ! $request->user()->hasPermission('attendance.manage'), 403);

        return $employee;
    }

    protected function dayLinks(?int $outletId, string $date)
    {
        return ContentLink::query()
            ->with('employee.user')
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->whereDate('work_date', $date)
            ->orderBy('id')
            ->get();
    }
}
