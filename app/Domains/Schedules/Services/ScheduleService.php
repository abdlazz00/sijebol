<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
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
}
