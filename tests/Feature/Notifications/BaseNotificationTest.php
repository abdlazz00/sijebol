<?php

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

function contohNotifikasi(): BaseNotification
{
    return new class extends BaseNotification
    {
        public function title(): string
        {
            return 'Judul';
        }

        public function body(): string
        {
            return 'Isi pesan.';
        }

        public function url(): string
        {
            return '/users/5';
        }
    };
}

test('notifikasi dasar antre dengan channel database dan mail', function () {
    $n = contohNotifikasi();

    expect($n)->toBeInstanceOf(ShouldQueue::class)
        ->and($n->via(new User))->toBe(['database', 'mail'])
        ->and($n->tries)->toBe(3)
        ->and($n->backoff())->toBe([60, 300]);
});

test('toArray memuat title, body, dan url', function () {
    expect(contohNotifikasi()->toArray(new User))->toBe([
        'title' => 'Judul', 'body' => 'Isi pesan.', 'url' => '/users/5',
    ]);
});

test('email berbahasa Indonesia dengan tautan absolut', function () {
    $user = User::factory()->make(['name' => 'Sari']);
    $mail = contohNotifikasi()->toMail($user);

    expect($mail->subject)->toBe('Judul')
        ->and($mail->greeting)->toBe('Halo, Sari.')
        ->and($mail->introLines)->toBe(['Isi pesan.'])
        ->and($mail->actionText)->toBe('Buka di SIJEBOL')
        ->and($mail->actionUrl)->toBe(url('/users/5'));
});
