<?php

use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Notifications\Notifications\RegistrationSubmittedNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;

test('toSchool hanya menjangkau pengguna sekolah itu yang berizin', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $opA = makeUserWith(['pendaftaran.lihat'], $a, true, 'op');
    $opB = makeUserWith(['pendaftaran.lihat'], $b, true, 'op');
    $staf = makeUserWith(['pendaftaran.lihat'], roleName: 'staf');
    $tanpaIzin = makeUserWith(['siswa.lihat'], $a, true, 'op-lain');
    $admin = makeUserWith([], roleName: 'super-admin');

    $count = app(NotificationService::class)->toSchool('pendaftaran.lihat', new EntryDecidedNotification(S::Terverifikasi, 'Budi', 1, null), $a->id);

    expect($count)->toBe(1);
    Notification::assertSentTo($opA, EntryDecidedNotification::class);
    Notification::assertNotSentTo([$opB, $staf, $tanpaIzin, $admin], EntryDecidedNotification::class);
});

test('isi notifikasi pengajuan dan keputusan', function () {
    $baru = new RegistrationSubmittedNotification('SMAN 1', 3);
    $ulang = new RegistrationSubmittedNotification('SMAN 1', 1, true);
    $fix = new EntryDecidedNotification(S::PerluPerbaikan, 'Budi', 7, 'KK buram');
    $ok = new EntryDecidedNotification(S::Terverifikasi, 'Budi', 7, null);

    expect($baru->title())->toBe('Pendaftaran baru diajukan')
        ->and($baru->body())->toBe('SMAN 1: 3 siswa menunggu verifikasi.')
        ->and($baru->url())->toBe('/verifikasi')
        ->and($ulang->title())->toBe('Pengajuan ulang diterima')
        ->and($fix->title())->toBe('Perlu perbaikan')
        ->and($fix->body())->toBe('Budi: KK buram')
        ->and($ok->title())->toBe('Pendaftaran diverifikasi')
        ->and($ok->body())->toBe('Budi')
        ->and($ok->url())->toBe('/pendaftaran/7')
        ->and((new EntryDecidedNotification(S::Ditolak, 'Budi', 7, 'x'))->title())->toBe('Pendaftaran ditolak');
});
