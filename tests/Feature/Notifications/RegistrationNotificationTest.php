<?php

use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;

test('toSchool hanya menjangkau pengguna sekolah itu yang berizin', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $opA = makeUserWith(['jadwal.lihat'], $a, true, 'op');
    $opB = makeUserWith(['jadwal.lihat'], $b, true, 'op');
    $staf = makeUserWith(['jadwal.lihat'], roleName: 'staf');
    $tanpaIzin = makeUserWith(['siswa.lihat'], $a, true, 'op-lain');
    $admin = makeUserWith([], roleName: 'super-admin');

    $count = app(NotificationService::class)->toSchool('jadwal.lihat', ScheduleNotification::marked(1, 1, 'Sudah rekaman', '2026-12-01'), $a->id);

    expect($count)->toBe(1);
    Notification::assertSentTo($opA, ScheduleNotification::class);
    Notification::assertNotSentTo([$opB, $staf, $tanpaIzin, $admin], ScheduleNotification::class);
});
