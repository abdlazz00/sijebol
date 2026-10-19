<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Schools\Models\School;
use Spatie\Activitylog\Models\Activity;

/** Pendaftaran berstatus tertentu yang dibuat pada waktu tertentu. */
function makeEntryAt(S $status, School $school, string $createdAt): RegistrationStudent
{
    $entry = makeEntry($status, $school);
    $entry->forceFill(['created_at' => $createdAt])->save();

    return $entry;
}

beforeEach(function () {
    $this->service = app(ReportService::class);
});

test('rekap menghitung pendaftaran per sekolah dan seluruh status, sekolah tanpa pendaftaran tampil 0', function () {
    $a = School::factory()->create(['name' => 'SMPN A']);
    $b = School::factory()->create(['name' => 'SMPN B']);
    School::factory()->create(['name' => 'SMPN C']);
    makeEntry(S::MenungguVerifikasi, $a);
    makeEntry(S::MenungguVerifikasi, $a);
    makeEntry(S::SudahRekaman, $a);
    makeEntry(S::Terdaftar, $b);
    makeEntry(S::Dibatalkan, $b);

    $recap = $this->service->recap(null, null);

    expect(collect($recap['columns'])->pluck('key')->all())
        ->toBe(['menunggu_verifikasi', 'perlu_perbaikan', 'terdaftar', 'sudah_rekaman', 'tidak_hadir', 'belum_rekaman', 'ditolak', 'dibatalkan', 'kedaluwarsa'])
        ->and(collect($recap['columns'])->pluck('label')->first())->toBe('Menunggu verifikasi')
        ->and(collect($recap['rows'])->pluck('school')->all())->toBe(['SMPN A', 'SMPN B', 'SMPN C'])
        ->and($recap['rows'][0]['counts']['menunggu_verifikasi'])->toBe(2)
        ->and($recap['rows'][0]['total'])->toBe(3)
        ->and($recap['rows'][1]['total'])->toBe(2)
        ->and($recap['rows'][2]['total'])->toBe(0)
        ->and($recap['totals']['total'])->toBe(5)
        ->and($recap['totals']['counts']['menunggu_verifikasi'])->toBe(2)
        ->and($recap['totals']['counts']['dibatalkan'])->toBe(1)
        ->and($recap['totals']['counts']['sudah_rekaman'])->toBe(1);
});

test('tanpa sekolah, rekap kosong dengan total 0', function () {
    $recap = $this->service->recap(null, null);

    expect($recap['rows'])->toBe([])
        ->and($recap['totals']['total'])->toBe(0)
        ->and($recap['totals']['counts']['menunggu_verifikasi'])->toBe(0);
});

test('filter tanggal pendaftaran inklusif sampai 23:59:59 hari sampai', function () {
    $s = School::factory()->create();
    makeEntryAt(S::MenungguVerifikasi, $s, '2026-10-04 23:59:59');
    makeEntryAt(S::MenungguVerifikasi, $s, '2026-10-05 00:00:00');
    makeEntryAt(S::MenungguVerifikasi, $s, '2026-10-06 23:59:59');
    makeEntryAt(S::MenungguVerifikasi, $s, '2026-10-07 00:00:00');

    expect($this->service->recap('2026-10-05', '2026-10-06')['totals']['total'])->toBe(2)
        ->and($this->service->recap('2026-10-06', null)['totals']['total'])->toBe(2)
        ->and($this->service->recap(null, '2026-10-05')['totals']['total'])->toBe(2)
        ->and($this->service->recap(null, null)['totals']['total'])->toBe(4);
});

test('hitungan status operator hanya milik sekolahnya', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    makeEntry(S::Ditolak, $a);
    makeEntry(S::Ditolak, $a);
    makeEntry(S::Ditolak, $b);
    makeEntry(S::MenungguVerifikasi, $b);

    $this->actingAs(makeUserWith([], $a, true, 'op-lap'));

    expect(app(ReportRepositoryInterface::class)->statusCounts())->toBe(['ditolak' => 2]);
});

