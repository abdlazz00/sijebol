<?php

namespace App\Domains\Registrations\Enums;

enum RegistrationStatus: string
{
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case PerluPerbaikan = 'perlu_perbaikan';
    case Terdaftar = 'terdaftar';
    case SudahRekaman = 'sudah_rekaman';
    case TidakHadir = 'tidak_hadir';
    case BelumRekaman = 'belum_rekaman';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';
    case Kedaluwarsa = 'kedaluwarsa';

    public function label(): string
    {
        return match ($this) {
            self::MenungguVerifikasi => 'Menunggu verifikasi',
            self::PerluPerbaikan => 'Perlu perbaikan',
            self::Terdaftar => 'Terdaftar',
            self::SudahRekaman => 'Sudah rekaman',
            self::TidakHadir => 'Tidak hadir',
            self::BelumRekaman => 'Belum rekaman',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
            self::Kedaluwarsa => 'Kedaluwarsa',
        };
    }

    /**
     * Transisi yang sah dari status ini (state machine spec alur mandiri §6).
     * Terdaftar yang sesinya lewat menjadi Tidak hadir, bukan Kedaluwarsa.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::MenungguVerifikasi => [self::Terdaftar, self::PerluPerbaikan, self::Ditolak, self::Dibatalkan, self::Kedaluwarsa],
            self::PerluPerbaikan => [self::MenungguVerifikasi, self::Dibatalkan, self::Kedaluwarsa],
            self::Terdaftar => [self::SudahRekaman, self::TidakHadir, self::BelumRekaman, self::Dibatalkan],
            self::SudahRekaman, self::TidakHadir, self::BelumRekaman => [self::Terdaftar],
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
     * Status yang menghitung sebagai satu-satunya pendaftaran aktif siswa (sama dengan kolom `active_student_id`).
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::MenungguVerifikasi, self::PerluPerbaikan, self::Terdaftar, self::SudahRekaman];
    }

    /**
     * Status yang menahan satu kursi di sesi (kuota).
     *
     * @return list<self>
     */
    public static function holdingSeat(): array
    {
        return [...self::active(), self::BelumRekaman];
    }

    /**
     * Status yang mengunci data dan dokumen siswa.
     *
     * @return list<self>
     */
    public static function locking(): array
    {
        return [self::MenungguVerifikasi, self::Terdaftar, self::SudahRekaman];
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
