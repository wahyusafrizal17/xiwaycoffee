<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesPermissions
{
    protected function allow(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
