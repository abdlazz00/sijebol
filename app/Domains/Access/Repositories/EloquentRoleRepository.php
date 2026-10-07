<?php

namespace App\Domains\Access\Repositories;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get();
    }

    public function find(int $id): Role
    {
        return Role::query()->with('permissions')->findOrFail($id);
    }

    public function create(array $attributes): Role
    {
        /** @var Role $role */
        $role = Role::create($attributes + ['guard_name' => 'web']);

        return $role;
    }

    public function update(Role $role, array $attributes): Role
    {
        $role->update($attributes);

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function userCount(Role $role): int
    {
        return $role->users()->count();
    }
}
