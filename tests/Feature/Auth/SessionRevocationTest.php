<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

// phpunit.xml memakai sesi array; pengakhiran sesi bekerja pada tabel sessions (driver database seperti di produksi).
beforeEach(fn () => config(['session.driver' => 'database']));

function sessionRow(string $id, int $userId): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest', 'payload' => '', 'last_activity' => time(),
    ]);
}

test('ganti password mengeluarkan perangkat lain, sesi saat ini dan sesi orang lain tetap', function () {
    $user = User::factory()->create(['remember_token' => 'token-lama']);
    $lain = User::factory()->create();
    $current = Str::random(40);
    sessionRow('perangkat-lama-1', $user->id);
    sessionRow('perangkat-lama-2', $user->id);
    sessionRow($current, $user->id);
    sessionRow('milik-orang-lain', $lain->id);

    $this->withCookie(config('session.cookie'), $current)->actingAs($user)->from('/profile')
        ->put('/password', ['current_password' => 'password', 'password' => 'new-password1', 'password_confirmation' => 'new-password1'])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->whereIn('id', ['perangkat-lama-1', 'perangkat-lama-2'])->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', $current)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'milik-orang-lain')->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('token-lama');
});

test('ganti password gagal (password lama salah) tidak mengeluarkan siapa pun', function () {
    $user = User::factory()->create(['remember_token' => 'token-lama']);
    sessionRow('perangkat-lama-1', $user->id);

    $this->actingAs($user)->from('/profile')
        ->put('/password', ['current_password' => 'salah', 'password' => 'new-password1', 'password_confirmation' => 'new-password1'])
        ->assertSessionHasErrors('current_password');

    expect(DB::table('sessions')->where('id', 'perangkat-lama-1')->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->toBe('token-lama');
});

test('reset password mengakhiri semua sesi pengguna itu tanpa menyentuh sesi orang lain', function () {
    $user = User::factory()->create(['remember_token' => 'token-lama']);
    $lain = User::factory()->create();
    sessionRow('perangkat-lama-1', $user->id);
    sessionRow('perangkat-lama-2', $user->id);
    sessionRow('milik-orang-lain', $lain->id);

    $this->post('/reset-password', [
        'token' => Password::createToken($user), 'email' => $user->email,
        'password' => 'new-password1', 'password_confirmation' => 'new-password1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'milik-orang-lain')->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('token-lama');
});

test('reset password dengan token salah tidak mengakhiri sesi apa pun', function () {
    $user = User::factory()->create();
    sessionRow('perangkat-lama-1', $user->id);

    $this->post('/reset-password', [
        'token' => 'ngawur', 'email' => $user->email,
        'password' => 'new-password1', 'password_confirmation' => 'new-password1',
    ])->assertSessionHasErrors('email');

    expect(DB::table('sessions')->where('id', 'perangkat-lama-1')->exists())->toBeTrue();
});
