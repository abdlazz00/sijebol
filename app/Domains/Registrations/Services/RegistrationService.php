<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationSubmittedNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function __construct(
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly StatusTransitioner $transitions,
        private readonly EligibilityService $eligibility,
        private readonly NotificationService $notifications,
    ) {}

    /** @return LengthAwarePaginator<int, Registration> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->registrations->paginate();
    }

    /** @param array{title: string, expected_count?: ?int, school_id?: ?int, student_ids: list<int>} $data */
    public function createDraft(array $data, User $actor): Registration
    {
        return DB::transaction(function () use ($data, $actor) {
            $registration = $this->registrations->create([
                // Operator sekolah dikunci ke sekolahnya; staf kecamatan memilih sekolah.
                'school_id' => $actor->school_id ?? $data['school_id'],
                'title' => $data['title'],
                'expected_count' => $data['expected_count'] ?? null,
                'created_by' => $actor->id,
            ]);
            $this->registrations->syncDraftEntries($registration, $data['student_ids']);

            return $registration;
        });
    }

    /** @param array{title: string, expected_count?: ?int, student_ids: list<int>} $data */
    public function updateDraft(Registration $registration, array $data): Registration
    {
        $this->assertDraft($registration);

        return DB::transaction(function () use ($registration, $data) {
            $this->registrations->update($registration, [
                'title' => $data['title'],
                'expected_count' => $data['expected_count'] ?? null,
            ]);
            $this->registrations->syncDraftEntries($registration, $data['student_ids']);

            return $registration;
        });
    }

    public function deleteDraft(Registration $registration): void
    {
        $this->assertDraft($registration);
        $this->registrations->delete($registration);
    }

    public function submit(Registration $registration, User $actor): void
    {
        $this->assertDraft($registration);

        try {
            $count = DB::transaction(function () use ($registration, $actor) {
                $ids = $registration->entries()->pluck('student_id')->all();
                if ($ids === []) {
                    throw ValidationException::withMessages(['student_ids' => 'Pilih minimal satu siswa.']);
                }

                // Kunci baris siswa (urut id) agar dua pengajuan bersamaan tidak saling menyalip;
                // bila tetap lolos, indeks unik active_student_id yang menghentikan.
                Student::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $entries = $registration->entries()->with(['student.documents', 'student.registrationEntries'])->get();
                $this->assertEligible($entries, $registration->id);

                foreach ($entries as $entry) {
                    $this->transitions->move($entry, RegistrationStatus::Diajukan, $actor);
                }
                $registration->update(['submitted_at' => now()]);
                activity('pendaftaran')->performedOn($registration)->causedBy($actor)
                    ->withProperties(['jumlah' => $entries->count()])->log('pendaftaran.diajukan');

                return $entries->count();
            });
        } catch (UniqueConstraintViolationException) {
            throw new AccessRuleException('Sebagian siswa sudah terdaftar di pendaftaran lain.');
        }

        $this->notifications->toPermission('verifikasi.putuskan', new RegistrationSubmittedNotification($registration->school->name, $count));
    }

    public function resubmit(RegistrationStudent $entry, User $actor): void
    {
        if ($entry->status !== RegistrationStatus::PerluPerbaikan) {
            throw new AccessRuleException('Hanya siswa berstatus Perlu perbaikan yang dapat diajukan ulang.');
        }

        DB::transaction(function () use ($entry, $actor) {
            Student::query()->whereKey($entry->student_id)->lockForUpdate()->first();
            $entry->load(['student.documents', 'student.registrationEntries']);
            $this->assertEligible(new Collection([$entry]), $entry->registration_id);

            $this->transitions->move($entry, RegistrationStatus::Diajukan, $actor);
            activity('pendaftaran')->performedOn($entry->registration)->causedBy($actor)
                ->withProperties(['siswa_id' => $entry->student_id])->log('pendaftaran.diajukan-ulang');
        });

        $this->notifications->toPermission('verifikasi.putuskan', new RegistrationSubmittedNotification($entry->registration->school->name, 1, again: true));
    }

    private function assertDraft(Registration $registration): void
    {
        if (! $registration->isDraft()) {
            throw new AccessRuleException('Pendaftaran yang sudah diajukan tidak dapat diubah.');
        }
    }

    /** @param Collection<int, RegistrationStudent> $entries */
    private function assertEligible(Collection $entries, int $registrationId): void
    {
        $errors = [];
        foreach ($entries as $entry) {
            $check = $this->eligibility->check($entry->student, null, $registrationId);
            if (! $check['eligible']) {
                $errors["student_ids.{$entry->student_id}"] = $entry->student->name.': '.implode(' ', $check['reasons']);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['student_ids' => 'Beberapa siswa belum memenuhi syarat.'] + $errors);
        }
    }
}
