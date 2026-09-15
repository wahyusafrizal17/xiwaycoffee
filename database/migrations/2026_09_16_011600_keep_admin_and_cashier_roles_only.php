<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new RoleSeeder)->run();

        $adminId = Role::query()->where('name', 'admin')->value('id');
        $cashierId = Role::query()->where('name', 'cashier')->value('id');

        if (! $adminId || ! $cashierId) {
            return;
        }

        foreach (User::query()->with('roles')->get() as $user) {
            $names = $user->roles->pluck('name');
            $user->roles()->sync(
                $names->intersect(['super_admin', 'outlet_manager', 'admin'])->isNotEmpty()
                    ? [$adminId]
                    : [$cashierId]
            );
        }

        Role::query()->whereNotIn('name', ['admin', 'cashier'])->each(function (Role $role) {
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        });
    }

    public function down(): void
    {
        //
    }
};
