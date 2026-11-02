<?php

namespace App\Domains\Reports\Services;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;

class ReportService
{
    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly EligibilityService $eligibility,
    ) {}

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

    /** Kelompok daftar rekaman dan KTP-el (urutan = urutan tampil). */
    public const GROUPS = ['langsung' => 'Langsung terbit', 'menunggu' => 'Menunggu genap 17', 'belum' => 'Belum rekaman'];

    /**
     * Satu baris per siswa menurut pendaftaran terkininya. `$filters`: school_id, schedule_id, search, dari, sampai
     * (rentang tanggal sesi; siswa tanpa pendaftaran selalu lolos), group. `counts` dihitung sebelum filter `group`.
     * Semua siswa dalam cakupan dimuat ke memori.
     *
     * @param  array{school_id?: ?int, schedule_id?: ?int, search?: ?string, dari?: ?string, sampai?: ?string, group?: ?string}  $filters
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function studentRecords(array $filters): array
    {
        // ponytail: semua siswa dimuat ke memori; pindahkan ke query SQL bila siswa mencapai puluhan ribu.
        $rows = $this->reports->studentsWithRegistrations($filters['school_id'] ?? null, $filters['search'] ?? null)
            ->map(fn (Student $s) => $this->studentRecord($s))
            ->filter(fn (array $r) => $this->inScope($r, $filters))
            ->values();

        $counts = array_fill_keys(array_keys(self::GROUPS), 0);
        foreach ($rows as $row) {
            $counts[$row['group']]++;
        }

        $order = array_flip(array_keys(self::GROUPS));
        $group = $filters['group'] ?? null;

        return [
            'rows' => $rows->when($group, fn ($c) => $c->where('group', $group))
                ->sortBy(fn (array $r) => $order[$r['group']])->values()->all(),
            'counts' => $counts,
        ];
    }

    /**
     * Pilihan sesi untuk filter (sesi tidak dibatalkan, terbaru lebih dulu).
     *
     * @return list<array{id: int, label: string}>
     */
    public function sessionOptions(): array
    {
        return array_reverse(array_map(
            fn (array $s) => ['id' => $s['id'], 'label' => "{$s['date']} · {$s['school']}"],
            $this->reports->sessionStatusCounts(null, null),
        ));
    }

    /** @return array<string, mixed> */
    private function studentRecord(Student $student): array
    {
        $entry = $student->currentRegistration();
        $recorded = $entry?->status === RegistrationStatus::SudahRekaman;
        $sessionDate = $entry?->schedule->date;

        return [
            'id' => $student->id,
            'name' => $student->name,
            'nik' => $student->nik,
            'nisn' => $student->nisn,
            'birth_date' => $student->birth_date->toDateString(),
            'gender' => $student->gender,
            'school' => $student->school->name,
            'group' => ! $recorded ? 'belum' : ($this->eligibility->directKtp($student, $sessionDate) ? 'langsung' : 'menunggu'),
            'schedule_id' => $entry?->schedule_id,
            'session_date' => $sessionDate?->toDateString(),
            'eligible_from' => $this->eligibility->directKtpFrom($student)->toDateString(),
            'reason' => $recorded ? null : ($entry?->status->label() ?? 'Belum mendaftar sesi'),
        ];
    }

    /** @param array<string, mixed> $row */
    private function inScope(array $row, array $filters): bool
    {
        $schedule = $filters['schedule_id'] ?? null;
        if ($schedule !== null && $row['schedule_id'] !== $schedule) {
            return false;
        }
        $date = $row['session_date'];
        if ($date === null) {
            return true;
        }
        [$from, $to] = [$filters['dari'] ?? null, $filters['sampai'] ?? null];

        return ($from === null || $date >= $from) && ($to === null || $date <= $to);
    }

    /** @param  array<string, mixed>  $extra  Properti tambahan log, mis. filter dan jumlah baris. */
    public function recordExport(User $actor, ?string $from, ?string $to, ?string $kind = null, array $extra = []): void
    {
        activity('laporan')->causedBy($actor)
            ->withProperties(['dari' => $from, 'sampai' => $to, ...($kind !== null ? ['jenis' => $kind] : []), ...$extra])
            ->log('laporan.diekspor');
    }
}
