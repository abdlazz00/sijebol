<?php

namespace Database\Factories;

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'nik' => fake()->unique()->numerify('################'),
            'nisn' => fake()->unique()->numerify('##########'),
            'name' => fake()->name(),
            // Umur 16 tahun agar eligible secara umur secara bawaan.
            'birth_date' => now()->subYears(16)->subDays(fake()->numberBetween(1, 300))->toDateString(),
            'gender' => fake()->randomElement(['L', 'P']),
        ];
    }
}
