<?php

namespace App\Http\Controllers;

use App\Models\CleaningReport;
use App\Models\Employee;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request): RedirectResponse|JsonResponse
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
        if (! CleaningReport::accepts($now->toDateString(), $slot, $now)) {
            throw ValidationException::withMessages([
                'slot' => 'Toleransi 1 jam sudah lewat. Report ini tidak bisa dikirim.',
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

        $already = CleaningReport::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $now->toDateString())
            ->where('slot', $slot)
            ->get();
        foreach ($checked as $key => $indexes) {
            $checked[$key] = array_values(array_filter(
                $indexes,
                fn (int $index) => ! $already->contains(fn (CleaningReport $row) => $row->area === $key && $row->item !== null && (int) $row->item === $index)
            ));
        }
        if (collect($checked)->flatten()->isEmpty()) {
            throw ValidationException::withMessages([
                'slot' => 'Report jam ini sudah dikirim.',
            ]);
        }

        $rules = [];
        foreach ($checked as $key => $indexes) {
            foreach ($indexes as $index) {
                $rules['photo.'.$key.'.'.$index] = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];
            }
        }

        $request->validate($rules, [
            'photo.*.*.required' => 'Setiap tugas yang dicentang wajib ada fotonya.',
            'photo.*.*.image' => 'Bukti harus berupa foto JPG, PNG, atau WEBP.',
            'photo.*.*.uploaded' => 'Ada foto yang gagal diunggah. Ambil ulang dengan tombol Kamera.',
            'photo.*.*.mimes' => 'Bukti harus berupa foto JPG, PNG, atau WEBP.',
            'photo.*.*.max' => 'Ada foto yang masih terlalu besar. Ambil ulang dengan tombol Kamera.',
        ]);

        $stored = [];
        try {
            DB::transaction(function () use ($checked, $request, $employee, $now, $slot, &$stored) {
                foreach ($checked as $key => $indexes) {
                    foreach ($indexes as $index) {
                        $file = $this->shrink($request->file('photo.'.$key.'.'.$index));
                        $path = $file->storeAs('evidence/report', $file->hashName(), 'biznet');
                        $stored[] = $path;
                        CleaningReport::query()->create([
                            'employee_id' => $employee->id,
                            'outlet_id' => $employee->outlet_id,
                            'work_date' => $now->toDateString(),
                            'slot' => $slot,
                            'area' => $key,
                            'item' => $index,
                            'photo' => $path,
                            'unchecked' => 0,
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            report($e);
            try {
                Storage::disk('biznet')->delete($stored);
            } catch (\Throwable) {
            }
            throw ValidationException::withMessages([
                'slot' => 'Report gagal terkirim. Coba lagi. Yang sudah terkirim tidak perlu diulang.',
            ]);
        }

        $message = 'Report '.$slot.' tercatat.';
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    private function shrink(UploadedFile $file): UploadedFile
    {
        if ($file->getSize() < 900_000 || ! function_exists('imagecreatefromstring')) {
            return $file;
        }
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            return $file;
        }
        $width = imagesx($src);
        $height = imagesy($src);
        $scale = min(1, 1600 / max($width, $height, 1));
        $nextWidth = max(1, (int) round($width * $scale));
        $nextHeight = max(1, (int) round($height * $scale));
        $dst = imagecreatetruecolor($nextWidth, $nextHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nextWidth, $nextHeight, $width, $height);
        imagedestroy($src);
        $tmp = tempnam(sys_get_temp_dir(), 'rep');
        imagejpeg($dst, $tmp, 72);
        imagedestroy($dst);

        return new UploadedFile($tmp, 'bukti.jpg', 'image/jpeg', null, true);
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
        $shifts = WorkShift::query()
            ->with('employee.user')
            ->when(current_outlet_id(), fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('outlet_id', current_outlet_id())))
            ->whereDate('work_date', $date)
            ->get()
            ->sortBy('employee_id')
            ->values();
        $boards = $shifts
            ->map(function (WorkShift $shift) use ($reports, $date, $shifts, &$shown) {
                $employee = $shift->employee;
                if (! $employee) {
                    return null;
                }
                $shown[] = $employee->id;
                $rows = $reports->get($employee->id, collect());
                $slots = [];
                if ($shift->starts_at) {
                    foreach (CleaningReport::plans($employee, $shift, $date, CleaningReport::slots($shift, $date), $shifts) as $plan) {
                        $due = ! CleaningReport::accepts($date, $plan['slot']);
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
                        $finished = 0;
                        $expected = 0;
                        foreach ($areas as $area) {
                            if ($area['items'] !== []) {
                                $expected += count($area['items']);
                                $finished += collect($area['items'])->where('done', true)->count();
                            } else {
                                $expected += count($area['legacy_items']);
                                $finished += max(0, count($area['legacy_items']) - (int) $area['legacy_unchecked']);
                            }
                        }
                        $slots[] = [
                            'slot' => $plan['slot'],
                            'areas' => $areas,
                            'done' => $finished,
                            'total' => $expected,
                            'status' => ($expected > 0 && $finished === $expected) ? 'done' : ($due ? 'miss' : 'wait'),
                        ];
                    }
                }

                $tone = match (true) {
                    ! $shift->starts_at => 'off',
                    collect($slots)->contains(fn (array $slot) => $slot['status'] === 'miss') => 'miss',
                    collect($slots)->contains(fn (array $slot) => $slot['status'] === 'wait') => 'wait',
                    default => 'done',
                };

                return [
                    'name' => $employee->user?->name ?? 'Karyawan',
                    'shift' => $shift->label($employee->position),
                    'off' => ! $shift->starts_at,
                    'tone' => $tone,
                    'slots' => $slots,
                ];
            })
            ->filter()
            ->sortBy(fn (array $board) => ['miss' => 0, 'wait' => 1, 'done' => 2, 'off' => 3][$board['tone']])
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
