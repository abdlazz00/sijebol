<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class EligibilityService
{
    public const MIN_AGE = 16;

    public const MAX_AGE = 17;

    private const ZONE = 'Asia/Jakarta';

    /** @return array{eligible: bool, reasons: list<string>} */
    public function check(Student $student, ?CarbonInterface $on = null): array
    {
        $reasons = [];
        $age = $this->age($student, $on);

        if ($age < self::MIN_AGE || $age > self::MAX_AGE) {
            $reasons[] = "Umur harus 16 atau 17 tahun (umur sekarang {$age}).";
        }

        $types = $student->documents->pluck('type')->all();
        if (! in_array('kk', $types, true)) {
            $reasons[] = 'Kartu Keluarga belum diunggah.';
        }
        if (! in_array('akta_lahir', $types, true)) {
            $reasons[] = 'Akta kelahiran belum diunggah.';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    public function age(Student $student, ?CarbonInterface $on = null): int
    {
        return (int) $student->birth_date->copy()->startOfDay()->diffInYears($this->day($on)->startOfDay(), true);
    }

    /**
     * Rentang tanggal lahir (inklusif) untuk umur 16–17 pada tanggal acuan.
     *
     * @return array{0: string, 1: string}
     */
    public function birthDateBounds(?CarbonInterface $on = null): array
    {
        $day = $this->day($on)->startOfDay();

        return [
            $day->copy()->subYears(self::MAX_AGE + 1)->addDay()->toDateString(),
            $day->copy()->subYears(self::MIN_AGE)->toDateString(),
        ];
    }

    private function day(?CarbonInterface $on): Carbon
    {
        return Carbon::instance($on ?? now())->setTimezone(self::ZONE);
    }
}
