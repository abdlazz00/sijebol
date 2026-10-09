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
     * Jadwal hari ini atau setelahnya, terdekat dulu, lengkap dengan sekolah dan jumlah entri.
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
     * Jadwal tujuan pindah: sekolah sama, hari ini atau setelahnya, selain `$current`, tanggal naik.
     *
     * @return Collection<int, Schedule>
     */
    public function targets(Schedule $current): Collection;

    /**
     * Entri Terverifikasi sekolah jadwal yang belum punya jadwal, dengan siswa dan pendaftarannya.
     *
     * @return Collection<int, RegistrationStudent>
     */
    public function candidates(Schedule $schedule): Collection;

    /**
     * Mengunci baris entri (urut id) agar dua operasi bersamaan tidak sama-sama lolos.
     *
     * @param  list<int>  $ids
     * @return Collection<int, RegistrationStudent>
     */
    public function lockEntries(array $ids): Collection;
}
