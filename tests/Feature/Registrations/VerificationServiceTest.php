<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat', 'siswa.lihat'], $this->school, true, 'op-ver');
    $this->petugas = makeUserWith(['verifikasi.putuskan'], roleName: 'petugas-ver');
    $this->service = app(VerificationService::class);
});

/** Pendaftaran Menunggu verifikasi milik siswa berakun, lengkap dokumennya, pada sesi tertentu. */
function pendaftaranSiswa(School $school, ?string $tanggal = null): array
{
    [$akun, $siswa] = siswaLengkap($school);
    $entry = makeEntry(S::MenungguVerifikasi, $school, $siswa, makeSchedule($school, $tanggal));

    return [$akun, $entry];
}

test('keputusan dipetakan ke status, mencatat riwayat dan log, dan memberi tahu siswa itu saja', function (string $keputusan, S $status) {
    [$akun, $entry] = pendaftaranSiswa($this->school);
    [$lain] = siswaLengkap($this->school);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    expect($entry->fresh()->status)->toBe($status)
        ->and($entry->fresh()->decided_by)->toBe($this->petugas->id)
        ->and($entry->fresh()->note)->toBe('catatan')
        ->and($entry->histories()->count())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(1);
    Notification::assertSentToTimes($akun, RegistrationNotification::class, 1);
    Notification::assertNotSentTo([$this->op, $this->petugas, $lain], RegistrationNotification::class);
})->with([['approve', S::Terdaftar], ['fix', S::PerluPerbaikan], ['reject', S::Ditolak]]);

test('judul notifikasi sesuai keputusan', function (string $keputusan, string $judul) {
    [$akun, $entry] = pendaftaranSiswa($this->school);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    Notification::assertSentTo($akun, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->title() === $judul);
})->with([
    ['approve', 'Pendaftaran disetujui'], ['fix', 'Pendaftaran perlu diperbaiki'], ['reject', 'Pendaftaran ditolak'],
]);

test('setujui menerbitkan kode tiket unik dan tautan notifikasi ke tiket itu', function () {
    [$akunA, $a] = pendaftaranSiswa($this->school);
    [, $b] = pendaftaranSiswa($this->school);

    $this->service->decide($a, 'approve', null, $this->petugas);
    $this->service->decide($b, 'approve', null, $this->petugas);

    expect($a->fresh()->ticket_code)->toHaveLength(32)->and($b->fresh()->ticket_code)->toHaveLength(32)
        ->and($a->fresh()->ticket_code)->not->toBe($b->fresh()->ticket_code);
    Notification::assertSentTo($akunA, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->url() === "/me/tickets/{$a->id}");
});

test('minta perbaikan mengisi tenggat 48 jam untuk sesi jauh dan akhir hari ini untuk sesi besok', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    [, $jauh] = pendaftaranSiswa($this->school, '2026-10-29');
    [, $besok] = pendaftaranSiswa($this->school, '2026-10-20');

    $this->service->decide($jauh, 'fix', 'KK buram', $this->petugas);
    $this->service->decide($besok, 'fix', 'KK buram', $this->petugas);

    expect($jauh->fresh()->fix_deadline_at->setTimezone('Asia/Jakarta')->toDateTimeString())->toBe('2026-10-21 10:00:00')
        ->and($besok->fresh()->fix_deadline_at->setTimezone('Asia/Jakarta')->toDateTimeString())->toBe('2026-10-19 23:59:59')
        ->and($jauh->fresh()->ticket_code)->toBeNull();
});

test('minta perbaikan pada hari sesi ditolak, setujui dan tolak tetap boleh', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 07:00:00', 'Asia/Jakarta'));
    [$akun, $entry] = pendaftaranSiswa($this->school, '2026-10-20');

    expect(fn () => $this->service->decide($entry, 'fix', 'KK buram', $this->petugas))
        ->toThrow(AccessRuleException::class, 'Permintaan perbaikan hanya dapat dibuat sampai sehari sebelum sesi');
    expect($entry->fresh()->status)->toBe(S::MenungguVerifikasi)->and($entry->fresh()->fix_deadline_at)->toBeNull();
    Notification::assertNothingSent();

    $this->service->decide($entry, 'approve', null, $this->petugas);
    expect($entry->fresh()->status)->toBe(S::Terdaftar);
});

