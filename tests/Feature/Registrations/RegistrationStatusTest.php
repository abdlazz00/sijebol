<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;

test('peta transisi mengikuti state machine spec', function () {
    expect(S::MenungguVerifikasi->next())->toBe([S::Terdaftar, S::PerluPerbaikan, S::Ditolak, S::Dibatalkan, S::Kedaluwarsa])
        ->and(S::PerluPerbaikan->next())->toBe([S::MenungguVerifikasi, S::Dibatalkan, S::Kedaluwarsa])
        ->and(S::Terdaftar->next())->toBe([S::SudahRekaman, S::TidakHadir, S::BelumRekaman, S::Dibatalkan])
        ->and(S::SudahRekaman->next())->toBe([S::Terdaftar])
        ->and(S::TidakHadir->next())->toBe([S::Terdaftar])
        ->and(S::BelumRekaman->next())->toBe([S::Terdaftar])
        ->and(S::Ditolak->next())->toBe([])
        ->and(S::Dibatalkan->next())->toBe([])
        ->and(S::Kedaluwarsa->next())->toBe([]);
});

test('status aktif, penahan kursi, dan pengunci data', function () {
    expect(S::active())->toBe([S::MenungguVerifikasi, S::PerluPerbaikan, S::Terdaftar, S::SudahRekaman])
        ->and(S::holdingSeat())->toBe([S::MenungguVerifikasi, S::PerluPerbaikan, S::Terdaftar, S::SudahRekaman, S::BelumRekaman])
        ->and(S::locking())->toBe([S::MenungguVerifikasi, S::Terdaftar, S::SudahRekaman])
        ->and(S::Terdaftar->isActive())->toBeTrue()
        ->and(S::BelumRekaman->isActive())->toBeFalse()
        ->and(S::PerluPerbaikan->locksStudent())->toBeFalse();
});

test('setiap status punya label bahasa Indonesia', function () {
    expect(S::MenungguVerifikasi->label())->toBe('Menunggu verifikasi')
        ->and(S::Terdaftar->label())->toBe('Terdaftar')
        ->and(S::Dibatalkan->label())->toBe('Dibatalkan')
        ->and(S::Kedaluwarsa->label())->toBe('Kedaluwarsa')
        ->and(collect(S::cases())->every(fn (S $s) => $s->label() !== ''))->toBeTrue();
});
