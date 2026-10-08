<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordingService
{
    /** Status kehadiran yang boleh ditandai; ketiganya final. */
    private const MARKS = [RegistrationStatus::SudahRekaman, RegistrationStatus::TidakHadir, RegistrationStatus::BelumRekaman];

    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly StatusTransitioner $transitions,
    ) {}

    /**
     * Menandai siswa Terjadwal pada jadwal ini; seluruhnya berhasil atau seluruhnya gagal.
     *
     * @param  list<int>  $entryIds
     */
    public function mark(Schedule $schedule, array $entryIds, RegistrationStatus $to, ?string $note, User $actor): void
    {
        // Tanpa guard ini, Terverifikasi (transisi sah dari Terjadwal) menjadi "keluarkan" tanpa mengosongkan schedule_id.
        if (! in_array($to, self::MARKS, true)) {
            throw new AccessRuleException('Status penandaan tidak valid.');
        }
        if (! $schedule->isOpenForMarking()) {
            throw new AccessRuleException('Penandaan baru dapat dilakukan pada atau setelah tanggal jadwal.');
        }

        DB::transaction(function () use ($schedule, $entryIds, $to, $note, $actor) {
            $entries = $this->schedules->lockEntries($entryIds);
            $invalid = $entries->count() !== count(array_unique($entryIds))
                || $entries->contains(fn (RegistrationStudent $e) => $e->schedule_id !== $schedule->id || $e->status !== RegistrationStatus::Terjadwal);
            if ($invalid) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian siswa tidak dapat ditandai; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, $to, $actor, $note);
            }
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['status' => $to->value, 'jumlah' => $entries->count()])->log('rekaman.ditandai');
        });
    }
}
