<?php

namespace App\Domains\Students\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Repositories\Contracts\StudentRepositoryInterface;
use App\Domains\Students\Services\EligibilityService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentStudentRepository implements StudentRepositoryInterface
{
    public function __construct(private readonly EligibilityService $eligibility) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $base = Student::query()->with(['school', 'documents', 'registrationEntries.schedule'])->orderBy('name')->orderBy('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                // NIK hanya cocok persis: pencarian sebagian akan membongkar masker NIK di tampilan.
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->where(
                    fn (Builder $w) => $w->where('name', 'like', "%{$v}%")
                        ->orWhere('nisn', 'like', "%{$v}%")
                        ->orWhere('nik', (string) $v)
                )),
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('docs', function (Builder $q, $v) {
                    if ($v === 'lengkap') {
                        $q->whereHas('documents', $this->ofType('kk'))->whereHas('documents', $this->ofType('akta_lahir'));
                    } elseif ($v === 'kurang') {
                        $q->where(fn (Builder $w) => $w
                            ->whereDoesntHave('documents', $this->ofType('kk'))
                            ->orWhereDoesntHave('documents', $this->ofType('akta_lahir')));
                    }
                }),
                AllowedFilter::callback('eligible', function (Builder $q, $v) {
                    if ((string) $v !== '1') {
                        return;
                    }
                    $q->where('birth_date', '<=', $this->eligibility->latestBirthDate())
                        ->whereHas('documents', $this->ofType('kk'))
                        ->whereHas('documents', $this->ofType('akta_lahir'));
                }),
                // Pendaftaran terkini: `none` = belum pernah mendaftar; status aktif = pendaftaran aktifnya; status lain =
                // tanpa pendaftaran aktif dan pendaftaran terbarunya berstatus itu.
                AllowedFilter::callback('registration', function (Builder $q, $v) {
                    $status = RegistrationStatus::tryFrom((string) $v);
                    if ((string) $v === 'none') {
                        $q->whereDoesntHave('registrationEntries');
                    } elseif ($status?->isActive()) {
                        $q->whereHas('registrationEntries', fn ($e) => $e->where('status', $status->value));
                    } elseif ($status !== null) {
                        $q->whereDoesntHave('registrationEntries', fn ($e) => $e->whereIn('status', RegistrationStatus::values(RegistrationStatus::active())))
                            ->whereHas('registrationEntries', fn ($e) => $e->where('status', $status->value)
                                ->whereRaw('registration_students.id = (select max(r.id) from registration_students r where r.student_id = registration_students.student_id)'));
                    }
                }),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): Student
    {
        return Student::create($attributes);
    }

    public function update(Student $student, array $attributes): Student
    {
        $student->update($attributes);

        return $student;
    }

    public function delete(Student $student): void
    {
        $student->delete();
    }

    /** Syarat relasi dokumen dengan jenis tertentu. */
    private function ofType(string $type): Closure
    {
        return fn ($documents) => $documents->where('type', $type);
    }
}