test('keputusan pada sesi yang sudah lewat ditolak', function (string $keputusan) {
    Carbon::setTestNow(Carbon::parse('2026-10-21 07:00:00', 'Asia/Jakarta'));
    [, $entry] = pendaftaranSiswa($this->school, '2026-10-20');

    expect(fn () => $this->service->decide($entry, $keputusan, 'catatan', $this->petugas))
        ->toThrow(AccessRuleException::class, 'Sesi sudah lewat');
    expect($entry->fresh()->status)->toBe(S::MenungguVerifikasi);
    Notification::assertNothingSent();
})->with(['approve', 'fix', 'reject']);

test('setujui tunggal memeriksa ulang kelayakan pada tanggal sesi', function () {
    [$akun, $entry] = pendaftaranSiswa($this->school);
    $entry->student->documents()->where('type', 'akta_lahir')->delete();

    expect(fn () => $this->service->decide($entry, 'approve', null, $this->petugas))
        ->toThrow(AccessRuleException::class, 'Akta kelahiran belum diunggah.');
    expect($entry->fresh()->status)->toBe(S::MenungguVerifikasi)->and($entry->fresh()->ticket_code)->toBeNull();
    Notification::assertNothingSent();
});

test('siswa tanpa akun tidak membuat keputusan gagal', function () {
    $entry = makeEntry(S::MenungguVerifikasi, $this->school, eligibleStudent($this->school));

    $this->service->decide($entry, 'approve', null, $this->petugas);

    expect($entry->fresh()->status)->toBe(S::Terdaftar);
    Notification::assertNothingSent();
});

test('menolak atau meminta perbaikan melepas kursi di sesi', function (string $keputusan) {
    $entry = makeEntry(S::MenungguVerifikasi, $this->school);
    expect($entry->schedule->seatsTaken())->toBe(1);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    expect($entry->schedule->seatsTaken())->toBe($keputusan === 'reject' ? 0 : 1);
})->with(['reject', 'fix']);

test('entri yang bukan menunggu verifikasi tidak dapat diputuskan, dan keputusan kedua ditolak', function () {
    $terdaftar = makeEntry(S::Terdaftar, $this->school);
    expect(fn () => $this->service->decide($terdaftar, 'approve', null, $this->petugas))->toThrow(AccessRuleException::class);

    [, $entry] = pendaftaranSiswa($this->school);
    $this->service->decide($entry, 'approve', null, $this->petugas);
    expect(fn () => $this->service->decide($entry, 'reject', 'x', $this->petugas))->toThrow(AccessRuleException::class);
    expect($entry->histories()->count())->toBe(1);
});

test('antrean hanya memuat pendaftaran menunggu verifikasi dan dapat disaring per sesi', function () {
    $sesi = makeSchedule($this->school);
    makeEntry(S::MenungguVerifikasi, $this->school, schedule: $sesi);
    makeEntry(S::MenungguVerifikasi, $this->school);
    makeEntry(S::PerluPerbaikan, $this->school);
    makeEntry(S::Terdaftar, $this->school);

    expect($this->service->queue()->total())->toBe(2);

    request()->query->set('filter', ['schedule_id' => $sesi->id]);
    expect($this->service->queue()->total())->toBe(1);
});

