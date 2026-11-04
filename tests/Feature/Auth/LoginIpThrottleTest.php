<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

// Batas kecil agar tes singkat; angka bawaan (50 per 10 menit) ada di config/security.php.
beforeEach(fn () => config(['security.login_ip_failures' => 6, 'security.login_ip_window' => 600]));

function loginAs(string $email, string $password = 'password')
{
    return test()->post('/login', ['email' => $email, 'password' => $password]);
}

test('kegagalan dengan email berbeda dari satu IP akhirnya mengunci IP itu, bahkan untuk kredensial benar', function () {
    User::factory()->create(['email' => 'korban@x.test']);

    foreach (range(1, 6) as $i) {
        loginAs("tebak{$i}@x.test", 'salah')->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    loginAs('korban@x.test')->assertSessionHasErrors('throttle');
    expect(session('errors')->first('throttle'))->toContain('Terlalu banyak percobaan. Coba lagi dalam');
    $this->assertGuest();
    expect(Activity::where('description', 'login.terkunci')->exists())->toBeTrue();
});

test('IP lain tidak ikut terkunci', function () {
    User::factory()->create(['email' => 'ok@x.test']);
    foreach (range(1, 6) as $i) {
        loginAs("tebak{$i}@x.test", 'salah');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post('/login', ['email' => 'ok@x.test', 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('login sukses tidak menambah hitungan, banyak siswa berhasil masuk dari satu IP tidak terkunci', function () {
    foreach (range(1, 10) as $i) {
        User::factory()->create(['email' => "siswa{$i}@x.test"]);
        loginAs("siswa{$i}@x.test")->assertRedirect('/dashboard');
        $this->post('/logout');
    }

    loginAs('siswa1@x.test')->assertRedirect('/dashboard');
});

test('login sukses tidak mereset hitungan kegagalan IP', function () {
    User::factory()->create(['email' => 'milik-penyerang@x.test']);
    foreach (range(1, 5) as $i) {
        loginAs("tebak{$i}@x.test", 'salah');
    }
    loginAs('milik-penyerang@x.test')->assertRedirect('/dashboard');
    $this->post('/logout');

    loginAs('tebak6@x.test', 'salah');

    loginAs('milik-penyerang@x.test')->assertSessionHasErrors('throttle');
});

test('hitungan kegagalan IP kedaluwarsa sesudah jendela waktu', function () {
    User::factory()->create(['email' => 'ok@x.test']);
    foreach (range(1, 6) as $i) {
        loginAs("tebak{$i}@x.test", 'salah');
    }
    loginAs('ok@x.test')->assertSessionHasErrors('throttle');

    $this->travel(11)->minutes();

    loginAs('ok@x.test')->assertRedirect('/dashboard');
});
