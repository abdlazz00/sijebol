<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));
    $this->school = School::factory()->create();
    $this->sesi = makeSchedule($this->school, '2026-10-20');
    [$this->akun, $this->siswa] = siswaLengkap($this->school);
    $this->admin = makeUserWith(['jadwal.lihat', 'verifikasi.putuskan'], roleName: 'admin-batal');
    $this->service = app(RegistrationService::class);
});

afterEach(fn () => Carbon::setTestNow());

test('admin membatalkan pendaftaran pra-kehadiran dengan catatan; kursi lepas dan hanya siswa itu diberi tahu', function (S $status) {
    [$lain] = siswaLengkap($this->school);
    $entry = makeEntry($status, $this->school, $this->siswa, $this->sesi);

    $this->service->cancelByStaff($entry, 'Data ganda dengan pendaftaran lain.', $this->admin);

    $fresh = $entry->fresh();
    expect($fresh->status)->toBe(S::Dibatalkan)->and($fresh->note)->toBe('Data ganda dengan pendaftaran lain.')
        ->and($this->sesi->seatsTaken())->toBe(0)
        ->and($fresh->histories()->latest('id')->first()->by_user_id)->toBe($this->admin->id)
        ->and(Activity::where('description', 'pendaftaran.dibatalkan')->latest('id')->first()->properties['oleh_staf'])->toBeTrue();
    Notification::assertSentToTimes($this->akun, RegistrationNotification::class, 1);
    Notification::assertSentTo($this->akun, RegistrationNotification::class, fn (RegistrationNotification $n) => $n->title() === 'Pendaftaran dibatalkan petugas');
    Notification::assertNotSentTo([$lain, $this->admin], RegistrationNotification::class);
})->with([[S::MenungguVerifikasi], [S::PerluPerbaikan], [S::Terdaftar]]);

test('pembatalan oleh admin ditolak untuk status lain dan pada hari sesi', function () {
    foreach ([S::Ditolak, S::SudahRekaman, S::Dibatalkan] as $status) {
        $entry = makeEntry($status, $this->school, siswaLengkap($this->school)[1], $this->sesi);
        expect(fn () => $this->service->cancelByStaff($entry, 'Catatan lengkap.', $this->admin))->toThrow(AccessRuleException::class);
        expect($entry->fresh()->status)->toBe($status);
    }

    $hariH = makeSchedule($this->school, '2026-10-01');
    $entry = makeEntry(S::Terdaftar, $this->school, siswaLengkap($this->school)[1], $hariH);
    expect(fn () => $this->service->cancelByStaff($entry, 'Catatan lengkap.', $this->admin))
        ->toThrow(AccessRuleException::class, 'Pada hari sesi, gunakan penandaan manual.');
    expect($entry->fresh()->status)->toBe(S::Terdaftar);
    Notification::assertNothingSent();
});

test('pembatalan oleh admin untuk siswa tanpa akun tidak membuat galat', function () {
    $entry = makeEntry(S::Terdaftar, $this->school, schedule: $this->sesi);

    $this->service->cancelByStaff($entry, 'Catatan lengkap.', $this->admin);

    expect($entry->fresh()->status)->toBe(S::Dibatalkan);
    Notification::assertNothingSent();
});

test('HTTP: catatan wajib, sukses mengalihkan ke sesi, izin dan batas sekolah dijaga', function () {
    $entry = makeEntry(S::Terdaftar, $this->school, $this->siswa, $this->sesi);
    $url = "/jadwal/{$this->sesi->id}/peserta/{$entry->id}/batalkan";
    $this->actingAs($this->admin);

    $this->post($url, [])->assertSessionHasErrors('note');
    $this->post($url, ['note' => 'abc'])->assertSessionHasErrors('note');
    expect($entry->fresh()->status)->toBe(S::Terdaftar);

    $this->post($url, ['note' => 'Data ganda dengan pendaftaran lain.'])->assertRedirect("/jadwal/{$this->sesi->id}")->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::Dibatalkan);

    $this->post($url, ['note' => 'Sekali lagi lengkap.'])->assertSessionHas('error');

    $pengamat = makeUserWith(['jadwal.lihat'], roleName: 'pengamat-batal');
    $this->actingAs($pengamat)->post($url, ['note' => 'Tidak boleh.'])->assertForbidden();
});

test('HTTP: entri milik sesi lain dan sesi sekolah lain menghasilkan 404', function () {
    $lain = makeSchedule($this->school, '2026-10-22');
    $entry = makeEntry(S::Terdaftar, $this->school, $this->siswa, $lain);
    $this->actingAs($this->admin);

    $this->post("/jadwal/{$this->sesi->id}/peserta/{$entry->id}/batalkan", ['note' => 'Catatan lengkap.'])->assertNotFound();
    expect($entry->fresh()->status)->toBe(S::Terdaftar);

    $operator = makeUserWith(['jadwal.lihat', 'verifikasi.putuskan'], School::factory()->create(), true, 'op-batal-asing');
    $this->actingAs($operator)->post("/jadwal/{$lain->id}/peserta/{$entry->id}/batalkan", ['note' => 'Catatan lengkap.'])->assertNotFound();
});

test('halaman sesi menandai peserta yang dapat dibatalkan', function () {
    makeEntry(S::Terdaftar, $this->school, $this->siswa, $this->sesi);
    makeEntry(S::Ditolak, $this->school, siswaLengkap($this->school)[1], $this->sesi);
    $hariH = makeSchedule($this->school, '2026-10-01');
    makeEntry(S::Terdaftar, $this->school, siswaLengkap($this->school)[1], $hariH);

    $this->actingAs($this->admin)->get("/jadwal/{$this->sesi->id}")->assertInertia(fn (Assert $p) => $p
        ->where('participants.0.can_cancel', true)->where('participants.1.can_cancel', false));
    $this->get("/jadwal/{$hariH->id}")->assertInertia(fn (Assert $p) => $p->where('participants.0.can_cancel', false));
});
