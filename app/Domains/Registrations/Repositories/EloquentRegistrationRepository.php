<?php

namespace App\Domains\Registrations\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentRegistrationRepository implements RegistrationRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $base = Registration::query()->with(['school', 'entries:id,registration_id,status'])->latest('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->where('title', 'like', "%{$v}%")),
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('status', function (Builder $q, $v) {
                    // Draf = pendaftaran yang belum diajukan (bisa saja belum berisi entri).
                    $v === RegistrationStatus::Draf->value
                        ? $q->whereNull('submitted_at')
                        : $q->whereHas('entries', fn (Builder $e) => $e->where('status', (string) $v));
                }),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function queue(int $perPage = 20): LengthAwarePaginator
    {
        $base = RegistrationStudent::query()
            ->where('status', RegistrationStatus::Diajukan->value)
            ->with(['student', 'registration.school'])
            ->orderBy('updated_at')->orderBy('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->whereHas(
                    'student',
                    fn (Builder $s) => $s->where(fn (Builder $w) => $w->where('name', 'like', "%{$v}%")->orWhere('nisn', 'like', "%{$v}%"))
                )),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function lockEntries(array $ids): Collection
    {
        return RegistrationStudent::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()
            ->with(['student.documents', 'student.registrationEntries', 'registration'])->get();
    }

    public function create(array $attributes): Registration
    {
        return Registration::create($attributes);
    }

    public function update(Registration $registration, array $attributes): Registration
    {
        $registration->update($attributes);

        return $registration;
    }

    public function delete(Registration $registration): void
    {
        $registration->delete();
    }

    public function syncDraftEntries(Registration $registration, array $studentIds): void
    {
        $registration->entries()->whereNotIn('student_id', $studentIds)->delete();
        $existing = $registration->entries()->pluck('student_id')->all();

        foreach (array_diff($studentIds, $existing) as $id) {
            $registration->entries()->create([
                'school_id' => $registration->school_id,
                'student_id' => $id,
                'status' => RegistrationStatus::Draf,
            ]);
        }
    }
}
