<?php

namespace Database\Factories;

use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Models\User;
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
            'location' => 'Aula '.fake()->words(2, true),
            'quota' => 30,
            'created_by' => User::factory(),
        ];
    }
}
