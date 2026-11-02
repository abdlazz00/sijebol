<?php

namespace Database\Factories;

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Carbon\Carbon;
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

    /**
     * Siswa dummy untuk simulasi (perintah sijebol:isi-data-dummy): NIK berawalan 9999 dan NISN 99 sebagai penanda
     * pembersihan, nama gaya Indonesia selaras jenis kelamin, dan berumur `$age` tahun hari ini (bukan tepat di hari ulang tahun).
     */
    public function dummy(int $age = 16): static
    {
        return $this->state(function () use ($age) {
            $faker = fake('id_ID');
            $gender = $faker->randomElement(['L', 'P']);

            return [
                'nik' => '9999'.$faker->unique()->numerify('############'),
                'nisn' => '99'.$faker->unique()->numerify('########'),
                'name' => $faker->name($gender === 'L' ? 'male' : 'female'),
                'gender' => $gender,
                'birth_date' => Carbon::now('Asia/Jakarta')->startOfDay()->subYears($age)->subDays($faker->numberBetween(1, 364))->toDateString(),
            ];
        });
    }
}
