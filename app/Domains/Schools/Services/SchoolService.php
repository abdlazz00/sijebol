<?php

namespace App\Domains\Schools\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Schools\Models\School;
use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use App\Models\User;
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

    /** Daftar sekolah hanya untuk staf tanpa sekolah; operator terkunci ke sekolahnya. */
    public function optionsFor(User $actor): ?Collection
    {
        return $actor->school_id === null
            ? $this->options()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()
            : null;
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

    public function delete(School $school, User $actor): void
    {
        if ($school->students()->exists() || $school->users()->exists() || $school->schedules()->exists() || $school->registrationEntries()->exists()) {
            throw new AccessRuleException('Sekolah masih memiliki data terkait (siswa, sesi jadwal, atau pengguna) dan tidak dapat dihapus.');
        }

        $this->schools->delete($school);
        activity('akses')->performedOn($school)->causedBy($actor)->log('sekolah.dihapus');
    }

    /**
     * Link pendaftaran per sekolah: operator hanya sekolahnya, staf tanpa sekolah semuanya.
     *
     * @return list<array{id: int, name: string, npsn: string, url: string, students_count: int}>
     */
    public function linksFor(User $actor): array
    {
        return $this->schools->withStudentCount($actor->school_id)->map(fn (School $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'npsn' => $s->npsn,
            'url' => $s->registrationUrl(),
            'students_count' => (int) $s->students_count,
        ])->values()->all();
    }

    public function resetRegistrationToken(School $school, User $actor): School
    {
        if ($actor->school_id !== null && $actor->school_id !== $school->id) {
            throw new AccessRuleException('Anda hanya dapat mengelola link sekolah Anda sendiri.');
        }

        $school->resetRegistrationToken();
        activity('akses')->performedOn($school)->causedBy($actor)->log('sekolah.tautan_direset');

        return $school;
    }

    public function findByRegistrationToken(string $token): ?School
    {
        return $this->schools->findByRegistrationToken($token);
    }
}
