<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    /** @return Collection<int, Role> */
    public function list(): Collection
    {
        return $this->roles->all();
    }

    /** @param array{name: string, description?: ?string, is_school_scoped: bool, permissions: list<string>} $data */
    public function create(array $data, User $actor): Role
    {
        $permissions = $this->assertKnown($data['permissions']);
        $this->assertOwned($permissions, $actor);

        return DB::transaction(function () use ($data, $permissions, $actor) {
            $role = $this->roles->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_school_scoped' => $data['is_school_scoped'],
            ]);
            $this->sync($role, $permissions, $actor);

            return $role;
        });
    }

    /** @param array{name: string, description?: ?string, is_school_scoped: bool, permissions: list<string>} $data */
    public function update(Role $role, array $data, User $actor): Role
    {
        if ($role->isProtected()) {
            throw new AccessRuleException('Role super-admin dilindungi dan tidak dapat diubah.');
        }
        if ((bool) $data['is_school_scoped'] !== (bool) $role->is_school_scoped && $this->roles->userCount($role) > 0) {
            throw new AccessRuleException('Pengaturan "terikat sekolah" tidak dapat diubah selama role masih dipakai pengguna.');
        }

        $requested = $this->assertKnown($data['permissions']);
        $current = $role->permissions->pluck('name')->all();
        $this->assertOwned(array_diff($requested, $current), $actor);

        $owned = AccessGuard::permissionsOf($actor);
        // Izin yang tidak dimiliki penyunting dibiarkan apa adanya; sisanya mengikuti permintaan.
        $final = array_values(array_unique([
            ...array_diff($current, $owned),
            ...array_intersect($requested, $owned),
        ]));

        return DB::transaction(function () use ($role, $data, $final, $current, $actor) {
            $this->roles->update($role, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_school_scoped' => $data['is_school_scoped'],
            ]);
            $this->sync($role, $final, $actor, $current);

            return $role;
        });
    }

    public function delete(Role $role, User $actor): void
    {
        if ($role->isProtected()) {
            throw new AccessRuleException('Role super-admin dilindungi dan tidak dapat dihapus.');
        }
        if (! AccessGuard::canGrantRole($actor, $role)) {
            throw new AccessRuleException('Anda tidak dapat menghapus role dengan izin di luar izin Anda.');
        }
        if ($this->roles->userCount($role) > 0) {
            throw new AccessRuleException('Role masih dipakai pengguna dan tidak dapat dihapus.');
        }

        $this->roles->delete($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('akses')->causedBy($actor)->withProperties(['role' => $role->name])->log('role.dihapus');
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function assertKnown(array $names): array
    {
        if (array_diff($names, Permission::values()) !== []) {
            throw new AccessRuleException('Terdapat izin yang tidak dikenal.');
        }

        return array_values(array_unique($names));
    }

    /** @param array<int, string> $added */
    private function assertOwned(array $added, User $actor): void
    {
        if (array_diff($added, AccessGuard::permissionsOf($actor)) !== []) {
            throw new AccessRuleException('Anda tidak dapat memberikan izin yang tidak Anda miliki.');
        }
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $before
     */
    private function sync(Role $role, array $permissions, User $actor, array $before = []): void
    {
        $role->syncPermissions($permissions);

        $added = array_values(array_diff($permissions, $before));
        $removed = array_values(array_diff($before, $permissions));

        if ($added !== [] || $removed !== []) {
            activity('akses')->performedOn($role)->causedBy($actor)
                ->withProperties(['ditambah' => $added, 'dicabut' => $removed])
                ->log('role.izin_diubah');
        }
    }
}
