<?php

namespace App\Domains\Schools\Repositories\Contracts;

use App\Domains\Schools\Models\School;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SchoolRepositoryInterface
{
    /** @return LengthAwarePaginator<int, School> */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /** @return Collection<int, School> */
    public function options(): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): School;

    /** @param array<string, mixed> $attributes */
    public function update(School $school, array $attributes): School;

    public function delete(School $school): void;

    public function findByRegistrationToken(string $token): ?School;

    /**
     * Sekolah dengan jumlah siswanya, urut nama; `$schoolId` membatasi ke satu sekolah.
     *
     * @return Collection<int, School>
     */
    public function withStudentCount(?int $schoolId): Collection;
}
