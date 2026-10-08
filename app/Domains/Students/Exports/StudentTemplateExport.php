<?php

namespace App\Domains\Students\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/** Template impor: kolom NIK, NISN, dan tanggal berformat teks agar angka 16 digit tidak berubah. */
class StudentTemplateExport implements FromArray, WithColumnFormatting, WithHeadings
{
    /** @return list<string> */
    public function headings(): array
    {
        return ['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin'];
    }

    /** @return array<int, array<int, string>> */
    public function array(): array
    {
        return [['3201123412341234', '0012345678', 'Contoh Nama', '2009-05-01', 'L']];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['A' => NumberFormat::FORMAT_TEXT, 'B' => NumberFormat::FORMAT_TEXT, 'D' => NumberFormat::FORMAT_TEXT];
    }
}
