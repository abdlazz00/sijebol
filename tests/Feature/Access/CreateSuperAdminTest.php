<?php

use App\Domains\Access\Models\AccountActivation;
use App\Models\User;
use Database\Seeders\AccessSeeder;

test('membuat super-admin pertama dan mencetak link aktivasi', function () {
    $this->seed(AccessSeeder::class);

    $this->artisan('sijebol:create-super-admin', ['email' => 'Root@Kecamatan.test', 'name' => 'Root'])
        ->expectsOutputToContain('/activation/')
        ->assertSuccessful();

    $user = User::firstWhere('email', 'root@kecamatan.test');
    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->activated_at)->toBeNull()
        ->and(AccountActivation::where('user_id', $user->id)->exists())->toBeTrue();
});

test('gagal dengan pesan jelas bila role belum di-seed atau email sudah ada', function () {
    $this->artisan('sijebol:create-super-admin', ['email' => 'a@x.test'])->assertFailed();

    $this->seed(AccessSeeder::class);
    User::factory()->create(['email' => 'a@x.test']);
    $this->artisan('sijebol:create-super-admin', ['email' => 'a@x.test'])->assertFailed();
});
