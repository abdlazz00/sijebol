<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use App\Support\Nik;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('nama dan nomor HP dapat diubah, email tidak', function () {
    $user = User::factory()->create(['email' => 'tetap@x.test']);

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Nama Baru',
        'phone' => '081234567890',
        'email' => 'dicoba@x.test',
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');

    $user->refresh();
    expect($user->name)->toBe('Nama Baru')
        ->and($user->phone)->toBe('081234567890')
        ->and($user->email)->toBe('tetap@x.test');
});

test('nomor HP tidak valid ditolak', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', ['name' => 'X', 'phone' => 'abc'])->assertSessionHasErrors('phone');
});

test('halaman profil menerima data profil pengguna', function () {
    $user = makeUserWith([], roleName: 'admin-kecamatan');
    $user->update(['phone' => '081234567890']);

    $this->actingAs($user)->get('/profile')
        ->assertInertia(fn ($page) => $page->component('Profile/Edit')
            ->where('profile.name', $user->name)
            ->where('profile.email', $user->email)
            ->where('profile.phone', '081234567890')
            ->where('profile.role', 'admin-kecamatan'));
});

test('nomor HP kosong disimpan sebagai null', function () {
    $user = User::factory()->create(['phone' => '081234567890']);

    $this->actingAs($user)->patch('/profile', ['name' => 'X', 'phone' => ''])->assertSessionHasNoErrors();

    expect($user->fresh()->phone)->toBeNull();
});

test('identitas siswa memuat data siswa dengan NIK dimasker dan umur', function () {
    $school = School::factory()->create();
    $akun = makeUserWith(['profil.lihat'], $school, true, 'siswa');
    $siswa = Student::factory()->for($school)->create(['user_id' => $akun->id]);

    $this->actingAs($akun)->get('/profile')
        ->assertInertia(fn ($page) => $page->component('Profile/Edit')
            ->where('identity.student.nik', Nik::mask($siswa->nik))
            ->where('identity.student.nisn', $siswa->nisn)
            ->where('identity.student.age', $siswa->birth_date->age)
            ->where('identity.student.gender', $siswa->gender)
            ->where('identity.school', $school->name)
            ->where('identity.can_open_student_data', true));
});

test('identitas siswa tanpa baris Student tetap valid', function () {
    $school = School::factory()->create();
    $akun = makeUserWith(['profil.lihat'], $school, true, 'siswa');

    $this->actingAs($akun)->get('/profile')
        ->assertInertia(fn ($page) => $page
            ->where('identity.student', null)
            ->where('identity.school', $school->name)
            ->where('identity.can_open_student_data', true));
});

test('identitas operator memuat sekolah dan deskripsi role', function () {
    $school = School::factory()->create();
    $operator = makeUserWith([], $school, true, 'operator-sekolah');
    $operator->currentRole()->update(['description' => 'Memantau siswa dan sesi di sekolah']);

    $this->actingAs($operator->fresh())->get('/profile')
        ->assertInertia(fn ($page) => $page
            ->where('identity.student', null)
            ->where('identity.school', $school->name)
            ->where('identity.access', 'Memantau siswa dan sesi di sekolah')
            ->where('identity.can_open_student_data', false));
});

test('identitas admin tanpa sekolah dan tanpa login terakhir', function () {
    $admin = makeUserWith([], roleName: 'admin-kecamatan');

    $this->actingAs($admin)->get('/profile')
        ->assertInertia(fn ($page) => $page
            ->where('identity.school', null)
            ->where('identity.access', null)
            ->where('identity.last_login_at', null)
            ->where('identity.can_open_student_data', false)
            ->where('identity.created_at', fn ($v) => is_string($v) && $v !== ''));
});
