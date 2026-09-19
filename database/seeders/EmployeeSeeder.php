<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = Outlet::query()->where('code', 'CMH')->first()
            ?? Outlet::query()->where('is_active', true)->first();

        if (! $outlet) {
            return;
        }

        // Approximate Cimahi — adjust in Outlets settings if needed.
        if (! $outlet->latitude || ! $outlet->longitude) {
            $outlet->update([
                'latitude' => -6.8721000,
                'longitude' => 107.5425000,
                'geo_radius_m' => 150,
            ]);
        }

        $role = Role::query()->where('name', 'karyawan')->firstOrFail();
        $password = Hash::make('password');

        $staff = [
            ['name' => 'Zakki', 'email' => 'zakki@xiway.local', 'position' => 'Barista', 'salary' => 3_000_000],
            ['name' => 'Naurah', 'email' => 'naurah@xiway.local', 'position' => 'Assisten Barista', 'salary' => 1_700_000],
            ['name' => 'Ergina', 'email' => 'ergina@xiway.local', 'position' => 'Kasir', 'salary' => 1_700_000],
            ['name' => 'Jimmy', 'email' => 'jimmy@xiway.local', 'position' => 'Waiters', 'salary' => 2_500_000],
        ];

        foreach ($staff as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
            $user->roles()->sync([$role->id]);
            $user->outlets()->sync([$outlet->id => ['is_default' => true]]);

            Employee::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'outlet_id' => $outlet->id,
                    'position' => $row['position'],
                    'salary' => $row['salary'],
                    'is_active' => true,
                ],
            );
        }
    }
}
