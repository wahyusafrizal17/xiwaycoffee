<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function clockIn(Employee $employee, float $lat, float $lng, UploadedFile|string $selfie): Attendance
    {
        $outlet = $employee->outlet;
        if (! $outlet?->latitude || ! $outlet?->longitude) {
            throw ValidationException::withMessages([
                'gps' => 'Lokasi ruko belum dikonfigurasi. Hubungi admin.',
            ]);
        }

        $distance = $this->distanceMeters($lat, $lng, (float) $outlet->latitude, (float) $outlet->longitude);
        $radius = (int) ($outlet->geo_radius_m ?: config('pos.attendance.geo_radius_m', 150));

        if ($distance > $radius) {
            throw ValidationException::withMessages([
                'gps' => 'Kamu di luar area ruko ('.$distance.' m). Dekati lokasi outlet.',
            ]);
        }

        $today = now()->toDateString();
        if (Attendance::query()->where('employee_id', $employee->id)->whereDate('work_date', $today)->whereNotNull('clock_in_at')->exists()) {
            throw ValidationException::withMessages([
                'clock_in' => 'Sudah absen masuk hari ini.',
            ]);
        }

        $path = $this->storeSelfie($employee, $selfie);
        $cutoff = (string) config('pos.attendance.late_after', '08:30');
        $isLate = now()->format('H:i') > $cutoff;

        return Attendance::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $today],
            [
                'outlet_id' => $outlet->id,
                'clock_in_at' => now(),
                'clock_in_lat' => $lat,
                'clock_in_lng' => $lng,
                'clock_in_distance_m' => $distance,
                'selfie_path' => $path,
                'is_late' => $isLate,
            ],
        );
    }

    public function clockOut(Employee $employee, float $lat, float $lng): Attendance
    {
        $outlet = $employee->outlet;
        if (! $outlet?->latitude || ! $outlet?->longitude) {
            throw ValidationException::withMessages([
                'gps' => 'Lokasi ruko belum dikonfigurasi. Hubungi admin.',
            ]);
        }

        $distance = $this->distanceMeters($lat, $lng, (float) $outlet->latitude, (float) $outlet->longitude);
        $radius = (int) ($outlet->geo_radius_m ?: config('pos.attendance.geo_radius_m', 150));

        if ($distance > $radius) {
            throw ValidationException::withMessages([
                'gps' => 'Kamu di luar area ruko ('.$distance.' m). Dekati lokasi outlet.',
            ]);
        }

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', now()->toDateString())
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'clock_out' => 'Belum absen masuk hari ini.',
            ]);
        }

        $attendance->update(['clock_out_at' => now()]);

        return $attendance->fresh();
    }

    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $earth * asin(min(1, sqrt($a))));
    }

    protected function storeSelfie(Employee $employee, UploadedFile|string $selfie): string
    {
        $dir = 'attendances/'.$employee->id;
        $name = now()->format('Ymd_His').'.jpg';

        if ($selfie instanceof UploadedFile) {
            return $selfie->storeAs($dir, $name, 'public');
        }

        $data = $selfie;
        if (str_contains($data, ',')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $binary = base64_decode($data, true);
        if ($binary === false || strlen($binary) < 100) {
            throw ValidationException::withMessages([
                'selfie' => 'Selfie tidak valid.',
            ]);
        }

        $path = $dir.'/'.$name;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
