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

test('halaman profil menerima data profil pengguna', function () {
    $user = makeUserWith([], roleName: 'admin-kecamatan');
    $user->update(['phone' => '081234567890']);

    $this->actingAs($user)->get('/profile')
        ->assertInertia(fn ($page) => $page->component('Profile/Edit')
            ->where('profile.name', $user->name)
            ->where('profile.email', $user->email)
            ->where('profile.phone', '081234567890')
            ->where('profile.role', 'admin-kecamatan'));
});

test('nomor HP kosong disimpan sebagai null', function () {
    $user = User::factory()->create(['phone' => '081234567890']);

    $this->actingAs($user)->patch('/profile', ['name' => 'X', 'phone' => ''])->assertSessionHasNoErrors();

    expect($user->fresh()->phone)->toBeNull();
});
