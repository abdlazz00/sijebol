<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.lihat'], $this->school, true, 'op-ver');
    $this->petugas = makeUserWith(['verifikasi.putuskan'], roleName: 'petugas-ver');
    $this->service = app(VerificationService::class);
});

test('keputusan dipetakan ke status, mencatat riwayat, log, dan memberi tahu operator sekolah', function (string $keputusan, S $status) {
    $entry = makeEntry(S::Diajukan, $this->school);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    expect($entry->fresh()->status)->toBe($status)
        ->and($entry->fresh()->decided_by)->toBe($this->petugas->id)
        ->and($entry->histories()->count())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(1);
    Notification::assertSentTo($this->op, EntryDecidedNotification::class);
    Notification::assertNotSentTo($this->petugas, EntryDecidedNotification::class);
})->with([['approve', S::Terverifikasi], ['fix', S::PerluPerbaikan], ['reject', S::Ditolak]]);

test('operator sekolah lain tidak diberi tahu', function () {
    $lain = makeUserWith(['pendaftaran.lihat'], School::factory()->create(), true, 'op-ver');
    $this->service->decide(makeEntry(S::Diajukan, $this->school), 'approve', null, $this->petugas);

    Notification::assertNotSentTo($lain, EntryDecidedNotification::class);
});

test('entri yang bukan berstatus diajukan tidak dapat diputuskan, dan keputusan kedua ditolak', function () {
    $draf = makeEntry(S::Draf, $this->school);
    expect(fn () => $this->service->decide($draf, 'approve', null, $this->petugas))->toThrow(AccessRuleException::class);

    $entry = makeEntry(S::Diajukan, $this->school);
    $this->service->decide($entry, 'approve', null, $this->petugas);
    expect(fn () => $this->service->decide($entry, 'reject', 'x', $this->petugas))->toThrow(AccessRuleException::class);
    expect($entry->histories()->count())->toBe(1);
});

test('antrean hanya memuat entri diajukan', function () {
    makeEntry(S::Diajukan, $this->school);
    makeEntry(S::Draf, $this->school);
    makeEntry(S::Terverifikasi, $this->school);

    expect($this->service->queue()->total())->toBe(1);
});

/** Entri Diajukan baru di pendaftaran yang sama dengan `$entry`, untuk siswa yang layak. */
function makeSiblingEntryDiajukan(RegistrationStudent $entry, string $name): RegistrationStudent
{
    $student = eligibleStudent($entry->registration->school);
    $student->update(['name' => $name]);

    return RegistrationStudent::factory()->create([
        'registration_id' => $entry->registration_id, 'school_id' => $entry->school_id, 'student_id' => $student->id, 'status' => S::Diajukan,
    ]);
}

test('setujui massal: semua Terverifikasi, riwayat per entri, satu notifikasi per pendaftaran dengan ringkasan', function () {
    $a1 = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $a1->student->update(['name' => 'Ani']);
    $a2 = makeSiblingEntryDiajukan($a1, 'Budi');
    $b1 = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $b1->student->update(['name' => 'Cici']);

    $jumlah = $this->service->approveMany([$a1->id, $a2->id, $b1->id], $this->petugas);

    expect($jumlah)->toBe(3);
    foreach ([$a1, $a2, $b1] as $e) {
        expect($e->fresh()->status)->toBe(S::Terverifikasi)->and($e->fresh()->decided_by)->toBe($this->petugas->id)->and($e->histories()->count())->toBe(1);
    }
    expect(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(3);
    Notification::assertSentToTimes($this->op, EntryDecidedNotification::class, 2);
    $bodies = Notification::sent($this->op, EntryDecidedNotification::class)->map(fn ($n) => $n->body())->all();
    expect($bodies)->toContain('2 siswa: Ani, Budi.')->and($bodies)->toContain('Cici');
});

test('satu entri tidak layak menggagalkan seluruh persetujuan massal tanpa notifikasi', function (string $kasus) {
    $baik = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $siswa = match ($kasus) {
        'tanpa dokumen' => Student::factory()->for($this->school)->create(['name' => 'Tanpa Dokumen']),
        'umur di bawah 16' => tap(eligibleStudent($this->school), fn (Student $s) => $s->update(['name' => 'Terlalu Muda', 'birth_date' => now()->subYears(15)->toDateString()])),
    };
    $buruk = makeEntry(S::Diajukan, $this->school, $siswa);

    try {
        $this->service->approveMany([$baik->id, $buruk->id], $this->petugas);
        $this->fail('Seharusnya ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['entry_ids'][0])->toContain($siswa->name);
    }

    expect($baik->fresh()->status)->toBe(S::Diajukan)->and($buruk->fresh()->status)->toBe(S::Diajukan)->and($baik->histories()->count())->toBe(0);
    Notification::assertNothingSent();
})->with(['tanpa dokumen', 'umur di bawah 16']);

test('persetujuan massal ditolak seluruhnya untuk id tidak ada, entri yang sudah diputuskan, dan bukan Diajukan', function (string $kasus) {
    $baik = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $buruk = match ($kasus) {
        'id tidak ada' => 999999,
        'sudah diputuskan' => makeEntry(S::Terverifikasi, $this->school)->id,
        'bukan Diajukan' => makeEntry(S::Draf, $this->school)->id,
    };

    expect(fn () => $this->service->approveMany([$baik->id, $buruk], $this->petugas))->toThrow(ValidationException::class);

    expect($baik->fresh()->status)->toBe(S::Diajukan);
    Notification::assertNothingSent();
})->with(['id tidak ada', 'sudah diputuskan', 'bukan Diajukan']);

test('operator terikat sekolah lain tidak dapat menyetujui entri sekolah ini (entri tak terlihat)', function () {
    $entri = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $terikat = makeUserWith(['verifikasi.putuskan'], School::factory()->create(), true, 'terikat-lain');
    $this->actingAs($terikat);

    expect(fn () => $this->service->approveMany([$entri->id], $terikat))->toThrow(ValidationException::class);
    expect($entri->fresh()->status)->toBe(S::Diajukan);
});
