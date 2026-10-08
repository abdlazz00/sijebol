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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly StatusTransitioner $transitions,
        private readonly NotificationService $notifications,
    ) {}

    /** @return LengthAwarePaginator<int, Schedule> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->schedules->paginate();
    }

    /** @return Collection<int, Schedule> */
    public function upcoming(int $limit = 3): Collection
    {
        return $this->schedules->upcoming($limit);
    }

    /** @return Collection<int, RegistrationStudent> */
    public function candidates(Schedule $schedule): Collection
    {
        return $this->schedules->candidates($schedule);
    }

    /** @param array{school_id?: ?int, date: string, location: string} $data */
    public function create(array $data, User $actor): Schedule
    {
        $schedule = $this->schedules->create([
            // Operator sekolah dikunci ke sekolahnya; staf kecamatan memilih sekolah.
            'school_id' => $actor->school_id ?? $data['school_id'],
            'date' => $data['date'],
            'location' => $data['location'],
            'created_by' => $actor->id,
        ]);
        activity('jadwal')->performedOn($schedule)->causedBy($actor)->log('jadwal.dibuat');

        return $schedule;
    }

    /** @param array{date: string, location: string} $data */
    public function update(Schedule $schedule, array $data, User $actor): Schedule
    {
        $this->schedules->update($schedule, ['date' => $data['date'], 'location' => $data['location']]);

        if ($schedule->wasChanged(['date', 'location'])) {
            activity('jadwal')->performedOn($schedule)->causedBy($actor)->log('jadwal.diubah');

            if ($schedule->entries()->exists()) {
                $this->notifications->toSchool(
                    'jadwal.lihat',
                    ScheduleNotification::changed($schedule->id, $schedule->date->toDateString(), $schedule->location),
                    $schedule->school_id,
                );
            }
        }

        return $schedule;
    }

    /** @param list<int> $entryIds */
    public function assign(Schedule $schedule, array $entryIds, User $actor): void
    {
        if ($schedule->isPast()) {
            throw new AccessRuleException('Jadwal sudah lewat; siswa tidak dapat ditambahkan.');
        }

        $count = DB::transaction(function () use ($schedule, $entryIds, $actor) {
            $entries = $this->schedules->lockEntries($entryIds);
            $invalid = $entries->count() !== count(array_unique($entryIds))
                || $entries->contains(fn (RegistrationStudent $e) => $e->school_id !== $schedule->school_id || $e->status !== RegistrationStatus::Terverifikasi);
            if ($invalid) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian siswa tidak dapat dijadwalkan; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, RegistrationStatus::Terjadwal, $actor, null, ['schedule_id' => $schedule->id]);
            }
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['ditambahkan' => $entries->count()])->log('jadwal.penempatan');

            return $entries->count();
        });

        $this->notifications->toSchool(
            'jadwal.lihat',
            ScheduleNotification::assigned($schedule->id, $count, $schedule->date->toDateString(), $schedule->location),
            $schedule->school_id,
        );
    }

    public function unassign(RegistrationStudent $entry, User $actor): void
    {
        DB::transaction(function () use ($entry, $actor) {
            // Dibaca sebelum move() mengosongkan schedule_id.
            $schedule = $entry->schedule;
            $this->transitions->move($entry, RegistrationStatus::Terverifikasi, $actor, null, ['schedule_id' => null]);
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['dikeluarkan' => 1])->log('jadwal.penempatan');
        });
    }
}
