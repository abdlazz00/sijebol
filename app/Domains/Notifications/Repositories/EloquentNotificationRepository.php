<?php

namespace App\Domains\Notifications\Repositories;

use App\Domains\Access\Models\Role;
use App\Domains\Notifications\Repositories\Contracts\NotificationRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function paginateFor(User $user, bool $unreadOnly, int $perPage = 15): LengthAwarePaginator
    {
        $query = $unreadOnly ? $user->unreadNotifications() : $user->notifications();

        return $query->paginate($perPage)->withQueryString();
    }

    public function latestFor(User $user, int $limit = 8): Collection
    {
        return $user->notifications()->limit($limit)->get();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function findFor(User $user, string $id): DatabaseNotification
    {
        return $user->notifications()->whereKey($id)->firstOrFail();
    }

    public function markRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function recipientsWithPermission(string $permission, ?int $schoolId): Collection
    {
        // Tanpa global scope: pengirim operator sekolah tidak boleh membatasi daftar penerima.
        return User::query()->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->permission($permission)
                ->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SUPER_ADMIN)))
            ->when($schoolId !== null, fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->whereNull('school_id')->orWhere('school_id', $schoolId)
            ))
            ->get();
    }
}
