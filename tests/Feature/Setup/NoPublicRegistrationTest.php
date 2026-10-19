<?php

test('rute registrasi publik tidak ada', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('rute verifikasi email tidak ada', function () {
    $this->get('/verify-email')->assertNotFound();
    $this->post('/email/verification-notification')->assertNotFound();
});

test('halaman utama mengalihkan ke dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

test('halaman daftar tanpa token yang dikenal tampil sebagai tidak berlaku, bukan 404', function () {
    $this->get('/daftar/token-acak')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Daftar/Index')->where('valid', false));
});
