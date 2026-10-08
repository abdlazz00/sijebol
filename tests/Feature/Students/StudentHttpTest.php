<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function dataBaru(array $override = []): array
{
    return [...['nik' => '3201123412341234', 'nisn' => '0012345678', 'name' => 'Budi', 'birth_date' => '2009-05-01', 'gender' => 'L'], ...$override];
}

function operatorSekolah(School $school, array $extra = [])
{
    return makeUserWith(['siswa.lihat', 'siswa.buat', 'siswa.ubah', 'siswa.hapus', ...$extra], $school, true, 'operator-uji');
}

test('operator menambah siswa dan sekolah dipaksa ke sekolahnya', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();

    $this->actingAs(operatorSekolah($a))
        ->post('/siswa', [...dataBaru(), 'school_id' => $b->id])
        ->assertRedirect();

    expect(Student::withoutGlobalScopes()->where('nik', '3201123412341234')->first()->school_id)->toBe($a->id);
});

test('staf tanpa sekolah wajib memilih sekolah', function () {
    $staf = makeUserWith(['siswa.buat'], roleName: 'staf');

    $this->actingAs($staf)->post('/siswa', dataBaru())->assertSessionHasErrors('school_id');

    $sekolah = School::factory()->create();
    $this->actingAs($staf)->post('/siswa', [...dataBaru(), 'school_id' => $sekolah->id])->assertSessionHasNoErrors();
});

test('sekolah tidak berubah saat ubah', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    $s = Student::factory()->for($a)->create();

    $this->actingAs(operatorSekolah($a))
        ->put("/siswa/{$s->id}", [...dataBaru(['nik' => $s->nik]), 'school_id' => $b->id])
        ->assertRedirect("/siswa/{$s->id}");

    expect($s->fresh()->school_id)->toBe($a->id)->and($s->fresh()->name)->toBe('Budi');
});

test('siswa sekolah lain tidak terlihat, tidak bisa diubah atau dihapus (404)', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    $milikB = Student::factory()->for($b)->create();
    $op = operatorSekolah($a);

    $this->actingAs($op)->get("/siswa/{$milikB->id}")->assertNotFound();
    $this->actingAs($op)->put("/siswa/{$milikB->id}", ['name' => 'X'])->assertNotFound();
    $this->actingAs($op)->delete("/siswa/{$milikB->id}")->assertNotFound();
});

test('daftar memasker NIK dan menyaring', function () {
    $a = School::factory()->create();
    $lengkap = Student::factory()->for($a)->create(['name' => 'Lengkap', 'nik' => '3201123412341234']);
    foreach (['kk', 'akta_lahir'] as $t) {
        $lengkap->documents()->create(['type' => $t, 'path' => $t, 'mime' => 'application/pdf', 'size' => 1]);
    }
    Student::factory()->for($a)->create(['name' => 'Kurang']);

    $this->actingAs(operatorSekolah($a))->get('/siswa')->assertInertia(fn (Assert $p) => $p
        ->component('Siswa/Index')->has('students.data', 2)
        ->where('students.data.0.nik', fn ($v) => str_contains($v, '*'))
        ->where('schools', null));

    $this->get('/siswa?filter[docs]=lengkap')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.name', 'Lengkap'));
    $this->get('/siswa?filter[docs]=kurang')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.name', 'Kurang'));
    $this->get('/siswa?filter[eligible]=1')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.eligible', true));
    $this->get('/siswa?filter[search]=Lengk')->assertInertia(fn (Assert $p) => $p->has('students.data', 1));
});

test('detail menampilkan eligibility dan NIK termasking; ubah menampilkan NIK penuh', function () {
    $a = School::factory()->create();
    $s = Student::factory()->for($a)->create(['nik' => '3201123412341234']);
    $op = operatorSekolah($a);

    $this->actingAs($op)->get("/siswa/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Siswa/Show')->where('student.nik', '3201********1234')
        ->where('eligibility.eligible', false)->has('eligibility.reasons', 2)->has('documents', 0));

    $this->get("/siswa/{$s->id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->component('Siswa/Form')->where('student.nik', '3201123412341234'));
});

test('perubahan siswa tercatat dan NIK termasking di respons log', function () {
    $a = School::factory()->create();
    $this->actingAs(operatorSekolah($a))->post('/siswa', dataBaru());

    expect(Activity::where('log_name', 'siswa')->where('event', 'created')->exists())->toBeTrue();

    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    $json = $this->actingAs($viewer)->get('/log')->getContent();
    expect($json)->not->toContain('3201123412341234')->and($json)->toContain('3201********1234');
});

test('hapus siswa memerlukan izin dan menghapus data', function () {
    $a = School::factory()->create();
    $s = Student::factory()->for($a)->create();

    $this->actingAs(makeUserWith(['siswa.lihat'], $a, true, 'baca'))->delete("/siswa/{$s->id}")->assertForbidden();
    $this->actingAs(operatorSekolah($a))->delete("/siswa/{$s->id}")->assertRedirect('/siswa');
    expect(Student::withoutGlobalScopes()->count())->toBe(0);
});

test('staf melihat semua sekolah dan daftar sekolah untuk filter', function () {
    Student::factory()->count(2)->create();

    $this->actingAs(makeUserWith(['siswa.lihat'], roleName: 'staf-lihat'))->get('/siswa')
        ->assertInertia(fn (Assert $p) => $p->has('students.data', 2)->has('schools', 2));
});
