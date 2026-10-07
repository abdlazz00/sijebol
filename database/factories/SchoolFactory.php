<?php

namespace Database\Factories;

use App\Domains\Schools\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<School> */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'name' => 'SMAN '.fake()->unique()->numberBetween(1, 999).' Sungai Beduk',
            'npsn' => (string) fake()->unique()->numerify('########'),
            'level' => fake()->randomElement(['SMA', 'SMK', 'MA']),
            'address' => fake()->streetAddress(),
        ];
    }
}
