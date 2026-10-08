<?php

namespace App\Domains\Registrations\Enums;

enum RegistrationStatus: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case PerluPerbaikan = 'perlu_perbaikan';
    case Terverifikasi = 'terverifikasi';
    case Terjadwal = 'terjadwal';
    case SudahRekaman = 'sudah_rekaman';
    case TidakHadir = 'tidak_hadir';
    case BelumRekaman = 'belum_rekaman';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::PerluPerbaikan => 'Perlu perbaikan',
            self::Terverifikasi => 'Terverifikasi',
            self::Terjadwal => 'Terjadwal',
            self::SudahRekaman => 'Sudah rekaman',
            self::TidakHadir => 'Tidak hadir',
            self::BelumRekaman => 'Belum rekaman',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * Transisi yang sah dari status ini (state machine spec v1 §3.7, ditambah keluarkan dari jadwal).
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Draf => [self::Diajukan],
            self::Diajukan => [self::Terverifikasi, self::PerluPerbaikan, self::Ditolak],
            self::PerluPerbaikan => [self::Diajukan],
            self::Terverifikasi => [self::Terjadwal],
            self::Terjadwal => [self::Terverifikasi, self::SudahRekaman, self::TidakHadir, self::BelumRekaman],
            default => [],
        };
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function locksStudent(): bool
    {
        return in_array($this, self::locking(), true);
    }

    /**
     * Status yang menghitung sebagai satu-satunya entri aktif siswa (sama dengan kolom `active_student_id`).
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Diajukan, self::PerluPerbaikan, self::Terverifikasi, self::Terjadwal, self::SudahRekaman];
    }

    /**
     * Status yang mengunci data dan dokumen siswa.
     *
     * @return list<self>
     */
    public static function locking(): array
    {
        return [self::Diajukan, self::Terverifikasi, self::Terjadwal, self::SudahRekaman];
    }

    /**
     * @param  list<self>  $cases
     * @return list<string>
     */
    public static function values(array $cases): array
    {
        return array_map(fn (self $case) => $case->value, $cases);
    }
}
