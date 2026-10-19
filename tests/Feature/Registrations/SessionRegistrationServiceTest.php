<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Services\EligibilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create();
    $this->sesi = makeSchedule($this->school, '2026-10-20');
    [$this->akun, $this->siswa] = siswaLengkap($this->school);
    $this->service = app(RegistrationService::class);
});

afterEach(fn () => Carbon::setTestNow());

test('siswa mendaftar: entri menunggu verifikasi, riwayat awal, log, dan data terkunci', function () {
    $this->actingAs($this->akun);

    $entry = $this->service->register($this->akun, $this->sesi);

    expect($entry->status)->toBe(S::MenungguVerifikasi)
        ->and($entry->schedule_id)->toBe($this->sesi->id)
        ->and($entry->student_id)->toBe($this->siswa->id)
        ->and($entry->school_id)->toBe($this->school->id)
        ->and($entry->histories()->count())->toBe(1)
        ->and($entry->histories()->first()->from_status)->toBeNull()
        ->and($entry->histories()->first()->to_status)->toBe(S::MenungguVerifikasi)
        ->and($entry->histories()->first()->by_user_id)->toBe($this->akun->id)
        ->and(Activity::where('description', 'pendaftaran.dibuat')->count())->toBe(1)
        ->and($this->siswa->fresh()->isLocked())->toBeTrue();
});

test('pendaftaran mengambil kunci baris sesi (for update)', function () {
    $this->actingAs($this->akun);
    DB::enableQueryLog();

    $this->service->register($this->akun, $this->sesi);

    $kunci = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q) => str_contains($q, 'from `schedules`') && str_contains($q, 'for update'));
    expect($kunci)->not->toBeEmpty();
});

test('kuota 1: siswa kedua ditolak, kursi yang dilepas dapat diisi siswa lain', function () {
    $this->sesi->update(['quota' => 1]);
    [$akunB, $siswaB] = siswaLengkap($this->school);

    $pertama = $this->actingAs($this->akun)->service->register($this->akun, $this->sesi);

    $this->actingAs($akunB);
    expect(fn () => $this->service->register($akunB, $this->sesi))->toThrow(AccessRuleException::class, 'Kuota sesi ini sudah penuh.');
    expect(RegistrationStudent::withoutGlobalScopes()->where('student_id', $siswaB->id)->exists())->toBeFalse();

    $pertama->update(['status' => S::Ditolak]);
    expect($this->service->register($akunB, $this->sesi)->student_id)->toBe($siswaB->id);
});

test('status penahan kursi menghitung, status lain tidak', function (S $status, bool $menahan) {
    $this->sesi->update(['quota' => 1]);
    makeEntry($status, $this->school, schedule: $this->sesi);
    $this->actingAs($this->akun);

    if ($menahan) {
        expect(fn () => $this->service->register($this->akun, $this->sesi))->toThrow(AccessRuleException::class, 'Kuota sesi ini sudah penuh.');
    } else {
        expect($this->service->register($this->akun, $this->sesi)->exists)->toBeTrue();
    }
})->with([
    [S::MenungguVerifikasi, true], [S::PerluPerbaikan, true], [S::Terdaftar, true], [S::SudahRekaman, true], [S::BelumRekaman, true],
    [S::TidakHadir, false], [S::Ditolak, false], [S::Dibatalkan, false], [S::Kedaluwarsa, false],
]);

test('kuota 2 menerima dua siswa lalu menolak yang ketiga', function () {
    $this->sesi->update(['quota' => 2]);
    [$b] = siswaLengkap($this->school);
    [$c] = siswaLengkap($this->school);

    foreach ([$this->akun, $b] as $akun) {
        $this->actingAs($akun);
        $this->service->register($akun, $this->sesi);
    }
    $this->actingAs($c);

    expect(fn () => $this->service->register($c, $this->sesi))->toThrow(AccessRuleException::class);
    expect($this->sesi->seatsTaken())->toBe(2);
});

test('data diri belum disimpan ditolak dengan petunjuk', function () {
    $akun = makeUserWith(['pendaftaran.buat'], $this->school, true, 'siswa');
    $this->actingAs($akun);

    expect(fn () => $this->service->register($akun, $this->sesi))->toThrow(AccessRuleException::class, 'Lengkapi data diri dan dokumen terlebih dahulu.');
});

