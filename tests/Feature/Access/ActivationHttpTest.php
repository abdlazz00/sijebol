<?php

use App\Domains\Access\Services\ActivationService;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function pendingUserWithToken(): array
{
    $user = User::factory()->pending()->create(['name' => 'Budi']);

    return [$user, app(ActivationService::class)->issue($user)];
}

test('link valid menampilkan data akun', function () {
    [$user, $token] = pendingUserWithToken();

    $this->get("/aktivasi/$token")->assertInertia(fn (Assert $p) => $p
        ->component('Auth/Activate')
        ->where('valid', true)
        ->where('name', 'Budi')
        ->where('email', $user->email));
});

test('link tidak dikenal menampilkan state tidak berlaku', function () {
    $this->get('/aktivasi/acak')->assertInertia(fn (Assert $p) => $p->where('valid', false));
});

test('aktivasi sukses mengisi password, login, dan mengarah ke dashboard', function () {
    [$user, $token] = pendingUserWithToken();

    $this->post("/aktivasi/$token", ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('success');

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->activated_at)->not->toBeNull();
});

test('submit kedua dengan token sama menampilkan state tidak berlaku dan tidak login', function () {
    [$user, $token] = pendingUserWithToken();
    $payload = ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'];
    $this->post("/aktivasi/$token", $payload);
    auth()->logout();
    $this->flushSession();

    $this->post("/aktivasi/$token", $payload)->assertRedirect("/aktivasi/$token");
    $this->assertGuest();
    $this->get("/aktivasi/$token")->assertInertia(fn (Assert $p) => $p->where('valid', false));
});

test('password lemah atau tidak cocok ditolak dan akun tetap belum aktif', function () {
    [$user, $token] = pendingUserWithToken();

    $this->post("/aktivasi/$token", ['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])
        ->assertSessionHasErrors('password');
    $this->post("/aktivasi/$token", ['password' => 'rahasia123', 'password_confirmation' => 'beda12345'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->activated_at)->toBeNull();
});

test('rute aktivasi dibatasi 10 permintaan per menit', function () {
    foreach (range(1, 10) as $_) {
        $this->get('/aktivasi/acak')->assertOk();
    }

    $this->get('/aktivasi/acak')->assertStatus(429);
});
