<?php

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function operatorSekolah(School $school, array $extra = [])
{
    return makeUserWith(['siswa.lihat', ...$extra], $school, true, 'operator-uji');
}

test('rute tambah siswa dan impor Excel sudah tidak ada', function () {
    $this->actingAs(makeUserWith(['siswa.lihat'], roleName: 'staf-hapus-rute'));

    $this->get('/siswa/buat')->assertNotFound();
    $this->post('/siswa', [])->assertNotFound();
    $this->get('/siswa/impor/template')->assertNotFound();
    $this->post('/siswa/impor', [])->assertNotFound();
});

test('siswa sekolah lain tidak terlihat, tidak bisa diubah atau dihapus (404)', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    $milikB = Student::factory()->for($b)->create();
    $op = operatorSekolah($a, ['siswa.ubah', 'siswa.hapus']);

    $this->actingAs($op)->get("/siswa/{$milikB->id}")->assertNotFound();
    $this->put("/siswa/{$milikB->id}", ['name' => 'X'])->assertNotFound();
    $this->delete("/siswa/{$milikB->id}")->assertNotFound();
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
    $op = operatorSekolah($a, ['siswa.ubah']);

    $this->actingAs($op)->get("/siswa/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Siswa/Show')->where('student.nik', '3201********1234')
        ->where('eligibility.eligible', false)->has('eligibility.reasons', 2)->has('documents', 0));

    $this->get("/siswa/{$s->id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->component('Siswa/Form')->where('student.nik', '3201123412341234'));
});

test('staf melihat semua sekolah dan daftar sekolah untuk filter', function () {
    Student::factory()->count(2)->create();

    $this->actingAs(makeUserWith(['siswa.lihat'], roleName: 'staf-lihat'))->get('/siswa')
        ->assertInertia(fn (Assert $p) => $p->has('students.data', 2)->has('schools', 2));
});

test('pencarian NIK hanya cocok persis sehingga masker tidak bisa dibongkar', function () {
    $a = School::factory()->create();
    Student::factory()->for($a)->create(['name' => 'Rahasia', 'nik' => '3201123412341234']);
    $pembaca = makeUserWith(['siswa.lihat'], $a, true, 'pembaca-saja');

    $this->actingAs($pembaca);
    $this->get('/siswa?filter[search]=3201')->assertInertia(fn (Assert $p) => $p->has('students.data', 0));
    $this->get('/siswa?filter[search]=32011234123412')->assertInertia(fn (Assert $p) => $p->has('students.data', 0));
    $this->get('/siswa?filter[search]=3201123412341234')->assertInertia(fn (Assert $p) => $p->has('students.data', 1));
});

test('filter eligible tidak menampilkan siswa dengan entri aktif', function () {
    $sekolah = School::factory()->create();
    $bebas = eligibleStudent($sekolah);
    $sibuk = eligibleStudent($sekolah);
    makeEntry(RegistrationStatus::MenungguVerifikasi, student: $sibuk);
    $user = makeUserWith(['siswa.lihat'], $sekolah, true, 'op-filter');

    $this->actingAs($user)->get('/siswa?filter[eligible]=1')->assertInertia(fn (Assert $p) => $p
        ->has('students.data', 1)
        ->where('students.data.0.id', $bebas->id));
});

test('penanda KTP-el langsung mengikuti tanggal sesi pendaftaran aktif', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-19 10:00:00', 'Asia/Jakarta'));
    $a = School::factory()->create();
    $lebih = Student::factory()->for($a)->create(['name' => 'Lebih', 'birth_date' => '2009-10-19']);
    makeEntry(RegistrationStatus::Terdaftar, $a, $lebih, makeSchedule($a, '2026-10-20'));
    $pas = Student::factory()->for($a)->create(['name' => 'Pas', 'birth_date' => '2009-10-20']);
    makeEntry(RegistrationStatus::Terdaftar, $a, $pas, makeSchedule($a, '2026-10-20'));
    Student::factory()->for($a)->create(['name' => 'Enam', 'birth_date' => '2010-10-19']);
    $this->actingAs(operatorSekolah($a));

    $this->get('/siswa')->assertInertia(fn (Assert $p) => $p
        ->where('students.data', fn ($rows) => collect($rows)->pluck('ktp_langsung', 'name')->all() === ['Enam' => false, 'Lebih' => true, 'Pas' => false]));
    $this->get("/siswa/{$lebih->id}")->assertInertia(fn (Assert $p) => $p->where('student.ktp_langsung', true));
    $this->get("/siswa/{$pas->id}")->assertInertia(fn (Assert $p) => $p->where('student.ktp_langsung', false));

    Carbon::setTestNow();
});
