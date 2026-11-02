<?php

namespace App\Domains\Reports\Repositories\Contracts;

use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Collection;

interface ReportRepositoryInterface
{
    /** Jumlah entri per status; terkena scope sekolah pengguna. @return array<string, int> */
    public function statusCounts(): array;

    /** Nama sekolah urut nama. @return array<int, string> */
    public function schoolNames(): array;

    /**
     * Jumlah pendaftaran sesi per sekolah dan status; `$from`/`$to` (Y-m-d) inklusif pada tanggal pendaftaran.
     *
     * @return array<int, array<string, int>>
     */
    public function schoolStatusCounts(?string $from, ?string $to): array;

    /**
     * Sesi tidak dibatalkan dengan jumlah pendaftaran per status; `$from`/`$to` (Y-m-d) inklusif pada tanggal sesi.
     * Terkena scope sekolah pengguna; urut tanggal lalu id.
     *
     * @return list<array{id: int, date: string, start_time: string, end_time: string, school: string, location: string, quota: int, counts: array<string, int>}>
     */
    public function sessionStatusCounts(?string $from, ?string $to): array;

    /**
     * Siswa (terkena scope sekolah pengguna) dengan sekolah dan pendaftaran beserta sesinya, urut nama lalu id.
     * `$search` memadankan sebagian nama atau NISN.
     *
     * @return Collection<int, Student>
     */
    public function studentsWithRegistrations(?int $schoolId, ?string $search): Collection;
}
