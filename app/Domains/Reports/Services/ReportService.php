<?php

namespace App\Domains\Reports\Services;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Models\User;

class ReportService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * Rekap sekolah × seluruh status pendaftaran sesi, lengkap dengan baris total.
     *
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array{school: string, counts: array<string, int>, total: int}>, totals: array{counts: array<string, int>, total: int}}
     */
    public function recap(?string $from, ?string $to): array
    {
        $statuses = RegistrationStatus::cases();
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

    /**
     * Kehadiran per sesi (sesi tidak dibatalkan, menurut tanggal sesi). Kehadiran = (Sudah rekaman + Belum rekaman) dibagi
     * (Sudah rekaman + Belum rekaman + Tidak hadir): Belum rekaman berarti siswa hadir tetapi belum sempat direkam.
     *
     * @return array{rows: list<array{id: int, date: string, start_time: string, end_time: string, school: string, location: string, quota: int, taken: int, present: int, not_recorded: int, absent: int, attendance: int|null}>, totals: array{quota: int, taken: int, present: int, not_recorded: int, absent: int, attendance: int|null}}
     */
    public function sessionRecap(?string $from, ?string $to): array
    {
        $holding = RegistrationStatus::values(RegistrationStatus::holdingSeat());
        $rows = [];
        $totals = ['quota' => 0, 'taken' => 0, 'present' => 0, 'not_recorded' => 0, 'absent' => 0];

        foreach ($this->reports->sessionStatusCounts($from, $to) as $session) {
            $counts = $session['counts'];
            $figures = [
                'quota' => $session['quota'],
                'taken' => array_sum(array_map(fn (string $status) => $counts[$status] ?? 0, $holding)),
                'present' => $counts[RegistrationStatus::SudahRekaman->value] ?? 0,
                'not_recorded' => $counts[RegistrationStatus::BelumRekaman->value] ?? 0,
                'absent' => $counts[RegistrationStatus::TidakHadir->value] ?? 0,
            ];
            foreach ($figures as $key => $value) {
                $totals[$key] += $value;
            }
            $rows[] = [...array_diff_key($session, ['counts' => 1, 'quota' => 1]), ...$figures, 'attendance' => $this->attendance($figures)];
        }

        return ['rows' => $rows, 'totals' => [...$totals, 'attendance' => $this->attendance($totals)]];
    }

    /** @param  array{present: int, not_recorded: int, absent: int}&array<string, int>  $figures */
    private function attendance(array $figures): ?int
    {
        $came = $figures['present'] + $figures['not_recorded'];
        $expected = $came + $figures['absent'];

        return $expected > 0 ? (int) round($came / $expected * 100) : null;
    }

    public function recordExport(User $actor, ?string $from, ?string $to, ?string $kind = null): void
    {
        activity('laporan')->causedBy($actor)
            ->withProperties(['dari' => $from, 'sampai' => $to, ...($kind !== null ? ['jenis' => $kind] : [])])
            ->log('laporan.diekspor');
    }
}
