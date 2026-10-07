<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

function attempt(string $email, string $password = 'password')
{
    return test()->post('/login', ['email' => $email, 'password' => $password]);
}

test('login sukses mengisi last_login_at dan mencatat log', function () {
    $user = User::factory()->create(['email' => 'a@x.test']);

    attempt('a@x.test')->assertRedirect('/dashboard');

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(Activity::where('log_name', 'akses')->where('description', 'login')->count())->toBe(1);
});

test('akun nonaktif, belum aktif, salah password, dan password kosong semuanya menerima pesan yang sama', function () {
    User::factory()->inactive()->create(['email' => 'off@x.test']);
    User::factory()->pending()->create(['email' => 'wait@x.test']);
    User::factory()->create(['email' => 'ok@x.test']);

    foreach ([['off@x.test', 'password'], ['wait@x.test', 'password'], ['ok@x.test', 'salah'], ['none@x.test', 'password']] as [$email, $pw]) {
        attempt($email, $pw)->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }

    // Akun tanpa password tidak pernah lolos, bahkan dengan password kosong.
    attempt('wait@x.test', '')->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('lima percobaan gagal mengunci login dan mencatat log', function () {
    User::factory()->create(['email' => 'a@x.test']);

    foreach (range(1, 5) as $_) {
        attempt('a@x.test', 'salah');
    }
    attempt('a@x.test', 'password')->assertSessionHasErrors('throttle');
    expect(session('errors')->first('throttle'))->toContain('Terlalu banyak percobaan. Coba lagi dalam');

    $this->assertGuest();
    expect(Activity::where('description', 'login.terkunci')->exists())->toBeTrue();
});

test('user yang dinonaktifkan saat masih login langsung ter-logout', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

test('logout dicatat', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    expect(Activity::where('description', 'logout')->exists())->toBeTrue();
});
