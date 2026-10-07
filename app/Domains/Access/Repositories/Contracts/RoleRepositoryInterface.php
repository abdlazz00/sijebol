<?php

namespace App\Domains\Access\Repositories\Contracts;

use App\Domains\Access\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    /** @return Collection<int, Role> */
    public function all(): Collection;

    public function find(int $id): Role;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Role;

    /** @param array<string, mixed> $attributes */
    public function update(Role $role, array $attributes): Role;

    public function delete(Role $role): void;

    public function userCount(Role $role): int;
}
