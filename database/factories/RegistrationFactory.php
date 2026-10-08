<?php

namespace Database\Factories;

use App\Domains\Registrations\Models\Registration;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Registration> */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => 'Perekaman '.fake()->words(2, true),
            'expected_count' => null,
            'created_by' => User::factory(),
            'submitted_at' => null,
        ];
    }
}
