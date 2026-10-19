<?php

namespace App\Domains\Students\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\StudentCorrectedNotification;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Repositories\Contracts\StudentRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentService
{
    public function __construct(private readonly StudentRepositoryInterface $students) {}

    /** @return LengthAwarePaginator<int, Student> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->students->paginate();
    }

    /** @return LengthAwarePaginator<int, Student> */
    public function candidatesPage(int $schoolId, ?string $q): LengthAwarePaginator
    {
        return $this->students->searchForSchool($schoolId, $q);
    }

    /**
     * Data diri milik akun siswa: dibuat saat disimpan pertama kali (sekolah mengikuti akun), selanjutnya diperbarui.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOwn(User $actor, array $data): Student
    {
        // Query langsung, bukan relasi ter-cache, agar tidak basi bila objek user dipakai ulang.
        $student = $actor->student()->first();

        if ($student === null) {
            return $this->students->create([...$data, 'school_id' => $actor->school_id, 'user_id' => $actor->id]);
        }

        return $this->update($student, $data);
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

    /**
     * Koreksi oleh staf: mengabaikan kunci data, wajib bercatatan, tercatat di log, dan siswa diberi tahu.
     * Sekolah hanya dapat diganti oleh pelaku tanpa sekolah dan bila siswa tidak punya pendaftaran aktif.
     *
     * @param  array<string, mixed>  $data
     */
    public function correct(Student $student, array $data, string $note, User $actor): Student
    {
        $schoolId = $actor->school_id === null ? ($data['school_id'] ?? $student->school_id) : $student->school_id;

        if ($schoolId !== $student->school_id && $this->hasActiveEntry($student)) {
            throw new AccessRuleException('Sekolah tidak dapat diganti: siswa masih punya pendaftaran aktif.');
        }

        // Akun siswa ikut pindah: scope sekolah membaca users.school_id, sehingga siswa tidak terkunci dari datanya sendiri.
        $student = DB::transaction(function () use ($student, $data, $schoolId) {
            $student = $this->students->update($student, [...$data, 'school_id' => $schoolId]);
            $student->user?->update(['school_id' => $schoolId]);

            return $student;
        });
        activity('siswa')->performedOn($student)->causedBy($actor)->withProperties(['catatan' => $note])->log('siswa.dikoreksi');
        $student->user?->notify(new StudentCorrectedNotification($note));

        return $student;
    }

    private function hasActiveEntry(Student $student): bool
    {
        return $student->registrationEntries()
            ->whereIn('status', RegistrationStatus::values(RegistrationStatus::active()))
            ->exists();
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
