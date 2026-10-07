<?php

test('menampilkan halaman 404 lewat Inertia untuk pengguna login', function () {
    $this->actingAs(makeUserWith([], roleName: 'admin-kecamatan'))
        ->get('/tidak-ada')
        ->assertStatus(404)
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

test('menampilkan halaman 403 bagi pengguna tanpa izin', function () {
    $this->actingAs(makeUserWith([], roleName: 'operator-sekolah'))
        ->get(route('role.index'))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
});

test('tamu mendapat halaman 404 tanpa data pengguna', function () {
    $this->get('/tidak-ada')
        ->assertStatus(404)
        ->assertInertia(fn ($page) => $page->component('Error')->where('auth.user', null));
});

test('permintaan JSON tetap menerima respons JSON', function () {
    $this->getJson('/tidak-ada')->assertStatus(404)->assertJsonStructure(['message']);
});
