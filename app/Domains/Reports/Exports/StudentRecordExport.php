<?php

namespace App\Domains\Reports\Exports;

use App\Domains\Reports\Services\ReportService;
use App\Support\Nik;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Berkas laporan KTP-el pelajar; barisnya dari `ReportService::studentRecords`, jadi identik dengan halaman.
 * Semua sel diikat sebagai teks agar NIK 16 digit tidak berubah menjadi notasi ilmiah.
 */
class StudentRecordExport extends StringValueBinder implements FromArray, WithCustomValueBinder, WithHeadings
{
    private const GENDERS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    /** @param  list<array<string, mixed>>  $rows  Baris `ReportService::studentRecords`. */
    public function __construct(private readonly array $rows, private readonly bool $fullNik) {}

    /** @return list<string> */
    public function headings(): array
    {
        return ['Nama', 'NIK', 'NISN', 'Tanggal lahir', 'Jenis kelamin', 'Sekolah', 'Tanggal rekaman', 'KTP-el', 'Terbit mulai', 'Alasan'];
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return array_map(fn (array $r) => [
            RecapExport::safe($r['name']),
            $this->fullNik ? $r['nik'] : Nik::mask($r['nik']),
            $r['nisn'],
            $r['birth_date'],
            self::GENDERS[$r['gender']],
            RecapExport::safe($r['school']),
            $r['group'] === 'belum' ? '' : (string) $r['session_date'],
            ReportService::GROUPS[$r['group']],
            $r['eligible_from'],
            (string) $r['reason'],
        ], $this->rows);
    }
}
