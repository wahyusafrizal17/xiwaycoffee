<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        $this->expectException(\Illuminate\Validation\ValidationException::class);
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
        );

        $this->assertTrue($row->is_late);
        $this->assertNotNull($row->selfie_path);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'is_late' => 1,
        ]);
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

    public function test_karyawan_lands_on_attendance_after_login(): void
    {
        $this->post(route('login'), [
            'email' => 'zakki@test.local',
            'password' => 'password',
        ])->assertRedirect(route('attendance.index'));
    }
}
