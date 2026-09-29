<?php

namespace Tests\Feature;

use App\Models\Attendance;
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

    public function test_shift_clock_in_is_late_after_thirty_minutes_before_start(): void
    {
        WorkShift::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => now()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);

        $service = app(AttendanceService::class);
        $this->travelTo(now()->setTime(8, 30));
        $onTime = $service->clockIn(
            $this->employee,
            -6.8721000,
            107.5425000,
            UploadedFile::fake()->image('selfie.jpg'),
        );
        $this->assertFalse($onTime->is_late);

        $onTime->delete();
        $this->travelTo(now()->setTime(8, 31));

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

        $late = $service->clockIn(
            $this->employee,
            -6.8721000,
            107.5425000,
            UploadedFile::fake()->image('selfie.jpg'),
            'Macet',
        );
        $this->assertTrue($late->is_late);
        $this->assertSame('Macet', $late->late_reason);
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
        $this->travelTo(now()->setTime(8, 0));
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
            'position' => 'Barista',
            'salary' => 3000000,
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
            ->assertSee('09.00-22.00*');

        $this->assertSame(128, WorkShift::query()->count());

        $zakiId = User::query()->where('email', 'zakki@xiway.local')->first()->employee->id;
        $shift = WorkShift::query()->where('employee_id', $zakiId)->whereDate('work_date', '2026-09-30')->first();
        $this->assertSame('09:00', $shift->starts_at);
        $this->assertSame('22:00', $shift->ends_at);

        $rere = WorkShift::query()->where('employee_id', $ergina->employee->id)->whereDate('work_date', '2026-09-30')->first();
        $this->assertSame('09:00', $rere->starts_at);
        $this->assertSame('17:00', $rere->ends_at);

        $off = WorkShift::query()->where('employee_id', $ergina->employee->id)->whereDate('work_date', '2026-10-01')->first();
        $this->assertNull($off->starts_at);
    }

    public function test_karyawan_lands_on_attendance_after_login(): void
    {
        $this->post(route('login'), [
            'email' => 'zakki@test.local',
            'password' => 'password',
        ])->assertRedirect(route('attendance.index'));
    }
}
