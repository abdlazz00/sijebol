<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('email tidak terdaftar mendapat respons yang sama dengan email terdaftar', function () {
    Notification::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'tidakada@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

test('reset dengan token tidak valid tidak membedakan pengguna tidak ada dan token salah', function () {
    $user = User::factory()->create();

    $unknownUser = $this->post('/reset-password', [
        'token' => 'ngawur', 'email' => 'tidakada@example.com',
        'password' => 'abcd1234', 'password_confirmation' => 'abcd1234',
    ])->assertSessionHasErrors('email');

    $badToken = $this->post('/reset-password', [
        'token' => 'ngawur', 'email' => $user->email,
        'password' => 'abcd1234', 'password_confirmation' => 'abcd1234',
    ])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe('Link reset tidak berlaku atau sudah kedaluwarsa.');
});

test('permintaan berulang untuk email terdaftar tetap tidak menampilkan error throttle', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');
});

test('notifikasi reset password menggunakan bahasa Indonesia dan identitas SIJEBOL', function () {
    Notification::fake();

    $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);

    $this->post('/forgot-password', ['email' => 'budi@example.com']);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Atur Ulang Password Akun SIJEBOL'
            && str_contains($mail->greeting ?? '', 'Budi Santoso')
            && str_contains($mail->actionUrl, url('/reset-password/'))
            && str_contains($mail->actionText, 'Atur Ulang Password');
    });
});
