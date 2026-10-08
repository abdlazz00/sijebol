<?php

namespace App\Domains\Students\Repositories\Contracts;

use App\Domains\Students\Models\Student;
use Illuminate\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface
{
    /**
     * Filter dibaca dari query string: filter[search], filter[docs], filter[eligible], filter[school_id].
     *
     * @return LengthAwarePaginator<int, Student>
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Student;

    /** @param array<string, mixed> $attributes */
    public function update(Student $student, array $attributes): Student;

    public function delete(Student $student): void;
}
