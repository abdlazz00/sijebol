<?php

namespace App\Domains\Registrations\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentRegistrationRepository implements RegistrationRepositoryInterface
{
    public function queue(int $perPage = 20): LengthAwarePaginator
    {
        $base = RegistrationStudent::query()
            ->where('status', RegistrationStatus::MenungguVerifikasi->value)
            ->with(['student', 'schedule.school'])
            ->orderBy('updated_at')->orderBy('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::exact('school_id'),
                AllowedFilter::exact('schedule_id'),
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->whereHas(
                    'student',
                    fn (Builder $s) => $s->where(fn (Builder $w) => $w->where('name', 'like', "%{$v}%")->orWhere('nisn', 'like', "%{$v}%"))
                )),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): RegistrationStudent
    {
        return RegistrationStudent::create($attributes);
    }

    public function lockEntries(array $ids): Collection
    {
        return RegistrationStudent::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()
            ->with(['student.documents', 'student.registrationEntries', 'schedule'])->get();
    }
}
