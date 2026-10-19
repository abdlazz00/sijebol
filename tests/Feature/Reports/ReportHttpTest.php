<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Exports\RecapExport;
use App\Domains\Reports\Exports\SessionRecapExport;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'SMPN A']);
    $this->viewer = makeUserWith(['laporan.lihat'], roleName: 'pelapor');
});

test('halaman laporan memuat rekap dan filter kosong', function () {
    makeEntry(S::MenungguVerifikasi, $this->school);

    $this->actingAs($this->viewer)->get('/laporan')->assertInertia(fn (Assert $p) => $p
        ->component('Laporan/Index')
        ->where('recap.rows.0.school', 'SMPN A')
        ->where('recap.rows.0.counts.menunggu_verifikasi', 1)
        ->where('recap.totals.total', 1)
        ->where('filters.dari', null)
        ->where('filters.sampai', null));
});

test('filter dipakai halaman; salah satu batas boleh kosong; urutan terbalik dan format salah ditolak', function () {
    $this->actingAs($this->viewer)->get('/laporan?dari=2026-10-05&sampai=2026-10-06')
        ->assertInertia(fn (Assert $p) => $p->where('filters.dari', '2026-10-05')->where('filters.sampai', '2026-10-06'));

    $this->actingAs($this->viewer)->get('/laporan?sampai=2026-10-05')->assertOk()->assertSessionHasNoErrors();
    $this->actingAs($this->viewer)->get('/laporan?dari=2026-10-05')->assertOk()->assertSessionHasNoErrors();

    $this->actingAs($this->viewer)->from('/laporan')->get('/laporan?dari=2026-10-06&sampai=2026-10-05')->assertSessionHasErrors('sampai');
    $this->actingAs($this->viewer)->from('/laporan')->get('/laporan?dari=bukan-tanggal')->assertSessionHasErrors('dari');
});

test('ekspor berisi data yang sama dengan halaman', function () {
    Excel::fake();
    makeEntry(S::MenungguVerifikasi, $this->school);

    $this->actingAs($this->viewer)->get('/laporan/ekspor');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', function (RecapExport $export) {
        $rows = $export->array();

        return $export->headings() === ['Sekolah', 'Menunggu verifikasi', 'Perlu perbaikan', 'Terdaftar', 'Sudah rekaman', 'Tidak hadir', 'Belum rekaman', 'Ditolak', 'Dibatalkan', 'Kedaluwarsa', 'Total']
            && $rows[0] === ['SMPN A', 1, 0, 0, 0, 0, 0, 0, 0, 0, 1]
            && $rows[1] === ['Total', 1, 0, 0, 0, 0, 0, 0, 0, 0, 1];
    });
});

test('ekspor mengikuti filter tanggal', function () {
    Excel::fake();
    makeEntry(S::MenungguVerifikasi, $this->school);

    $this->actingAs($this->viewer)->get('/laporan/ekspor?dari=2099-01-01');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', fn (RecapExport $e) => $e->array()[0] === ['SMPN A', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
});

test('ekspor menghasilkan berkas xlsx sungguhan dan tercatat di log', function () {
    $this->actingAs($this->viewer)->get('/laporan/ekspor?dari=2026-10-01')
        ->assertOk()->assertDownload('rekap-pendaftaran.xlsx');

    $log = Activity::where('description', 'laporan.diekspor')->sole();
    expect($log->causer_id)->toBe($this->viewer->id)->and($log->properties['dari'])->toBe('2026-10-01');
});

test('nama sekolah berawalan simbol formula dinetralkan di berkas ekspor', function () {
    Excel::fake();
    School::factory()->create(['name' => '=HYPERLINK("http://x")']);
    School::factory()->create(['name' => '@cmd']);

    $this->actingAs($this->viewer)->get('/laporan/ekspor');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', function (RecapExport $e) {
        $names = array_column($e->array(), 0);

        return in_array("'=HYPERLINK(\"http://x\")", $names, true) && in_array("'@cmd", $names, true) && in_array('SMPN A', $names, true);
    });
});

