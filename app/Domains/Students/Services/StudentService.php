<?php

namespace App\Domains\Students\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Repositories\Contracts\StudentRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class StudentService
{
    public function __construct(private readonly StudentRepositoryInterface $students) {}

    /** @return LengthAwarePaginator<int, Student> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->students->paginate();
    }

    /** @return Collection<int, Student> */
    public function forSchool(int $schoolId): Collection
    {
        return $this->students->forSchool($schoolId);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Student
    {
        // Operator sekolah selalu dikunci ke sekolahnya; staf kecamatan memilih sekolah.
        $data['school_id'] = $actor->school_id ?? $data['school_id'];

        return $this->students->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Student $student, array $data): Student
    {
        if ($student->isLocked()) {
            throw new AccessRuleException(Student::LOCKED_MESSAGE);
        }

        // Sekolah hanya ditentukan saat tambah.
        unset($data['school_id']);

        return $this->students->update($student, $data);
    }

    public function delete(Student $student): void
    {
        if ($student->registrationEntries()->exists()) {
            throw new AccessRuleException('Siswa sudah memiliki pendaftaran; keluarkan dari draf atau hubungi admin.');
        }

        // Baris dihapus dulu: bila gagal, berkas tidak ikut hilang.
        $this->students->delete($student);
        Storage::disk('local')->deleteDirectory("students/{$student->id}");
    }
}
