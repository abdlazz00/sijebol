<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use App\Domains\Access\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly ActivationService $activations,
    ) {}

    /** @return LengthAwarePaginator<int, User> */
    public function list(): LengthAwarePaginator
    {
        return $this->users->paginate();
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, role_id: int, school_id?: ?int}  $data
     * @return array{user: User, token: string}
     */
    public function create(array $data, User $actor): array
    {
        $role = $this->roles->find($data['role_id']);
        $this->assertRoleAllowed($role, $data['school_id'] ?? null, $actor);

        return DB::transaction(function () use ($data, $role, $actor) {
            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $this->normalizeEmail($data['email']),
                'phone' => $data['phone'] ?? null,
                'school_id' => $role->is_school_scoped ? $data['school_id'] : null,
                'created_by' => $actor->id,
            ]);
            $user->assignRole($role);

            return ['user' => $user, 'token' => $this->activations->issue($user)];
        });
    }

    /** @param array{name: string, email: string, phone?: ?string, role_id: int, school_id?: ?int} $data */
    public function update(User $user, array $data, User $actor): User
    {
        $role = $this->roles->find($data['role_id']);
        $current = $user->currentRole();
        $roleChanged = $current?->id !== $role->id;

        if ($roleChanged) {
            if ($user->is($actor)) {
                throw new AccessRuleException('Anda tidak dapat mengubah role diri sendiri.');
            }
            $this->assertNotLastSuperAdmin($user, 'Super-admin aktif terakhir tidak dapat diturunkan role-nya.');
        }
        $this->assertRoleAllowed($role, $data['school_id'] ?? null, $actor);

        return DB::transaction(function () use ($user, $data, $role, $roleChanged) {
            $attributes = [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'school_id' => $role->is_school_scoped ? $data['school_id'] : null,
            ];
            if ($user->activated_at === null) {
                $attributes['email'] = $this->normalizeEmail($data['email']);
            }

            $this->users->update($user, $attributes);

            if ($roleChanged) {
                $user->syncRoles($role);
            }

            return $user;
        });
    }

    public function deactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new AccessRuleException('Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $this->assertNotLastSuperAdmin($user, 'Super-admin aktif terakhir tidak dapat dinonaktifkan.');

        $this->users->update($user, ['is_active' => false]);
        activity('akses')->performedOn($user)->causedBy($actor)->log('user.dinonaktifkan');
    }

    public function activate(User $user): void
    {
        $this->users->update($user, ['is_active' => true]);
        activity('akses')->performedOn($user)->causedBy(auth()->user())->log('user.diaktifkan');
    }

    public function resendActivation(User $user): string
    {
        if ($user->activated_at !== null) {
            throw new AccessRuleException('Akun ini sudah aktif; link aktivasi tidak diperlukan.');
        }

        return $this->activations->issue($user);
    }

    private function assertRoleAllowed(Role $role, ?int $schoolId, User $actor): void
    {
        if (! AccessGuard::canGrantRole($actor, $role)) {
            throw new AccessRuleException('Anda tidak dapat memberikan role dengan izin di luar izin Anda.');
        }
        if ($role->is_school_scoped && $schoolId === null) {
            throw new AccessRuleException('Role ini terikat sekolah; pilih sekolah.');
        }
        if (! $role->is_school_scoped && $schoolId !== null) {
            throw new AccessRuleException('Role ini tidak terikat sekolah; kosongkan sekolah.');
        }
        // Pelaku yang terikat sekolah tidak boleh menempatkan pengguna di sekolah lain atau tanpa sekolah.
        if ($actor->school_id !== null && $schoolId !== $actor->school_id) {
            throw new AccessRuleException('Anda hanya dapat mengelola pengguna di sekolah Anda sendiri.');
        }
    }

    private function assertNotLastSuperAdmin(User $user, string $message): void
    {
        if ($user->hasRole(Role::SUPER_ADMIN) && $user->is_active && $this->users->activeSuperAdminCount() <= 1) {
            throw new AccessRuleException($message);
        }
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
