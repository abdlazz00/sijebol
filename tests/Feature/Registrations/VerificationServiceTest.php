<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat', 'siswa.lihat'], $this->school, true, 'op-ver');
    $this->petugas = makeUserWith(['verifikasi.putuskan'], roleName: 'petugas-ver');
    $this->service = app(VerificationService::class);
});

test('keputusan dipetakan ke status, mencatat riwayat dan log, tanpa notifikasi (menyusul di 7c)', function (string $keputusan, S $status) {
    $entry = makeEntry(S::MenungguVerifikasi, $this->school);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    expect($entry->fresh()->status)->toBe($status)
        ->and($entry->fresh()->decided_by)->toBe($this->petugas->id)
        ->and($entry->fresh()->note)->toBe('catatan')
        ->and($entry->histories()->count())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(1);
    Notification::assertNothingSent();
})->with([['approve', S::Terdaftar], ['fix', S::PerluPerbaikan], ['reject', S::Ditolak]]);

test('menolak atau meminta perbaikan melepas kursi di sesi', function (string $keputusan) {
    $entry = makeEntry(S::MenungguVerifikasi, $this->school);
    expect($entry->schedule->seatsTaken())->toBe(1);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    expect($entry->schedule->seatsTaken())->toBe($keputusan === 'reject' ? 0 : 1);
})->with(['reject', 'fix']);

test('entri yang bukan menunggu verifikasi tidak dapat diputuskan, dan keputusan kedua ditolak', function () {
    $terdaftar = makeEntry(S::Terdaftar, $this->school);
    expect(fn () => $this->service->decide($terdaftar, 'approve', null, $this->petugas))->toThrow(AccessRuleException::class);

    $entry = makeEntry(S::MenungguVerifikasi, $this->school);
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

test('setujui massal: semua Terdaftar, riwayat per entri, log per entri, tanpa notifikasi', function () {
    $sesi = makeSchedule($this->school);
    $entries = collect(range(1, 3))->map(fn () => makeEntry(S::MenungguVerifikasi, $this->school, eligibleStudent($this->school), $sesi));

    $jumlah = $this->service->approveMany($entries->pluck('id')->all(), $this->petugas);

    expect($jumlah)->toBe(3);
    foreach ($entries as $e) {
        expect($e->fresh()->status)->toBe(S::Terdaftar)->and($e->fresh()->decided_by)->toBe($this->petugas->id)->and($e->histories()->count())->toBe(1);
    }
    expect(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(3);
    Notification::assertNothingSent();
});

test('satu entri tidak layak pada tanggal sesinya menggagalkan seluruh persetujuan massal', function (string $kasus) {
    $baik = makeEntry(S::MenungguVerifikasi, $this->school, eligibleStudent($this->school));
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

    expect($baik->fresh()->status)->toBe(S::MenungguVerifikasi)->and($buruk->fresh()->status)->toBe(S::MenungguVerifikasi)->and($baik->histories()->count())->toBe(0);
})->with(['tanpa dokumen', 'umur di bawah 16']);

test('umur dinilai pada tanggal sesi: siswa yang genap 16 sebelum sesi boleh disetujui', function () {
    $sesi = makeSchedule($this->school, now('Asia/Jakarta')->addDays(10)->toDateString());
    $siswa = eligibleStudent($this->school);
    $siswa->update(['birth_date' => now('Asia/Jakarta')->subYears(16)->addDays(5)->toDateString()]);
    $entry = makeEntry(S::MenungguVerifikasi, $this->school, $siswa, $sesi);

    expect($this->service->approveMany([$entry->id], $this->petugas))->toBe(1);
});

test('persetujuan massal ditolak seluruhnya untuk id tidak ada dan status bukan menunggu verifikasi', function (string $kasus) {
    $baik = makeEntry(S::MenungguVerifikasi, $this->school, eligibleStudent($this->school));
    $buruk = match ($kasus) {
        'id tidak ada' => 999999,
        'sudah diputuskan' => makeEntry(S::Terdaftar, $this->school)->id,
        'perlu perbaikan' => makeEntry(S::PerluPerbaikan, $this->school)->id,
    };

    expect(fn () => $this->service->approveMany([$baik->id, $buruk], $this->petugas))->toThrow(ValidationException::class);

    expect($baik->fresh()->status)->toBe(S::MenungguVerifikasi);
})->with(['id tidak ada', 'sudah diputuskan', 'perlu perbaikan']);

test('operator terikat sekolah lain tidak dapat menyetujui entri sekolah ini (entri tak terlihat)', function () {
    $entri = makeEntry(S::MenungguVerifikasi, $this->school, eligibleStudent($this->school));
    $terikat = makeUserWith(['verifikasi.putuskan'], School::factory()->create(), true, 'terikat-lain');
    $this->actingAs($terikat);

    expect(fn () => $this->service->approveMany([$entri->id], $terikat))->toThrow(ValidationException::class);
    expect($entri->fresh()->status)->toBe(S::MenungguVerifikasi);
});
