<?php

namespace App\Domains\Schedules\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentScheduleRepository implements ScheduleRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $today = Schedule::today();
        // Akan datang dulu (terdekat), lalu yang lewat (terbaru): bendera lewat, tanggal naik untuk yang akan datang, lalu menurun.
        $base = Schedule::query()->with(['school', 'entries:id,schedule_id,status'])
            ->orderByRaw('date < ?', [$today])
            ->orderByRaw('case when date >= ? then date end asc', [$today])
            ->orderByDesc('date')->orderBy('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->where('location', 'like', "%{$v}%")),
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('period', function (Builder $q, $v) {
                    match ($v) {
                        'akan-datang' => $q->where('date', '>=', Schedule::today()),
                        'lewat' => $q->where('date', '<', Schedule::today()),
                        default => null,
                    };
                }),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function upcoming(int $limit): Collection
    {
        return Schedule::query()->with('school')->withCount('entries')
            ->where('date', '>=', Schedule::today())->orderBy('date')->orderBy('id')->limit($limit)->get();
    }

    public function create(array $attributes): Schedule
    {
        return Schedule::create($attributes);
    }

    public function update(Schedule $schedule, array $attributes): Schedule
    {
        $schedule->update($attributes);

        return $schedule;
    }

    public function delete(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function targets(Schedule $current): Collection
    {
        return Schedule::query()
            ->where('school_id', $current->school_id)->where('id', '!=', $current->id)
            ->where('date', '>=', Schedule::today())->orderBy('date')->orderBy('id')->get();
    }

    public function candidates(Schedule $schedule): Collection
    {
        return RegistrationStudent::query()->with(['student', 'registration'])
            ->where('school_id', $schedule->school_id)
            ->where('status', RegistrationStatus::Terverifikasi->value)
            ->whereNull('schedule_id')
            ->orderBy('id')->get();
    }

    public function lockEntries(array $ids): Collection
    {
        return RegistrationStudent::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }
}