test('ekspor dicatat di log aktivitas dengan filternya', function () {
    $admin = makeUserWith(['laporan.lihat'], roleName: 'pelapor');

    $this->service->recordExport($admin, '2026-10-01', null);

    $log = Activity::where('description', 'laporan.diekspor')->sole();
    expect($log->causer_id)->toBe($admin->id)
        ->and($log->properties['dari'])->toBe('2026-10-01')
        ->and($log->properties['sampai'])->toBeNull();
});

/** Dua sesi berisi campuran status untuk rekap kehadiran. */
function sesiUntukRekap(): array
{
    $a = School::factory()->create(['name' => 'SMPN A']);
    $b = School::factory()->create(['name' => 'SMPN B']);
    $sesiA = makeSchedule($a, '2026-10-20');
    $sesiB = makeSchedule($b, '2026-10-21');
    foreach ([S::SudahRekaman, S::SudahRekaman, S::SudahRekaman, S::BelumRekaman, S::TidakHadir, S::Terdaftar, S::Ditolak] as $status) {
        makeEntry($status, $a, schedule: $sesiA);
    }
    makeEntry(S::MenungguVerifikasi, $b, schedule: $sesiB);
    makeEntry(S::MenungguVerifikasi, $b, schedule: $sesiB);

    return [$sesiA, $sesiB];
}

test('rekap kehadiran per sesi menghitung angka per baris dan total', function () {
    [$sesiA, $sesiB] = sesiUntukRekap();

    $recap = $this->service->sessionRecap(null, null);

    expect($recap['rows'])->toHaveCount(2)
        ->and($recap['rows'][0])->toMatchArray([
            'id' => $sesiA->id, 'date' => '2026-10-20', 'start_time' => '08:00', 'end_time' => '12:00', 'school' => 'SMPN A',
            'location' => $sesiA->location, 'quota' => 30, 'taken' => 5, 'present' => 3, 'not_recorded' => 1, 'absent' => 1, 'attendance' => 80,
        ])
        ->and($recap['rows'][1])->toMatchArray(['id' => $sesiB->id, 'taken' => 2, 'present' => 0, 'not_recorded' => 0, 'absent' => 0, 'attendance' => null])
        ->and($recap['totals'])->toBe(['quota' => 60, 'taken' => 7, 'present' => 3, 'not_recorded' => 1, 'absent' => 1, 'attendance' => 80]);
});

test('rekap kehadiran tidak memuat sesi dibatalkan dan terurut tanggal lalu id', function () {
    [$sesiA, $sesiB] = sesiUntukRekap();
    $sama = makeSchedule($sesiA->school, '2026-10-20');
    $batal = makeSchedule($sesiA->school, '2026-10-19');
    $batal->update(['cancelled_at' => now()]);

    $ids = collect($this->service->sessionRecap(null, null)['rows'])->pluck('id')->all();

    expect($ids)->toBe([$sesiA->id, $sama->id, $sesiB->id]);
});

test('filter rekap kehadiran memakai tanggal sesi, inklusif di kedua ujung', function () {
    [$sesiA, $sesiB] = sesiUntukRekap();

    $ids = fn (?string $dari, ?string $sampai) => collect($this->service->sessionRecap($dari, $sampai)['rows'])->pluck('id')->all();

    expect($ids('2026-10-20', '2026-10-21'))->toBe([$sesiA->id, $sesiB->id])
        ->and($ids('2026-10-21', null))->toBe([$sesiB->id])
        ->and($ids(null, '2026-10-20'))->toBe([$sesiA->id])
        ->and($ids('2026-10-22', null))->toBe([]);

    $kosong = $this->service->sessionRecap('2026-10-22', null);
    expect($kosong['totals'])->toBe(['quota' => 0, 'taken' => 0, 'present' => 0, 'not_recorded' => 0, 'absent' => 0, 'attendance' => null]);
});

test('operator terikat sekolah hanya melihat sesi sekolahnya pada rekap kehadiran', function () {
    [$sesiA] = sesiUntukRekap();
    $this->actingAs(makeUserWith([], $sesiA->school, true, 'op-rekap-sesi'));

    $rows = $this->service->sessionRecap(null, null)['rows'];

    expect($rows)->toHaveCount(1)->and($rows[0]['id'])->toBe($sesiA->id);
});
