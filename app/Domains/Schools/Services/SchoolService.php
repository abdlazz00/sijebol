<?php

namespace App\Domains\Schools\Services;

use App\Domains\Schools\Models\School;
use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SchoolService
{
    public function __construct(private readonly SchoolRepositoryInterface $schools) {}

    /** @return LengthAwarePaginator<int, School> */
    public function list(): LengthAwarePaginator
    {
        return $this->schools->paginate();
    }

    /** @return Collection<int, School> */
    public function options(): Collection
    {
        return $this->schools->options();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): School
    {
        return $this->schools->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(School $school, array $data): School
    {
        return $this->schools->update($school, $data);
    }
}