test('setujui massal: semua Terdaftar, tiket berbeda, satu notifikasi per siswa', function () {
    $sesi = makeSchedule($this->school);
    $akun = [];
    $entries = collect(range(1, 3))->map(function () use ($sesi, &$akun) {
        [$user, $siswa] = siswaLengkap($this->school);
        $akun[] = $user;

        return makeEntry(S::MenungguVerifikasi, $this->school, $siswa, $sesi);
    });

    $jumlah = $this->service->approveMany($entries->pluck('id')->all(), $this->petugas);

    expect($jumlah)->toBe(3);
    foreach ($entries as $e) {
        expect($e->fresh()->status)->toBe(S::Terdaftar)->and($e->fresh()->decided_by)->toBe($this->petugas->id)
            ->and($e->fresh()->ticket_code)->toHaveLength(32)->and($e->histories()->count())->toBe(1);
    }
    expect($entries->map->fresh()->pluck('ticket_code')->unique())->toHaveCount(3)
        ->and(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(3);
    foreach ($akun as $user) {
        Notification::assertSentToTimes($user, RegistrationNotification::class, 1);
    }
    Notification::assertNotSentTo([$this->op, $this->petugas], RegistrationNotification::class);
});

test('satu entri tidak layak pada tanggal sesinya menggagalkan seluruh persetujuan massal tanpa tiket dan notifikasi', function (string $kasus) {
    [, $baik] = pendaftaranSiswa($this->school);
    $siswa = match ($kasus) {
        'tanpa dokumen' => Student::factory()->for($this->school)->create(['name' => 'Tanpa Dokumen']),
        'umur di bawah 16' => tap(eligibleStudent($this->school), fn (Student $s) => $s->update(['name' => 'Terlalu Muda', 'birth_date' => now()->subYears(15)->toDateString()])),
    };
    $buruk = makeEntry(S::MenungguVerifikasi, $this->school, $siswa);

    try {
        $this->service->approveMany([$baik->id, $buruk->id], $this->petugas);
        $this->fail('Seharusnya ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['entry_ids'][0])->toContain($siswa->name);
    }

    expect($baik->fresh()->status)->toBe(S::MenungguVerifikasi)->and($buruk->fresh()->status)->toBe(S::MenungguVerifikasi)
        ->and($baik->histories()->count())->toBe(0)->and($baik->fresh()->ticket_code)->toBeNull();
    Notification::assertNothingSent();
})->with(['tanpa dokumen', 'umur di bawah 16']);

test('umur dinilai pada tanggal sesi: siswa yang genap 16 sebelum sesi boleh disetujui', function () {
    $sesi = makeSchedule($this->school, now('Asia/Jakarta')->addDays(10)->toDateString());
    $siswa = eligibleStudent($this->school);
    $siswa->update(['birth_date' => now('Asia/Jakarta')->subYears(16)->addDays(5)->toDateString()]);
    $entry = makeEntry(S::MenungguVerifikasi, $this->school, $siswa, $sesi);

    expect($this->service->approveMany([$entry->id], $this->petugas))->toBe(1);
});

test('persetujuan massal ditolak seluruhnya untuk id tidak ada dan status bukan menunggu verifikasi', function (string $kasus) {
    [, $baik] = pendaftaranSiswa($this->school);
    $buruk = match ($kasus) {
        'id tidak ada' => 999999,
        'sudah diputuskan' => makeEntry(S::Terdaftar, $this->school)->id,
        'perlu perbaikan' => makeEntry(S::PerluPerbaikan, $this->school)->id,
    };

    expect(fn () => $this->service->approveMany([$baik->id, $buruk], $this->petugas))->toThrow(ValidationException::class);

    expect($baik->fresh()->status)->toBe(S::MenungguVerifikasi);
    Notification::assertNothingSent();
})->with(['id tidak ada', 'sudah diputuskan', 'perlu perbaikan']);

test('operator terikat sekolah lain tidak dapat menyetujui entri sekolah ini (entri tak terlihat)', function () {
    [, $entri] = pendaftaranSiswa($this->school);
    $terikat = makeUserWith(['verifikasi.putuskan'], School::factory()->create(), true, 'terikat-lain');
    $this->actingAs($terikat);

    expect(fn () => $this->service->approveMany([$entri->id], $terikat))->toThrow(ValidationException::class);
    expect($entri->fresh()->status)->toBe(S::MenungguVerifikasi);
});
