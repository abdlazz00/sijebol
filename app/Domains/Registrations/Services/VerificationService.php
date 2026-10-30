<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        private readonly NotificationService $notifications,
    ) {}

    /** @return LengthAwarePaginator<int, RegistrationStudent> */
    public function queue(): LengthAwarePaginator
    {
        return $this->registrations->queue();
    }

    /**
     * Keputusan satu pendaftaran. Setujui menerbitkan kode tiket; minta perbaikan memberi tenggat dan hanya boleh
     * sampai sehari sebelum sesi. Siswa diberi tahu setelah transaksi selesai.
     *
     * @param  'approve'|'fix'|'reject'  $decision
     */
    public function decide(RegistrationStudent $entry, string $decision, ?string $note, User $actor): void
    {
        $to = self::DECISIONS[$decision];
        $schedule = $entry->schedule;

        if ($schedule->isPast()) {
            throw new AccessRuleException('Sesi sudah lewat; pendaftaran ini akan kedaluwarsa.');
        }
        if ($to === RegistrationStatus::PerluPerbaikan && $schedule->isOpenForMarking()) {
            throw new AccessRuleException('Permintaan perbaikan hanya dapat dibuat sampai sehari sebelum sesi; pada hari sesi pilih Setujui atau Tolak.');
        }

        $attributes = match ($to) {
            RegistrationStatus::Terdaftar => $this->ticketAttributes(),
            RegistrationStatus::PerluPerbaikan => ['fix_deadline_at' => $schedule->fixDeadlineFrom(now()), 'fix_reminded_at' => null],
            default => [],
        };

        DB::transaction(function () use ($entry, $to, $note, $actor, $attributes, $schedule) {
            if ($to === RegistrationStatus::Terdaftar) {
                $check = $this->eligibility->check($entry->student()->with(['documents', 'registrationEntries'])->firstOrFail(), $schedule->date, $entry->id);
                if (! $check['eligible']) {
                    throw new AccessRuleException(implode(' ', $check['reasons']));
                }
            }

            $this->transitions->move($entry, $to, $actor, $note, $attributes);
            activity('pendaftaran')->performedOn($entry)->causedBy($actor)
                ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => $to->value])->log('pendaftaran.diputuskan');
        });

        $this->notifications->toStudent($entry->student, match ($to) {
            RegistrationStatus::Terdaftar => $this->approvedNotification($entry),
            RegistrationStatus::PerluPerbaikan => RegistrationNotification::needsFix($note ?? '', $attributes['fix_deadline_at']),
            default => RegistrationNotification::rejected($note ?? ''),
        });
    }

    /**
     * Menyetujui banyak pendaftaran sekaligus: seluruhnya berhasil atau seluruhnya gagal. Kelayakan diperiksa ulang
     * per pendaftaran pada tanggal sesinya (umur, dokumen, pendaftaran aktif lain). Tiket terbit per pendaftaran dan
     * setiap siswa mendapat satu notifikasi setelah transaksi.
     *
     * @param  list<int>  $entryIds
     */
    public function approveMany(array $entryIds, User $actor): int
    {
        $approved = DB::transaction(function () use ($entryIds, $actor) {
            $entries = $this->registrations->lockEntries($entryIds);
            if ($entries->count() !== count(array_unique($entryIds)) || $entries->contains(fn (RegistrationStudent $e) => $e->status !== RegistrationStatus::MenungguVerifikasi)) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian pendaftaran sudah diputuskan; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                if ($entry->schedule->isPast()) {
                    throw ValidationException::withMessages(['entry_ids' => "{$entry->student->name} tidak dapat disetujui: sesinya sudah lewat."]);
                }
                $check = $this->eligibility->check($entry->student, $entry->schedule->date, $entry->id);
                if (! $check['eligible']) {
                    throw ValidationException::withMessages(['entry_ids' => "{$entry->student->name} tidak dapat disetujui: {$check['reasons'][0]}"]);
                }
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, RegistrationStatus::Terdaftar, $actor, null, $this->ticketAttributes());
                activity('pendaftaran')->performedOn($entry)->causedBy($actor)
                    ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => RegistrationStatus::Terdaftar->value, 'massal' => true])->log('pendaftaran.diputuskan');
            }

            return $entries;
        });

        foreach ($approved as $entry) {
            $this->notifications->toStudent($entry->student, $this->approvedNotification($entry));
        }

        return $approved->count();
    }

    /** @return array<string, mixed> */
    private function ticketAttributes(): array
    {
        return ['ticket_code' => Str::random(32), 'fix_deadline_at' => null, 'fix_reminded_at' => null];
    }

    private function approvedNotification(RegistrationStudent $entry): RegistrationNotification
    {
        $schedule = $entry->schedule;

        return RegistrationNotification::approved($entry->id, $schedule->date->toDateString(), $schedule->start_time, $schedule->end_time, $schedule->location);
    }
}
