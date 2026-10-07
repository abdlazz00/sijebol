<?php

use App\Domains\Access\Models\Role;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin-kecamatan');
    $this->school = School::factory()->create();
    $this->operatorRole = Role::findByName('operator-sekolah');
});

test('daftar pengguna dengan filter status dan pencarian', function () {
    User::factory()->create(['name' => 'Siti', 'email' => 'siti@x.test'])->assignRole('operator-sekolah');
    User::factory()->inactive()->create(['name' => 'Off'])->assignRole('operator-sekolah');

    $this->actingAs($this->admin)->get('/pengguna?filter[status]=nonaktif')->assertInertia(fn (Assert $p) => $p
        ->component('Pengguna/Index')
        ->has('users.data', 1)
        ->where('users.data.0.name', 'Off')
        ->where('users.data.0.status', 'nonaktif'));

    $this->get('/pengguna?filter[search]=siti')->assertInertia(fn (Assert $p) => $p->has('users.data', 1));
});

test('buat pengguna mengarah ke halaman berhasil yang menampilkan link sekali saja', function () {
    $response = $this->actingAs($this->admin)->post('/pengguna', [
        'name' => 'Budi', 'email' => 'budi@x.test', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id,
    ]);
    $user = User::firstWhere('email', 'budi@x.test');
    $response->assertRedirect("/pengguna/{$user->id}/berhasil");

    $this->get("/pengguna/{$user->id}/berhasil")->assertInertia(fn (Assert $p) => $p
        ->component('Pengguna/Berhasil')
        ->where('user.name', 'Budi')
        ->where('link', fn ($link) => str_contains($link, '/aktivasi/')));

    $this->get("/pengguna/{$user->id}/berhasil")->assertRedirect("/pengguna/{$user->id}");
});

test('validasi: email unik tanpa memandang huruf besar, sekolah menurut role', function () {
    User::factory()->create(['email' => 'ada@x.test']);

    $this->actingAs($this->admin)->post('/pengguna', [
        'name' => 'X', 'email' => 'ADA@x.test', 'role_id' => $this->operatorRole->id,
    ])->assertSessionHasErrors(['email', 'school_id']);
});

test('ubah pengguna dan kirim ulang aktivasi', function () {
    $user = User::factory()->pending()->create(['email' => 'p@x.test'])->assignRole('operator-sekolah');
    $user->update(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->put("/pengguna/{$user->id}", [
        'name' => 'Baru', 'email' => 'p@x.test', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id,
    ])->assertRedirect("/pengguna/{$user->id}");
    expect($user->fresh()->name)->toBe('Baru');

    $this->post("/pengguna/{$user->id}/kirim-ulang-aktivasi")->assertRedirect("/pengguna/{$user->id}/berhasil");
});

test('nonaktifkan dan aktifkan', function () {
    $user = User::factory()->create()->assignRole('operator-sekolah');

    $this->actingAs($this->admin)->post("/pengguna/{$user->id}/nonaktifkan")->assertRedirect();
    expect($user->fresh()->is_active)->toBeFalse();

    $this->post("/pengguna/{$user->id}/aktifkan")->assertRedirect();
    expect($user->fresh()->is_active)->toBeTrue();
});

test('akun berhak lebih tinggi tidak dapat diubah oleh admin biasa (403) tetapi dapat dilihat', function () {
    $super = User::factory()->create()->assignRole('super-admin');

    $this->actingAs($this->admin);
    $this->get("/pengguna/{$super->id}")->assertOk();
    $this->get("/pengguna/{$super->id}/ubah")->assertForbidden();
    $this->post("/pengguna/{$super->id}/nonaktifkan")->assertForbidden();
    $this->post("/pengguna/{$super->id}/kirim-ulang-aktivasi")->assertForbidden();
});

test('operator tidak dapat membuka pengguna sekolah lain (404)', function () {
    [$a, $b] = School::factory()->count(2)->create();
    $opA = makeUserWith(['user.lihat', 'user.ubah'], $a, true, 'opA');
    $opB = makeUserWith([], $b, true, 'opB');

    $this->actingAs($opA);
    $this->get("/pengguna/{$opB->id}")->assertNotFound();
    $this->get("/pengguna/{$opB->id}/ubah")->assertNotFound();
    $this->get('/pengguna')->assertInertia(fn (Assert $p) => $p->has('users.data', 1));
});

test('pelanggaran aturan bisnis menjadi flash error', function () {
    $this->actingAs($this->admin)->from('/pengguna')->post("/pengguna/{$this->admin->id}/nonaktifkan")
        ->assertRedirect('/pengguna')->assertSessionHas('error');
});
