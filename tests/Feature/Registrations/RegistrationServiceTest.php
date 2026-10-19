<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationSubmittedNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Services\EligibilityService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.buat', 'pendaftaran.ajukan'], $this->school, true, 'op-reg');
    $this->petugas = makeUserWith(['verifikasi.putuskan'], roleName: 'petugas');
    $this->service = app(RegistrationService::class);
    $this->draf = function (array $siswa, ?string $judul = 'Gelombang 1') {
        return $this->service->createDraft(['title' => $judul, 'student_ids' => collect($siswa)->pluck('id')->all()], $this->op);
    };
});

test('draf dibuat di sekolah operator dengan entri berstatus draf', function () {
    $a = eligibleStudent($this->school);
    $b = eligibleStudent($this->school);

    $r = ($this->draf)([$a, $b]);

    expect($r->school_id)->toBe($this->school->id)
        ->and($r->created_by)->toBe($this->op->id)
        ->and($r->submitted_at)->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Draf]);
});

test('staf kecamatan memilih sekolah lewat data', function () {
    $staf = makeUserWith(['pendaftaran.buat'], roleName: 'staf-reg');
    $a = eligibleStudent($this->school);

    $r = $this->service->createDraft(['title' => 'X', 'school_id' => $this->school->id, 'student_ids' => [$a->id]], $staf);

    expect($r->school_id)->toBe($this->school->id);
});

test('ubah draf menyinkronkan daftar siswa', function () {
    [$a, $b, $c] = [eligibleStudent($this->school), eligibleStudent($this->school), eligibleStudent($this->school)];
    $r = ($this->draf)([$a, $b]);

    $this->service->updateDraft($r, ['title' => 'Baru', 'expected_count' => 30, 'student_ids' => [$b->id, $c->id]]);

    expect($r->fresh()->title)->toBe('Baru')->and($r->fresh()->expected_count)->toBe(30)
        ->and($r->entries()->pluck('student_id')->sort()->values()->all())->toBe(collect([$b->id, $c->id])->sort()->values()->all());
});

test('pengajuan berhasil: semua entri diajukan, riwayat, log, dan petugas diberi tahu', function () {
    $a = eligibleStudent($this->school);
    $b = eligibleStudent($this->school);
    $r = ($this->draf)([$a, $b]);

    $this->service->submit($r, $this->op);

    expect($r->fresh()->submitted_at)->not->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Diajukan])
        ->and($r->entries->first()->histories()->count())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diajukan')->count())->toBe(1);
    Notification::assertSentTo($this->petugas, RegistrationSubmittedNotification::class);
    Notification::assertNotSentTo($this->op, RegistrationSubmittedNotification::class);
});

test('satu siswa tidak eligible menggagalkan semua dengan alasan per siswa', function () {
    $ok = eligibleStudent($this->school);
    $buruk = eligibleStudent($this->school);
    $buruk->documents()->where('type', 'kk')->delete();
    $r = ($this->draf)([$ok, $buruk]);

    try {
        $this->service->submit($r, $this->op);
        $this->fail('Seharusnya gagal.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['student_ids', "student_ids.{$buruk->id}"])
            ->and($e->errors()["student_ids.{$buruk->id}"][0])->toContain('Kartu Keluarga belum diunggah.')
            ->and($e->errors())->not->toHaveKey("student_ids.{$ok->id}");
    }

    expect($r->fresh()->submitted_at)->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Draf]);
    Notification::assertNothingSent();
});

test('pengajuan tanpa siswa ditolak', function () {
    $r = ($this->draf)([]);

    expect(fn () => $this->service->submit($r, $this->op))->toThrow(ValidationException::class);
});

test('siswa yang sudah aktif di pendaftaran lain ditolak dan pengajuan kedua gagal', function () {
    $a = eligibleStudent($this->school);
    $pertama = ($this->draf)([$a], 'Pertama');
    $kedua = ($this->draf)([$a], 'Kedua');

    $this->service->submit($pertama, $this->op);

    try {
        $this->service->submit($kedua, $this->op);
        $this->fail('Seharusnya gagal.');
    } catch (ValidationException $e) {
        expect($e->errors()["student_ids.{$a->id}"][0])->toContain('Sudah terdaftar di pendaftaran lain.');
    }
});

test('balapan yang lolos pemeriksaan tetap dihentikan indeks unik dan dibatalkan utuh', function () {
    $a = eligibleStudent($this->school);
    $b = eligibleStudent($this->school);
    makeEntry(S::Diajukan, student: $a); // entri aktif milik pendaftaran lain
    // Eligibility dibuat buta untuk mensimulasikan pengajuan lain yang baru saja commit.
    $this->mock(EligibilityService::class, fn ($m) => $m->shouldReceive('check')->andReturn(['eligible' => true, 'reasons' => []]));
    $r = app(RegistrationService::class)->createDraft(['title' => 'Balapan', 'student_ids' => [$b->id, $a->id]], $this->op);

    expect(fn () => app(RegistrationService::class)->submit($r, $this->op))
        ->toThrow(AccessRuleException::class, 'Sebagian siswa sudah terdaftar di pendaftaran lain.');
    expect($r->fresh()->submitted_at)->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Draf]);
});

test('pendaftaran yang sudah diajukan tidak dapat diubah atau dihapus', function () {
    $r = ($this->draf)([eligibleStudent($this->school)]);
    $this->service->submit($r, $this->op);

    expect(fn () => $this->service->updateDraft($r->fresh(), ['title' => 'x', 'student_ids' => []]))->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->deleteDraft($r->fresh()))->toThrow(AccessRuleException::class);
});

test('draf dapat dihapus beserta entrinya', function () {
    $r = ($this->draf)([eligibleStudent($this->school)]);

    $this->service->deleteDraft($r);

    expect(Registration::find($r->id))->toBeNull();
});

test('ajukan ulang hanya untuk perlu perbaikan dan memeriksa eligibility lagi', function () {
    $siswa = eligibleStudent($this->school);
    $entry = makeEntry(S::PerluPerbaikan, student: $siswa);

    $this->service->resubmit($entry, $this->op);

    expect($entry->fresh()->status)->toBe(S::Diajukan)
        ->and(Activity::where('description', 'pendaftaran.diajukan-ulang')->count())->toBe(1);
    Notification::assertSentTo($this->petugas, RegistrationSubmittedNotification::class);

    expect(fn () => $this->service->resubmit($entry->fresh(), $this->op))->toThrow(AccessRuleException::class);
});

test('ajukan ulang gagal bila dokumen masih kurang', function () {
    $siswa = eligibleStudent($this->school);
    $siswa->documents()->where('type', 'akta_lahir')->delete();
    $entry = makeEntry(S::PerluPerbaikan, student: $siswa);

    expect(fn () => $this->service->resubmit($entry, $this->op))->toThrow(ValidationException::class);
    expect($entry->fresh()->status)->toBe(S::PerluPerbaikan);
});
