<?php

use App\Domains\Notifications\Notifications\StudentSignupNotification;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

const TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    Notification::fake();
    $this->school = School::factory()->create();
    $this->url = "/register/{$this->school->registration_token}";
    $this->data = ['name' => 'Budi', 'email' => 'budi@example.com'];
});

function nyalakanTurnstile(): void
{
    config(['services.turnstile.site_key' => 'kunci-situs', 'services.turnstile.secret' => 'rahasia-turnstile']);
}

test('tanpa secret, pendaftaran berjalan tanpa token dan tidak menghubungi Cloudflare', function () {
    Http::fake();

    $this->post($this->url, $this->data)->assertSessionHas('sent', true)->assertSessionHasNoErrors();

    Notification::assertSentTo(User::where('email', 'budi@example.com')->first(), StudentSignupNotification::class);
    Http::assertNothingSent();
    $this->get($this->url)->assertInertia(fn (Assert $p) => $p->where('turnstile_site_key', null));
});

test('saat aktif, halaman membawa site key', function () {
    nyalakanTurnstile();

    $this->get($this->url)->assertInertia(fn (Assert $p) => $p->where('turnstile_site_key', 'kunci-situs'));
});

test('saat aktif, tanpa token ditolak tanpa akun dan tanpa email', function () {
    nyalakanTurnstile();
    Http::fake();

    $this->post($this->url, $this->data)->assertSessionHasErrors('turnstile');

    expect(User::count())->toBe(0);
    Notification::assertNothingSent();
    Http::assertNothingSent();
});

test('saat aktif, token sah diverifikasi dengan secret, token, dan IP lalu pendaftaran berjalan', function () {
    nyalakanTurnstile();
    Http::fake([TURNSTILE_URL => Http::response(['success' => true])]);

    $this->post($this->url, [...$this->data, 'turnstile' => 'token-dari-widget'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('sent', true);

    Http::assertSent(fn ($request) => $request->url() === TURNSTILE_URL
        && $request['secret'] === 'rahasia-turnstile'
        && $request['response'] === 'token-dari-widget'
        && $request['remoteip'] === '127.0.0.1');
    Notification::assertSentTo(User::where('email', 'budi@example.com')->first(), StudentSignupNotification::class);
});

test('saat aktif, token yang ditolak Cloudflare tidak membuat akun atau mengirim email', function () {
    nyalakanTurnstile();
    Http::fake([TURNSTILE_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    $this->post($this->url, [...$this->data, 'turnstile' => 'token-palsu'])->assertSessionHasErrors('turnstile');

    expect(User::count())->toBe(0);
    Notification::assertNothingSent();
});

test('saat aktif, Cloudflare tidak terjangkau berarti ditolak dengan pesan, bukan galat server', function () {
    nyalakanTurnstile();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->post($this->url, [...$this->data, 'turnstile' => 'token'])
        ->assertSessionHasErrors(['turnstile' => 'Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.']);

    expect(User::count())->toBe(0);
});
