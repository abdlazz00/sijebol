<?php

namespace App\Domains\Notifications\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    /** @return LengthAwarePaginator<int, DatabaseNotification> */
    public function paginateFor(User $user, bool $unreadOnly, int $perPage = 15): LengthAwarePaginator;

    /** @return Collection<int, DatabaseNotification> */
    public function latestFor(User $user, int $limit = 8): Collection;

    public function unreadCount(User $user): int;

    /** @throws ModelNotFoundException */
    public function findFor(User $user, string $id): DatabaseNotification;

    public function markRead(DatabaseNotification $notification): void;

    public function markAllRead(User $user): void;

    /** @return Collection<int, User> */
    public function recipientsWithPermission(string $permission, ?int $schoolId): Collection;
}
