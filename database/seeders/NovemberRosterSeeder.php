<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class NovemberRosterSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = Outlet::query()->where('code', 'CMH')->first()
            ?? Outlet::query()->where('is_active', true)->first();

        if (! $outlet) {
            return;
        }

        $staff = [
            ['name' => 'Dinda', 'email' => 'dinda@xiway.local', 'position' => 'Barista/Kasir', 'salary' => 1_700_000],
            ['name' => 'Jimmy', 'email' => 'jimmy@xiway.local', 'position' => 'Barista/Kasir', 'salary' => 2_000_000],
            ['name' => 'Bagas', 'email' => 'bagas@xiway.local', 'position' => 'Waiters', 'salary' => 1_000_000],
            ['name' => 'Sultan', 'email' => 'sultan@xiway.local', 'position' => 'Content Creator', 'salary' => 500_000],
        ];

        DB::transaction(function () use ($outlet, $staff) {
            $role = Role::query()->where('name', 'karyawan')->firstOrFail();

            $employees = [];
            foreach ($staff as $row) {
                $employees[$row['name']] = $this->employee($row, $outlet, $role);
            }

            // Karyawan lama hanya dinonaktifkan: absensi, payroll, dan transaksi mereka tetap ada.
            $leavers = Employee::query()
                ->with('user')
                ->where('outlet_id', $outlet->id)
                ->where('is_active', true)
                ->whereNotIn('id', collect($employees)->pluck('id'))
                ->get();
            foreach ($leavers as $employee) {
                $employee->update(['is_active' => false]);
                $employee->user?->update(['is_active' => false]);
                $this->command?->info('Dinonaktifkan: '.$employee->user?->name);
            }

            $this->shifts($employees);
        });
    }

    /**
     * @param  array{name: string, email: string, position: string, salary: int}  $row
     */
    protected function employee(array $row, Outlet $outlet, Role $role): Employee
    {
        // Email di server bisa beda dengan seeder lama, jadi cari lewat nama. Password akun lama tidak diubah.
        $user = User::query()->where('name', $row['name'])->first()
            ?? User::query()->create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

        if ($user->wasRecentlyCreated) {
            $user->roles()->sync([$role->id]);
            $user->outlets()->sync([$outlet->id => ['is_default' => true]]);
        } else {
            $user->update(['is_active' => true]);
        }

        $employee = Employee::query()->firstOrNew(['user_id' => $user->id]);
        $employee->fill([
            'outlet_id' => $employee->outlet_id ?? $outlet->id,
            'position' => $row['position'],
            'primary_position' => '',
            'salary' => $row['salary'],
            'is_active' => true,
        ]);

        // payParts() memakai komponen gaji kalau terisi, jadi totalnya harus ikut gaji baru.
        $parts = (float) $employee->base_salary + (float) $employee->job_allowance
            + (float) $employee->transport_allowance + (float) $employee->cleanliness_allowance;
        if ($parts !== (float) $row['salary']) {
            $employee->fill([
                'base_salary' => $row['salary'],
                'job_allowance' => 0,
                'transport_allowance' => 0,
                'cleanliness_allowance' => 0,
            ]);
        }

        $employee->save();
        $this->command?->info('Karyawan: '.$user->name.' ('.$user->email.') - '.$row['position']);

        return $employee;
    }

    /**
     * @param  array<string, Employee>  $employees
     */
    protected function shifts(array $employees): void
    {
        $crew = [$employees['Dinda'], $employees['Jimmy'], $employees['Bagas']];

        $slots = [
            'a' => ['09:00', '22:00'],
            'b' => ['09:00', '17:00'],
            'c' => ['14:00', '22:00'],
            'o' => [null, null],
        ];

        $rows = [
            '2026-11-01' => 'bcc',
            '2026-11-02' => 'cbc',
            '2026-11-03' => 'aoc',
            '2026-11-04' => 'cbo',
            '2026-11-05' => 'oac',
            '2026-11-06' => 'bcc',
            '2026-11-07' => 'cbc',
            '2026-11-08' => 'cbc',
            '2026-11-09' => 'aoc',
            '2026-11-10' => 'cbo',
            '2026-11-11' => 'oac',
            '2026-11-12' => 'bcc',
            '2026-11-13' => 'bcc',
            '2026-11-14' => 'bcc',
            '2026-11-15' => 'bcc',
            '2026-11-16' => 'cbc',
            '2026-11-17' => 'aoc',
            '2026-11-18' => 'cbo',
            '2026-11-19' => 'oac',
            '2026-11-20' => 'bcc',
            '2026-11-21' => 'cbc',
            '2026-11-22' => 'cbc',
            '2026-11-23' => 'oac',
            '2026-11-24' => 'bcc',
            '2026-11-25' => 'aoc',
            '2026-11-26' => 'cbo',
            '2026-11-27' => 'bcc',
            '2026-11-28' => 'bcc',
            '2026-11-29' => 'bcc',
            '2026-11-30' => 'cbc',
        ];

        foreach ($rows as $date => $code) {
            foreach ($crew as $index => $employee) {
                [$start, $end] = $slots[$code[$index]];
                $shift = $employee->shifts()->whereDate('work_date', $date)->first()
                    ?? $employee->shifts()->make(['work_date' => $date]);
                $shift->fill(['starts_at' => $start, 'ends_at' => $end])->save();
            }
        }
    }
}
