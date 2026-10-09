<?php

namespace App\Domains\Reports\Services;

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Models\User;

class DashboardService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * Kartu dan aksi Beranda. Angka sudah dibatasi sekolah oleh scope model; operator (terikat sekolah)
     * dan staf kecamatan mendapat kartu berbeda, dan tiap kartu/aksi butuh izin modulnya.
     *
     * @return array{summary: list<array{label: string, value: int, caption: string, icon: string}>, actions: list<array{title: string, description: string, label: string, href: string, icon: string}>}
     */
    public function forUser(User $user): array
    {
        $counts = $this->reports->statusCounts();
        $n = fn (S $status): int => $counts[$status->value] ?? 0;
        $bound = $user->school_id !== null;
        $summary = [];
        $actions = [];

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

        return ['summary' => $summary, 'actions' => $actions];
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