test('halaman laporan memuat rekap kehadiran per sesi', function () {
    $sesi = makeSchedule($this->school, '2026-10-20');
    makeEntry(S::SudahRekaman, $this->school, schedule: $sesi);
    makeEntry(S::TidakHadir, $this->school, schedule: $sesi);

    $this->actingAs($this->viewer)->get('/laporan?dari=2026-10-20&sampai=2026-10-20')->assertInertia(fn (Assert $p) => $p
        ->where('session_recap.rows.0.school', 'SMPN A')
        ->where('session_recap.rows.0.present', 1)->where('session_recap.rows.0.absent', 1)->where('session_recap.rows.0.attendance', 50)
        ->where('session_recap.totals.attendance', 50));
});

test('ekspor kehadiran sesi mengikuti filter, berkas xlsx, dan tercatat di audit', function () {
    makeEntry(S::SudahRekaman, $this->school, schedule: makeSchedule($this->school, '2026-10-20'));
    makeEntry(S::SudahRekaman, $this->school, schedule: makeSchedule($this->school, '2026-11-20'));

    $this->actingAs($this->viewer)->get('/laporan/ekspor-sesi?dari=2026-10-01&sampai=2026-10-31')
        ->assertOk()->assertDownload('rekap-kehadiran-sesi.xlsx');

    $log = Activity::where('description', 'laporan.diekspor')->sole();
    expect($log->properties['jenis'])->toBe('kehadiran-sesi')->and($log->properties['dari'])->toBe('2026-10-01');
});

test('ekspor kehadiran sesi: judul kolom, baris total, dan hanya sesi dalam rentang', function () {
    Excel::fake();
    makeEntry(S::SudahRekaman, $this->school, schedule: makeSchedule($this->school, '2026-10-20'));
    makeSchedule($this->school, '2026-11-20');

    $this->actingAs($this->viewer)->get('/laporan/ekspor-sesi?dari=2026-10-01&sampai=2026-10-31');

    Excel::assertDownloaded('rekap-kehadiran-sesi.xlsx', function (SessionRecapExport $e) {
        $rows = $e->array();

        return $e->headings() === ['Tanggal', 'Jam', 'Sekolah', 'Lokasi', 'Kuota', 'Terisi', 'Sudah rekaman', 'Belum rekaman', 'Tidak hadir', 'Kehadiran (%)']
            && count($rows) === 2
            && $rows[0][0] === '2026-10-20' && $rows[0][1] === '08.00–12.00' && $rows[0][2] === 'SMPN A' && array_slice($rows[0], 4) === [30, 1, 1, 0, 0, 100]
            && $rows[1] === ['Total', '', '', '', 30, 1, 1, 0, 0, 100];
    });
});

test('ekspor kehadiran sesi menetralkan sel berawalan simbol formula', function () {
    Excel::fake();
    $sekolah = School::factory()->create(['name' => '=HYPERLINK("http://x")']);
    $sesi = makeSchedule($sekolah, '2026-10-20');
    $sesi->update(['location' => '@lokasi']);

    $this->actingAs($this->viewer)->get('/laporan/ekspor-sesi');

    Excel::assertDownloaded('rekap-kehadiran-sesi.xlsx', fn (SessionRecapExport $e) => $e->array()[0][2] === "'=HYPERLINK(\"http://x\")" && $e->array()[0][3] === "'@lokasi");
});

test('ekspor kehadiran sesi ditolak tanpa laporan.lihat dan dibatasi lajunya', function () {
    $this->actingAs(makeUserWith([], roleName: 'tanpa-laporan'))->get('/laporan/ekspor-sesi')->assertForbidden();

    expect(app('router')->getRoutes()->getByName('laporan.ekspor-sesi')->gatherMiddleware())->toContain('throttle:20,1');
});
