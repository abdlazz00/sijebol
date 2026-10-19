<?php

namespace App\Domains\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Berkas kehadiran per sesi; isinya diambil dari `ReportService::sessionRecap` sehingga identik dengan halaman.
 *
 * @phpstan-type SessionRecap array{rows: list<array{id: int, date: string, start_time: string, end_time: string, school: string, location: string, quota: int, taken: int, present: int, not_recorded: int, absent: int, attendance: int|null}>, totals: array{quota: int, taken: int, present: int, not_recorded: int, absent: int, attendance: int|null}}
 */
class SessionRecapExport implements FromArray, WithHeadings
{
    /** @param  SessionRecap  $recap */
    public function __construct(private readonly array $recap) {}

    /** @return list<string> */
    public function headings(): array
    {
        return ['Tanggal', 'Jam', 'Sekolah', 'Lokasi', 'Kuota', 'Terisi', 'Sudah rekaman', 'Belum rekaman', 'Tidak hadir', 'Kehadiran (%)'];
    }

    /** @return list<list<string|int>> */
    public function array(): array
    {
        $rows = array_map(fn (array $r) => [
            $r['date'],
            str_replace(':', '.', $r['start_time']).'–'.str_replace(':', '.', $r['end_time']),
            RecapExport::safe($r['school']),
            RecapExport::safe($r['location']),
            $r['quota'], $r['taken'], $r['present'], $r['not_recorded'], $r['absent'],
            $r['attendance'] ?? '-',
        ], $this->recap['rows']);

        $t = $this->recap['totals'];
        $rows[] = ['Total', '', '', '', $t['quota'], $t['taken'], $t['present'], $t['not_recorded'], $t['absent'], $t['attendance'] ?? '-'];

        return $rows;
    }
}
