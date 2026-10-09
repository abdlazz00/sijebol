<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    /** Kata keputusan di request -> status tujuan. */
    private const DECISIONS = [
        'approve' => RegistrationStatus::Terverifikasi,
        'fix' => RegistrationStatus::PerluPerbaikan,
        'reject' => RegistrationStatus::Ditolak,
    ];

    public function __construct(
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly StatusTransitioner $transitions,
        private readonly NotificationService $notifications,
        private readonly EligibilityService $eligibility,
    ) {}

    /** @return LengthAwarePaginator<int, RegistrationStudent> */
    public function queue(): LengthAwarePaginator
    {
        return $this->registrations->queue();
    }

    /** @param 'approve'|'fix'|'reject' $decision */
    public function decide(RegistrationStudent $entry, string $decision, ?string $note, User $actor): void
    {
        $to = self::DECISIONS[$decision];

        DB::transaction(function () use ($entry, $to, $note, $actor) {
            $this->transitions->move($entry, $to, $actor, $note);
            activity('pendaftaran')->performedOn($entry->registration)->causedBy($actor)
                ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => $to->value])->log('pendaftaran.diputuskan');
        });

        $this->notifications->toSchool(
            'pendaftaran.lihat',
            new EntryDecidedNotification($to, $entry->student->name, $entry->registration_id, $note),
            $entry->school_id,
        );
    }

    /**
     * Menyetujui banyak entri sekaligus: seluruhnya berhasil atau seluruhnya gagal. Kelayakan diperiksa ulang
     * per entri (umur pada tanggal pengajuan, dokumen, entri aktif lain).
     *
     * @param  list<int>  $entryIds
     */
    public function approveMany(array $entryIds, User $actor): int
    {
        $approved = DB::transaction(function () use ($entryIds, $actor) {
            $entries = $this->registrations->lockEntries($entryIds);
            if ($entries->count() !== count(array_unique($entryIds)) || $entries->contains(fn (RegistrationStudent $e) => $e->status !== RegistrationStatus::Diajukan)) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian entri sudah diputuskan; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $check = $this->eligibility->check($entry->student, $entry->registration->submitted_at, $entry->registration_id);
                if (! $check['eligible']) {
                    throw ValidationException::withMessages(['entry_ids' => "{$entry->student->name} tidak dapat disetujui: {$check['reasons'][0]}"]);
                }
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, RegistrationStatus::Terverifikasi, $actor);
                activity('pendaftaran')->performedOn($entry->registration)->causedBy($actor)
                    ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => RegistrationStatus::Terverifikasi->value, 'massal' => true])->log('pendaftaran.diputuskan');
            }

            return $entries;
        });

        // Satu notifikasi per pendaftaran, bukan per siswa.
        foreach ($approved->groupBy('registration_id') as $registrationId => $group) {
            $this->notifications->toSchool(
                'pendaftaran.lihat',
                new EntryDecidedNotification(RegistrationStatus::Terverifikasi, self::summary($group->map(fn (RegistrationStudent $e) => $e->student->name)->all()), (int) $registrationId, null),
                $group->first()->school_id,
            );
        }

        return $approved->count();
    }

    /** @param list<string> $names */
    private static function summary(array $names): string
    {
        $n = count($names);

        return match (true) {
            $n === 1 => $names[0],
            $n <= 3 => "{$n} siswa: ".implode(', ', $names).'.',
            default => "{$n} siswa: {$names[0]}, {$names[1]}, dan ".($n - 2).' lainnya.',
        };
    }
}
