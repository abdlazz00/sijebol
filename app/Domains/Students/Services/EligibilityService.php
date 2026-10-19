<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class EligibilityService
{
    public const MIN_AGE = 16;

    private const DIRECT_KTP_AGE = 17;

    private const ZONE = 'Asia/Jakarta';

    /** @return array{eligible: bool, reasons: list<string>} */
    public function check(Student $student, ?CarbonInterface $on = null, ?int $exceptEntryId = null): array
    {
        $reasons = [];
        $age = $this->age($student, $on);

        if ($age < self::MIN_AGE) {
            $reasons[] = "Umur minimal 16 tahun (umur {$age}).";
        }

        $types = $student->documents->pluck('type')->all();
        if (! in_array('kk', $types, true)) {
            $reasons[] = 'Kartu Keluarga belum diunggah.';
        }
        if (! in_array('akta_lahir', $types, true)) {
            $reasons[] = 'Akta kelahiran belum diunggah.';
        }

        $busy = $student->registrationEntries->contains(
            fn ($entry) => $entry->status->isActive() && $entry->id !== $exceptEntryId
        );
        if ($busy) {
            $reasons[] = 'Sudah memiliki pendaftaran sesi yang aktif.';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    public function age(Student $student, ?CarbonInterface $on = null): int
    {
        return (int) $student->birth_date->copy()->startOfDay()->diffInYears($this->day($on)->startOfDay(), true);
    }

    /**
     * Penanda informasi: sudah lewat ulang tahun ke-17 pada tanggal acuan (17 tahun lebih 1 hari), sehingga KTP-el dapat langsung terbit.
     * Lahir 29 Februari dihitung berulang tahun 28 Februari pada tahun biasa.
     */
    public function directKtp(Student $student, ?CarbonInterface $on = null): bool
    {
        // Dibandingkan sebagai tanggal (Y-m-d): tanggal lahir tersimpan tanpa zona, tanggal acuan di zona Jakarta.
        $seventeenth = $student->birth_date->copy()->addYearsNoOverflow(self::DIRECT_KTP_AGE)->toDateString();

        return $this->day($on)->toDateString() > $seventeenth;
    }

    /** Tanggal sesi pendaftaran aktif siswa (butuh `registrationEntries.schedule` termuat); null bila tidak ada. */
    public function activeSessionDate(Student $student): ?Carbon
    {
        return $student->registrationEntries->first(fn ($entry) => $entry->status->isActive())?->schedule->date;
    }

    /**
     * Tanggal lahir paling lambat (inklusif) agar berumur minimal 16 tahun pada tanggal acuan.
     */
    public function latestBirthDate(?CarbonInterface $on = null): string
    {
        return $this->day($on)->startOfDay()->subYears(self::MIN_AGE)->toDateString();
    }

    /**
     * Rentang tanggal lahir (inklusif) untuk umur minimal 16 pada tanggal acuan.
     *
     * @return array{0: string, 1: string}
     */
    public function birthDateBounds(?CarbonInterface $on = null): array
    {
        return [
            '1900-01-01',
            $this->latestBirthDate($on),
        ];
    }

    private function day(?CarbonInterface $on): Carbon
    {
        return Carbon::instance($on ?? now())->setTimezone(self::ZONE);
    }
}
