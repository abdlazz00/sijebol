<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Models\User;

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
    [S::MenungguVerifikasi, S::Terdaftar], [S::MenungguVerifikasi, S::PerluPerbaikan], [S::MenungguVerifikasi, S::Ditolak],
    [S::MenungguVerifikasi, S::Dibatalkan], [S::PerluPerbaikan, S::MenungguVerifikasi], [S::Terdaftar, S::SudahRekaman],
    [S::SudahRekaman, S::Terdaftar],
]);

test('transisi ilegal ditolak tanpa mengubah apa pun', function (S $from, S $to) {
    $entry = makeEntry($from);

    expect(fn () => app(StatusTransitioner::class)->move($entry, $to, User::factory()->create()))
        ->toThrow(AccessRuleException::class);
    expect($entry->fresh()->status)->toBe($from)->and($entry->histories()->count())->toBe(0);
})->with([
    [S::Ditolak, S::MenungguVerifikasi], [S::Dibatalkan, S::Terdaftar], [S::Kedaluwarsa, S::MenungguVerifikasi],
    [S::Terdaftar, S::MenungguVerifikasi], [S::PerluPerbaikan, S::Terdaftar], [S::MenungguVerifikasi, S::MenungguVerifikasi],
]);

test('keputusan petugas mengisi decided_by dan decided_at, pembatalan tidak', function () {
    $petugas = User::factory()->create();

    $moved = app(StatusTransitioner::class)->move(makeEntry(S::MenungguVerifikasi), S::Terdaftar, $petugas);
    expect($moved->decided_by)->toBe($petugas->id)->and($moved->decided_at)->not->toBeNull();

    $batal = app(StatusTransitioner::class)->move(makeEntry(S::MenungguVerifikasi), S::Dibatalkan, $petugas);
    expect($batal->decided_by)->toBeNull()->and($batal->decided_at)->toBeNull();
});

test('keputusan kedua pada entri yang sama ditolak walau modelnya basi', function () {
    $entry = makeEntry(S::MenungguVerifikasi);
    $basi = RegistrationStudent::find($entry->id);
    $actor = User::factory()->create();

    app(StatusTransitioner::class)->move($entry, S::Terdaftar, $actor);

    expect(fn () => app(StatusTransitioner::class)->move($basi, S::Ditolak, $actor))->toThrow(AccessRuleException::class);
    expect($entry->histories()->count())->toBe(1);
});

test('aksi sistem (tanpa aktor) mencatat riwayat tanpa pelaku dan tidak mengisi decided_*', function () {
    $entry = makeEntry(S::PerluPerbaikan);

    $moved = app(StatusTransitioner::class)->move($entry, S::Kedaluwarsa, null, 'Batas waktu perbaikan terlewati.');

    expect($moved->status)->toBe(S::Kedaluwarsa)
        ->and($moved->decided_by)->toBeNull()->and($moved->decided_at)->toBeNull()
        ->and($moved->histories()->first()->by_user_id)->toBeNull()
        ->and($moved->histories()->first()->note)->toBe('Batas waktu perbaikan terlewati.');
});

test('keputusan oleh aktor tetap mengisi decided_* walau atribut tambahan ikut disimpan', function () {
    $petugas = User::factory()->create();

    $moved = app(StatusTransitioner::class)->move(makeEntry(S::MenungguVerifikasi), S::Terdaftar, $petugas, null, ['ticket_code' => 'KODE-TES']);

    expect($moved->decided_by)->toBe($petugas->id)->and($moved->ticket_code)->toBe('KODE-TES');
});
