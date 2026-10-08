<?php

namespace App\Domains\Audit\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

interface ActivityLogRepositoryInterface
{
    /**
     * Filter dibaca dari query string: filter[user], filter[type], filter[from], filter[to].
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator;

    /** @return Collection<int, User> */
    public function causers(): Collection;

    /** @return Collection<int, Activity> */
    public function recentFor(User $user, int $limit = 10): Collection;
}
