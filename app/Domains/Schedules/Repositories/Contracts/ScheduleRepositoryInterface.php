<?php

namespace App\Domains\Schedules\Repositories\Contracts;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ScheduleRepositoryInterface
{
    /**
     * Filter dari query string: filter[search] (lokasi), filter[school_id], filter[period] = akan-datang|lewat.
     *
     * @return LengthAwarePaginator<int, Schedule>
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /**
     * Sesi hari ini atau setelahnya, terdekat dulu, lengkap dengan sekolah dan `taken_count` (kursi tertahan).
     *
     * @return Collection<int, Schedule>
     */
    public function upcoming(int $limit): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Schedule;

    /** @param array<string, mixed> $attributes */
    public function update(Schedule $schedule, array $attributes): Schedule;

    public function delete(Schedule $schedule): void;

    /**
     * Mengunci baris entri (urut id) agar dua operasi bersamaan tidak sama-sama lolos.
     *
     * @param  list<int>  $ids
     * @return Collection<int, RegistrationStudent>
     */
    public function lockEntries(array $ids): Collection;
}
