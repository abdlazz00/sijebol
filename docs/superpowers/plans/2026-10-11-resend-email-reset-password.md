# Resend Email & Password Reset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengintegrasikan Resend sebagai mailer aplikasi (`resend/resend-laravel`), mengkustomisasi email reset password agar beridentitas SIJEBOL dalam Bahasa Indonesia baku, dan menyediakan perintah pengujian pengiriman email riil.

**Architecture:** Memasang SDK resmi `resend/resend-laravel`, menyetel konfigurasi `.env` (`MAIL_MAILER=resend`, `MAIL_FROM_ADDRESS=onboarding@resend.dev`), mengimplementasikan `ResetPasswordNotification` berbahasa Indonesia baku pada domain Access, serta menyediakan perintah `sijebol:test-mail` untuk pengujian langsung ke inbox.

**Tech Stack:** Laravel 12+, PHP 8.3+, Resend API (`resend/resend-laravel`), Pest PHP.

**Spec:** [`docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`](file:///C:/Users/abdulaziz/Documents/pribadi/SIJEBOL/docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md) (Bagian 5.4 Lupa Password).

## Global Constraints

- Bahasa UI dan konten email wajib Bahasa Indonesia baku.
- Dilarang menambahkan baris `Co-Authored-By` pada git commit.
- API key Resend tidak boleh dicatat secara hardcoded di kode sumber, melainkan dibaca melalui variabel lingkungan `RESEND_API_KEY`.
- Semua pengujian Pest harus 100% lulus.

---

### Task 1: Instalasi Package resend/resend-laravel & Konfigurasi Lingkungan

**Files:**
- Modify: `composer.json`
- Modify: `.env.example`
- Modify: `.env`

**Interfaces:**
- Consumes: Composer, Laravel service container
- Produces: Driver mail transport `'resend'` aktif

- [ ] **Step 1: Install package `resend/resend-laravel` via Composer**

Run: `composer require resend/resend-laravel`

- [ ] **Step 2: Tambahkan konfigurasi di `.env` dan `.env.example`**

Pastikan entri berikut tersedia di `.env` dan `.env.example`:
```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="onboarding@resend.dev"
MAIL_FROM_NAME="SIJEBOL - Kec. Sungai Beduk"
RESEND_API_KEY=
```

---

### Task 2: Kustomisasi Notifikasi Reset Password Bahasa Indonesia (TDD)

**Files:**
- Create: `app/Domains/Access/Notifications/ResetPasswordNotification.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Auth/PasswordResetNotificationTest.php`

**Interfaces:**
- Consumes: `User` model, token reset password
- Produces: `MailMessage` berbahasa Indonesia dengan subjek "Atur Ulang Password Akun SIJEBOL", batas waktu 60 menit, dan tautan reset password resmi.

- [ ] **Step 1: Tulis unit test untuk verifikasi notifikasi reset password (RED)**

Buat file `tests/Feature/Auth/PasswordResetNotificationTest.php`:
```php
<?php

use App\Domains\Access\Notifications\ResetPasswordNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('permintaan reset password mengirim notifikasi berbahasa Indonesia dengan tautan valid', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'pengguna@example.com', 'name' => 'Budi Santoso']);

    $this->post('/lupa-password', ['email' => 'pengguna@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Atur Ulang Password Akun SIJEBOL'
            && str_contains($mail->introLines[0], 'Budi Santoso')
            && str_contains($mail->actionUrl, url('/reset-password/'));
    });
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (RED)**

Run: `php artisan test --filter=PasswordResetNotificationTest`
Expected: FAIL (karena notifikasi kustom belum dibuat).

- [ ] **Step 3: Buat notifikasi `ResetPasswordNotification.php` (GREEN)**

Buat file `app/Domains/Access/Notifications/ResetPasswordNotification.php`:
```php
<?php

namespace App\Domains\Access\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $name = $notifiable instanceof User ? $notifiable->name : 'Pengguna';

        return (new MailMessage)
            ->subject('Atur Ulang Password Akun SIJEBOL')
            ->greeting("Halo, {$name}")
            ->line('Kami menerima permintaan untuk mengatur ulang password akun SIJEBOL Anda.')
            ->action('Atur Ulang Password', $url)
            ->line('Tautan atur ulang password ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika Anda tidak merasa meminta pengaturan ulang password, Anda dapat mengabaikan email ini.');
    }
}
```

- [ ] **Step 4: Hubungkan ke model `User.php`**

Tambahkan method pada `app/Models/User.php`:
```php
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Domains\Access\Notifications\ResetPasswordNotification($token));
    }
```

- [ ] **Step 5: Jalankan test untuk memverifikasi kelulusan (GREEN)**

Run: `php artisan test --filter=PasswordResetNotificationTest`
Expected: PASS (1 test passed).

---

### Task 3: Perintah Smoke Test Email (Artisan Command)

**Files:**
- Create: `app/Console/Commands/SendTestMailCommand.php`
- Test: `tests/Feature/Console/SendTestMailCommandTest.php`

**Interfaces:**
- Consumes: `php artisan sijebol:test-mail {email}`
- Produces: Mengirim email percobaan langsung via Resend mailer dan melaporkan hasil ke terminal

- [ ] **Step 1: Tulis unit test untuk perintah test mail (RED)**

Buat `tests/Feature/Console/SendTestMailCommandTest.php`:
```php
<?php

use Illuminate\Support\Facades\Mail;

test('perintah sijebol:test-mail mengirim email uji coba', function () {
    Mail::fake();

    $this->artisan('sijebol:test-mail budi@example.com')
        ->expectsOutputToContain('Email uji coba berhasil dikirim')
        ->assertSuccessful();

    Mail::assertSent(function (\Illuminate\Mail\Mailable $mail) {
        return $mail->hasTo('budi@example.com')
            && $mail->hasSubject('Uji Coba Pengiriman Email SIJEBOL');
    });
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (RED)**

Run: `php artisan test --filter=SendTestMailCommandTest`
Expected: FAIL (perintah belum terdaftar).

- [ ] **Step 3: Implementasi `SendTestMailCommand.php` (GREEN)**

Buat `app/Console/Commands/SendTestMailCommand.php`.

- [ ] **Step 4: Jalankan test untuk memverifikasi kelulusan (GREEN)**

Run: `php artisan test --filter=SendTestMailCommandTest`
Expected: PASS.

---

### Task 4: Verifikasi Suite Penuh

**Files:**
- Test: Seluruh unit & feature test suite

- [ ] **Step 1: Jalankan Larastan**
Run: `composer phpstan` atau `./vendor/bin/phpstan`

- [ ] **Step 2: Jalankan Laravel Pint**
Run: `composer pint` atau `./vendor/bin/pint --test`

- [ ] **Step 3: Jalankan seluruh pengujian Pest PHP**
Run: `php artisan test --parallel`
Expected: Seluruh 458+ tests PASS.

---

### Task 5: Panduan Pengujian untuk Pengguna

- [ ] **Step 1: Berikan instruksi kepada pengguna cara mengisi `RESEND_API_KEY` dan menjalankan `php artisan sijebol:test-mail {email}`**
