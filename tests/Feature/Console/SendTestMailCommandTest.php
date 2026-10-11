<?php

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;

test('perintah sijebol:test-mail mengirim email uji coba ke alamat tujuan', function () {
    Mail::fake();

    $this->artisan('sijebol:test-mail penerima@example.com')
        ->expectsOutputToContain('Email uji coba berhasil dikirim')
        ->assertSuccessful();

    Mail::assertSent(TestMail::class, function ($mail) {
        return $mail->hasTo('penerima@example.com')
            && $mail->hasSubject('Uji Coba Pengiriman Email SIJEBOL');
    });
});

test('perintah sijebol:test-mail menolak alamat email yang tidak valid', function () {
    Mail::fake();

    $this->artisan('sijebol:test-mail bukan-email')
        ->expectsOutputToContain('Alamat email tidak valid')
        ->assertFailed();

    Mail::assertNothingSent();
});
