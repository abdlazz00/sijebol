<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sijebol:test-mail {email : Alamat email penerima pengujian}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim email uji coba untuk memverifikasi konfigurasi mailer Resend';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Alamat email tidak valid: {$email}");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $this->info("Mengirim email uji coba ke {$email} menggunakan mailer [{$mailer}]...");

        try {
            Mail::to($email)->send(new TestMail);

            $this->info("Email uji coba berhasil dikirim ke {$email}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Gagal mengirim email: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
