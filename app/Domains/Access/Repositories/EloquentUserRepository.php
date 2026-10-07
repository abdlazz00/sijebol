<?php

namespace App\Domains\Access\Repositories;

use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $base = User::query()
            ->with(['roles', 'school'])
            ->withExists(['activations as has_pending_activation' => fn ($q) => $q->pending()])
            ->orderBy('name');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $w) => $w->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")
                )),
                AllowedFilter::callback('role', fn (Builder $q, $value) => $q->whereHas('roles', fn (Builder $r) => $r->where('roles.id', $value))),
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('status', function (Builder $q, $value) {
                    if ($status = UserStatus::tryFrom((string) $value)) {
                        $q->whereStatus($status);
                    }
                }),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }

    public function activeSuperAdminCount(): int
    {
        return User::role(Role::SUPER_ADMIN)->where('is_active', true)->count();
    }
}
