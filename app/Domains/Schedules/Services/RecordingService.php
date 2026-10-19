<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordingService
{
    /** Status kehadiran yang boleh ditandai; ketiganya dapat dibatalkan kembali ke Terdaftar. */
    private const MARKS = [RegistrationStatus::SudahRekaman, RegistrationStatus::TidakHadir, RegistrationStatus::BelumRekaman];

    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly StatusTransitioner $transitions,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Menandai siswa Terdaftar pada sesi ini; seluruhnya berhasil atau seluruhnya gagal.
     *
     * @param  list<int>  $entryIds
     */
    public function mark(Schedule $schedule, array $entryIds, RegistrationStatus $to, ?string $note, User $actor): void
    {
        // Tanpa guard ini, Dibatalkan (transisi sah dari Terdaftar) lolos sebagai "penandaan".
        if (! in_array($to, self::MARKS, true)) {
            throw new AccessRuleException('Status penandaan tidak valid.');
        }
        if (! $schedule->isOpenForMarking()) {
            throw new AccessRuleException('Penandaan baru dapat dilakukan pada atau setelah tanggal sesi.');
        }

        $count = DB::transaction(function () use ($schedule, $entryIds, $to, $note, $actor) {
            $entries = $this->schedules->lockEntries($entryIds);
            $invalid = $entries->count() !== count(array_unique($entryIds))
                || $entries->contains(fn (RegistrationStudent $e) => $e->schedule_id !== $schedule->id || $e->status !== RegistrationStatus::Terdaftar);
            if ($invalid) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian siswa tidak dapat ditandai; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, $to, $actor, $note);
            }
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['status' => $to->value, 'jumlah' => $entries->count()])->log('rekaman.ditandai');

            return $entries->count();
        });

        $this->notifications->toSchool(
            'jadwal.lihat',
            ScheduleNotification::marked($schedule->id, $count, $to->label(), $schedule->date->toDateString()),
            $schedule->school_id,
        );
    }

    /** Membatalkan penandaan satu siswa: kembali ke Terdaftar pada sesi yang sama, dengan catatan. */
    public function undo(RegistrationStudent $entry, string $note, User $actor): void
    {
        if (! in_array($entry->status, self::MARKS, true)) {
            throw new AccessRuleException('Hanya siswa yang sudah ditandai yang dapat dibatalkan penandaannya.');
        }
        $previous = $entry->status;
        $schedule = $entry->schedule;
        $conflict = 'Siswa sudah memiliki pendaftaran aktif lain; penandaan tidak dapat dibatalkan.';

        try {
            DB::transaction(function () use ($entry, $note, $actor, $previous, $schedule, $conflict) {
                // Tidak hadir dan Belum rekaman melepas slot entri aktif; siswa mungkin sudah didaftarkan ulang.
                $busy = RegistrationStudent::query()
                    ->where('student_id', $entry->student_id)->where('id', '!=', $entry->id)
                    ->whereIn('status', RegistrationStatus::values(RegistrationStatus::active()))
                    ->lockForUpdate()->count() > 0;
                if ($busy) {
                    throw new AccessRuleException($conflict);
                }

                // Tidak hadir tidak menahan kursi; mengembalikannya harus tetap muat dalam kuota (di bawah kunci sesi).
                if (! $previous->isActive() && $previous !== RegistrationStatus::BelumRekaman) {
                    $locked = Schedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
                    if ($locked->seatsTaken() >= $locked->quota) {
                        throw new AccessRuleException('Kuota sesi sudah penuh; penandaan Tidak hadir tidak dapat dibatalkan.');
                    }
                }

                $this->transitions->move($entry, RegistrationStatus::Terdaftar, $actor, $note, ['scanned_at' => null]);
                activity('jadwal')->performedOn($schedule)->causedBy($actor)
                    ->withProperties(['status_lama' => $previous->value, 'siswa_id' => $entry->student_id])->log('rekaman.dibatalkan');
            });
        } catch (UniqueConstraintViolationException) {
            // Balapan: pendaftaran aktif baru masuk setelah pemeriksaan di atas; indeks unik active_student_id menolaknya.
            throw new AccessRuleException($conflict);
        }

        $this->notifications->toSchool(
            'jadwal.lihat',
            ScheduleNotification::undone($schedule->id, $previous->label()),
            $schedule->school_id,
        );
    }
}
