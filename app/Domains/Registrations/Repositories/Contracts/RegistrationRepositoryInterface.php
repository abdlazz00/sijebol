<?php

namespace App\Domains\Registrations\Repositories\Contracts;

use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RegistrationRepositoryInterface
{
    /**
     * Filter dari query string: filter[search], filter[status], filter[school_id].
     *
     * @return LengthAwarePaginator<int, Registration>
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /**
     * Antrean verifikasi: entri berstatus diajukan, terlama dulu.
     *
     * @return LengthAwarePaginator<int, RegistrationStudent>
     */
    public function queue(int $perPage = 20): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Registration;

    /** @param array<string, mixed> $attributes */
    public function update(Registration $registration, array $attributes): Registration;

    public function delete(Registration $registration): void;

    /**
     * Mengunci baris entri (urut id) beserta siswa, dokumen, entri siswa, dan pendaftarannya untuk keputusan massal.
     *
     * @param  list<int>  $ids
     * @return Collection<int, RegistrationStudent>
     */
    public function lockEntries(array $ids): Collection;

    /**
     * Menyamakan entri draf dengan daftar siswa: yang tidak ada dihapus, yang baru ditambahkan.
     *
     * @param  list<int>  $studentIds
     */
    public function syncDraftEntries(Registration $registration, array $studentIds): void;
}
