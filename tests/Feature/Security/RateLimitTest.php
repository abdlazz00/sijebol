<?php

test('melebihi batas web menghasilkan 429 per pengguna dan pengguna lain tidak terdampak', function () {
    config(['security.rate_limit' => 3]);
    $a = makeUserWith([], roleName: 'rl-a');
    $b = makeUserWith([], roleName: 'rl-b');

    $this->actingAs($a);
    foreach (range(1, 3) as $_) {
        $this->get('/dashboard')->assertOk();
    }
    $this->get('/dashboard')->assertStatus(429);

    $this->actingAs($b)->get('/dashboard')->assertOk();
});

test('respons 429 dirender sebagai halaman error aplikasi, membawa Retry-After dan header keamanan', function () {
    config(['security.rate_limit' => 1]);
    $this->actingAs(makeUserWith([], roleName: 'rl-c'));
    $this->get('/dashboard')->assertOk();

    $this->get('/dashboard')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 429));
});

test('tamu dibatasi per IP', function () {
    config(['security.rate_limit' => 2]);

    $this->get('/login')->assertOk();
    $this->get('/login')->assertOk();
    $this->get('/login')->assertStatus(429);
});
