<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('nama dan nomor HP dapat diubah, email tidak', function () {
    $user = User::factory()->create(['email' => 'tetap@x.test']);

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Nama Baru',
        'phone' => '081234567890',
        'email' => 'dicoba@x.test',
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');

    $user->refresh();
    expect($user->name)->toBe('Nama Baru')
        ->and($user->phone)->toBe('081234567890')
        ->and($user->email)->toBe('tetap@x.test');
});

test('nomor HP tidak valid ditolak', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', ['name' => 'X', 'phone' => 'abc'])->assertSessionHasErrors('phone');
});
