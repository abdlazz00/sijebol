<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

test('kolom tiket, tenggat, dan penanda pengingat ada', function () {
    expect(Schema::hasColumns('registration_students', ['ticket_code', 'fix_deadline_at', 'fix_reminded_at', 'session_reminded_at']))->toBeTrue();
});

test('kode tiket unik, banyak pendaftaran boleh tanpa kode', function () {
    makeEntry(S::Terdaftar)->update(['ticket_code' => 'KODE-A']);

    expect(fn () => makeEntry(S::Terdaftar)->update(['ticket_code' => 'KODE-A']))->toThrow(UniqueConstraintViolationException::class);

    makeEntry(S::MenungguVerifikasi);
    makeEntry(S::MenungguVerifikasi);
    expect(true)->toBeTrue();
});

test('riwayat tanpa pelaku (sistem) dapat disimpan', function () {
    $entry = makeEntry(S::Kedaluwarsa);

    $history = $entry->histories()->create(['from_status' => S::PerluPerbaikan, 'to_status' => S::Kedaluwarsa, 'by_user_id' => null]);

    expect($history->fresh()->by_user_id)->toBeNull()->and($history->fresh()->user)->toBeNull();
});

test('tenggat dan penanda pengingat tersimpan sebagai waktu', function () {
    $entry = makeEntry(S::PerluPerbaikan);
    $entry->update(['fix_deadline_at' => '2026-10-21 10:00:00', 'fix_reminded_at' => '2026-10-20 10:00:00', 'session_reminded_at' => '2026-10-20 08:00:00']);

    $fresh = $entry->fresh();
    expect($fresh->fix_deadline_at->toDateTimeString())->toBe('2026-10-21 10:00:00')
        ->and($fresh->fix_reminded_at)->not->toBeNull()
        ->and($fresh->session_reminded_at)->not->toBeNull();
});

test('scanned_at kosong secara bawaan dan tersimpan sebagai waktu', function () {
    expect(Schema::hasColumn('registration_students', 'scanned_at'))->toBeTrue();

    $entry = makeEntry(S::Terdaftar);
    expect($entry->fresh()->scanned_at)->toBeNull();

    $entry->update(['scanned_at' => '2026-10-20 08:15:00']);
    expect($entry->fresh()->scanned_at->toDateTimeString())->toBe('2026-10-20 08:15:00');
});
