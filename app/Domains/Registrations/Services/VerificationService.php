<?php

namespace App\Domains\Registrations\Services;

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
        'approve' => RegistrationStatus::Terdaftar,
        'fix' => RegistrationStatus::PerluPerbaikan,
        'reject' => RegistrationStatus::Ditolak,
    ];

    public function __construct(
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly StatusTransitioner $transitions,
        private readonly EligibilityService $eligibility,
    ) {}

    /** @return LengthAwarePaginator<int, RegistrationStudent> */
    public function queue(): LengthAwarePaginator
    {
        return $this->registrations->queue();
    }

    /**
     * Keputusan satu pendaftaran. Notifikasi ke siswa menyusul di fase 7c.
     *
     * @param  'approve'|'fix'|'reject'  $decision
     */
    public function decide(RegistrationStudent $entry, string $decision, ?string $note, User $actor): void
    {
        $to = self::DECISIONS[$decision];

        DB::transaction(function () use ($entry, $to, $note, $actor) {
            $this->transitions->move($entry, $to, $actor, $note);
            activity('pendaftaran')->performedOn($entry)->causedBy($actor)
                ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => $to->value])->log('pendaftaran.diputuskan');
        });
    }

    /**
     * Menyetujui banyak pendaftaran sekaligus: seluruhnya berhasil atau seluruhnya gagal. Kelayakan diperiksa ulang
     * per pendaftaran pada tanggal sesinya (umur, dokumen, pendaftaran aktif lain).
     *
     * @param  list<int>  $entryIds
     */
    public function approveMany(array $entryIds, User $actor): int
    {
        return DB::transaction(function () use ($entryIds, $actor) {
            $entries = $this->registrations->lockEntries($entryIds);
            if ($entries->count() !== count(array_unique($entryIds)) || $entries->contains(fn (RegistrationStudent $e) => $e->status !== RegistrationStatus::MenungguVerifikasi)) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian pendaftaran sudah diputuskan; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $check = $this->eligibility->check($entry->student, $entry->schedule->date, $entry->id);
                if (! $check['eligible']) {
                    throw ValidationException::withMessages(['entry_ids' => "{$entry->student->name} tidak dapat disetujui: {$check['reasons'][0]}"]);
                }
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, RegistrationStatus::Terdaftar, $actor);
                activity('pendaftaran')->performedOn($entry)->causedBy($actor)
                    ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => RegistrationStatus::Terdaftar->value, 'massal' => true])->log('pendaftaran.diputuskan');
            }

            return $entries->count();
        });
    }
}