test('dokumen kurang ditolak dengan alasannya dan tidak ada entri dibuat', function () {
    $this->siswa->documents()->where('type', 'akta_lahir')->delete();
    $this->actingAs($this->akun);

    expect(fn () => $this->service->register($this->akun, $this->sesi))->toThrow(AccessRuleException::class, 'Akta kelahiran belum diunggah.');
    expect(RegistrationStudent::count())->toBe(0);
});

test('siswa yang sudah punya pendaftaran aktif ditolak, setelah ditolak atau dibatalkan boleh mendaftar lagi', function () {
    $lain = makeSchedule($this->school, '2026-10-25');
    $lama = makeEntry(S::Terdaftar, $this->school, $this->siswa, $lain);
    $this->actingAs($this->akun);

    expect(fn () => $this->service->register($this->akun, $this->sesi))->toThrow(AccessRuleException::class);

    foreach ([S::Ditolak, S::Dibatalkan] as $status) {
        $lama->update(['status' => $status]);
        $baru = $this->service->register($this->akun, $this->sesi);
        expect($baru->status)->toBe(S::MenungguVerifikasi);
        $baru->update(['status' => S::Dibatalkan]);
    }
});

test('balapan pada indeks unik dipetakan menjadi galat aturan', function () {
    makeEntry(S::Terdaftar, $this->school, $this->siswa, makeSchedule($this->school, '2026-10-25'));
    $this->mock(EligibilityService::class, fn ($m) => $m->shouldReceive('check')->andReturn(['eligible' => true, 'reasons' => []]));
    $this->actingAs($this->akun);

    expect(fn () => app(RegistrationService::class)->register($this->akun, $this->sesi))
        ->toThrow(AccessRuleException::class, 'Anda sudah memiliki pendaftaran aktif.');
});

test('sesi sekolah lain tidak ditemukan', function () {
    $asing = makeSchedule(School::factory()->create(), '2026-10-20');
    $this->actingAs($this->akun);

    expect(fn () => $this->service->register($this->akun, $asing))->toThrow(ModelNotFoundException::class);
});

test('sesi dibatalkan tidak menerima pendaftaran', function () {
    $this->sesi->update(['cancelled_at' => now()]);
    $this->actingAs($this->akun);

    expect(fn () => $this->service->register($this->akun, $this->sesi))->toThrow(AccessRuleException::class, 'Pendaftaran untuk sesi ini sudah ditutup.');
});

test('batas daftar: H-2 diterima, H-1 ditutup', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-18 23:30:00', 'Asia/Jakarta'));
    $this->actingAs($this->akun);

    expect($this->service->register($this->akun, $this->sesi)->exists)->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-10-19 00:05:00', 'Asia/Jakarta'));
    [$b] = siswaLengkap($this->school);
    $this->actingAs($b);
    expect(fn () => $this->service->register($b, $this->sesi))->toThrow(AccessRuleException::class, 'sudah ditutup');
});

test('umur 16 dihitung pada tanggal sesi: tepat 16 diterima, sehari kurang ditolak', function () {
    $this->actingAs($this->akun);

    $this->siswa->update(['birth_date' => '2010-10-21']);
    expect(fn () => $this->service->register($this->akun, $this->sesi))->toThrow(AccessRuleException::class, 'Umur minimal 16 tahun');

    $this->siswa->update(['birth_date' => '2010-10-20']);
    expect($this->service->register($this->akun, $this->sesi)->exists)->toBeTrue();
});

test('siswa membatalkan pendaftarannya sampai sehari sebelum sesi, kursi dilepas', function (S $status) {
    $entry = makeEntry($status, $this->school, $this->siswa, $this->sesi);
    $this->actingAs($this->akun);

    $this->service->cancel($entry, $this->akun);

    $fresh = $entry->fresh();
    expect($fresh->status)->toBe(S::Dibatalkan)->and($fresh->note)->toBe('Dibatalkan oleh siswa')
        ->and($fresh->decided_by)->toBeNull()
        ->and($this->sesi->seatsTaken())->toBe(0)
        ->and(Activity::where('description', 'pendaftaran.dibatalkan')->count())->toBe(1);
})->with([[S::MenungguVerifikasi], [S::PerluPerbaikan], [S::Terdaftar]]);

