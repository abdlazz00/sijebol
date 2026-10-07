<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'activated_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function pending(): static
    {
        return $this->state(['password' => null, 'activated_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
