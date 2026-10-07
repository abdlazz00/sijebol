<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Models\User;

final class AccessGuard
{
    /** @return list<string> */
    public static function permissionsOf(User $user): array
    {
        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return Permission::values();
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }

    /** Hanya boleh memberi role yang seluruh izinnya sudah dimiliki; super-admin hanya oleh super-admin. */
    public static function canGrantRole(User $actor, Role $role): bool
    {
        if ($actor->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        if ($role->isProtected()) {
            return false;
        }

        return array_diff($role->permissions->pluck('name')->all(), self::permissionsOf($actor)) === [];
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        $role = $target->currentRole();

        return $role === null || self::canGrantRole($actor, $role);
    }
}
