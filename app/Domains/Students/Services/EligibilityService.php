<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class EligibilityService
{
    public const MIN_AGE = 16;

    private const ZONE = 'Asia/Jakarta';

    /** @return array{eligible: bool, reasons: list<string>} */
    public function check(Student $student, ?CarbonInterface $on = null, ?int $exceptRegistrationId = null): array
    {
        $reasons = [];
        $age = $this->age($student, $on);

        if ($age < self::MIN_AGE) {
            $reasons[] = "Umur minimal 16 tahun (umur sekarang {$age}).";
        }

        $types = $student->documents->pluck('type')->all();
        if (! in_array('kk', $types, true)) {
            $reasons[] = 'Kartu Keluarga belum diunggah.';
        }
        if (! in_array('akta_lahir', $types, true)) {
            $reasons[] = 'Akta kelahiran belum diunggah.';
        }

        $busy = $student->registrationEntries->contains(
            fn ($entry) => $entry->status->isActive() && $entry->registration_id !== $exceptRegistrationId
        );
        if ($busy) {
            $reasons[] = 'Sudah terdaftar di pendaftaran lain.';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    public function age(Student $student, ?CarbonInterface $on = null): int
    {
        return (int) $student->birth_date->copy()->startOfDay()->diffInYears($this->day($on)->startOfDay(), true);
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
