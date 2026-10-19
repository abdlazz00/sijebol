<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStatusHistory;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScanService;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 08:30:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create(['name' => 'SMPN 1 Sungai Beduk']);
    $this->sesi = makeSchedule($this->school, '2026-10-20');
    $this->petugas = makeUserWith(['rekaman.pindai'], roleName: 'petugas-pindai');
    $this->service = app(ScanService::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Pendaftaran di sesi tertentu dengan kode tiket tetap. */
function tiketDi(Schedule $sesi, S $status = S::Terdaftar, string $kode = 'KODE-UJI'): RegistrationStudent
{
    $entry = makeEntry($status, $sesi->school, schedule: $sesi);
    $entry->update(['ticket_code' => $kode]);

    return $entry;
}

function ditolak(object $t, string $kode, ?Schedule $sesi = null): string
{
    try {
        $t->service->scan($sesi ?? $t->sesi, $kode, $t->petugas);
    } catch (AccessRuleException $e) {
        return $e->getMessage();
    }

    return 'DITERIMA';
}

test('pindai berhasil: Sudah rekaman, scanned_at terisi, satu riwayat oleh petugas, satu log', function () {
    $entry = tiketDi($this->sesi);

    $hasil = $this->service->scan($this->sesi, 'KODE-UJI', $this->petugas);

    $fresh = $entry->fresh();
    expect($hasil->student->id)->toBe($entry->student_id)
        ->and($fresh->status)->toBe(S::SudahRekaman)
        ->and($fresh->scanned_at->equalTo(now()))->toBeTrue()
        ->and($fresh->histories()->count())->toBe(1)
        ->and($fresh->histories()->first()->by_user_id)->toBe($this->petugas->id)
        ->and($fresh->histories()->first()->note)->toBe('Dipindai petugas.')
        ->and(Activity::where('description', 'rekaman.dipindai')->count())->toBe(1);
});

test('pindai kedua kode yang sama ditolak dengan jam pindai pertama dan tidak menambah riwayat', function () {
    $entry = tiketDi($this->sesi);
    $this->service->scan($this->sesi, 'KODE-UJI', $this->petugas);
    Carbon::setTestNow(Carbon::parse('2026-10-20 08:45:00', 'Asia/Jakarta'));

    expect(ditolak($this, 'KODE-UJI'))->toBe('Tiket sudah dipindai pukul 08.30.')
        ->and($entry->histories()->count())->toBe(1)
        ->and($entry->fresh()->scanned_at->equalTo(Carbon::parse('2026-10-20 08:30:00', 'Asia/Jakarta')))->toBeTrue();
});

test('setiap jenis penolakan punya pesan sendiri dan tidak mengubah apa pun', function () {
    $lain = makeSchedule($this->school, '2026-10-21');
    tiketDi($lain, kode: 'SESI-LAIN');
    $dibatalkan = makeSchedule($this->school, '2026-10-20');
    tiketDi($dibatalkan, kode: 'SESI-BATAL');
    $dibatalkan->update(['cancelled_at' => now()]);
    $manual = tiketDi($this->sesi, S::SudahRekaman, 'MANUAL');
    $menunggu = tiketDi($this->sesi, S::MenungguVerifikasi, 'MENUNGGU');
    $tidakHadir = tiketDi($this->sesi, S::TidakHadir, 'ABSEN');

    expect(ditolak($this, 'ACAK'))->toBe('Kode tiket tidak dikenal.')
        ->and(ditolak($this, 'SESI-LAIN'))->toBe('Tiket ini untuk sesi lain (SMPN 1 Sungai Beduk, Rabu, 21 Oktober 2026).')
        ->and(ditolak($this, 'SESI-BATAL', $dibatalkan))->toBe('Sesi ini dibatalkan.')
        ->and(ditolak($this, 'MANUAL'))->toBe('Siswa sudah ditandai sudah rekaman.')
        ->and(ditolak($this, 'MENUNGGU'))->toBe('Tiket tidak berlaku (status: Menunggu verifikasi).')
        ->and(ditolak($this, 'ABSEN'))->toBe('Tiket tidak berlaku (status: Tidak hadir).');

    expect($manual->fresh()->scanned_at)->toBeNull()
        ->and($menunggu->fresh()->status)->toBe(S::MenungguVerifikasi)
        ->and($tidakHadir->fresh()->status)->toBe(S::TidakHadir)
        ->and(RegistrationStatusHistory::count())->toBe(0);
});

test('batas jendela: tepat di batas diterima, semenit di luar ditolak, hari lain ditolak', function (string $waktu, bool $diterima) {
    tiketDi($this->sesi);
    Carbon::setTestNow(Carbon::parse($waktu, 'Asia/Jakarta'));

    $pesan = ditolak($this, 'KODE-UJI');

    expect($pesan === 'DITERIMA')->toBe($diterima);
    if (! $diterima) {
        expect($pesan)->toBe('Di luar jam sesi. Pemindaian dibuka 07.00–13.00.');
    }
})->with([
    'tepat 60 menit sebelum mulai' => ['2026-10-20 07:00:00', true],
    'semenit sebelum jendela' => ['2026-10-20 06:59:00', false],
    'tepat 60 menit setelah selesai' => ['2026-10-20 13:00:00', true],
    'semenit setelah jendela' => ['2026-10-20 13:01:00', false],
    'hari berikutnya' => ['2026-10-21 08:30:00', false],
    'hari sebelumnya' => ['2026-10-19 08:30:00', false],
]);

test('penolakan atas tiket yang dikenal dicatat, kode acak tidak', function () {
    $entry = tiketDi($this->sesi, S::MenungguVerifikasi);

    ditolak($this, 'ACAK');
    expect(Activity::where('description', 'rekaman.pindai-ditolak')->count())->toBe(0);

    ditolak($this, 'KODE-UJI');
    $log = Activity::where('description', 'rekaman.pindai-ditolak')->sole();
    expect($log->properties['alasan'])->toBe('Tiket tidak berlaku (status: Menunggu verifikasi).')
        ->and($log->properties['siswa_id'])->toBe($entry->student_id)
        ->and($log->causer_id)->toBe($this->petugas->id);
});
