<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = config('pos.roles', []);
        $permissions = config('pos.permissions', []);
        $map = config('pos.role_permissions', []);

        foreach ($roles as $name => $label) {
            Role::query()->updateOrCreate(
                ['name' => $name],
                ['label' => $label, 'description' => $label],
            );
        }

        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'label' => Str::of($name)->replace('.', ' ')->headline()->toString(),
                    'module' => Str::before($name, '.'),
                ],
            );
        }

        $allNames = Permission::query()->pluck('name');

        foreach ($map as $roleName => $granted) {
            $role = Role::query()->where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $names = in_array('*', $granted, true)
                ? $allNames
                : collect($granted);

            $role->permissions()->sync(
                Permission::query()->whereIn('name', $names)->pluck('id')
            );
        }
    }
}
