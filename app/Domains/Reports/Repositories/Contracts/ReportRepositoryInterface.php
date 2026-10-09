<?php

namespace App\Domains\Reports\Repositories\Contracts;

interface ReportRepositoryInterface
{
    /** Jumlah entri per status; terkena scope sekolah pengguna. @return array<string, int> */
    public function statusCounts(): array;

    /** Nama sekolah urut nama. @return array<int, string> */
    public function schoolNames(): array;

    /**
     * Jumlah entri bukan-draf per sekolah dan status; `$from`/`$to` (Y-m-d) inklusif pada tanggal pengajuan pendaftaran.
     *
     * @return array<int, array<string, int>>
     */
    public function schoolStatusCounts(?string $from, ?string $to): array;
}
