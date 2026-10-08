<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

test('403 tanpa izin dan 200 dengan izin', function () {
    $this->actingAs(makeUserWith([], roleName: 'biasa'))->get('/log')->assertForbidden();
    $this->actingAs(makeUserWith(['log.lihat'], roleName: 'pengamat'))->get('/log')
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
    $this->get("/log?filter[user]={$a->id}")->assertInertia(fn ($p) => $p->has('activities.data', 2));
    $this->get('/log?filter[type]=default')->assertInertia(fn ($p) => $p->has('activities.data', 1));
    $this->get('/log?filter[from]=2026-10-05&filter[to]=2026-10-05')
        ->assertInertia(fn ($p) => $p->has('activities.data', 1)->where('activities.data.0.action', 'Logout'));
});

test('baris dengan pelaku sistem dan subjek terhapus tetap tampil, dengan label Indonesia', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    $hapus = User::factory()->create(['name' => 'Hilang']);
    activity('akses')->performedOn($hapus)->log('user.dinonaktifkan');
    DB::table('users')->where('id', $hapus->id)->delete();

    $this->actingAs($viewer)->get('/log')->assertOk()->assertInertia(fn ($p) => $p
        ->where('activities.data.0.causer', null)
        ->where('activities.data.0.action', 'Pengguna dinonaktifkan')
        ->where('activities.data.0.subject.type', 'Pengguna'));
});

test('perubahan memuat sebelum/sesudah dan properti sensitif tidak bocor', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    activity('default')->causedBy($viewer)->withProperties([
        'attributes' => ['name' => 'Baru', 'password' => 'rahasia', 'nik' => '3201123412341234'],
        'old' => ['name' => 'Lama', 'password' => 'lama'],
    ])->log('updated');

    $json = $this->actingAs($viewer)->get('/log')->assertOk()->getContent();

    expect($json)->not->toContain('rahasia')
        ->and($json)->not->toContain('3201123412341234')
        ->and($json)->toContain('3201********1234');
    // Urutan kunci JSON di MySQL tidak terjamin, jadi cukup pastikan perubahan ada.
    $this->get('/log')->assertInertia(fn ($p) => $p->where('activities.data.0.changes', fn ($changes) => collect($changes)
        ->contains(['field' => 'name', 'old' => 'Lama', 'new' => 'Baru'])));
});

test('tidak ada route tulis untuk log', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        // Rute fallback menjawab 404 untuk metode yang tidak terdaftar.
        $this->actingAs($viewer)->{$method}('/log')->assertNotFound();
    }
});

test('detail pengguna menyertakan aktivitas hanya bagi pemilik log.lihat', function () {
    $target = User::factory()->create();
    activity('akses')->performedOn($target)->causedBy($target)->log('login');
    $dengan = makeUserWith(['user.lihat', 'log.lihat'], roleName: 'dengan');
    $tanpa = makeUserWith(['user.lihat'], roleName: 'tanpa');

    $this->actingAs($dengan)->get("/pengguna/{$target->id}")->assertInertia(fn ($p) => $p->has('activities', 2)); // dibuat + login
    $this->actingAs($tanpa)->get("/pengguna/{$target->id}")->assertInertia(fn ($p) => $p->where('activities', null));
});
