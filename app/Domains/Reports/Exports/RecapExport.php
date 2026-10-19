<?php

namespace App\Domains\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Berkas rekap; isinya diambil dari `ReportService::recap` sehingga identik dengan halaman.
 *
 * @phpstan-type Recap array{columns: list<array{key: string, label: string}>, rows: list<array{school: string, counts: array<string, int>, total: int}>, totals: array{counts: array<string, int>, total: int}}
 */
class RecapExport implements FromArray, WithHeadings
{
    /** @param  Recap  $recap */
    public function __construct(private readonly array $recap) {}

    /** @return list<string> */
    public function headings(): array
    {
        return ['Sekolah', ...array_column($this->recap['columns'], 'label'), 'Total'];
    }

    /** @return list<list<string|int>> */
    public function array(): array
    {
        $rows = array_map(
            fn (array $r) => [self::safe($r['school']), ...array_values($r['counts']), $r['total']],
            $this->recap['rows'],
        );
        $rows[] = ['Total', ...array_values($this->recap['totals']['counts']), $this->recap['totals']['total']];

        return $rows;
    }

    /** Teks berawalan = + - @ dibaca Excel sebagai formula; awalan ' menjadikannya teks biasa. */
    public static function safe(string $text): string
    {
        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }
}
