<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// phpunit.xml memakai sesi array; fitur ini hanya berlaku untuk sesi database (seperti di produksi).
beforeEach(fn () => config(['session.driver' => 'database']));

function insertSessionRow(string $id, int $userId): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest', 'payload' => '', 'last_activity' => time(),
    ]);
}

test('sesi lain milik pengguna dihapus, sesi saat ini dan sesi pengguna lain tetap', function () {
    $user = User::factory()->create();
    $lain = User::factory()->create();
    $current = Str::random(40);
    insertSessionRow('hp-lama-1', $user->id);
    insertSessionRow('hp-lama-2', $user->id);
    insertSessionRow($current, $user->id);
    insertSessionRow('milik-orang-lain', $lain->id);

    // StartSession membuat id baru bila tidak ada cookie; kirim id sesi saat ini lewat cookie.
    $this->withCookie(config('session.cookie'), $current)->actingAs($user)
        ->delete('/profile/sessions', ['current_password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->whereIn('id', ['hp-lama-1', 'hp-lama-2'])->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', $current)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'milik-orang-lain')->exists())->toBeTrue();
});

test('remember token diputar agar perangkat lain tidak masuk lagi otomatis', function () {
    $user = User::factory()->create(['remember_token' => 'token-lama']);

    $this->actingAs($user)->delete('/profile/sessions', ['current_password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->remember_token)->not->toBe('token-lama');
});

test('password salah ditolak dan tidak ada sesi yang dihapus', function () {
    $user = User::factory()->create();
    insertSessionRow('hp-lama-1', $user->id);

    $this->actingAs($user)->delete('/profile/sessions', ['current_password' => 'salah'])
        ->assertSessionHasErrors('current_password');

    expect(DB::table('sessions')->where('id', 'hp-lama-1')->exists())->toBeTrue();
});

test('sukses memberi pesan flash', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/profile/sessions', ['current_password' => 'password'])
        ->assertSessionHas('success', 'Perangkat lain sudah dikeluarkan.');
});

test('driver sesi bukan database ditolak keras, bukan sukses palsu', function () {
    config(['session.driver' => 'file']);
    $user = User::factory()->create(['remember_token' => 'token-lama']);
    insertSessionRow('hp-lama-1', $user->id);

    $this->actingAs($user)->delete('/profile/sessions', ['current_password' => 'password'])
        ->assertStatus(501);

    expect(DB::table('sessions')->where('id', 'hp-lama-1')->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->toBe('token-lama');
});