test('pembatalan oleh siswa ditolak pada hari sesi atau untuk status lain', function () {
    $hariH = makeSchedule($this->school, '2026-10-01');
    $entry = makeEntry(S::Terdaftar, $this->school, $this->siswa, $hariH);
    $this->actingAs($this->akun);

    expect(fn () => $this->service->cancel($entry, $this->akun))
        ->toThrow(AccessRuleException::class, 'Pembatalan hanya dapat dilakukan sampai sehari sebelum sesi.');

    $selesai = makeEntry(S::SudahRekaman, $this->school, siswaLengkap($this->school)[1], $this->sesi);
    expect(fn () => $this->service->cancel($selesai, $this->akun))->toThrow(AccessRuleException::class);
    expect($entry->fresh()->status)->toBe(S::Terdaftar);
});

test('ajukan ulang dari perlu perbaikan: kembali menunggu verifikasi dan data terkunci lagi', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, $this->siswa, $this->sesi);
    $this->actingAs($this->akun);
    expect($this->siswa->fresh()->isLocked())->toBeFalse();

    $this->service->resubmit($entry, $this->akun);

    expect($entry->fresh()->status)->toBe(S::MenungguVerifikasi)
        ->and($this->siswa->fresh()->isLocked())->toBeTrue()
        ->and($this->sesi->seatsTaken())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diajukan-ulang')->count())->toBe(1);
});

test('ajukan ulang ditolak bila status bukan perlu perbaikan, hari sesi tiba, atau syarat belum terpenuhi', function () {
    $this->actingAs($this->akun);

    $menunggu = makeEntry(S::MenungguVerifikasi, $this->school, $this->siswa, $this->sesi);
    expect(fn () => $this->service->resubmit($menunggu, $this->akun))->toThrow(AccessRuleException::class);
    $menunggu->update(['status' => S::PerluPerbaikan]);

    $this->siswa->documents()->where('type', 'kk')->delete();
    expect(fn () => $this->service->resubmit($menunggu, $this->akun))->toThrow(AccessRuleException::class, 'Kartu Keluarga belum diunggah.');

    Carbon::setTestNow(Carbon::parse('2026-10-20 07:00:00', 'Asia/Jakarta'));
    expect(fn () => $this->service->resubmit($menunggu, $this->akun))->toThrow(AccessRuleException::class);
    expect($menunggu->fresh()->status)->toBe(S::PerluPerbaikan);
});

test('siswa lain tidak ditemukan lewat scope pemilik', function () {
    [$akunB, $siswaB] = siswaLengkap($this->school);
    $entryB = makeEntry(S::Terdaftar, $this->school, $siswaB, $this->sesi);
    $this->actingAs($this->akun);

    expect(RegistrationStudent::find($entryB->id))->toBeNull();
});

test('ajukan ulang ditolak setelah tenggat perbaikan walau perintah kedaluwarsa belum jalan', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, $this->siswa, $this->sesi);
    $entry->update(['fix_deadline_at' => now()->subMinute()]);
    $this->actingAs($this->akun);

    expect(fn () => $this->service->resubmit($entry, $this->akun))->toThrow(AccessRuleException::class, 'Batas waktu perbaikan sudah lewat.');
    expect($entry->fresh()->status)->toBe(S::PerluPerbaikan);
});

test('ajukan ulang sebelum tenggat mengosongkan tenggat dan penanda pengingat', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, $this->siswa, $this->sesi);
    $entry->update(['fix_deadline_at' => now()->addHours(5), 'fix_reminded_at' => now()->subHour()]);
    $this->actingAs($this->akun);

    $this->service->resubmit($entry, $this->akun);

    $fresh = $entry->fresh();
    expect($fresh->status)->toBe(S::MenungguVerifikasi)->and($fresh->fix_deadline_at)->toBeNull()->and($fresh->fix_reminded_at)->toBeNull();
});
