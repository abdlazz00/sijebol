<?php

namespace App\Domains\Reports\Services;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Models\User;

class ReportService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * Rekap sekolah × status (tanpa draf), lengkap dengan baris total.
     *
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array{school: string, counts: array<string, int>, total: int}>, totals: array{counts: array<string, int>, total: int}}
     */
    public function recap(?string $from, ?string $to): array
    {
        $statuses = array_values(array_filter(RegistrationStatus::cases(), fn (RegistrationStatus $s) => $s !== RegistrationStatus::Draf));
        $counts = $this->reports->schoolStatusCounts($from, $to);
        $totals = array_fill_keys(array_map(fn (RegistrationStatus $s) => $s->value, $statuses), 0);
        $rows = [];

        foreach ($this->reports->schoolNames() as $id => $name) {
            $row = [];
            foreach ($statuses as $status) {
                $row[$status->value] = $counts[$id][$status->value] ?? 0;
                $totals[$status->value] += $row[$status->value];
            }
            $rows[] = ['school' => $name, 'counts' => $row, 'total' => array_sum($row)];
        }

        return [
            'columns' => array_map(fn (RegistrationStatus $s) => ['key' => $s->value, 'label' => $s->label()], $statuses),
            'rows' => $rows,
            'totals' => ['counts' => $totals, 'total' => array_sum($totals)],
        ];
    }

    public function recordExport(User $actor, ?string $from, ?string $to): void
    {
        activity('laporan')->causedBy($actor)->withProperties(['dari' => $from, 'sampai' => $to])->log('laporan.diekspor');
    }
}
