<?php

namespace App\Domains\Reports\Services;

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;

class DashboardService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * Data Beranda lengkap untuk pengguna. Mendukung Staf Kecamatan dan Operator Sekolah.
     *
     * @return array{
     *     summary: list<array{label: string, value: int, caption: string, icon: string}>,
     *     actions: list<array{title: string, description: string, label: string, href: string, icon: string}>,
     *     kpi: list<array{key: string, label: string, value: int, percentage: int|null, unit: string, variant: string, icon: string}>,
     *     quick_actions: list<array{label: string, href: string, icon: string, variant: string}>,
     *     charts: array{
     *         school_recap: array{categories: list<string>, series: list<array{name: string, data: list<int>}>},
     *         recording_status: array{labels: list<string>, series: list<int>, total: int},
     *         monthly_trend: array{categories: list<string>, series: list<array{name: string, data: list<int>}>}
     *     },
     *     is_operator: bool,
     *     school_name: string|null
     * }
     */
    public function forUser(User $user): array
    {
        $counts = $this->reports->statusCounts();
        $n = fn (S $status): int => $counts[$status->value] ?? 0;
        $bound = $user->school_id !== null;
        $summary = [];
        $actions = [];

        // 1. Backward-compatible summary cards
        if (! $bound && $user->can('verifikasi.lihat')) {
            $summary[] = $this->card('Menunggu verifikasi', $n(S::Diajukan), 'Entri berstatus Diajukan', 'clipboard-list');
        }
        if ($bound && $user->can('pendaftaran.lihat')) {
            $summary[] = $this->card('Draf', $n(S::Draf), 'Belum diajukan', 'clipboard-list');
            $summary[] = $this->card('Perlu perbaikan', $n(S::PerluPerbaikan), 'Menunggu perbaikan sekolah', 'clipboard-list');
        }
        if ($user->can('jadwal.lihat')) {
            if (! $bound) {
                $summary[] = $this->card('Terjadwal', $n(S::Terjadwal), 'Menunggu penandaan rekaman', 'calendar-days');
            }
            $summary[] = $this->card('Sudah rekaman', $n(S::SudahRekaman), 'Sudah direkam', 'clipboard-check');
        }

        // 2. Backward-compatible action items
        if ($user->can('verifikasi.lihat') && $n(S::Diajukan) > 0) {
            $actions[] = $this->action($n(S::Diajukan).' entri menunggu verifikasi', 'Periksa data dan dokumen siswa yang diajukan sekolah.', 'Buka antrean', route('verifikasi.index', absolute: false), 'clipboard-list');
        }
        if ($user->can('jadwal.buat') && $n(S::Terverifikasi) > 0) {
            $actions[] = $this->action($n(S::Terverifikasi).' siswa terverifikasi belum dijadwalkan', 'Tetapkan jadwal perekaman untuk mereka.', 'Atur jadwal', route('jadwal.index', absolute: false), 'calendar-plus');
        }
        if ($bound && $user->can('pendaftaran.lihat') && $n(S::PerluPerbaikan) > 0) {
            $actions[] = $this->action($n(S::PerluPerbaikan).' entri perlu diperbaiki', 'Perbaiki data atau dokumen, lalu ajukan ulang.', 'Buka pendaftaran', route('pendaftaran.index', absolute: false), 'clipboard-list');
        }
        if ($user->can('jadwal.lihat') && ! $user->can('jadwal.buat') && $n(S::Terjadwal) > 0) {
            $actions[] = $this->action($n(S::Terjadwal).' siswa terjadwal', 'Lihat jadwal perekaman sekolah Anda.', 'Lihat jadwal', route('jadwal.index', absolute: false), 'calendar-plus');
        }

        return [
            'summary' => $summary,
            'actions' => $actions,
            'kpi' => $this->kpiSummary($user, $counts),
            'quick_actions' => $this->quickActions($user),
            'charts' => $this->charts($user, $counts),
            'is_operator' => $bound,
            'school_name' => $bound ? $user->school?->name : null,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, value: int, percentage: int|null, unit: string, variant: string, icon: string}>
     */
    public function kpiSummary(User $user, array $counts): array
    {
        $n = fn (S $status): int => $counts[$status->value] ?? 0;
        $pct = fn (int $val, int $total): ?int => $total > 0 ? (int) round(($val / $total) * 100) : null;

        if ($user->school_id === null) {
            // Staf/Admin Kecamatan (se-kecamatan)
            $total = array_sum($counts);
            $scheduled = $n(S::Terjadwal);
            $recorded = $n(S::SudahRekaman);
            $inProcess = $n(S::Terverifikasi);
            $unscheduled = $n(S::Draf) + $n(S::Diajukan) + $n(S::PerluPerbaikan);

            return [
                [
                    'key' => 'total_students',
                    'label' => 'Jumlah Pelajar Terdaftar',
                    'value' => $total,
                    'percentage' => null,
                    'unit' => 'Orang',
                    'variant' => 'blue',
                    'icon' => 'users',
                ],
                [
                    'key' => 'scheduled',
                    'label' => 'Sudah Dijadwalkan',
                    'value' => $scheduled,
                    'percentage' => $pct($scheduled, $total),
                    'unit' => 'Orang',
                    'variant' => 'green',
                    'icon' => 'calendar-check',
                ],
                [
                    'key' => 'recorded',
                    'label' => 'Sudah Melakukan Perekaman',
                    'value' => $recorded,
                    'percentage' => $pct($recorded, $total),
                    'unit' => 'Orang',
                    'variant' => 'amber',
                    'icon' => 'id-card',
                ],
                [
                    'key' => 'in_process',
                    'label' => 'Dalam Proses Jadwal',
                    'value' => $inProcess,
                    'percentage' => $pct($inProcess, $total),
                    'unit' => 'Orang',
                    'variant' => 'rose',
                    'icon' => 'clock',
                ],
                [
                    'key' => 'unscheduled',
                    'label' => 'Belum Dijadwalkan',
                    'value' => $unscheduled,
                    'percentage' => $pct($unscheduled, $total),
                    'unit' => 'Orang',
                    'variant' => 'slate',
                    'icon' => 'file-text',
                ],
            ];
        }

        // Operator Sekolah (khusus sekolahnya)
        $totalStudents = Student::query()->count();
        $eligibleCount = Student::query()
            ->where('birth_date', '<=', app(EligibilityService::class)->latestBirthDate())
            ->whereHas('documents', fn ($d) => $d->where('type', 'kk'))
            ->whereHas('documents', fn ($d) => $d->where('type', 'akta_lahir'))
            ->count();
        $ineligibleCount = max(0, $totalStudents - $eligibleCount);
        $scheduled = $n(S::Terjadwal);
        $recorded = $n(S::SudahRekaman);

        return [
            [
                'key' => 'total_students',
                'label' => 'Total Siswa Terdaftar',
                'value' => $totalStudents,
                'percentage' => null,
                'unit' => 'Siswa',
                'variant' => 'blue',
                'icon' => 'users',
            ],
            [
                'key' => 'eligible',
                'label' => 'Memenuhi Syarat',
                'value' => $eligibleCount,
                'percentage' => $pct($eligibleCount, $totalStudents),
                'unit' => 'Siswa',
                'variant' => 'green',
                'icon' => 'shield-check',
            ],
            [
                'key' => 'ineligible',
                'label' => 'Belum Memenuhi Syarat',
                'value' => $ineligibleCount,
                'percentage' => $pct($ineligibleCount, $totalStudents),
                'unit' => 'Siswa',
                'variant' => 'rose',
                'icon' => 'alert-circle',
            ],
            [
                'key' => 'scheduled',
                'label' => 'Sudah Masuk Jadwal',
                'value' => $scheduled,
                'percentage' => $pct($scheduled, $totalStudents),
                'unit' => 'Siswa',
                'variant' => 'amber',
                'icon' => 'calendar-check',
            ],
            [
                'key' => 'recorded',
                'label' => 'Sudah Selesai Rekam',
                'value' => $recorded,
                'percentage' => $pct($recorded, $totalStudents),
                'unit' => 'Siswa',
                'variant' => 'slate',
                'icon' => 'id-card',
            ],
        ];
    }

    /**
     * @return list<array{label: string, href: string, icon: string, variant: string}>
     */
    public function quickActions(User $user): array
    {
        $actions = [];

        if ($user->school_id === null) {
            // Staf Kecamatan / Admin
            if ($user->can('pendaftaran.buat')) {
                $actions[] = ['label' => 'Tambah Pendaftaran', 'href' => route('pendaftaran.create', absolute: false), 'icon' => 'plus', 'variant' => 'blue'];
            }
            if ($user->can('jadwal.buat')) {
                $actions[] = ['label' => 'Buat Jadwal', 'href' => route('jadwal.index', absolute: false), 'icon' => 'calendar-plus', 'variant' => 'green'];
            }
            if ($user->can('rekaman.tambah') || $user->can('jadwal.lihat')) {
                $actions[] = ['label' => 'Input Hasil Perekaman', 'href' => route('jadwal.index', absolute: false), 'icon' => 'id-card', 'variant' => 'amber'];
            }
            if ($user->can('laporan.ekspor') || $user->can('laporan.lihat')) {
                $actions[] = ['label' => 'Unduh Laporan', 'href' => route('laporan.index', absolute: false), 'icon' => 'download', 'variant' => 'rose'];
            }
        } else {
            // Operator Sekolah
            if ($user->can('pendaftaran.buat')) {
                $actions[] = ['label' => 'Ajukan Pendaftaran', 'href' => route('pendaftaran.create', absolute: false), 'icon' => 'clipboard-check', 'variant' => 'green'];
            }
            if ($user->can('siswa.lihat')) {
                $actions[] = ['label' => 'Cek Siswa Memenuhi Syarat', 'href' => route('siswa.index', ['filter' => ['eligible' => 1]], absolute: false), 'icon' => 'shield-check', 'variant' => 'amber'];
            }
            if ($user->can('laporan.lihat')) {
                $actions[] = ['label' => 'Unduh Rekap Sekolah', 'href' => route('laporan.index', absolute: false), 'icon' => 'download', 'variant' => 'rose'];
            }
        }

        return $actions;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{
     *     school_recap: array{categories: list<string>, series: list<array{name: string, data: list<int>}>},
     *     recording_status: array{labels: list<string>, series: list<int>, total: int},
     *     monthly_trend: array{categories: list<string>, series: list<array{name: string, data: list<int>}>}
     * }
     */
    public function charts(User $user, array $counts): array
    {
        $n = fn (S $status): int => $counts[$status->value] ?? 0;

        // 1. Donut: Status Perekaman
        $donutSeries = [
            $n(S::SudahRekaman),
            $n(S::Terjadwal),
            $n(S::Draf) + $n(S::Diajukan) + $n(S::PerluPerbaikan),
        ];
        $recordingStatus = [
            'labels' => ['Sudah Rekam', 'Dijadwalkan', 'Belum Dijadwalkan'],
            'series' => $donutSeries,
            'total' => array_sum($donutSeries),
        ];

        // 2. Bar: Rekap per Sekolah (Admin) vs Kesiapan Berkas (Operator)
        if ($user->school_id === null) {
            $schools = School::query()
                ->withCount('registrationEntries')
                ->orderByDesc('registration_entries_count')
                ->get();
            $top = $schools->take(5);
            $others = (int) $schools->slice(5)->sum('registration_entries_count');

            $categories = $top->pluck('name')->map(fn ($name) => (string) $name)->all();
            $data = $top->pluck('registration_entries_count')->map(fn ($c) => (int) $c)->all();

            if ($others > 0) {
                $categories[] = 'Lainnya';
                $data[] = $others;
            }
            if (empty($categories)) {
                $categories = ['Belum ada sekolah'];
                $data = [0];
            }

            $schoolRecap = [
                'categories' => $categories,
                'series' => [['name' => 'Pendaftar', 'data' => $data]],
            ];
        } else {
            $students = Student::query()->with('documents')->get();
            $both = $students->filter(fn ($s) => $s->documents->where('type', 'kk')->isNotEmpty() && $s->documents->where('type', 'akta_lahir')->isNotEmpty())->count();
            $onlyKk = $students->filter(fn ($s) => $s->documents->where('type', 'kk')->isNotEmpty() && $s->documents->where('type', 'akta_lahir')->isEmpty())->count();
            $onlyAkta = $students->filter(fn ($s) => $s->documents->where('type', 'kk')->isEmpty() && $s->documents->where('type', 'akta_lahir')->isNotEmpty())->count();
            $none = $students->filter(fn ($s) => $s->documents->isEmpty())->count();

            $schoolRecap = [
                'categories' => ['KK & Akta', 'Hanya KK', 'Hanya Akta', 'Belum Ada'],
                'series' => [['name' => 'Siswa', 'data' => [$both, $onlyKk, $onlyAkta, $none]]],
            ];
        }

        // 3. Line: Tren Perekaman Jan - Des Tahun Berjalan
        $year = (int) now()->year;
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $entries = RegistrationStudent::query()->whereYear('created_at', $year)->get(['created_at', 'status', 'updated_at']);

        $registered = array_fill(0, 12, 0);
        $recorded = array_fill(0, 12, 0);

        foreach ($entries as $e) {
            if ($e->created_at) {
                $m = (int) $e->created_at->format('n') - 1;
                $registered[$m]++;
            }
            if ($e->status === S::SudahRekaman && (int) $e->updated_at->year === $year) {
                $m = (int) $e->updated_at->format('n') - 1;
                $recorded[$m]++;
            }
        }

        $monthlyTrend = [
            'categories' => $months,
            'series' => [
                ['name' => 'Terdaftar', 'data' => $registered],
                ['name' => 'Sudah Rekam', 'data' => $recorded],
            ],
        ];

        return [
            'school_recap' => $schoolRecap,
            'recording_status' => $recordingStatus,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    /** @return array{label: string, value: int, caption: string, icon: string} */
    private function card(string $label, int $value, string $caption, string $icon): array
    {
        return compact('label', 'value', 'caption', 'icon');
    }

    /** @return array{title: string, description: string, label: string, href: string, icon: string} */
    private function action(string $title, string $description, string $label, string $href, string $icon): array
    {
        return compact('title', 'description', 'label', 'href', 'icon');
    }
}
