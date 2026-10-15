<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CleaningReport;
use App\Models\ContentLink;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\AttendanceService;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;

    protected Employee $employee;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        Storage::fake('biznet');

        $this->outlet = Outlet::query()->create([
            'code' => 'CMH',
            'name' => 'Xiway Coffee Cimahi',
            'latitude' => -6.8721000,
            'longitude' => 107.5425000,
            'geo_radius_m' => 150,
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', 'karyawan')->firstOrFail();
        $this->user = User::query()->create([
            'name' => 'Zakki',
            'email' => 'zakki@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->sync([$role->id]);
        $this->user->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->employee = Employee::query()->create([
            'user_id' => $this->user->id,
            'outlet_id' => $this->outlet->id,
            'position' => 'Barista',
            'salary' => 3_000_000,
            'is_active' => true,
        ]);
    }

    public function test_attendance_page_shows_a_month_calendar(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 02:00:00'));

        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-01',
        ]);
        Attendance::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-11',
            'clock_in_at' => '2026-10-11 00:00:00',
            'is_late' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Kalender absensi')
            ->assertSee('Oktober 2026')
            ->assertSee('07:00')
            ->assertSee('recap-off', false)
            ->assertDontSee('Riwayat 14 hari');
    }

    public function test_clock_in_requires_geofence_and_marks_late_after_cutoff(): void
    {
        $this->travelTo(now()->setTime(9, 0));

        $service = app(AttendanceService::class);
        $selfie = UploadedFile::fake()->image('selfie.jpg');

        $this->expectException(ValidationException::class);
        $service->clockIn($this->employee, -6.9000000, 107.6000000, $selfie);
    }

    public function test_clock_in_inside_radius_succeeds_and_flags_late(): void
    {
        $this->travelTo(now()->setTime(9, 15));

        $service = app(AttendanceService::class);
        $row = $service->clockIn(
            $this->employee,
            -6.8721000,
            107.5425000,
            UploadedFile::fake()->image('selfie.jpg'),
            'Macet',
        );

        $this->assertTrue($row->is_late);
        $this->assertSame('Macet', $row->late_reason);
        $this->assertNotNull($row->selfie_path);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'is_late' => 1,
        ]);
    }

    public function test_morning_shift_is_late_after_9_and_afternoon_after_14(): void
    {
        $service = app(AttendanceService::class);
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-07',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00', 'Asia/Jakarta'));
        $onTime = $service->clockIn(
            $this->employee,
            -6.8721000,
            107.5425000,
            UploadedFile::fake()->image('selfie.jpg'),
        );
        $this->assertFalse($onTime->is_late);

        $onTime->delete();
        $this->travelTo(Carbon::parse('2026-10-07 09:01:00', 'Asia/Jakarta'));
        try {
            $service->clockIn(
                $this->employee,
                -6.8721000,
                107.5425000,
                UploadedFile::fake()->image('selfie.jpg'),
            );
            $this->fail('Alasan terlambat wajib.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('late_reason', $e->errors());
        }

        $shift = WorkShift::query()->first();
        $shift->update(['starts_at' => '14:00', 'ends_at' => '22:00']);
        $this->travelTo(Carbon::parse('2026-10-07 14:00:00', 'Asia/Jakarta'));
        $this->assertFalse($service->isLate($this->employee));
        $this->travelTo(Carbon::parse('2026-10-07 14:01:00', 'Asia/Jakarta'));
        $this->assertTrue($service->isLate($this->employee));
    }

    public function test_off_day_does_not_require_clock_in(): void
    {
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => now()->toDateString(),
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->travelTo(now()->setTime(10, 0));
        $service = app(AttendanceService::class);
        $this->assertTrue($service->isOff($this->employee));
        $this->assertFalse($service->isLate($this->employee));

        try {
            $service->clockIn($this->employee, -6.8721000, 107.5425000, UploadedFile::fake()->image('selfie.jpg'));
            $this->fail('Hari OFF tidak perlu absen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('clock_in', $e->errors());
        }

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_admin_sets_cafe_point_and_clock_in_must_be_there(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-geo@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $admin->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->actingAs($admin)
            ->put(route('outlets.update', $this->outlet), [
                'code' => $this->outlet->code,
                'name' => $this->outlet->name,
                'latitude' => -6.2000000,
                'longitude' => 106.8000000,
                'geo_radius_m' => 80,
            ])
            ->assertRedirect(route('outlets.index'));

        $this->outlet->refresh();
        $this->assertEquals(-6.2, (float) $this->outlet->latitude);
        $this->assertSame(80, (int) $this->outlet->geo_radius_m);

        $this->employee->refresh();
        $this->travelTo(Carbon::parse(now()->toDateString().' 08:00:00', 'Asia/Jakarta'));
        $service = app(AttendanceService::class);

        try {
            $service->clockIn($this->employee, -6.8721000, 107.5425000, UploadedFile::fake()->image('selfie.jpg'));
            $this->fail('Absen di luar titik kafe harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('gps', $e->errors());
        }

        $row = $service->clockIn($this->employee, -6.2000000, 106.8000000, UploadedFile::fake()->image('selfie.jpg'));
        $this->assertNotNull($row->clock_in_at);
    }

    public function test_employee_seeder_creates_four_staff(): void
    {
        $this->seed(EmployeeSeeder::class);

        $emails = ['zakki@xiway.local', 'naurah@xiway.local', 'ergina@xiway.local', 'jimmy@xiway.local'];
        $this->assertSame(4, User::query()->whereIn('email', $emails)->count());
        $this->assertDatabaseHas('employees', [
            'position' => 'Barista / Senior Crew',
            'primary_position' => 'Bar',
            'salary' => 3000000,
        ]);
        $this->assertDatabaseHas('employees', [
            'position' => 'Café Crew',
            'primary_position' => 'Cashier',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'jimmy@xiway.local']);
    }

    public function test_schedule_is_tied_to_staff_accounts(): void
    {
        $this->seed(EmployeeSeeder::class);
        $this->seed(WorkShiftSeeder::class);

        $ergina = User::query()->where('email', 'ergina@xiway.local')->first();
        $this->actingAs($ergina)
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Ergina')
            ->assertSee('Zakki')
            ->assertSee('Naurah')
            ->assertSee('Jimmy')
            ->assertSee('OFF')
            ->assertSee('14.00-22.00');

        $this->assertSame(128, WorkShift::query()->count());

        $zakiId = User::query()->where('email', 'zakki@xiway.local')->first()->employee->id;
        $shift = WorkShift::query()->where('employee_id', $zakiId)->whereDate('work_date', '2026-09-30')->first();
        $this->assertSame('14:00', $shift->starts_at);
        $this->assertSame('22:00', $shift->ends_at);

        $rere = WorkShift::query()->where('employee_id', $ergina->employee->id)->whereDate('work_date', '2026-09-30')->first();
        $this->assertSame('09:00', $rere->starts_at);
        $this->assertSame('17:00', $rere->ends_at);

        $off = WorkShift::query()->where('employee_id', $ergina->employee->id)->whereDate('work_date', '2026-10-01')->first();
        $this->assertNull($off->starts_at);

        $offs = ['Ergina' => 0, 'Naurah' => 0, 'Jimmy' => 0, 'Zakki' => 0];
        $zakkiShifts = ['09:00' => 0, '14:00' => 0, 'full' => 0];
        for ($day = Carbon::parse('2026-10-01'); $day->lte(Carbon::parse('2026-10-31')); $day->addDay()) {
            $shifts = WorkShift::query()->with('employee.user')->whereDate('work_date', $day->toDateString())->get();
            $this->assertLessThanOrEqual(1, $shifts->whereNull('starts_at')->count(), $day->toDateString());
            $working = $shifts->filter(fn (WorkShift $shift) => $shift->starts_at);
            if ($working->count() === 4) {
                $this->assertSame(2, $working->where('starts_at', '09:00')->where('ends_at', '17:00')->count(), $day->toDateString());
                $this->assertSame(2, $working->where('starts_at', '14:00')->count(), $day->toDateString());
            }
            $zakki = $shifts->first(fn (WorkShift $shift) => $shift->employee->user->name === 'Zakki');
            $naurah = $shifts->first(fn (WorkShift $shift) => $shift->employee->user->name === 'Naurah');
            if ($day->gte(Carbon::parse('2026-10-15')) && $working->count() === 3) {
                $this->assertSame(1, $working->where('starts_at', '09:00')->where('ends_at', '22:00')->count(), $day->toDateString());
                $this->assertSame(1, $working->where('starts_at', '09:00')->where('ends_at', '17:00')->count(), $day->toDateString());
                $this->assertSame(1, $working->where('starts_at', '14:00')->where('ends_at', '22:00')->count(), $day->toDateString());
                if ($zakki->starts_at === null) {
                    $this->assertSame('22:00', $naurah->ends_at, $day->toDateString());
                }
                if ($naurah->starts_at === null) {
                    $this->assertSame('22:00', $zakki->ends_at, $day->toDateString());
                }
            }
            $this->assertFalse($zakki->starts_at === null && $naurah->starts_at === null, $day->toDateString());
            if ($zakki->starts_at && $naurah->starts_at) {
                $this->assertFalse($zakki->starts_at === $naurah->starts_at && $zakki->ends_at === $naurah->ends_at, $day->toDateString());
            }
            if ($day->gte(Carbon::parse('2026-10-15'))) {
                if (($day->isFriday() || $day->isSaturday() || $day->isSunday()) && ! in_array($day->toDateString(), ['2026-10-23', '2026-10-30'], true)) {
                    $this->assertTrue($shifts->every(fn (WorkShift $shift) => $shift->starts_at !== null), $day->toDateString());
                }
                $this->assertGreaterThan(0, $shifts->where('starts_at', '09:00')->count(), $day->toDateString());
                if ($zakki->starts_at === '09:00' && $zakki->ends_at === '17:00') {
                    $zakkiShifts['09:00']++;
                } elseif ($zakki->starts_at === '09:00' && $zakki->ends_at === '22:00') {
                    $zakkiShifts['full']++;
                } elseif ($zakki->starts_at === '14:00') {
                    $zakkiShifts['14:00']++;
                } else {
                    $this->assertNull($zakki->starts_at, $day->toDateString());
                }
            }
            foreach ($shifts as $shift) {
                if (! $shift->starts_at && isset($offs[$shift->employee->user->name])) {
                    $offs[$shift->employee->user->name]++;
                }
            }
        }
        $this->assertSame(['Ergina' => 4, 'Naurah' => 4, 'Jimmy' => 4, 'Zakki' => 4], $offs);
        $this->assertSame(['09:00' => 5, '14:00' => 5, 'full' => 3], $zakkiShifts);
    }

    public function test_full_shift_pays_overtime_on_top_of_the_day(): void
    {
        $this->employee->update(['salary' => 1_700_000]);
        $shift = WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-15',
            'starts_at' => '09:00',
            'ends_at' => '22:00',
        ]);
        $this->assertSame(0, $this->employee->overtimePay([], [$shift]));

        $attendance = Attendance::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 01:00:00',
        ]);

        $this->assertSame((int) round(9.5 * 1_700_000 / 173), $this->employee->overtimePay([$attendance], [$shift]));
    }

    public function test_payroll_slip_prorates_present_days_over_twenty_six(): void
    {
        $this->employee->update(['position' => 'Kasir', 'salary' => 1_700_000]);

        foreach (range(1, 11) as $day) {
            WorkShift::query()->create([
                'employee_id' => $this->employee->id,
                'work_date' => sprintf('2026-09-%02d', $day),
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ]);
        }

        foreach (range(1, 4) as $day) {
            Attendance::query()->create([
                'employee_id' => $this->employee->id,
                'outlet_id' => $this->outlet->id,
                'work_date' => sprintf('2026-09-%02d', $day),
                'clock_in_at' => sprintf('2026-09-%02d 08:20:00', $day),
            ]);
        }

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-gaji@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $admin->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->actingAs($this->user)->get(route('employees.payroll'))->assertForbidden();

        $this->actingAs($this->user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Slip Gaji', false);

        $this->actingAs($this->user)
            ->get(route('employees.mine'))
            ->assertOk()
            ->assertSee('September 2026', false)
            ->assertDontSee('SLIP GAJI', false);

        $this->actingAs($this->user)
            ->get(route('employees.mine', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('SLIP GAJI', false)
            ->assertSee('261,538', false);

        $other = User::query()->create([
            'name' => 'Lain',
            'email' => 'lain-gaji@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $otherEmployee = Employee::query()->create([
            'user_id' => $other->id,
            'outlet_id' => $this->outlet->id,
            'position' => 'Kasir',
            'salary' => 1_000_000,
            'is_active' => true,
        ]);
        $this->actingAs($this->user)->get(route('employees.slip', $otherEmployee))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('employees.slip', [$this->employee, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('SLIP GAJI', false)
            ->assertSee('PENERIMAAN', false)
            ->assertSee('POTONGAN', false)
            ->assertSee('TOTAL DITERIMA KARYAWAN', false)
            ->assertSee('261,538', false)
            ->assertSee('4/26 hari', false)
            ->assertSee('# Tertulis : Dua Ratus Enam Puluh Satu Ribu Lima Ratus Tiga Puluh Delapan Rupiah', false);

        $this->actingAs($admin)
            ->put(route('employees.payroll.update', $this->employee), [
                'month' => '2026-09',
                'days' => 10,
            ])
            ->assertRedirect(route('employees.payroll', ['month' => '2026-09']));

        $this->actingAs($admin)
            ->get(route('employees.slip', [$this->employee, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('10/26 hari', false)
            ->assertSee('653,846', false);

        $this->actingAs($admin)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Management Karyawan', false)
            ->assertSee('Zakki', false)
            ->assertDontSee('Slip Gaji', false);
    }

    public function test_late_after_fourteen_october_cuts_salary_per_second(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 01:00:00'));
        $this->employee->update(['salary' => 1_700_000, 'position' => 'Kasir']);

        foreach (['2026-10-13', '2026-10-14', '2026-10-15'] as $date) {
            WorkShift::query()->create([
                'employee_id' => $this->employee->id,
                'work_date' => $date,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ]);
        }

        Attendance::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-13',
            'clock_in_at' => '2026-10-13 01:45:00',
        ]);
        Attendance::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-14',
            'clock_in_at' => '2026-10-14 01:45:00',
        ]);
        Attendance::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 01:45:00',
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Telat',
            'email' => 'admin-telat@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $admin->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->actingAs($admin)
            ->get(route('employees.payroll', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Potongan telat')
            ->assertSee('Rp 681', false);

        $this->actingAs($admin)
            ->get(route('employees.slip', [$this->employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertSee('Potongan telat', false)
            ->assertSee('195,473', false);

        $this->actingAs($this->user)
            ->get(route('employees.mine'))
            ->assertOk()
            ->assertSee('Potongan telat')
            ->assertSee('Rp 681', false);

        $this->actingAs($this->user)
            ->get(route('employees.mine', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Potongan telat', false)
            ->assertSee('681', false);
    }

    public function test_employee_signs_contract_with_a_qr_code(): void
    {
        $this->actingAs($this->user)
            ->get(route('employees.contract'))
            ->assertOk()
            ->assertSee('SURAT PERJANJIAN KERJA', false)
            ->assertSee('Zakki', false)
            ->assertSee('Tanda tangan', false);

        $this->actingAs($this->user)
            ->post(route('employees.contract.sign'))
            ->assertRedirect();

        $this->employee->refresh();
        $url = route('employees.contract.verify', [$this->employee, $this->employee->contractSignature()]);

        $this->actingAs($this->user)
            ->get(route('employees.contract'))
            ->assertOk()
            ->assertSee('data:image/svg+xml', false)
            ->assertDontSee('>Tanda tangan<', false);

        $this->get($url)
            ->assertOk()
            ->assertSee('Tanda tangan sah', false)
            ->assertSee('Zakki', false);

        $this->get(route('employees.contract.verify', [$this->employee, '00000000000000000000']))
            ->assertNotFound();
    }

    public function test_cleaning_report_is_one_photo_per_area_every_two_hours(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 01:30:00'));
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-14',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);

        $html = $this->actingAs($this->user)
            ->get(route('cleaning.index'))
            ->assertOk()
            ->assertSee('Report Harian')
            ->assertSee('08:30')
            ->assertSee('name="tasks[lantai2][]"', false)
            ->assertSee('name="photo[meeting][0]"', false)
            ->assertSee('report-session report-wait', false)
            ->getContent();
        preg_match_all('/Cek: ([^·<]+)/', $html, $checks);
        $this->assertNotSame(trim($checks[1][0]), trim($checks[1][1]));
        $this->assertNotSame(trim($checks[1][1]), trim($checks[1][2]));

        $tasks = [];
        $photos = [];
        foreach (config('cleaning.areas') as $key => $area) {
            $tasks[$key] = array_keys($area['items']);
            foreach ($area['items'] as $index => $item) {
                $photos[$key][$index] = UploadedFile::fake()->image($key.$index.'.jpg');
            }
        }

        $this->actingAs($this->user)
            ->post(route('cleaning.store'), [
                'slot' => '99:99',
                'tasks' => $tasks,
                'photo' => $photos,
            ])
            ->assertSessionHasErrors('slot');

        $this->actingAs($this->user)
            ->post(route('cleaning.store'), [
                'slot' => '08:30',
                'tasks' => $tasks,
                'photo' => $photos,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $saved = CleaningReport::query()->where('slot', '08:30')->get();
        $this->assertSame(2, $saved->pluck('area')->unique()->count());
        $this->assertGreaterThan(2, $saved->count());
        $this->assertTrue(Storage::disk('biznet')->exists($saved->first()->photo));
        $this->assertFileDoesNotExist(public_path($saved->first()->photo));

        $this->actingAs($this->user)
            ->get(route('cleaning.index'))
            ->assertOk()
            ->assertSee('Sudah dikirim')
            ->assertSee('report-done', false);

        CleaningReport::query()->get()->each(function (CleaningReport $report) {
            $file = public_path($report->photo);
            if (is_file($file)) {
                unlink($file);
            }
        });
    }

    public function test_partial_cleaning_report_can_be_finished(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 01:30:00'));
        $shift = WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-14',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
        $areas = CleaningReport::plans($this->employee, $shift, '2026-10-14', CleaningReport::slots($shift, '2026-10-14'))[0]['areas'];
        $key = array_key_first($areas);

        $this->actingAs($this->user)
            ->post(route('cleaning.store'), [
                'slot' => '08:30',
                'tasks' => [$key => [0]],
                'photo' => [$key => [0 => UploadedFile::fake()->image('a.jpg')]],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($this->user)
            ->get(route('cleaning.index'))
            ->assertOk()
            ->assertSee('Kirim report 08:30')
            ->assertSee('Terkirim');

        $this->actingAs($this->user)
            ->post(route('cleaning.store'), [
                'slot' => '08:30',
                'tasks' => [$key => [0, 1]],
                'photo' => [$key => [1 => UploadedFile::fake()->image('b.jpg')]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, CleaningReport::query()->where('slot', '08:30')->where('area', $key)->count());
    }

    public function test_one_unchecked_cleaning_task_cuts_five_thousand(): void
    {
        $ignored = WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-14',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
        $this->travelTo(Carbon::parse('2026-10-15 01:00:00'));
        $this->assertSame(0, $this->employee->reportPenalty([$ignored]));

        $before = WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-15',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
        $this->travelTo(Carbon::parse('2026-10-15 02:31:00'));
        $this->assertSame(0, $this->employee->reportPenalty([$before]));

        $shift = WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-16',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-16 01:00:00'));
        $this->assertSame(0, $this->employee->reportPenalty([$shift]));

        $this->travelTo(Carbon::parse('2026-10-16 02:31:00'));
        $missed = 0;
        foreach (CleaningReport::plans($this->employee, $shift, '2026-10-16', CleaningReport::slots($shift, '2026-10-16'))[0]['areas'] as $area) {
            $missed += count($area['items']);
        }
        $this->actingAs($this->user)
            ->from(route('cleaning.index'))
            ->post(route('cleaning.store'), [
                'slot' => '08:30',
                'tasks' => ['lantai2' => [0]],
                'photo' => ['lantai2' => [0 => UploadedFile::fake()->image('late.jpg')]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('slot');
        $this->assertSame($missed * 100, $this->employee->reportPenalty([$shift]));

        $this->travelTo(Carbon::parse('2026-10-16 01:30:00'));
        $areas = CleaningReport::plans($this->employee, $shift, '2026-10-16', CleaningReport::slots($shift, '2026-10-16'))[0]['areas'];
        $tasks = [];
        $photos = [];
        foreach ($areas as $key => $area) {
            $tasks[$key] = array_keys($area['items']);
            foreach ($area['items'] as $index => $item) {
                $photos[$key][$index] = UploadedFile::fake()->image($key.$index.'.jpg');
            }
        }
        array_pop($tasks[array_key_first($tasks)]);

        $this->actingAs($this->user)
            ->post(route('cleaning.store'), [
                'slot' => '08:30',
                'tasks' => $tasks,
                'photo' => $photos,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, $this->employee->reportPenalty([$ignored, $before, $shift]));
        $this->travelTo(Carbon::parse('2026-10-16 02:31:00'));
        $this->assertSame(100, $this->employee->reportPenalty([$ignored, $before, $shift]));

        $admin = User::query()->create([
            'name' => 'Admin Denda',
            'email' => 'admin-denda@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $admin->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->actingAs($admin)
            ->get(route('employees.payroll', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Denda report')
            ->assertSee('Rp 100', false);

        $this->actingAs($this->user)
            ->get(route('employees.mine', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Denda report', false)
            ->assertSee('>100<', false);

        CleaningReport::query()->get()->each(function (CleaningReport $report) {
            $file = public_path($report->photo);
            if (is_file($file)) {
                unlink($file);
            }
        });
    }

    public function test_owner_can_review_daily_cleaning_reports(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 02:31:00'));
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-15',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
        CleaningReport::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-15',
            'slot' => '08:30',
            'area' => 'lantai1',
            'photo' => 'evidence/report/sample.jpg',
            'unchecked' => 1,
        ]);
        CleaningReport::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-15',
            'slot' => '08:30',
            'area' => 'meeting',
            'item' => 0,
            'photo' => 'evidence/report/sapu.jpg',
            'unchecked' => 0,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Report',
            'email' => 'admin-report@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $admin->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->actingAs($this->user)
            ->get(route('cleaning.review'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('cleaning.review', ['date' => '2026-10-15']))
            ->assertOk()
            ->assertSee('Report Harian')
            ->assertSee('Zakki')
            ->assertSee('evidence/report/sample.jpg', false)
            ->assertSee('1 tidak dicentang')
            ->assertSee('Sapu Ruang Meeting')
            ->assertSee('Dikerjakan')
            ->assertSee('Pel Ruang Meeting')
            ->assertSee('Belum dikerjakan')
            ->assertSee('Belum waktunya');
    }

    public function test_afternoon_shift_cleaning_report_starts_at_1330(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 06:30:00'));
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-14',
            'starts_at' => '14:00',
            'ends_at' => '22:00',
        ]);

        $this->actingAs($this->user)
            ->get(route('cleaning.index'))
            ->assertOk()
            ->assertSee('13:30')
            ->assertSee('name="tasks[lantai1][]"', false)
            ->assertSee('15:30')
            ->assertSee('Kirim report 15:30');
    }

    public function test_cleaning_areas_rotate_between_people_on_the_same_shift(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 01:30:00'));
        $role = Role::query()->where('name', 'karyawan')->firstOrFail();
        $partnerUser = User::query()->create([
            'name' => 'Ergina',
            'email' => 'ergina@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $partnerUser->roles()->sync([$role->id]);
        $partner = Employee::query()->create([
            'user_id' => $partnerUser->id,
            'outlet_id' => $this->outlet->id,
            'position' => 'Barista',
            'salary' => 1_700_000,
            'is_active' => true,
        ]);
        foreach (['2026-10-14', '2026-10-15'] as $date) {
            WorkShift::query()->create([
                'employee_id' => $this->employee->id,
                'work_date' => $date,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ]);
            WorkShift::query()->create([
                'employee_id' => $partner->id,
                'work_date' => $date,
                'starts_at' => '09:00',
                'ends_at' => '22:00',
            ]);
        }

        $pair = function (User $user, string $slot): string {
            $html = $this->actingAs($user)->get(route('cleaning.index'))->assertOk()->getContent();
            preg_match('/'.$slot.'.*?Cek: ([^·<]+)/s', $html, $match);

            return trim($match[1] ?? '');
        };

        $zakki = $pair($this->user, '08:30');
        $ergina = $pair($partnerUser, '08:30');
        $this->assertNotSame('', $zakki);
        $this->assertNotSame($zakki, $ergina);
        $this->assertSame([], array_intersect(
            array_map('trim', explode(',', $zakki)),
            array_map('trim', explode(',', $ergina)),
        ));
        foreach (['Halaman Luar', 'Lantai 1', 'Lantai 2', 'Ruang Meeting'] as $area) {
            $this->assertStringContainsString($area, $zakki.', '.$ergina);
        }
        $this->assertNotSame($zakki, $pair($this->user, '10:30'));

        $tasks = [];
        $photos = [];
        foreach (config('cleaning.areas') as $key => $area) {
            $tasks[$key] = array_keys($area['items']);
            foreach ($area['items'] as $index => $item) {
                $photos[$key][$index] = UploadedFile::fake()->image($key.$index.'.jpg');
            }
        }
        $this->actingAs($this->user)
            ->post(route('cleaning.store'), [
                'slot' => '08:30',
                'tasks' => $tasks,
                'photo' => $photos,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
        $saved = CleaningReport::query()->where('employee_id', $this->employee->id)->get();
        $this->assertSame(2, $saved->pluck('area')->unique()->count());
        $this->assertGreaterThan(2, $saved->count());

        $this->travelTo(Carbon::parse('2026-10-15 01:30:00'));
        $this->assertNotSame($zakki, $pair($this->user, '08:30'));

        CleaningReport::query()->get()->each(function (CleaningReport $report) {
            $file = public_path($report->photo);
            if (is_file($file)) {
                unlink($file);
            }
        });
    }

    public function test_afternoon_shift_gives_each_person_two_areas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 06:30:00'));
        $role = Role::query()->where('name', 'karyawan')->firstOrFail();
        $ids = [$this->employee->id];
        foreach (['Ergina' => 'ergina-siang@test.local', 'Jimmy' => 'jimmy-siang@test.local'] as $name => $email) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $user->roles()->sync([$role->id]);
            $ids[] = Employee::query()->create([
                'user_id' => $user->id,
                'outlet_id' => $this->outlet->id,
                'position' => 'Café Crew',
                'salary' => 1_700_000,
                'is_active' => true,
            ])->id;
        }
        foreach ($ids as $employeeId) {
            WorkShift::query()->create([
                'employee_id' => $employeeId,
                'work_date' => '2026-10-14',
                'starts_at' => '14:00',
                'ends_at' => '22:00',
            ]);
        }

        $html = $this->actingAs($this->user)->get(route('cleaning.index'))->assertOk()->assertSee('name="tasks[meeting][]"', false)->getContent();
        preg_match('/13:30.*?15:30/s', $html, $at1330);
        preg_match('/15:30.*?17:30/s', $html, $at1530);
        preg_match('/17:30.*?19:30/s', $html, $at1730);
        preg_match_all('/: ([^·<]+)/', $at1330[0], $assigned);
        $assigned = array_map('trim', $assigned[1]);
        $this->assertCount(3, array_unique($assigned));
        $together = implode(', ', $assigned);
        foreach (['Halaman Luar', 'Lantai 1', 'Lantai 2', 'Ruang Meeting'] as $area) {
            $this->assertStringContainsString($area, $together);
        }
        $this->assertStringNotContainsString($together, $at1530[0]);
    }

    public function test_slip_splits_components_and_keeps_bonus_off_prorata(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-komponen@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $admin->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->actingAs($admin)->put(route('employees.update', $this->employee), [
            'position' => 'Kasir',
            'base_salary' => 1_000_000,
            'job_allowance' => 300_000,
            'transport_allowance' => 200_000,
            'cleanliness_allowance' => 200_000,
            'sales_bonus' => 0,
            'deduction' => 0,
        ])->assertRedirect();

        $this->employee->refresh();
        $this->assertSame(1_700_000, (int) $this->employee->salary);

        $this->actingAs($admin)->put(route('employees.payroll.update', $this->employee), [
            'month' => '2026-09',
            'days' => 26,
        ])->assertRedirect();

        $full = $this->actingAs($admin)->get(route('employees.slip', [$this->employee, 'month' => '2026-09']));
        $full->assertOk()
            ->assertSee('Gaji Pokok', false)
            ->assertSee('Tunjangan Job', false)
            ->assertSee('Tunjangan Transportasi', false)
            ->assertSee('Tunjangan Kebersihan', false)
            ->assertSee('Bonus Penjualan', false)
            ->assertSee('1,000,000', false)
            ->assertSee('1,700,000', false)
            ->assertDontSee('Tunjangan Komunikasi', false);

        $this->employee->update(['sales_bonus' => 250_000]);
        $this->actingAs($admin)
            ->get(route('employees.slip', [$this->employee, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('1,950,000', false)
            ->assertSee('250,000', false);

        $this->employee->update(['sales_bonus' => 0]);
        $this->actingAs($admin)->put(route('employees.payroll.update', $this->employee), [
            'month' => '2026-09',
            'days' => 4,
        ]);
        $this->actingAs($admin)
            ->get(route('employees.slip', [$this->employee, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('261,538', false)
            ->assertSee('4/26 hari', false);

        $this->employee->update(['sales_bonus' => 100_000]);
        $this->actingAs($admin)
            ->get(route('employees.slip', [$this->employee, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('100,000', false)
            ->assertSee('361,538', false)
            ->assertSee('# Tertulis : Tiga Ratus Enam Puluh Satu Ribu Lima Ratus Tiga Puluh Delapan Rupiah', false);

        $this->employee->update(['sales_bonus' => 250_000, 'deduction' => 50_000]);
        $this->actingAs($admin)->put(route('employees.payroll.update', $this->employee), [
            'month' => '2026-09',
            'days' => 26,
        ]);
        $this->actingAs($admin)
            ->get(route('employees.slip', [$this->employee, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('1,950,000', false)
            ->assertSee('50,000', false)
            ->assertSee('1,900,000', false)
            ->assertSee('# Tertulis : Satu Juta Sembilan Ratus Ribu Rupiah', false);
    }

    public function test_recap_shows_clock_times_and_late_on_the_month_grid(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-recap@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);

        $this->travelTo('2026-10-07 02:00:00');
        Attendance::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-06',
            'clock_in_at' => '2026-10-06 01:15:00',
            'clock_out_at' => '2026-10-06 10:02:00',
            'is_late' => true,
        ]);
        Attendance::query()->create([
            'employee_id' => $this->employee->id,
            'outlet_id' => $this->outlet->id,
            'work_date' => '2026-10-03',
            'clock_in_at' => '2026-10-03 07:01:00',
            'is_late' => false,
        ]);
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-06',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-03',
            'starts_at' => '14:00',
            'ends_at' => '22:00',
        ]);
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-04',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-05',
        ]);
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-20',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('employees.recap', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Zakki')
            ->assertSee('08:15')
            ->assertSee('14:01')
            ->assertSee('17:02')
            ->assertSee('Telat')
            ->assertSee('recap-time">L</span>', false)
            ->assertSee('class="recap-miss"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="recap-off"'));
        $this->assertSame(1, substr_count($html, 'class="recap-miss"'));
        $this->assertSame(1, substr_count($html, 'class="recap-late"'));
    }

    public function test_three_daily_videos_are_shared_by_the_crew(): void
    {
        $this->travelTo(Carbon::parse('2026-10-16 02:00:00'));
        $role = Role::query()->where('name', 'karyawan')->firstOrFail();
        foreach (['Naurah', 'Ergina', 'Jimmy'] as $name) {
            $user = User::query()->create([
                'name' => $name,
                'email' => strtolower($name).'@test.local',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $user->roles()->sync([$role->id]);
            Employee::query()->create([
                'user_id' => $user->id,
                'outlet_id' => $this->outlet->id,
                'position' => 'Cafe Crew',
                'salary' => 1_700_000,
                'is_active' => true,
            ]);
        }

        $this->assertSame(0, ContentLink::share($this->outlet->id, '2026-10-01', '2026-10-31', 4));

        $this->actingAs($this->user)
            ->get(route('content.index'))
            ->assertOk()
            ->assertSee('3 link video');

        $this->actingAs($this->user)
            ->post(route('content.store'), ['url' => 'https://www.instagram.com/reel/abc?igsh=1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->user)
            ->post(route('content.store'), ['url' => 'https://instagram.com/reel/abc'])
            ->assertSessionHasErrors('url');

        $this->actingAs($this->user)
            ->post(route('content.store'), ['url' => 'https://example.com/reel/abc'])
            ->assertSessionHasErrors('url');

        $this->actingAs($this->user)->post(route('content.store'), ['url' => 'https://instagram.com/reel/def'])->assertSessionHas('success');
        $this->actingAs($this->user)->post(route('content.store'), ['url' => 'https://instagram.com/reel/ghi'])->assertSessionHas('success');
        $this->actingAs($this->user)->post(route('content.store'), ['url' => 'https://instagram.com/reel/jkl'])->assertSessionHasErrors('url');
        $this->assertSame(3, ContentLink::query()->whereDate('work_date', '2026-10-16')->count());

        $this->actingAs($this->user)->get(route('content.review'))->assertForbidden();

        $admin = User::query()->create([
            'name' => 'Admin Konten',
            'email' => 'admin-konten@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $admin->outlets()->sync([$this->outlet->id => ['is_default' => true]]);

        $this->actingAs($admin)
            ->get(route('content.review', ['date' => '2026-10-16']))
            ->assertOk()
            ->assertSee('Zakki')
            ->assertSee('https://instagram.com/reel/abc', false);

        $this->travelTo(Carbon::parse('2026-10-18 02:00:00'));
        $this->assertSame(7_500, ContentLink::share($this->outlet->id, '2026-10-01', '2026-10-31', 4));

        $this->actingAs($admin)
            ->get(route('employees.payroll', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Denda konten')
            ->assertSee('Rp 7.500', false);
    }

    public function test_karyawan_lands_on_attendance_after_login(): void
    {
        $this->post(route('login'), [
            'email' => 'zakki@test.local',
            'password' => 'password',
        ])->assertRedirect(route('attendance.index'));
    }
}
