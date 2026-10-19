<?php

use App\Domains\Notifications\Notifications\StudentCorrectedNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function koreksi(array $override = []): array
{
    return [...[
        'nik' => '3201123412349999', 'nisn' => '0012345678', 'name' => 'Nama Benar',
        'birth_date' => '2009-05-01', 'gender' => 'L', 'note' => 'NIK salah ketik saat mendaftar.',
    ], ...$override];
}

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->akun = makeUserWith(['profil.lihat'], $this->school, true, 'siswa');
    $this->siswa = Student::factory()->for($this->school)->create(['user_id' => $this->akun->id, 'nik' => '3201123412341234']);
    $this->admin = makeUserWith(['siswa.lihat', 'siswa.ubah', 'siswa.hapus'], roleName: 'admin-koreksi');
});

test('admin mengoreksi data walau terkunci, tercatat dengan catatan, dan siswa diberi tahu', function () {
    makeEntry(S::MenungguVerifikasi, student: $this->siswa);

    $this->actingAs($this->admin)->put("/siswa/{$this->siswa->id}", koreksi())
        ->assertRedirect("/siswa/{$this->siswa->id}")
        ->assertSessionHas('success');

    $siswa = Student::withoutGlobalScopes()->find($this->siswa->id);
    expect($siswa->nik)->toBe('3201123412349999')
        ->and($siswa->name)->toBe('Nama Benar')
        ->and(Activity::where('description', 'siswa.dikoreksi')->first()->properties['catatan'])->toBe('NIK salah ketik saat mendaftar.');

    Notification::assertSentTo($this->akun, StudentCorrectedNotification::class);
});

test('catatan koreksi wajib dan minimal 5 karakter', function () {
    $this->actingAs($this->admin);

    $this->put("/siswa/{$this->siswa->id}", koreksi(['note' => '']))->assertSessionHasErrors('note');
    $this->put("/siswa/{$this->siswa->id}", koreksi(['note' => 'abc']))->assertSessionHasErrors('note');

    expect($this->siswa->fresh()->name)->not->toBe('Nama Benar');
    Notification::assertNothingSent();
});

test('NIK yang bentrok dengan siswa lain ditolak dan tidak ada yang berubah', function () {
    Student::factory()->create(['nik' => '3201123412349999']);

    $this->actingAs($this->admin)->put("/siswa/{$this->siswa->id}", koreksi())->assertSessionHasErrors('nik');

    expect($this->siswa->fresh()->nik)->toBe('3201123412341234');
    Notification::assertNothingSent();
});

test('sekolah dapat diganti oleh staf bila siswa tidak punya pendaftaran aktif', function () {
    $baru = School::factory()->create();

    $this->actingAs($this->admin)->put("/siswa/{$this->siswa->id}", koreksi(['school_id' => $baru->id]))->assertSessionHasNoErrors();

    expect(Student::withoutGlobalScopes()->find($this->siswa->id)->school_id)->toBe($baru->id);
});

test('setelah sekolah diganti, siswa tetap dapat membuka data dan melihat sekolah barunya', function () {
    $baru = School::factory()->create(['name' => 'SMAN Baru']);

    $this->actingAs($this->admin)->put("/siswa/{$this->siswa->id}", koreksi(['school_id' => $baru->id]));

    expect($this->akun->fresh()->school_id)->toBe($baru->id);

    $this->actingAs($this->akun->fresh())->get('/saya')->assertInertia(fn (Assert $p) => $p
        ->where('student.id', $this->siswa->id)
        ->where('school', 'SMAN Baru'));
});

test('sekolah tidak dapat diganti bila siswa masih punya pendaftaran aktif', function () {
    $baru = School::factory()->create();
    makeEntry(S::MenungguVerifikasi, student: $this->siswa);

    $this->actingAs($this->admin)->put("/siswa/{$this->siswa->id}", koreksi(['school_id' => $baru->id]))
        ->assertSessionHas('error');

    $siswa = Student::withoutGlobalScopes()->find($this->siswa->id);
    expect($siswa->school_id)->toBe($this->school->id)->and($siswa->name)->not->toBe('Nama Benar');
});

test('pelaku yang terikat sekolah tidak dapat memindah sekolah siswa', function () {
    $lain = School::factory()->create();
    $operator = makeUserWith(['siswa.ubah'], $this->school, true, 'operator-koreksi');

    $this->actingAs($operator)->put("/siswa/{$this->siswa->id}", koreksi(['school_id' => $lain->id]))->assertSessionHasNoErrors();

    expect(Student::withoutGlobalScopes()->find($this->siswa->id)->school_id)->toBe($this->school->id);
});

test('halaman koreksi memuat NIK penuh dan daftar sekolah untuk staf, null untuk pelaku bersekolah', function () {
    School::factory()->create();

    $this->actingAs($this->admin)->get("/siswa/{$this->siswa->id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->component('Siswa/Form')
        ->where('student.nik', '3201123412341234')
        ->has('schools', 2));

    $operator = makeUserWith(['siswa.ubah'], $this->school, true, 'operator-koreksi');
    $this->actingAs($operator)->get("/siswa/{$this->siswa->id}/ubah")->assertInertia(fn (Assert $p) => $p->where('schools', null));
});

test('NIK pada log aktivitas termasking setelah koreksi', function () {
    $this->actingAs($this->admin)->put("/siswa/{$this->siswa->id}", koreksi());

    $json = $this->actingAs(makeUserWith(['log.lihat'], roleName: 'pengamat'))->get('/log')->getContent();

    expect($json)->not->toContain('3201123412349999')->and($json)->not->toContain('3201123412341234')
        ->and($json)->toContain('3201********9999');
});

test('hapus siswa memerlukan izin dan menghapus data', function () {
    $lepas = Student::factory()->for($this->school)->create();

    $this->actingAs(makeUserWith(['siswa.lihat'], $this->school, true, 'baca'))->delete("/siswa/{$lepas->id}")->assertForbidden();
    $this->actingAs($this->admin)->delete("/siswa/{$lepas->id}")->assertRedirect('/siswa');

    expect(Student::withoutGlobalScopes()->find($lepas->id))->toBeNull();
});

test('siswa dengan entri apa pun tidak dapat dihapus', function () {
    makeEntry(S::Ditolak, student: $this->siswa);

    $this->actingAs($this->admin)->delete("/siswa/{$this->siswa->id}")
        ->assertSessionHas('error', 'Siswa sudah memiliki pendaftaran; keluarkan dari draf atau hubungi admin.');

    expect(Student::withoutGlobalScopes()->find($this->siswa->id))->not->toBeNull();
});
