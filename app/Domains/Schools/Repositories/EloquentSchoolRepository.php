<?php

namespace App\Domains\Schools\Repositories;

use App\Domains\Schools\Models\School;
use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(School::query()->withCount('users')->orderBy('name'))
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $w) => $w->where('name', 'like', "%{$value}%")->orWhere('npsn', 'like', "%{$value}%")
                )),
                AllowedFilter::exact('level'),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function options(): Collection
    {
        return School::query()->orderBy('name')->get(['id', 'name']);
    }

    public function create(array $attributes): School
    {
        return School::create($attributes);
    }

    public function update(School $school, array $attributes): School
    {
        $school->update($attributes);

        return $school;
    }

    public function withStudentCount(?int $schoolId): Collection
    {
        return School::query()->withCount('students')
            ->when($schoolId !== null, fn (Builder $q) => $q->whereKey($schoolId))
            ->orderBy('name')->get();
    }

    public function findByRegistrationToken(string $token): ?School
    {
        return School::query()->where('registration_token', $token)->first();
    }
}
