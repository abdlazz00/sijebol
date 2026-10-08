<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('transisi sah mengubah status dan menulis riwayat', function (S $from, S $to) {
    $actor = User::factory()->create();
    $entry = makeEntry($from);

    $moved = app(StatusTransitioner::class)->move($entry, $to, $actor, 'catatan');
    $history = $moved->histories()->first();

    expect($moved->status)->toBe($to)
        ->and($moved->note)->toBe('catatan')
        ->and($moved->histories()->count())->toBe(1)
        ->and($history->from_status)->toBe($from)
        ->and($history->to_status)->toBe($to)
        ->and($history->by_user_id)->toBe($actor->id);
})->with([
    [S::Draf, S::Diajukan], [S::Diajukan, S::Terverifikasi], [S::Diajukan, S::PerluPerbaikan],
    [S::Diajukan, S::Ditolak], [S::PerluPerbaikan, S::Diajukan],
]);

test('transisi ilegal ditolak tanpa mengubah apa pun', function (S $from, S $to) {
    $entry = makeEntry($from);

    expect(fn () => app(StatusTransitioner::class)->move($entry, $to, User::factory()->create()))
        ->toThrow(AccessRuleException::class);
    expect($entry->fresh()->status)->toBe($from)->and($entry->histories()->count())->toBe(0);
})->with([
    [S::Draf, S::Terverifikasi], [S::Terverifikasi, S::Diajukan], [S::Ditolak, S::Diajukan],
    [S::Diajukan, S::Draf], [S::PerluPerbaikan, S::Terverifikasi], [S::Diajukan, S::Diajukan],
]);

test('keputusan petugas mengisi decided_by dan decided_at, pengajuan tidak', function () {
    $petugas = User::factory()->create();
    $entry = makeEntry(S::Diajukan);

    $moved = app(StatusTransitioner::class)->move($entry, S::Terverifikasi, $petugas);
    expect($moved->decided_by)->toBe($petugas->id)->and($moved->decided_at)->not->toBeNull();

    $baru = app(StatusTransitioner::class)->move(makeEntry(S::Draf), S::Diajukan, $petugas);
    expect($baru->decided_by)->toBeNull();
});

test('keputusan kedua pada entri yang sama ditolak walau modelnya basi', function () {
    $entry = makeEntry(S::Diajukan);
    $basi = RegistrationStudent::find($entry->id);
    $actor = User::factory()->create();

    app(StatusTransitioner::class)->move($entry, S::Terverifikasi, $actor);

    expect(fn () => app(StatusTransitioner::class)->move($basi, S::Ditolak, $actor))->toThrow(AccessRuleException::class);
    expect($entry->histories()->count())->toBe(1);
});

test('mengajukan siswa yang sudah punya entri aktif lain dihentikan indeks unik', function () {
    $aktif = makeEntry(S::Diajukan);
    $draf = makeEntry(S::Draf, student: $aktif->student);

    expect(fn () => app(StatusTransitioner::class)->move($draf, S::Diajukan, User::factory()->create()))
        ->toThrow(UniqueConstraintViolationException::class);
    expect($draf->fresh()->status)->toBe(S::Draf)->and($draf->histories()->count())->toBe(0);
});
