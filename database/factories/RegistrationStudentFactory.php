<?php

namespace Database\Factories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistrationStudent> */
class RegistrationStudentFactory extends Factory
{
    protected $model = RegistrationStudent::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'status' => RegistrationStatus::MenungguVerifikasi,
        ];
    }
}
