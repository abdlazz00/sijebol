<?php

namespace App\Domains\Registrations\Repositories\Contracts;

use App\Domains\Registrations\Models\RegistrationStudent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RegistrationRepositoryInterface
{
    /**
     * Antrean verifikasi: pendaftaran berstatus menunggu verifikasi, terlama dulu.
     * Filter dari query string: filter[search] (nama/NISN), filter[school_id], filter[schedule_id].
     *
     * @return LengthAwarePaginator<int, RegistrationStudent>
     */
    public function queue(int $perPage = 20): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): RegistrationStudent;

    /**
     * Mengunci baris pendaftaran (urut id) beserta siswa, dokumen, pendaftaran siswa itu, dan sesinya untuk keputusan massal.
     *
     * @param  list<int>  $ids
     * @return Collection<int, RegistrationStudent>
     */
    public function lockEntries(array $ids): Collection;
}
