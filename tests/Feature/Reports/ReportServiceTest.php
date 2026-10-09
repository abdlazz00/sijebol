<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Schools\Models\School;
use Spatie\Activitylog\Models\Activity;

/** Entri berstatus tertentu yang pendaftarannya diajukan pada waktu tertentu. */
function makeEntryAt(S $status, School $school, string $submittedAt): RegistrationStudent
{
    $entry = makeEntry($status, $school);
    $entry->registration->update(['submitted_at' => $submittedAt]);

    return $entry;
}

beforeEach(function () {
    $this->service = app(ReportService::class);
});

test('rekap menghitung entri per sekolah dan status, draf tidak ikut, sekolah tanpa entri tampil 0', function () {
    $a = School::factory()->create(['name' => 'SMPN A']);
    $b = School::factory()->create(['name' => 'SMPN B']);
    School::factory()->create(['name' => 'SMPN C']);
    makeEntry(S::Diajukan, $a);
    makeEntry(S::Diajukan, $a);
    makeEntry(S::SudahRekaman, $a);
    makeEntry(S::Terjadwal, $b);
    makeEntry(S::Draf, $b);

    $recap = $this->service->recap(null, null);

    expect(collect($recap['columns'])->pluck('key')->all())
        ->toBe(['diajukan', 'perlu_perbaikan', 'terverifikasi', 'terjadwal', 'sudah_rekaman', 'tidak_hadir', 'belum_rekaman', 'ditolak'])
        ->and(collect($recap['columns'])->pluck('label')->first())->toBe('Diajukan')
        ->and(collect($recap['rows'])->pluck('school')->all())->toBe(['SMPN A', 'SMPN B', 'SMPN C'])
        ->and($recap['rows'][0]['counts']['diajukan'])->toBe(2)
        ->and($recap['rows'][0]['total'])->toBe(3)
        ->and($recap['rows'][1]['total'])->toBe(1)
        ->and($recap['rows'][2]['total'])->toBe(0)
        ->and($recap['totals']['total'])->toBe(4)
        ->and($recap['totals']['counts']['diajukan'])->toBe(2)
        ->and($recap['totals']['counts']['sudah_rekaman'])->toBe(1);
});

test('tanpa sekolah, rekap kosong dengan total 0', function () {
    $recap = $this->service->recap(null, null);

    expect($recap['rows'])->toBe([])
        ->and($recap['totals']['total'])->toBe(0)
        ->and($recap['totals']['counts']['diajukan'])->toBe(0);
});

test('filter tanggal pengajuan inklusif sampai 23:59:59 hari sampai', function () {
    $s = School::factory()->create();
    makeEntryAt(S::Diajukan, $s, '2026-10-04 23:59:59');
    makeEntryAt(S::Diajukan, $s, '2026-10-05 00:00:00');
    makeEntryAt(S::Diajukan, $s, '2026-10-06 23:59:59');
    makeEntryAt(S::Diajukan, $s, '2026-10-07 00:00:00');

    expect($this->service->recap('2026-10-05', '2026-10-06')['totals']['total'])->toBe(2)
        ->and($this->service->recap('2026-10-06', null)['totals']['total'])->toBe(2)
        ->and($this->service->recap(null, '2026-10-05')['totals']['total'])->toBe(2)
        ->and($this->service->recap(null, null)['totals']['total'])->toBe(4);
});

test('hitungan status operator hanya milik sekolahnya', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    makeEntry(S::Draf, $a);
    makeEntry(S::Draf, $a);
    makeEntry(S::Draf, $b);
    makeEntry(S::Diajukan, $b);

    $this->actingAs(makeUserWith([], $a, true, 'op-lap'));

    expect(app(ReportRepositoryInterface::class)->statusCounts())->toBe(['draf' => 2]);
});

test('ekspor dicatat di log aktivitas dengan filternya', function () {
    $admin = makeUserWith(['laporan.lihat'], roleName: 'pelapor');

    $this->service->recordExport($admin, '2026-10-01', null);

    $log = Activity::where('description', 'laporan.diekspor')->sole();
    expect($log->causer_id)->toBe($admin->id)
        ->and($log->properties['dari'])->toBe('2026-10-01')
        ->and($log->properties['sampai'])->toBeNull();
});
