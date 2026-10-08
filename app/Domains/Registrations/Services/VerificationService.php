<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
}
