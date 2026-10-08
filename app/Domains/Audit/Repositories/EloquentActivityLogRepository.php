<?php

namespace App\Domains\Audit\Repositories;

use App\Domains\Audit\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentActivityLogRepository implements ActivityLogRepositoryInterface
{
    private const ZONE = 'Asia/Jakarta';

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return QueryBuilder::for($this->base())
            ->allowedFilters(
                AllowedFilter::callback('user', fn (Builder $q, $v) => $q->where('causer_type', User::class)->where('causer_id', $v)),
                AllowedFilter::exact('type', 'log_name'),
                AllowedFilter::callback('from', fn (Builder $q, $v) => $q->where('created_at', '>=', $this->boundary($v, false))),
                AllowedFilter::callback('to', fn (Builder $q, $v) => $q->where('created_at', '<=', $this->boundary($v, true))),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function causers(): Collection
    {
        return User::query()->withoutGlobalScopes()
            ->whereIn('id', Activity::query()->where('causer_type', User::class)->select('causer_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function recentFor(User $user, int $limit = 10): Collection
    {
        return $this->base()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $c) => $c->where('causer_type', User::class)->where('causer_id', $user->id))
                ->orWhere(fn (Builder $s) => $s->where('subject_type', User::class)->where('subject_id', $user->id)))
            ->limit($limit)
            ->get();
    }

    /** @return Builder<Activity> */
    private function base(): Builder
    {
        return Activity::query()->with(['causer', 'subject'])->latest('created_at')->latest('id');
    }

    /** Tanggal masukan dibaca di zona Asia/Jakarta, lalu diubah ke zona penyimpanan. */
    private function boundary(string $date, bool $endOfDay): Carbon
    {
        $day = Carbon::parse($date, self::ZONE);

        return ($endOfDay ? $day->endOfDay() : $day->startOfDay())->setTimezone(config('app.timezone'));
    }
}
