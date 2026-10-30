<?php

use App\Domains\Schools\Models\School;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

test('403 tanpa izin dan 200 dengan izin', function () {
    $this->actingAs(makeUserWith([], roleName: 'biasa'))->get('/audit-logs')->assertForbidden();
    $this->actingAs(makeUserWith(['log.lihat'], roleName: 'pengamat'))->get('/audit-logs')
        ->assertOk()->assertInertia(fn ($p) => $p->component('Log/Index')->has('activities.data')->has('types')->has('users'));
});

test('filter pengguna, jenis, dan rentang tanggal (to inklusif)', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    $a = User::factory()->create(['name' => 'Aa']);
    $b = User::factory()->create(['name' => 'Bb']);
    activity('akses')->causedBy($a)->log('login');
    activity('default')->causedBy($b)->log('updated');
    $akhir = activity('akses')->causedBy($a)->log('logout');
    $akhir->forceFill(['created_at' => Carbon::parse('2026-10-05 23:30', 'Asia/Jakarta')->utc()])->save();

    $this->actingAs($viewer);
    $this->get("/audit-logs?filter[user]={$a->id}")->assertInertia(fn ($p) => $p->has('activities.data', 2));
    $this->get('/audit-logs?filter[type]=default')->assertInertia(fn ($p) => $p->has('activities.data', 1));
    $this->get('/audit-logs?filter[from]=2026-10-05&filter[to]=2026-10-05')
        ->assertInertia(fn ($p) => $p->has('activities.data', 1)->where('activities.data.0.action', 'Logout'));
});

test('baris dengan pelaku sistem dan subjek terhapus tetap tampil, dengan label Indonesia', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    $hapus = User::factory()->create(['name' => 'Hilang']);
    activity('akses')->performedOn($hapus)->log('user.dinonaktifkan');
    DB::table('users')->where('id', $hapus->id)->delete();

    $this->actingAs($viewer)->get('/audit-logs')->assertOk()->assertInertia(fn ($p) => $p
        ->where('activities.data.0.causer', null)
        ->where('activities.data.0.action', 'Pengguna dinonaktifkan')
        ->where('activities.data.0.subject.type', 'Pengguna'));
});

test('perubahan model nyata tampil dengan sebelum dan sesudah', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    $this->actingAs($viewer);
    $school = School::factory()->create(['name' => 'Awal']);
    $school->update(['name' => 'Akhir']);

    $this->get('/audit-logs')->assertInertia(fn ($p) => $p
        ->where('activities.data.0.action', 'Diubah')
        ->where('activities.data.0.subject.label', 'Akhir')
        ->where('activities.data.0.changes', fn ($changes) => collect($changes)
            ->contains(['field' => 'name', 'old' => 'Awal', 'new' => 'Akhir'])));
});

test('properti sensitif tidak bocor dan NIK dimasker di respons', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    activity('default')->causedBy($viewer)->withChanges([
        'attributes' => ['name' => 'Baru', 'password' => 'rahasia', 'nik' => '3201123412341234'],
        'old' => ['name' => 'Lama', 'password' => 'lama'],
    ])->log('updated');

    $json = $this->actingAs($viewer)->get('/audit-logs')->assertOk()->getContent();

    expect($json)->not->toContain('rahasia')
        ->and($json)->not->toContain('3201123412341234')
        ->and($json)->toContain('3201********1234');
});

test('tidak ada route tulis untuk log', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        // Rute fallback menjawab 404 untuk metode yang tidak terdaftar.
        $this->actingAs($viewer)->{$method}('/audit-logs')->assertNotFound();
    }
});

test('detail pengguna menyertakan aktivitas hanya bagi pemilik log.lihat', function () {
    $target = User::factory()->create();
    activity('akses')->performedOn($target)->causedBy($target)->log('login');
    $dengan = makeUserWith(['user.lihat', 'log.lihat'], roleName: 'dengan');
    $tanpa = makeUserWith(['user.lihat'], roleName: 'tanpa');

    $this->actingAs($dengan)->get("/users/{$target->id}")->assertInertia(fn ($p) => $p->has('activities', 2)); // dibuat + login
    $this->actingAs($tanpa)->get("/users/{$target->id}")->assertInertia(fn ($p) => $p->where('activities', null));
});
