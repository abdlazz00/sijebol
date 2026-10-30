<?php

use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
});

test('tamu dialihkan ke halaman login saat membuka /dashboard', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('siswa mengakses /dashboard mendapatkan halaman Student/Dashboard tanpa redirect', function () {
    $student = User::factory()->create()->assignRole('siswa');

    $this->actingAs($student)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Dashboard')
            ->has('student')
            ->has('stepper')
            ->has('smart_hero')
            ->has('school_schedules')
            ->has('guidelines')
        );
});

test('staf operator mengakses /dashboard mendapatkan halaman staf Dashboard', function () {
    $operator = User::factory()->create()->assignRole('operator-sekolah');

    $this->actingAs($operator)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('summary')
            ->has('kpi')
        );
});
