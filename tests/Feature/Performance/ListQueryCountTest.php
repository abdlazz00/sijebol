<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('indeks created_at ada pada activity_log', function () {
    expect(Schema::hasIndex('activity_log', ['created_at']))->toBeTrue();
});

/** Menambah `$n` baris data untuk halaman daftar tertentu. */
function perfSeedRows(string $halaman, int $n, School $school): void
{
    for ($i = 0; $i < $n; $i++) {
        match ($halaman) {
            'pengguna' => User::factory()->create(),
            'role' => Role::create(['name' => 'role-'.Str::random(8), 'guard_name' => 'web']),
            'sekolah' => School::factory()->create(),
            'siswa' => makeEntry(S::Terdaftar, $school), // dengan pendaftaran agar N+1 relasi sesi terdeteksi
            'verifikasi' => makeEntry(S::MenungguVerifikasi, $school),
            'jadwal' => makeEntry(S::Terdaftar, $school),
            'log' => activity('uji')->log("aksi {$i}"),
            'notifikasi' => DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => 'uji', 'notifiable_type' => User::class,
                'notifiable_id' => auth()->id(),
                'data' => json_encode(['title' => 'T', 'body' => 'B', 'url' => '/dashboard']),
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'laporan' => makeEntry(S::MenungguVerifikasi, School::factory()->create()),
        };
    }
}

/** Jumlah query yang dipakai satu permintaan GET. */
function perfQueryCount(object $test, string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $test->get($url)->assertOk();
    $jumlah = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $jumlah;
}

test('jumlah query halaman daftar tidak tumbuh menurut jumlah baris', function (string $halaman, string $url) {
    $school = School::factory()->create();
    $admin = makeUserWith(Permission::values(), roleName: 'perf-admin');
    $this->actingAs($admin);

    $this->get($url)->assertOk();            // pemanasan: cache izin dan sejenisnya
    perfSeedRows($halaman, 3, $school);
    $kecil = perfQueryCount($this, $url);

    perfSeedRows($halaman, 20, $school);
    $besar = perfQueryCount($this, $url);

    expect($besar)->toBeLessThanOrEqual($kecil, "{$halaman}: {$kecil} query untuk 3 baris, {$besar} untuk 23 baris (N+1)");
})->with([
    ['pengguna', '/pengguna'],
    ['role', '/role'],
    ['sekolah', '/sekolah'],
    ['siswa', '/siswa'],
    ['verifikasi', '/verifikasi'],
    ['jadwal', '/jadwal'],
    ['log', '/log'],
    ['notifikasi', '/notifikasi'],
    ['laporan', '/laporan'],
]);
