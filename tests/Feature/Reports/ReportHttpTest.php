<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Exports\RecapExport;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'SMPN A']);
    $this->viewer = makeUserWith(['laporan.lihat'], roleName: 'pelapor');
});

test('halaman laporan memuat rekap dan filter kosong', function () {
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->viewer)->get('/laporan')->assertInertia(fn (Assert $p) => $p
        ->component('Laporan/Index')
        ->where('recap.rows.0.school', 'SMPN A')
        ->where('recap.rows.0.counts.diajukan', 1)
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
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->viewer)->get('/laporan/ekspor');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', function (RecapExport $export) {
        $rows = $export->array();

        return $export->headings() === ['Sekolah', 'Diajukan', 'Perlu perbaikan', 'Terverifikasi', 'Terjadwal', 'Sudah rekaman', 'Tidak hadir', 'Belum rekaman', 'Ditolak', 'Total']
            && $rows[0] === ['SMPN A', 1, 0, 0, 0, 0, 0, 0, 0, 1]
            && $rows[1] === ['Total', 1, 0, 0, 0, 0, 0, 0, 0, 1];
    });
});

test('ekspor mengikuti filter tanggal', function () {
    Excel::fake();
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->viewer)->get('/laporan/ekspor?dari=2099-01-01');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', fn (RecapExport $e) => $e->array()[0] === ['SMPN A', 0, 0, 0, 0, 0, 0, 0, 0, 0]);
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
