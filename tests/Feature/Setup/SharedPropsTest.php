<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('pengguna login menerima daftar permission miliknya', function () {
    PermissionModel::findOrCreate('user.lihat');
    $user = User::factory()->create();
    $user->givePermissionTo('user.lihat');

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.id', $user->id)
        ->where('auth.permissions', ['user.lihat'])
    );
});

test('tamu menerima permission kosong tanpa error', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', null)
        ->where('auth.permissions', [])
    );
});

test('flash success dan error dibagikan', function () {
    $user = User::factory()->create();

    $this->withSession(['success' => 'Tersimpan', 'error' => 'Gagal'])
        ->actingAs($user)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Tersimpan')
            ->where('flash.error', 'Gagal')
        );
});
