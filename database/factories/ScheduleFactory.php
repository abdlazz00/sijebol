<?php

namespace Database\Factories;

use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Schedule> */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'date' => now()->addDays(7)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
            // Aturan lama (dua hari sebelum sesi, akhir hari) agar tes yang menyetel `date` tetap bermakna.
            'registration_closes_at' => fn (array $attributes) => Carbon::parse($attributes['date'])->subDays(2)->setTime(23, 59, 59),
            'location' => 'Aula '.fake()->words(2, true),
            'quota' => 30,
            'created_by' => User::factory(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['cancelled_at' => now()]);
    }
}
