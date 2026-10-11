<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Uji Coba Pengiriman Email SIJEBOL',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<h2>Uji Coba Pengiriman Email SIJEBOL</h2>'.
                '<p>Halo,</p>'.
                '<p>Email ini dikirim untuk menguji konfigurasi mailer Resend pada sistem SIJEBOL Kecamatan Sungai Beduk.</p>'.
                '<p>Jika Anda menerima email ini, berarti integrasi Resend telah berhasil dan siap digunakan.</p>'.
                '<br><p>Salam hangat,<br><strong>Tim SIJEBOL Kec. Sungai Beduk</strong></p>',
        );
    }
}
