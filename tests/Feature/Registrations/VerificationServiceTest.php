<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.lihat'], $this->school, true, 'op-ver');
    $this->petugas = makeUserWith(['verifikasi.putuskan'], roleName: 'petugas-ver');
    $this->service = app(VerificationService::class);
});

test('keputusan dipetakan ke status, mencatat riwayat, log, dan memberi tahu operator sekolah', function (string $keputusan, S $status) {
    $entry = makeEntry(S::Diajukan, $this->school);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    expect($entry->fresh()->status)->toBe($status)
        ->and($entry->fresh()->decided_by)->toBe($this->petugas->id)
        ->and($entry->histories()->count())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(1);
    Notification::assertSentTo($this->op, EntryDecidedNotification::class);
    Notification::assertNotSentTo($this->petugas, EntryDecidedNotification::class);
})->with([['approve', S::Terverifikasi], ['fix', S::PerluPerbaikan], ['reject', S::Ditolak]]);

test('operator sekolah lain tidak diberi tahu', function () {
    $lain = makeUserWith(['pendaftaran.lihat'], School::factory()->create(), true, 'op-ver');
    $this->service->decide(makeEntry(S::Diajukan, $this->school), 'approve', null, $this->petugas);

    Notification::assertNotSentTo($lain, EntryDecidedNotification::class);
});

test('entri yang bukan berstatus diajukan tidak dapat diputuskan, dan keputusan kedua ditolak', function () {
    $draf = makeEntry(S::Draf, $this->school);
    expect(fn () => $this->service->decide($draf, 'approve', null, $this->petugas))->toThrow(AccessRuleException::class);

    $entry = makeEntry(S::Diajukan, $this->school);
    $this->service->decide($entry, 'approve', null, $this->petugas);
    expect(fn () => $this->service->decide($entry, 'reject', 'x', $this->petugas))->toThrow(AccessRuleException::class);
    expect($entry->histories()->count())->toBe(1);
});

test('antrean hanya memuat entri diajukan', function () {
    makeEntry(S::Diajukan, $this->school);
    makeEntry(S::Draf, $this->school);
    makeEntry(S::Terverifikasi, $this->school);

    expect($this->service->queue()->total())->toBe(1);
});
