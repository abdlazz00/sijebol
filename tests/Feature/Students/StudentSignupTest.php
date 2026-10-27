<?php

use App\Domains\Access\Services\ActivationService;
use App\Domains\Notifications\Notifications\StudentSignupNotification;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    Notification::fake();
    $this->school = School::factory()->create(['name' => 'SMAN 1 Sungai Beduk']);
    $this->url = "/register/{$this->school->registration_token}";
});

test('token sah menampilkan form dan nama sekolah', function () {
    $this->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->component('Daftar/Index')
        ->where('valid', true)
        ->where('school', 'SMAN 1 Sungai Beduk')
        ->where('sent', false)
        ->where('ttl_days', ActivationService::TTL_DAYS));
});

test('token tidak dikenal menampilkan valid=false tanpa nama sekolah', function () {
    $this->get('/register/acak')->assertInertia(fn (Assert $p) => $p
        ->where('valid', false)
        ->missing('school'));
});

test('pendaftaran membuat akun siswa belum aktif di sekolah pemilik token', function () {
    $this->post($this->url, ['name' => 'Budi Santoso', 'email' => 'Budi@Example.com'])
        ->assertRedirect($this->url)
        ->assertSessionHas('sent', true);

    $user = User::where('email', 'budi@example.com')->first();

    expect($user->name)->toBe('Budi Santoso')
        ->and($user->school_id)->toBe($this->school->id)
        ->and($user->isStudent())->toBeTrue()
        ->and($user->activated_at)->toBeNull()
        ->and($user->password)->toBeNull();

    Notification::assertSentTo($user, StudentSignupNotification::class);
});

test('email yang sama dua kali (belum aktif) menerbitkan ulang link tanpa baris ganda', function () {
    $data = ['name' => 'Budi', 'email' => 'budi@example.com'];
    $this->post($this->url, $data);
    $user = User::where('email', 'budi@example.com')->first();
    $lama = $user->activations()->first();

    $this->post($this->url, $data);

    expect(User::where('email', 'budi@example.com')->count())->toBe(1)
        ->and($user->activations()->count())->toBe(1)
        ->and($user->activations()->first()->id)->not->toBe($lama->id);
    Notification::assertSentToTimes($user, StudentSignupNotification::class, 2);
});

test('email milik akun aktif, staf, atau akun non-siswa memberi respons identik dan tidak mengirim apa pun', function () {
    User::factory()->create(['email' => 'aktif@example.com', 'school_id' => $this->school->id])->assignRole('siswa');
    User::factory()->create(['email' => 'staf@example.com'])->assignRole('admin-kecamatan');
    $operator = User::factory()->pending()->create(['email' => 'op@example.com'])->assignRole('operator-sekolah');

    foreach (['aktif@example.com', 'staf@example.com', 'op@example.com'] as $email) {
        $this->post($this->url, ['name' => 'X', 'email' => $email])
            ->assertRedirect($this->url)
            ->assertSessionHas('sent', true);
    }

    Notification::assertNothingSent();
    expect(User::where('email', 'staf@example.com')->first()->name)->not->toBe('X')
        ->and($operator->activations()->count())->toBe(0);
});

test('siswa belum aktif yang mendaftar lewat sekolah lain tidak dipindahkan', function () {
    $lain = School::factory()->create();
    $this->post("/register/{$lain->registration_token}", ['name' => 'Budi', 'email' => 'budi@example.com']);

    $this->post($this->url, ['name' => 'Budi', 'email' => 'budi@example.com']);

    expect(User::where('email', 'budi@example.com')->first()->school_id)->toBe($lain->id);
});

test('token tidak dikenal tidak membuat akun dan tidak mengirim apa pun', function () {
    $this->post('/register/acak', ['name' => 'Budi', 'email' => 'budi@example.com'])
        ->assertRedirect('/register/acak');

    expect(User::count())->toBe(0);
    Notification::assertNothingSent();
});

test('nama dan email divalidasi', function () {
    $this->post($this->url, [])->assertSessionHasErrors(['name', 'email']);
    $this->post($this->url, ['name' => str_repeat('a', 151), 'email' => 'bukan-email'])
        ->assertSessionHasErrors(['name', 'email']);

    expect(User::count())->toBe(0);
});

test('email aktivasi menyebut sekolah, masa berlaku link, dan abaikan bila tidak mendaftar', function () {
    $this->post($this->url, ['name' => 'Budi', 'email' => 'budi@example.com']);
    $user = User::where('email', 'budi@example.com')->first();

    Notification::assertSentTo($user, StudentSignupNotification::class, function (StudentSignupNotification $n) use ($user) {
        $teks = implode(' ', $n->toMail($user)->introLines);

        expect($teks)->toContain('SMAN 1 Sungai Beduk')
            ->toContain((string) ActivationService::TTL_DAYS)
            ->toContain('Abaikan email ini');

        return true;
    });
});

test('link aktivasi dari email mengaktifkan akun dan siswa masuk', function () {
    $this->post($this->url, ['name' => 'Budi', 'email' => 'budi@example.com']);
    $user = User::where('email', 'budi@example.com')->first();

    $link = null;
    Notification::assertSentTo($user, StudentSignupNotification::class, function (StudentSignupNotification $n) use (&$link) {
        $link = $n->url();

        return true;
    });

    $this->post($link, ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->activated_at)->not->toBeNull();
});

test('POST pendaftaran dibatasi 5 kali per menit', function () {
    foreach (range(1, 5) as $i) {
        $this->post($this->url, ['name' => 'A', 'email' => "a{$i}@example.com"])->assertRedirect($this->url);
    }

    $this->post($this->url, ['name' => 'A', 'email' => 'a6@example.com'])->assertStatus(429);
});

test('siswa belum aktif yang memakai Lupa password dapat masuk setelah reset', function () {
    $user = User::factory()->pending()->create(['email' => 'budi@example.com', 'school_id' => $this->school->id])->assignRole('siswa');
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token, 'email' => $user->email,
        'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
    ])->assertRedirect('/login');

    expect($user->fresh()->activated_at)->not->toBeNull();
    $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect('/dashboard');
});
