<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    /** Pendaftaran yang masih dapat dibatalkan siswa sendiri. */
    private const CANCELLABLE = [RegistrationStatus::MenungguVerifikasi, RegistrationStatus::PerluPerbaikan, RegistrationStatus::Terdaftar];

    public function __construct(
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly StatusTransitioner $transitions,
        private readonly EligibilityService $eligibility,
    ) {}

    /**
     * Siswa mendaftar ke sesi sekolahnya. Sesi dikunci (urut: sesi lalu siswa) selama pemeriksaan
     * kuota agar pendaftaran serentak tidak melebihi kuota; indeks unik `active_student_id` menjaga satu pendaftaran aktif.
     */
    public function register(User $actor, Schedule $schedule): RegistrationStudent
    {
        // Query langsung, bukan relasi ter-cache, agar tidak basi bila objek user dipakai ulang.
        $student = $actor->student()->first();
        if ($student === null) {
            throw new AccessRuleException('Lengkapi data diri dan dokumen terlebih dahulu.');
        }

        try {
            return DB::transaction(function () use ($actor, $schedule, $student) {
                $locked = Schedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
                Student::query()->whereKey($student->id)->lockForUpdate()->first();

                if (! $locked->isOpenForRegistration()) {
                    throw new AccessRuleException('Pendaftaran untuk sesi ini sudah ditutup.');
                }
                $check = $this->eligibility->check($student->load(['documents', 'registrationEntries']), $locked->date);
                if (! $check['eligible']) {
                    throw new AccessRuleException(implode(' ', $check['reasons']));
                }
                if ($locked->seatsTaken() >= $locked->quota) {
                    throw new AccessRuleException('Kuota sesi ini sudah penuh.');
                }

                $entry = $this->registrations->create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                    'schedule_id' => $locked->id,
                    'status' => RegistrationStatus::MenungguVerifikasi,
                ]);
                $entry->histories()->create([
                    'from_status' => null,
                    'to_status' => RegistrationStatus::MenungguVerifikasi,
                    'by_user_id' => $actor->id,
                ]);
                activity('pendaftaran')->performedOn($entry)->causedBy($actor)
                    ->withProperties(['sesi_id' => $locked->id])->log('pendaftaran.dibuat');

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            throw new AccessRuleException('Anda sudah memiliki pendaftaran aktif.');
        }
    }

    public function cancel(RegistrationStudent $entry, User $actor): void
    {
        if (! in_array($entry->status, self::CANCELLABLE, true)) {
            throw new AccessRuleException('Pendaftaran dengan status ini tidak dapat dibatalkan.');
        }
        $this->assertStudentMayChange($entry, 'Pembatalan');

        DB::transaction(function () use ($entry, $actor) {
            $this->transitions->move($entry, RegistrationStatus::Dibatalkan, $actor, 'Dibatalkan oleh siswa');
            activity('pendaftaran')->performedOn($entry)->causedBy($actor)->log('pendaftaran.dibatalkan');
        });
    }

    public function resubmit(RegistrationStudent $entry, User $actor): void
    {
        if ($entry->status !== RegistrationStatus::PerluPerbaikan) {
            throw new AccessRuleException('Hanya pendaftaran berstatus Perlu perbaikan yang dapat diajukan ulang.');
        }
        $this->assertStudentMayChange($entry, 'Pengajuan ulang');

        DB::transaction(function () use ($entry, $actor) {
            Student::query()->whereKey($entry->student_id)->lockForUpdate()->first();
            $student = $entry->student()->with(['documents', 'registrationEntries'])->firstOrFail();

            $check = $this->eligibility->check($student, $entry->schedule->date, $entry->id);
            if (! $check['eligible']) {
                throw new AccessRuleException(implode(' ', $check['reasons']));
            }

            $this->transitions->move($entry, RegistrationStatus::MenungguVerifikasi, $actor);
            activity('pendaftaran')->performedOn($entry)->causedBy($actor)->log('pendaftaran.diajukan-ulang');
        });
    }

    /** Pendaftaran yang ditampilkan ke siswa: yang aktif bila ada, selain itu yang terbaru. */
    public function currentEntry(Student $student): ?RegistrationStudent
    {
        $entries = $student->registrationEntries()->with('schedule')->orderByDesc('id')->get();

        return $entries->first(fn (RegistrationStudent $e) => $e->status->isActive()) ?? $entries->first();
    }

    /**
     * Sesi sekolah siswa yang pendaftarannya masih dibuka, terdekat dulu, lengkap dengan kursi dan kelayakan
     * (umur dihitung pada tanggal sesi itu). Sesi penuh tetap dikembalikan agar dapat ditampilkan nonaktif.
     *
     * @return list<array{id: int, date: string, start_time: string, end_time: string, location: string, quota: int, taken: int, full: bool, eligible: bool, reasons: list<string>}>
     */
    public function openSchedules(Student $student): array
    {
        $student->load(['documents', 'registrationEntries']);
        $firstOpenDate = Carbon::parse(Schedule::today())->addDays((int) config('sijebol.registration_close_days'))->toDateString();

        return Schedule::query()
            ->whereNull('cancelled_at')->where('date', '>=', $firstOpenDate)
            ->withCount(['entries as taken_count' => fn ($q) => $q->withoutGlobalScope('owner')->whereIn('status', RegistrationStatus::values(RegistrationStatus::holdingSeat()))])
            ->orderBy('date')->orderBy('start_time')->orderBy('id')->get()
            ->map(function (Schedule $s) use ($student) {
                $check = $this->eligibility->check($student, $s->date);

                return [
                    'id' => $s->id,
                    'date' => $s->date->toDateString(),
                    'start_time' => substr($s->start_time, 0, 5),
                    'end_time' => substr($s->end_time, 0, 5),
                    'location' => $s->location,
                    'quota' => $s->quota,
                    'taken' => $s->taken_count,
                    'full' => $s->taken_count >= $s->quota,
                    'eligible' => $check['eligible'],
                    'reasons' => $check['reasons'],
                ];
            })->values()->all();
    }

    private function assertStudentMayChange(RegistrationStudent $entry, string $action): void
    {
        if (! $entry->schedule->allowsStudentChanges()) {
            throw new AccessRuleException("{$action} hanya dapat dilakukan sampai sehari sebelum sesi.");
        }
    }
}
