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
