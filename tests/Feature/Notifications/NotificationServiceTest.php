<?php

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;

class NotifikasiUji extends BaseNotification
{
    public function title(): string
    {
        return 'T';
    }

    public function body(): string
    {
        return 'B';
    }

    public function url(): string
    {
        return '/x';
    }
}

test('penerima adalah pemilik permission yang aktif', function () {
    Notification::fake();
    $penerima = makeUserWith(['verifikasi.putuskan'], roleName: 'verifikator');
    $lain = makeUserWith(['siswa.lihat'], roleName: 'pengamat');
    $nonaktif = makeUserWith(['verifikasi.putuskan'], roleName: 'verifikator');
    $nonaktif->update(['is_active' => false]);

    $jumlah = app(NotificationService::class)->toPermission('verifikasi.putuskan', new NotifikasiUji);

    expect($jumlah)->toBe(1);
    Notification::assertSentTo($penerima, NotifikasiUji::class);
    Notification::assertNotSentTo([$lain, $nonaktif], NotifikasiUji::class);
});

test('pembatasan sekolah: sekolah lain tidak menerima, pengguna tanpa sekolah menerima', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $opA = makeUserWith(['verifikasi.putuskan'], $a, true, 'op');
    $opB = makeUserWith(['verifikasi.putuskan'], $b, true, 'op');
    $admin = makeUserWith(['verifikasi.putuskan'], roleName: 'admin');

    $jumlah = app(NotificationService::class)->toPermission('verifikasi.putuskan', new NotifikasiUji, $a->id);

    expect($jumlah)->toBe(2);
    Notification::assertSentTo([$opA, $admin], NotifikasiUji::class);
    Notification::assertNotSentTo($opB, NotifikasiUji::class);
});

test('pencarian penerima tidak tersaring scope sekolah pengirim', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $pengirim = makeUserWith([], $a, true, 'op');
    makeUserWith(['verifikasi.putuskan'], $b, true, 'op2');
    $this->actingAs($pengirim);

    expect(app(NotificationService::class)->toPermission('verifikasi.putuskan', new NotifikasiUji))->toBe(1);
});

test('tandai dibaca dan ringkasan', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiUji);
    $user->notify(new NotifikasiUji);
    $svc = app(NotificationService::class);

    expect($svc->summary($user)['unread_count'])->toBe(2);

    $svc->markRead($user, $user->notifications()->first()->id);
    expect($svc->summary($user)['unread_count'])->toBe(1);

    $svc->markAllRead($user);
    expect($svc->summary($user)['unread_count'])->toBe(0)
        ->and($svc->summary($user)['latest'])->toHaveCount(2)
        ->and($svc->summary($user)['latest'][0])->toHaveKeys(['id', 'title', 'body', 'url', 'read_at', 'created_at']);
});

test('tidak bisa menandai notifikasi milik orang lain', function () {
    $pemilik = User::factory()->create();
    $lain = User::factory()->create();
    $pemilik->notify(new NotifikasiUji);

    app(NotificationService::class)->markRead($lain, $pemilik->notifications()->first()->id);
})->throws(ModelNotFoundException::class);
