# Fase 0 — Setup Project Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kerangka BE (Laravel + Breeze Inertia Vue TS, MySQL, package inti, tooling kualitas) dan FE (token UI, shadcn-vue, formatter, composable, komponen dasar, layout) siap dipakai Fase 1.

**Architecture:** Monolit Laravel dengan Inertia + Vue 3 + TypeScript. Kode domain di `app/Domains/*` (diisi mulai Fase 1). Track BE selesai penuh dulu (Task 0–6), baru Track FE (Task 7–12). Permission didefinisikan sebagai enum PHP dan dicerminkan sebagai konstanta TypeScript yang dijaga sinkron oleh satu test.

**Tech Stack:** PHP 8.5 (mesin dev), Laravel stabil terbaru, MySQL/MariaDB, Breeze (Inertia + Vue + TS), Pest, Larastan, Pint, spatie/laravel-permission, spatie/laravel-activitylog, maatwebsite/excel, spatie/laravel-query-builder, Tailwind v4, shadcn-vue, lucide-vue-next, Vitest + @vue/test-utils, ESLint, Prettier, vue-tsc.

**Spec:** `docs/superpowers/specs/2026-10-07-sijebol-v1-design.md` (sistem), `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (UI). Peta fase: `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`.

## Global Constraints

- Mesin dev: PHP 8.5.7, Composer 2.10, Node 24, npm 11, Git 2.54 (Windows, Git Bash). MySQL/MariaDB belum terpasang di mesin ini (lihat Task 0).
- Database: MySQL/MariaDB. Database dev `sijebol`, database test `sijebol_test`.
- Zona waktu aplikasi `Asia/Jakarta`; locale `id`; faker locale `id_ID`. Pesan validasi berbahasa Indonesia.
- Bahasa antarmuka: Indonesia. Tanggal `7 Okt 2026`, waktu `14.30`.
- Folder frontend mengikuti penamaan Breeze: `resources/js/Components` (dengan `ui/` dan `shared/`), `Layouts`, `Pages`; folder baru huruf kecil: `lib`, `composables`, `config`.
- Satu sumber formatter: `resources/js/lib/` (`date.ts`, `format.ts`, `status.ts`, `permissions.ts`). Dilarang menulis ulang formatter di komponen atau halaman.
- Token warna persis seperti panduan UI bagian 2 (canvas `#F4F6F9`, sidebar `#0B2A5B`, primary `#1D4ED8`, dst.). Tanpa mode gelap, tanpa gradien.
- Registrasi publik dan verifikasi email Breeze dihapus total. Akun hanya dibuat admin (Fase 1).
- Pola backend: `Controller → FormRequest → Service → Repository → Model`. Belum ada kelas domain di Fase 0; `BaseRepository`, `RepositoryServiceProvider`, trait `ScopedBySchool`, dan folder domain kosong **sengaja ditunda ke Fase 1** (tidak ada konsumennya sekarang; menambahkannya hanya berupa kode spekulatif).
- Commit: pesan detail, **tanpa `Co-Authored-By` atau atribusi AI** (`CLAUDE.md`). Karena `CLAUDE.md` juga mengatur "commit hanya saat diminta", eksekutor meminta persetujuan sekali di awal eksekusi bahwa langkah commit di rencana ini boleh dijalankan.
- Jika sebuah package belum mendukung PHP 8.5 atau versi Laravel terpasang, **berhenti dan laporkan** ke pengguna; jangan menurunkan versi atau mem-pin diam-diam.
- Review sebelum fase ditutup: `ponytail:ponytail-review` untuk semua perubahan, `impeccable` untuk perubahan FE, lalu `superpowers:verification-before-completion`.

## Review Focus

Kondisi yang tidak disebut spec tapi mudah menggigit; masing-masing sudah punya test di task pemiliknya:

1. Tamu (belum login) membuka halaman Inertia: `auth.permissions` harus `[]`, bukan error (Task 6).
2. Tanggal kosong, `null`, atau tidak valid pada formatter: tampil `—`, bukan `Invalid Date` (Task 8).
3. Hitungan umur tepat pada hari ulang tahun dan sehari sebelumnya (Task 8). Catatan: server tetap sumber kebenaran kelayakan; FE hanya tampilan.
4. `maskNik` pada `null`, string kosong, dan string pendek tidak melempar error (Task 8).
5. `usePermission` saat `auth` belum ada di props atau pengguna tamu: semua izin ditolak (Task 9).
6. Daftar permission di PHP dan TypeScript tidak boleh berbeda (Task 8, test sinkron).
7. Rute registrasi dan verifikasi email benar-benar 404, termasuk metode POST (Task 2).

## File Structure

**Backend (dibuat/diubah di Fase 0)**
- `app/Domains/Access/Enums/Permission.php` — daftar permission tetap (satu sumber di PHP).
- `app/Http/Middleware/HandleInertiaRequests.php` — membagikan `auth.user`, `auth.permissions`, `flash`.
- `app/Models/User.php` — memakai `HasRoles`.
- `routes/web.php`, `routes/auth.php` — tanpa registrasi dan verifikasi email; `/` mengarah ke `/dashboard`.
- `phpunit.xml`, `phpstan.neon`, `composer.json` (skrip `test`, `lint`, `analyse`).
- `config/app.php`, `.env.example` — zona waktu, locale, MySQL.
- `tests/Feature/Setup/*`, `tests/Unit/PermissionEnumTest.php`.

**Frontend**
- `resources/css/app.css` — token dan tema Tailwind.
- `resources/js/lib/{utils,date,format,status,permissions}.ts` + test.
- `resources/js/composables/{usePermission,useFlash,useDataTable}.ts` + test.
- `resources/js/Components/ui/*` — komponen shadcn-vue.
- `resources/js/Components/shared/{PasswordInput,StatusBadge,EmptyState,PageHeader,ConfirmDialog,DataTable}.vue`.
- `resources/js/config/navigation.ts` — menu sidebar + penyaring berdasarkan permission.
- `resources/js/Layouts/{AuthLayout,AppLayout}.vue`, `Layouts/partials/{Sidebar,Topbar}.vue`.
- `resources/js/Pages/Auth/Login.vue`, `Pages/Dashboard.vue` — dipindah ke layout baru.
- `vitest.config.ts`, `components.json`, `eslint`/`prettier` config.

---

## Track BE

### Task 0: Prasyarat lingkungan (database)

**Files:** tidak ada file proyek.

- [ ] **Step 1: Pastikan ekstensi PDO MySQL aktif**

Run: `php -m | grep -i pdo_mysql`
Expected: `pdo_mysql`. Jika kosong, aktifkan `extension=pdo_mysql` di `php.ini` (lokasi: `php --ini`).

- [ ] **Step 2: Pastikan server MySQL/MariaDB berjalan**

Minta pengguna memasang/menyalakan MySQL atau MariaDB (misalnya lewat Laragon atau XAMPP) dan menyebut host, port, user, serta password. Jangan menebak kredensial. **Berhenti sampai pengguna mengonfirmasi.**

- [ ] **Step 3: Buat dua database**

Run (ganti user/password sesuai jawaban pengguna; default dev biasa `root` tanpa password):
```bash
php -r '$p=new PDO("mysql:host=127.0.0.1;port=3306","root",""); $p->exec("CREATE DATABASE IF NOT EXISTS sijebol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $p->exec("CREATE DATABASE IF NOT EXISTS sijebol_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); echo "ok\n";'
```
Expected: `ok`.

### Task 1: Skeleton Laravel, git, lokalisasi

**Files:**
- Create: seluruh skeleton Laravel di root repo (`composer.json`, `app/`, `config/`, dst.)
- Modify: `.env`, `.env.example`, `config/app.php`, `phpunit.xml`, `.gitignore`
- Test: `tests/Feature/Setup/LocalizationTest.php`

**Interfaces:**
- Produces: aplikasi Laravel berjalan dengan MySQL; locale `id`; zona `Asia/Jakarta`; `php artisan test` hijau.

- [ ] **Step 1: Buat skeleton di luar repo lalu salin ke root**

`composer create-project` menolak folder tidak kosong, jadi buat di folder sementara di samping repo.
```bash
cd C:/Users/abdulaziz/Documents/pribadi
composer create-project laravel/laravel _sijebol_scaffold --no-interaction
cp -r _sijebol_scaffold/. SIJEBOL/
rm -rf _sijebol_scaffold
cd SIJEBOL
ls
```
Expected: `app`, `artisan`, `composer.json`, `docs`, `CLAUDE.md`, dst. `docs/` dan `CLAUDE.md` tetap ada. Jika ada file bernama sama yang tertimpa (cek `git status` setelah langkah 3), laporkan.

- [ ] **Step 2: Atur `.env` dan `.env.example`**

Ubah kedua file (nilai kredensial dari Task 0):
```dotenv
APP_NAME=SIJEBOL
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sijebol
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
```
Di `.env.example` biarkan `DB_PASSWORD=` kosong.

- [ ] **Step 3: Zona waktu dan git**

Di `config/app.php` ubah nilai `'timezone'` menjadi `'Asia/Jakarta'`. Hapus file sqlite bawaan bila ada: `rm -f database/database.sqlite`. Tambahkan `.idea/` ke `.gitignore`. Lalu:
```bash
git init
git add -A
git status --short | head -20
```
Expected: ada `CLAUDE.md`, `docs/`, skeleton Laravel; tidak ada `.env`, `vendor/`, `.idea/`.

- [ ] **Step 4: Arahkan phpunit ke MySQL test**

Di `phpunit.xml`, hapus baris `DB_CONNECTION`/`DB_DATABASE` bawaan (sqlite) dan ganti dengan:
```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="sijebol_test"/>
```

- [ ] **Step 5: Pesan validasi bahasa Indonesia**

```bash
composer require laravel-lang/common
php artisan lang:add id
```
Jika `lang:add` tidak ada, jalankan `php artisan list lang` dan gunakan perintah yang tersedia dari paket itu.

- [ ] **Step 6: Tulis test lokalisasi (gagal dulu)**

Create `tests/Feature/Setup/LocalizationTest.php`:
```php
<?php

test('aplikasi memakai locale dan zona waktu Indonesia', function () {
    expect(config('app.locale'))->toBe('id')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta');
});

test('pesan validasi berbahasa Indonesia', function () {
    $validator = validator(['email' => ''], ['email' => 'required']);

    $validator->fails();

    expect($validator->errors()->first('email'))->toContain('wajib diisi');
});
```
Karena test memakai Pest, pastikan `tests/Pest.php` ada; jika belum (Pest belum terpasang di skeleton), jalankan dulu: `composer remove phpunit/phpunit --dev && composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies && ./vendor/bin/pest --init`. Jika Pest sudah terpasang (Breeze akan memasangnya di Task 2), cukup lanjut dan jalankan test setelah Task 2.

- [ ] **Step 7: Jalankan migrasi dan test**

```bash
php artisan migrate
php artisan test --filter=LocalizationTest
```
Expected: migrasi sukses ke database `sijebol`; kedua test PASS. Jika pesan tidak mengandung "wajib diisi", periksa `lang/id/validation.php` hasil `lang:add`.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore: inisialisasi proyek Laravel SIJEBOL" -m "Skeleton Laravel dengan MySQL, locale id, zona waktu Asia/Jakarta, pesan validasi bahasa Indonesia, dan test lokalisasi. Menyertakan dokumen spec, panduan UI, sprint plan, dan CLAUDE.md."
```

### Task 2: Breeze (Inertia + Vue + TS) tanpa registrasi publik

**Files:**
- Modify: `routes/web.php`, `routes/auth.php`, `app/Models/User.php` (jika memakai `MustVerifyEmail`)
- Delete: controller/halaman/test registrasi dan verifikasi email Breeze, `resources/js/Pages/Welcome.vue`
- Test: `tests/Feature/Setup/NoPublicRegistrationTest.php`

**Interfaces:**
- Produces: login/logout/reset password Breeze berjalan; `/register` dan rute verifikasi email 404; `/` mengalihkan ke `/dashboard`; `dashboard` hanya butuh `auth`.

- [ ] **Step 1: Pasang Breeze**

```bash
composer require laravel/breeze --dev
php artisan breeze:install --help
```
Baca opsi yang tersedia, lalu jalankan dengan stack Vue, TypeScript, dan Pest (sesuaikan nama flag dengan keluaran `--help`):
```bash
php artisan breeze:install vue --typescript --pest --no-interaction
```
Jika installer menanyakan ESLint, pilih ya. Expected: `npm install` dan build otomatis berjalan tanpa error.

- [ ] **Step 2: Pastikan baseline hijau**

```bash
php artisan migrate
php artisan test
```
Expected: semua test bawaan Breeze PASS (termasuk `LocalizationTest`).

- [ ] **Step 3: Tulis test yang menegaskan registrasi dan verifikasi email hilang (gagal dulu)**

Create `tests/Feature/Setup/NoPublicRegistrationTest.php`:
```php
<?php

test('rute registrasi publik tidak ada', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('rute verifikasi email tidak ada', function () {
    $this->get('/verify-email')->assertNotFound();
    $this->post('/email/verification-notification')->assertNotFound();
});

test('halaman utama mengalihkan ke dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
```
Run: `php artisan test --filter=NoPublicRegistrationTest`
Expected: FAIL (rute masih ada).

- [ ] **Step 4: Hapus registrasi dan verifikasi email**

1. Di `routes/auth.php` hapus: rute `register` (GET dan POST), `verification.notice`, `verification.verify`, `verification.send`.
2. Hapus file: `app/Http/Controllers/Auth/RegisteredUserController.php`, `EmailVerificationPromptController.php`, `VerifyEmailController.php`, `EmailVerificationNotificationController.php`, `resources/js/Pages/Auth/Register.vue`, `resources/js/Pages/Auth/VerifyEmail.vue`, `resources/js/Pages/Welcome.vue`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/EmailVerificationTest.php`.
3. Di `routes/web.php`: hapus rute `/` Welcome, ganti dengan
```php
Route::redirect('/', '/dashboard');
```
dan ubah middleware `['auth', 'verified']` pada `dashboard` dan rute lain menjadi `['auth']`.
4. Di `Pages/Auth/Login.vue` hapus tautan/teks ke halaman register jika ada. Cari sisa referensi: `grep -rn "register\|verification" routes app resources/js | grep -v node_modules`.

- [ ] **Step 5: Jalankan test**

```bash
php artisan test
npm run build
```
Expected: semua PASS (test registrasi lama sudah dihapus); build sukses.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: pasang Breeze tanpa registrasi publik" -m "Breeze Inertia + Vue + TypeScript + Pest. Hapus registrasi, verifikasi email, dan halaman Welcome karena akun hanya dibuat admin. Halaman utama mengalihkan ke dashboard."
```

### Task 3: Package inti (permission, activity log, excel, query builder)

**Files:**
- Modify: `composer.json`
- Create: migrasi permission dan activity log hasil publish, `config/permission.php`, `config/activitylog.php`, `config/excel.php`
- Test: `tests/Feature/Setup/PackageTablesTest.php`

**Interfaces:**
- Produces: tabel `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, `activity_log` di database.

- [ ] **Step 1: Test tabel (gagal dulu)**

Create `tests/Feature/Setup/PackageTablesTest.php`:
```php
<?php

use Illuminate\Support\Facades\Schema;

test('tabel RBAC dan activity log tersedia', function () {
    foreach (['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'activity_log'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
});
```
Run: `php artisan test --filter=PackageTablesTest`
Expected: FAIL.

- [ ] **Step 2: Pasang package**

```bash
composer require spatie/laravel-permission spatie/laravel-activitylog maatwebsite/excel spatie/laravel-query-builder
```
Jika salah satu gagal karena batasan versi PHP/Laravel, berhenti dan laporkan (lihat Global Constraints).

- [ ] **Step 3: Publish migrasi dan konfigurasi**

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --no-interaction
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config --no-interaction
ls database/migrations | grep -E "permission|activity"
```
Expected: migrasi `create_permission_tables` dan `create_activity_log_table` (dan migrasi pendamping jika ada) muncul.

- [ ] **Step 4: Migrasi dan test**

```bash
php artisan migrate
php artisan test --filter=PackageTablesTest
```
Expected: PASS. (Test memakai `RefreshDatabase` dari `tests/Pest.php` hasil Breeze; jika `Feature` belum memakainya, tambahkan `uses(Illuminate\Foundation\Testing\RefreshDatabase::class)` pada file test ini.)

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: pasang package RBAC, audit log, excel, query builder" -m "spatie/laravel-permission, spatie/laravel-activitylog, maatwebsite/excel, spatie/laravel-query-builder beserta migrasi dan konfigurasi."
```

### Task 4: Tooling kualitas (Larastan, Pint, skrip composer)

**Files:**
- Create: `phpstan.neon`
- Modify: `composer.json` (skrip)

**Interfaces:**
- Produces: `composer test`, `composer lint`, `composer analyse`.

- [ ] **Step 1: Pasang Larastan**

```bash
composer require --dev larastan/larastan
```

- [ ] **Step 2: Konfigurasi**

Create `phpstan.neon`:
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
    level: 5
```

- [ ] **Step 3: Skrip composer**

Di `composer.json` bagian `scripts`, pastikan ada tiga entri ini (ganti `test` bawaan jika sudah ada):
```json
"test": ["@php artisan config:clear --ansi", "@php artisan test"],
"lint": "pint --test",
"analyse": "phpstan analyse --memory-limit=1G"
```

- [ ] **Step 4: Jalankan**

```bash
composer lint
composer analyse
composer test
```
Expected: ketiganya sukses. Jika `lint` gagal pada kode hasil generate, jalankan `./vendor/bin/pint` sekali untuk merapikan lalu ulangi. Jika `analyse` melaporkan error pada kode Breeze yang bukan milik kita, buat baseline sekali: `./vendor/bin/phpstan analyse --generate-baseline` dan tambahkan `- phpstan-baseline.neon` ke `includes`. Jangan menurunkan `level`.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: tambah Larastan, Pint, dan skrip composer" -m "Skrip composer test, lint, analyse. Larastan level 5 pada folder app."
```

### Task 5: Enum Permission

**Files:**
- Create: `app/Domains/Access/Enums/Permission.php`
- Test: `tests/Unit/PermissionEnumTest.php`

**Interfaces:**
- Produces: `App\Domains\Access\Enums\Permission` (backed enum string) dengan `public static function values(): array` (list string nilai). 32 case, format `modul.aksi`.

- [ ] **Step 1: Test (gagal dulu)**

Create `tests/Unit/PermissionEnumTest.php`:
```php
<?php

use App\Domains\Access\Enums\Permission;

test('permission berformat modul.aksi dan unik', function () {
    $values = Permission::values();

    expect($values)->toHaveCount(32)
        ->and(array_unique($values))->toHaveCount(32);

    foreach ($values as $value) {
        expect($value)->toMatch('/^[a-z]+\.[a-z-]+$/');
    }
});

test('setiap modul pada spec punya permission lihat', function () {
    foreach (['user', 'role', 'sekolah', 'siswa', 'dokumen', 'pendaftaran', 'verifikasi', 'jadwal', 'rekaman', 'laporan', 'log'] as $module) {
        expect(Permission::values())->toContain("$module.lihat");
    }
});
```
Run: `php artisan test --filter=PermissionEnumTest`
Expected: FAIL (class not found).

- [ ] **Step 2: Implementasi**

Create `app/Domains/Access/Enums/Permission.php`:
```php
<?php

namespace App\Domains\Access\Enums;

enum Permission: string
{
    case UserLihat = 'user.lihat';
    case UserBuat = 'user.buat';
    case UserUbah = 'user.ubah';
    case UserNonaktifkan = 'user.nonaktifkan';
    case UserKirimUlangAktivasi = 'user.kirim-ulang-aktivasi';

    case RoleLihat = 'role.lihat';
    case RoleBuat = 'role.buat';
    case RoleUbah = 'role.ubah';
    case RoleHapus = 'role.hapus';

    case SekolahLihat = 'sekolah.lihat';
    case SekolahBuat = 'sekolah.buat';
    case SekolahUbah = 'sekolah.ubah';

    case SiswaLihat = 'siswa.lihat';
    case SiswaBuat = 'siswa.buat';
    case SiswaUbah = 'siswa.ubah';
    case SiswaHapus = 'siswa.hapus';
    case SiswaImpor = 'siswa.impor';

    case DokumenLihat = 'dokumen.lihat';
    case DokumenUnggah = 'dokumen.unggah';
    case DokumenHapus = 'dokumen.hapus';

    case PendaftaranLihat = 'pendaftaran.lihat';
    case PendaftaranBuat = 'pendaftaran.buat';
    case PendaftaranAjukan = 'pendaftaran.ajukan';

    case VerifikasiLihat = 'verifikasi.lihat';
    case VerifikasiPutuskan = 'verifikasi.putuskan';

    case JadwalLihat = 'jadwal.lihat';
    case JadwalBuat = 'jadwal.buat';
    case JadwalUbah = 'jadwal.ubah';

    case RekamanLihat = 'rekaman.lihat';
    case RekamanTandai = 'rekaman.tandai';

    case LaporanLihat = 'laporan.lihat';

    case LogLihat = 'log.lihat';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 3: Jalankan**

Run: `php artisan test --filter=PermissionEnumTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: tambah enum Permission" -m "32 permission format modul.aksi sesuai spec bagian 6, satu sumber di PHP."
```

### Task 6: Shared props Inertia (auth, permissions, flash)

**Files:**
- Modify: `app/Models/User.php`, `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Setup/SharedPropsTest.php`

**Interfaces:**
- Consumes: `Permission` enum (Task 5), tabel spatie (Task 3).
- Produces: setiap respons Inertia membawa `auth.user` (`id`, `name`, `email` atau `null`), `auth.permissions` (list string, `[]` untuk tamu), `flash.success`, `flash.error`.

- [ ] **Step 1: Test (gagal dulu)**

Create `tests/Feature/Setup/SharedPropsTest.php`:
```php
<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('pengguna login menerima daftar permission miliknya', function () {
    PermissionModel::findOrCreate('user.lihat');
    $user = User::factory()->create();
    $user->givePermissionTo('user.lihat');

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.id', $user->id)
        ->where('auth.permissions', ['user.lihat'])
    );
});

test('tamu menerima permission kosong tanpa error', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', null)
        ->where('auth.permissions', [])
    );
});

test('flash success dan error dibagikan', function () {
    $user = User::factory()->create();

    $this->withSession(['success' => 'Tersimpan', 'error' => 'Gagal'])
        ->actingAs($user)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Tersimpan')
            ->where('flash.error', 'Gagal')
        );
});
```
Run: `php artisan test --filter=SharedPropsTest`
Expected: FAIL.

- [ ] **Step 2: `HasRoles` pada User**

Di `app/Models/User.php` tambahkan `use Spatie\Permission\Traits\HasRoles;` dan `HasRoles` pada daftar `use` trait di dalam class.

- [ ] **Step 3: Bagikan props**

Di `app/Http/Middleware/HandleInertiaRequests.php` ganti isi `share()`:
```php
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        'auth' => [
            'user' => $user ? $user->only(['id', 'name', 'email']) : null,
            'permissions' => $user
                ? $user->getAllPermissions()->pluck('name')->values()->all()
                : [],
        ],
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error' => fn () => $request->session()->get('error'),
        ],
    ];
}
```

- [ ] **Step 4: Jalankan semua pemeriksaan BE**

```bash
composer test
composer analyse
composer lint
```
Expected: semuanya sukses.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: bagikan auth, permissions, dan flash ke Inertia" -m "User memakai HasRoles. Shared props auth.user, auth.permissions (kosong untuk tamu), flash.success dan flash.error."
```

### Gerbang BE (sebelum Track FE)

- [ ] `composer test`, `composer analyse`, `composer lint` lulus.
- [ ] `php artisan route:list | grep -E "register|verif"` tidak menampilkan apa pun.
- [ ] `php artisan migrate:fresh` sukses di database `sijebol`.
- [ ] Review `ponytail:ponytail-review` pada perubahan BE; perbaiki temuan valid.

---

## Track FE

### Task 7: Tooling frontend (lint, format, typecheck)

**Files:**
- Modify: `package.json`, konfigurasi ESLint/Prettier hasil Breeze

**Interfaces:**
- Produces: `npm run lint`, `npm run format`, `npm run typecheck`, `npm run build` semuanya jalan.

- [ ] **Step 1: Periksa kondisi hasil Breeze**

Run: `cat package.json && ls -a | grep -E "eslint|prettier|tsconfig"`
Catat skrip dan config yang sudah ada (Breeze biasanya menyertakan `build` dengan `vue-tsc && vite build`).

- [ ] **Step 2: Pasang yang belum ada dan tambah skrip**

Pastikan `eslint`, `prettier`, `eslint-plugin-vue`, `typescript-eslint`, `vue-tsc` ada di `devDependencies` (pasang yang kurang dengan `npm i -D ...`). Tambahkan di `scripts`:
```json
"lint": "eslint resources/js --max-warnings 0",
"format": "prettier --write resources/js",
"typecheck": "vue-tsc --noEmit"
```
Buat `.prettierrc` jika belum ada:
```json
{ "semi": false, "singleQuote": true, "printWidth": 100 }
```

- [ ] **Step 3: Jalankan**

```bash
npm run format
npm run typecheck
npm run lint
npm run build
```
Expected: semuanya sukses. Perbaiki error pada kode generate Breeze (bukan menonaktifkan aturan).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: tambah skrip lint, format, typecheck" -m "ESLint, Prettier, dan vue-tsc dengan --max-warnings 0."
```

### Task 8: Pustaka `lib/` (formatter, status, permissions) dengan Vitest

**Files:**
- Create: `vitest.config.ts`, `resources/js/lib/date.ts`, `format.ts`, `status.ts`, `permissions.ts`, dan test pendampingnya `*.test.ts`; `tests/Unit/PermissionTypescriptSyncTest.php`
- Modify: `package.json`

**Interfaces:**
- Produces (dipakai Task 9–12):
  - `formatDate(v): string`, `formatTime(v): string`, `formatDateTime(v): string`, `relativeTime(v, now?): string`, `calculateAge(birth, on?): number | null` dari `@/lib/date`.
  - `maskNik(nik): string`, `formatNumber(n): string`, `initials(name): string` dari `@/lib/format`.
  - `type BadgeVariant`, `userStatus(status): { label: string; variant: BadgeVariant }` dari `@/lib/status`.
  - `PERMISSIONS` (objek konstanta) dan `type Permission` dari `@/lib/permissions`.

- [ ] **Step 1: Pasang Vitest**

```bash
npm i -D vitest @vue/test-utils jsdom
```
Create `vitest.config.ts`:
```ts
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
  test: { environment: 'jsdom', include: ['resources/js/**/*.test.ts'] },
})
```
Tambahkan skrip `"test": "vitest run"` di `package.json`. Tambahkan `vitest.config.ts` ke `include` di `tsconfig.json` bila typecheck mengeluhkannya.

- [ ] **Step 2: Test tanggal (gagal dulu)**

Create `resources/js/lib/date.test.ts`:
```ts
import { describe, expect, it } from 'vitest'
import { calculateAge, formatDate, formatDateTime, formatTime, relativeTime } from './date'

describe('date', () => {
  it('memformat tanggal Indonesia', () => {
    expect(formatDate('2026-10-07')).toBe('7 Okt 2026')
  })

  it('memformat waktu dengan titik (zona Asia/Jakarta)', () => {
    expect(formatTime('2026-10-07T07:30:00Z')).toBe('14.30')
    expect(formatDateTime('2026-10-07T07:30:00Z')).toBe('7 Okt 2026 14.30')
  })

  it('nilai kosong atau tidak valid menjadi tanda strip', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDate('')).toBe('—')
    expect(formatDate('bukan-tanggal')).toBe('—')
    expect(formatTime(undefined)).toBe('—')
  })

  it('menghitung umur tepat di hari ulang tahun', () => {
    expect(calculateAge('2009-10-07', '2026-10-06')).toBe(16)
    expect(calculateAge('2009-10-07', '2026-10-07')).toBe(17)
    expect(calculateAge('2009-10-07', '2027-10-06')).toBe(17)
  })

  it('umur null untuk input tidak valid', () => {
    expect(calculateAge('', '2026-10-07')).toBeNull()
    expect(calculateAge('abc', '2026-10-07')).toBeNull()
  })

  it('waktu relatif', () => {
    const now = new Date('2026-10-07T07:30:00Z')
    expect(relativeTime('2026-10-07T07:28:00Z', now)).toMatch(/2 menit/)
    expect(relativeTime('2026-10-07T07:29:58Z', now)).toBe('baru saja')
    expect(relativeTime(null, now)).toBe('—')
  })
})
```
Run: `npm test` → Expected: FAIL (modul tidak ada).

- [ ] **Step 3: Implementasi `date.ts`**

Create `resources/js/lib/date.ts`:
```ts
const TZ = 'Asia/Jakarta'
const EMPTY = '—'

type DateInput = string | Date | null | undefined

const dateFmt = new Intl.DateTimeFormat('id-ID', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  timeZone: TZ,
})
const timeFmt = new Intl.DateTimeFormat('id-ID', {
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
  timeZone: TZ,
})
const ymdFmt = new Intl.DateTimeFormat('en-CA', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  timeZone: TZ,
})
const rtf = new Intl.RelativeTimeFormat('id-ID', { numeric: 'auto' })

function toDate(value: DateInput): Date | null {
  if (!value) return null
  const d = value instanceof Date ? value : new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

function ymd(value: string | Date): [number, number, number] | null {
  if (typeof value === 'string') {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
    return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null
  }
  const parts = Object.fromEntries(ymdFmt.formatToParts(value).map((p) => [p.type, p.value]))
  return [Number(parts.year), Number(parts.month), Number(parts.day)]
}

export function formatDate(value: DateInput): string {
  const d = toDate(value)
  return d ? dateFmt.format(d) : EMPTY
}

export function formatTime(value: DateInput): string {
  const d = toDate(value)
  return d ? timeFmt.format(d) : EMPTY
}

export function formatDateTime(value: DateInput): string {
  const d = toDate(value)
  return d ? `${dateFmt.format(d)} ${timeFmt.format(d)}` : EMPTY
}

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 31_536_000],
  ['month', 2_592_000],
  ['day', 86_400],
  ['hour', 3_600],
  ['minute', 60],
]

export function relativeTime(value: DateInput, now: Date = new Date()): string {
  const d = toDate(value)
  if (!d) return EMPTY
  const diff = Math.round((d.getTime() - now.getTime()) / 1000)
  for (const [unit, seconds] of UNITS) {
    if (Math.abs(diff) >= seconds) return rtf.format(Math.round(diff / seconds), unit)
  }
  return 'baru saja'
}

/** Hanya untuk tampilan; kelayakan umur ditentukan server. */
export function calculateAge(birthDate: string, on: string | Date = new Date()): number | null {
  const b = ymd(birthDate)
  const o = ymd(on)
  if (!b || !o) return null
  let age = o[0] - b[0]
  if (o[1] < b[1] || (o[1] === b[1] && o[2] < b[2])) age--
  return age
}
```
Run: `npm test -- date` → Expected: PASS. Jika `formatTime` menghasilkan `14:30`, sesuaikan nilai ekspektasi **dan** panduan UI hanya setelah memeriksa keluaran ICU Node; catat di laporan.

- [ ] **Step 4: Test dan implementasi `format.ts`**

Create `resources/js/lib/format.test.ts`:
```ts
import { describe, expect, it } from 'vitest'
import { formatNumber, initials, maskNik } from './format'

describe('format', () => {
  it('menyamarkan NIK 16 digit', () => {
    expect(maskNik('6171010101010001')).toBe('6171********0001')
  })

  it('aman untuk NIK kosong atau pendek', () => {
    expect(maskNik(null)).toBe('—')
    expect(maskNik('')).toBe('—')
    expect(maskNik('1234')).toBe('****')
  })

  it('memformat angka Indonesia', () => {
    expect(formatNumber(1234567)).toBe('1.234.567')
  })

  it('mengambil inisial dua huruf', () => {
    expect(initials('Budi Santoso')).toBe('BS')
    expect(initials('Budi')).toBe('B')
    expect(initials('  ')).toBe('?')
  })
})
```
Create `resources/js/lib/format.ts`:
```ts
const numberFmt = new Intl.NumberFormat('id-ID')

export function maskNik(nik?: string | null): string {
  if (!nik) return '—'
  if (nik.length <= 8) return '*'.repeat(nik.length)
  return nik.slice(0, 4) + '*'.repeat(nik.length - 8) + nik.slice(-4)
}

export function formatNumber(value: number): string {
  return numberFmt.format(value)
}

export function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  return parts
    .slice(0, 2)
    .map((p) => p[0].toUpperCase())
    .join('')
}
```
Run: `npm test -- format` → PASS.

- [ ] **Step 5: Test dan implementasi `status.ts`**

Create `resources/js/lib/status.test.ts`:
```ts
import { describe, expect, it } from 'vitest'
import { userStatus } from './status'

describe('userStatus', () => {
  it('memetakan status pengguna ke label dan varian', () => {
    expect(userStatus('aktif')).toEqual({ label: 'Aktif', variant: 'success' })
    expect(userStatus('menunggu')).toEqual({ label: 'Menunggu aktivasi', variant: 'warning' })
    expect(userStatus('kedaluwarsa')).toEqual({ label: 'Kedaluwarsa', variant: 'danger' })
    expect(userStatus('nonaktif')).toEqual({ label: 'Nonaktif', variant: 'neutral' })
  })

  it('status tak dikenal jatuh ke netral dengan label apa adanya', () => {
    expect(userStatus('aneh')).toEqual({ label: 'aneh', variant: 'neutral' })
  })
})
```
Create `resources/js/lib/status.ts`:
```ts
export type BadgeVariant = 'success' | 'warning' | 'danger' | 'neutral' | 'info'

export interface StatusMeta {
  label: string
  variant: BadgeVariant
}

const USER_STATUS: Record<string, StatusMeta> = {
  aktif: { label: 'Aktif', variant: 'success' },
  menunggu: { label: 'Menunggu aktivasi', variant: 'warning' },
  kedaluwarsa: { label: 'Kedaluwarsa', variant: 'danger' },
  nonaktif: { label: 'Nonaktif', variant: 'neutral' },
}

export function userStatus(status: string): StatusMeta {
  return USER_STATUS[status] ?? { label: status, variant: 'neutral' }
}
```
Run: `npm test -- status` → PASS.

- [ ] **Step 6: `permissions.ts` dan test sinkron dengan PHP (gagal dulu)**

Create `tests/Unit/PermissionTypescriptSyncTest.php`:
```php
<?php

use App\Domains\Access\Enums\Permission;

test('konstanta TypeScript permission sama dengan enum PHP', function () {
    $ts = file_get_contents(base_path('resources/js/lib/permissions.ts'));
    preg_match_all("/'([a-z]+\.[a-z-]+)'/", $ts, $matches);

    $fromTs = $matches[1];
    $fromPhp = Permission::values();
    sort($fromTs);
    sort($fromPhp);

    expect($fromTs)->toBe($fromPhp);
});
```
Run: `php artisan test --filter=PermissionTypescriptSyncTest` → FAIL (file tidak ada).

Create `resources/js/lib/permissions.ts` (cermin `Permission.php`):
```ts
export const PERMISSIONS = {
  UserLihat: 'user.lihat',
  UserBuat: 'user.buat',
  UserUbah: 'user.ubah',
  UserNonaktifkan: 'user.nonaktifkan',
  UserKirimUlangAktivasi: 'user.kirim-ulang-aktivasi',

  RoleLihat: 'role.lihat',
  RoleBuat: 'role.buat',
  RoleUbah: 'role.ubah',
  RoleHapus: 'role.hapus',

  SekolahLihat: 'sekolah.lihat',
  SekolahBuat: 'sekolah.buat',
  SekolahUbah: 'sekolah.ubah',

  SiswaLihat: 'siswa.lihat',
  SiswaBuat: 'siswa.buat',
  SiswaUbah: 'siswa.ubah',
  SiswaHapus: 'siswa.hapus',
  SiswaImpor: 'siswa.impor',

  DokumenLihat: 'dokumen.lihat',
  DokumenUnggah: 'dokumen.unggah',
  DokumenHapus: 'dokumen.hapus',

  PendaftaranLihat: 'pendaftaran.lihat',
  PendaftaranBuat: 'pendaftaran.buat',
  PendaftaranAjukan: 'pendaftaran.ajukan',

  VerifikasiLihat: 'verifikasi.lihat',
  VerifikasiPutuskan: 'verifikasi.putuskan',

  JadwalLihat: 'jadwal.lihat',
  JadwalBuat: 'jadwal.buat',
  JadwalUbah: 'jadwal.ubah',

  RekamanLihat: 'rekaman.lihat',
  RekamanTandai: 'rekaman.tandai',

  LaporanLihat: 'laporan.lihat',

  LogLihat: 'log.lihat',
} as const

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS]
```
Run: `php artisan test --filter=PermissionTypescriptSyncTest` → PASS.

- [ ] **Step 7: Jalankan semuanya dan commit**

```bash
npm test && npm run typecheck && npm run lint && composer test
git add -A
git commit -m "feat: tambah pustaka lib formatter, status, dan permissions" -m "date, format, status, permissions di resources/js/lib dengan Vitest. Test PHP menjaga konstanta permission TypeScript sama dengan enum PHP."
```
Expected sebelum commit: semua sukses.

### Task 9: Composable (`usePermission`, `useFlash`, `useDataTable`)

**Files:**
- Modify: `resources/js/types/index.d.ts` (tipe `PageProps`)
- Create: `resources/js/composables/usePermission.ts`, `useFlash.ts`, `useDataTable.ts` dan `*.test.ts`

**Interfaces:**
- Consumes: `Permission` dari `@/lib/permissions`; shared props Task 6.
- Produces:
  - `usePermission(): { can(p: Permission): boolean; canAny(ps: Permission[]): boolean }`
  - `useFlash(): { flash: ComputedRef<{ success?: string | null; error?: string | null }> }`
  - `useDataTable(url: string, initial?: Record<string, string | number | null>): { filters; apply(): void; reset(): void }`

- [ ] **Step 1: Tipe PageProps**

Buka `resources/js/types/index.d.ts` dan pastikan `PageProps` memuat:
```ts
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: { id: number; name: string; email: string } | null
    permissions: string[]
  }
  flash: { success?: string | null; error?: string | null }
}
```
(Pertahankan tipe `User` yang sudah ada milik Breeze jika dipakai halaman lain; sesuaikan agar `auth.user` boleh `null`.) Jalankan `npm run typecheck` dan perbaiki halaman Breeze yang mengasumsikan `auth.user` selalu ada (tambahkan `!` atau cek `null` seperlunya).

- [ ] **Step 2: Test `usePermission` (gagal dulu)**

Create `resources/js/composables/usePermission.test.ts`:
```ts
import { beforeEach, describe, expect, it, vi, type Mock } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({ usePage: vi.fn() }))

import { usePage } from '@inertiajs/vue3'
import { PERMISSIONS } from '@/lib/permissions'
import { usePermission } from './usePermission'

function setProps(props: Record<string, unknown>) {
  ;(usePage as Mock).mockReturnValue({ props })
}

describe('usePermission', () => {
  beforeEach(() => vi.clearAllMocks())

  it('mengizinkan permission yang dimiliki', () => {
    setProps({ auth: { user: { id: 1 }, permissions: ['user.lihat'] } })
    const { can, canAny } = usePermission()
    expect(can(PERMISSIONS.UserLihat)).toBe(true)
    expect(can(PERMISSIONS.UserBuat)).toBe(false)
    expect(canAny([PERMISSIONS.UserBuat, PERMISSIONS.UserLihat])).toBe(true)
  })

  it('menolak semua saat auth tidak ada atau tamu', () => {
    setProps({})
    expect(usePermission().can(PERMISSIONS.UserLihat)).toBe(false)
    setProps({ auth: { user: null, permissions: [] } })
    expect(usePermission().canAny([PERMISSIONS.UserLihat])).toBe(false)
  })
})
```
Run: `npm test -- usePermission` → FAIL.

- [ ] **Step 3: Implementasi `usePermission`**

Create `resources/js/composables/usePermission.ts`:
```ts
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { Permission } from '@/lib/permissions'
import type { PageProps } from '@/types'

export function usePermission() {
  const page = usePage<PageProps>()
  const granted = computed(() => new Set<string>(page.props.auth?.permissions ?? []))

  const can = (permission: Permission): boolean => granted.value.has(permission)
  const canAny = (permissions: Permission[]): boolean => permissions.some(can)

  return { can, canAny }
}
```
Run: `npm test -- usePermission` → PASS.

- [ ] **Step 4: `useFlash` dengan test**

Create `resources/js/composables/useFlash.test.ts`:
```ts
import { describe, expect, it, vi, type Mock } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({ usePage: vi.fn() }))

import { usePage } from '@inertiajs/vue3'
import { useFlash } from './useFlash'

describe('useFlash', () => {
  it('mengembalikan flash dari props', () => {
    ;(usePage as Mock).mockReturnValue({ props: { flash: { success: 'Tersimpan', error: null } } })
    expect(useFlash().flash.value).toEqual({ success: 'Tersimpan', error: null })
  })

  it('aman bila flash tidak ada', () => {
    ;(usePage as Mock).mockReturnValue({ props: {} })
    expect(useFlash().flash.value).toEqual({})
  })
})
```
Create `resources/js/composables/useFlash.ts`:
```ts
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'

export function useFlash() {
  const page = usePage<PageProps>()
  const flash = computed(() => page.props.flash ?? {})
  return { flash }
}
```
Run: `npm test -- useFlash` → PASS.

- [ ] **Step 5: `useDataTable` dengan test**

Create `resources/js/composables/useDataTable.test.ts`:
```ts
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({ router: { get: vi.fn() } }))

import { router } from '@inertiajs/vue3'
import { useDataTable } from './useDataTable'

describe('useDataTable', () => {
  beforeEach(() => vi.clearAllMocks())

  it('mengirim filter tanpa nilai kosong', () => {
    const { filters, apply } = useDataTable('/pengguna', { search: '', role: null })
    filters.search = 'budi'
    apply()
    expect(router.get).toHaveBeenCalledWith(
      '/pengguna',
      { search: 'budi' },
      { preserveState: true, preserveScroll: true, replace: true },
    )
  })

  it('reset mengembalikan nilai awal lalu memuat ulang', () => {
    const { filters, reset } = useDataTable('/pengguna', { search: '' })
    filters.search = 'x'
    reset()
    expect(filters.search).toBe('')
    expect(router.get).toHaveBeenCalledWith('/pengguna', {}, expect.any(Object))
  })
})
```
Create `resources/js/composables/useDataTable.ts`:
```ts
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

type FilterValue = string | number | null

export function useDataTable(url: string, initial: Record<string, FilterValue> = {}) {
  const filters = reactive<Record<string, FilterValue>>({ ...initial })

  function apply() {
    const query = Object.fromEntries(
      Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined),
    )
    router.get(url, query, { preserveState: true, preserveScroll: true, replace: true })
  }

  function reset() {
    for (const key of Object.keys(filters)) filters[key] = initial[key] ?? ''
    apply()
  }

  return { filters, apply, reset }
}
```
Run: `npm test` → PASS semua.

- [ ] **Step 6: Verifikasi dan commit**

```bash
npm run typecheck && npm run lint && npm test
git add -A
git commit -m "feat: tambah composable usePermission, useFlash, useDataTable" -m "Tipe PageProps memuat auth.permissions dan flash. Semua composable punya test, termasuk kasus tamu dan props kosong."
```

### Task 10: Token Tailwind dan komponen dasar shadcn-vue

**Files:**
- Modify: `resources/css/app.css`, `resources/views/app.blade.php`, `package.json`
- Create: `components.json`, `resources/js/lib/utils.ts`, `resources/js/Components/ui/*`

**Interfaces:**
- Produces: kelas Tailwind bertema (`bg-background`, `bg-card`, `text-muted-foreground`, `bg-primary`, `bg-sidebar`, `bg-success-soft`, `text-success`, dst.) dan komponen shadcn: `button`, `input`, `label`, `checkbox`, `select`, `card`, `dialog`, `sonner`, `skeleton`, `avatar`, `breadcrumb`, `separator`, `dropdown-menu`, `table`.

- [ ] **Step 1: Pastikan Tailwind v4**

Run: `npm ls tailwindcss`
Expected: versi `4.x`. Jika `3.x`: jalankan `npx @tailwindcss/upgrade`, tinjau diff-nya, lalu `npm run build`. Jika upgrade gagal, berhenti dan laporkan.

- [ ] **Step 2: Font Inter**

Di `resources/views/app.blade.php` ganti tautan font Figtree dengan:
```html
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
```

- [ ] **Step 3: Inisialisasi shadcn-vue**

```bash
npx shadcn-vue@latest init --help
```
Jalankan init dengan opsi default/otomatis sesuai keluaran `--help` (umumnya `npx shadcn-vue@latest init --defaults --yes`). Jika tetap interaktif, pilih: style New York, base color Slate, CSS variables ya. Setelah selesai, buka `components.json` dan pastikan alias:
```json
"aliases": {
  "components": "@/Components",
  "ui": "@/Components/ui",
  "utils": "@/lib/utils",
  "lib": "@/lib",
  "composables": "@/composables"
}
```
dan `tailwind.css` menunjuk `resources/css/app.css`. Perbaiki bila berbeda, lalu pastikan `resources/js/lib/utils.ts` (fungsi `cn`) ada.

- [ ] **Step 4: Token tema**

Ganti isi variabel tema di `resources/css/app.css` (pertahankan `@import "tailwindcss"` di atas dan impor animasi yang ditambahkan shadcn) menjadi:
```css
:root {
  --background: #f4f6f9;
  --foreground: #0f172a;
  --card: #ffffff;
  --card-foreground: #0f172a;
  --popover: #ffffff;
  --popover-foreground: #0f172a;
  --primary: #1d4ed8;
  --primary-foreground: #ffffff;
  --primary-hover: #1e40af;
  --primary-soft: #e8effd;
  --secondary: #f8fafc;
  --secondary-foreground: #0f172a;
  --muted: #f8fafc;
  --muted-foreground: #64748b;
  --accent: #e8effd;
  --accent-foreground: #1d4ed8;
  --destructive: #b91c1c;
  --border: #e2e8f0;
  --input: #e2e8f0;
  --ring: #1d4ed8;
  --radius: 0.5rem;

  --success: #15803d;
  --success-soft: #dcfce7;
  --warning: #b45309;
  --warning-soft: #fef3c7;
  --danger: #b91c1c;
  --danger-soft: #fee2e2;
  --neutral-soft: #e2e8f0;

  --sidebar: #0b2a5b;
  --sidebar-foreground: #ffffff;
  --sidebar-muted: #b6c4dd;
  --sidebar-primary: #1d4ed8;
  --sidebar-primary-foreground: #ffffff;
  --sidebar-accent: #14397a;
  --sidebar-accent-foreground: #ffffff;
  --sidebar-border: #14397a;
  --sidebar-ring: #1d4ed8;
}

@theme inline {
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;

  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-card-foreground: var(--card-foreground);
  --color-popover: var(--popover);
  --color-popover-foreground: var(--popover-foreground);
  --color-primary: var(--primary);
  --color-primary-foreground: var(--primary-foreground);
  --color-primary-hover: var(--primary-hover);
  --color-primary-soft: var(--primary-soft);
  --color-secondary: var(--secondary);
  --color-secondary-foreground: var(--secondary-foreground);
  --color-muted: var(--muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-accent: var(--accent);
  --color-accent-foreground: var(--accent-foreground);
  --color-destructive: var(--destructive);
  --color-border: var(--border);
  --color-input: var(--input);
  --color-ring: var(--ring);

  --color-success: var(--success);
  --color-success-soft: var(--success-soft);
  --color-warning: var(--warning);
  --color-warning-soft: var(--warning-soft);
  --color-danger: var(--danger);
  --color-danger-soft: var(--danger-soft);
  --color-neutral-soft: var(--neutral-soft);

  --color-sidebar: var(--sidebar);
  --color-sidebar-foreground: var(--sidebar-foreground);
  --color-sidebar-muted: var(--sidebar-muted);
  --color-sidebar-primary: var(--sidebar-primary);
  --color-sidebar-primary-foreground: var(--sidebar-primary-foreground);
  --color-sidebar-accent: var(--sidebar-accent);
  --color-sidebar-accent-foreground: var(--sidebar-accent-foreground);
  --color-sidebar-border: var(--sidebar-border);
  --color-sidebar-ring: var(--sidebar-ring);

  --radius-lg: var(--radius);
  --radius-md: calc(var(--radius) - 2px);
  --radius-sm: calc(var(--radius) - 4px);
}

@layer base {
  * {
    @apply border-border;
  }
  body {
    @apply bg-background text-foreground font-sans antialiased;
  }
}
```
Hapus blok `.dark { ... }` dan variabel tema gelap yang ditambahkan shadcn (tanpa mode gelap).

- [ ] **Step 5: Tambah komponen**

```bash
npx shadcn-vue@latest add button input label checkbox select card dialog sonner skeleton avatar breadcrumb separator dropdown-menu table --yes
```
Jika flag `--yes` ditolak, jalankan tanpa flag dan setujui penimpaan. Pastikan komponen berada di `resources/js/Components/ui/`.

- [ ] **Step 6: Verifikasi**

```bash
npm run typecheck && npm run lint && npm run build
```
Expected: sukses. Perbaiki error lint pada file shadcn dengan menambahkan `resources/js/Components/ui/**` ke `ignores` ESLint (kode vendor hasil generate), bukan dengan mengubah aturan global.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: tambah token tema dan komponen dasar shadcn-vue" -m "Token warna sesuai panduan UI (canvas, sidebar biru tua, primary, status), font Inter, Tailwind v4, dan komponen ui dasar."
```

### Task 11: Komponen `shared/`

**Files:**
- Create: `resources/js/Components/shared/PasswordInput.vue`, `StatusBadge.vue`, `EmptyState.vue`, `PageHeader.vue`, `ConfirmDialog.vue`, `DataTable.vue`; test `StatusBadge.test.ts`, `DataTable.test.ts` (di folder yang sama)

**Interfaces:**
- Consumes: `userStatus`, `BadgeVariant` (`@/lib/status`); komponen shadcn Task 10.
- Produces:
  - `<StatusBadge :status="string" />`
  - `<EmptyState title description? >` dengan slot `action`
  - `<PageHeader title description?>` dengan slot `actions`
  - `<PasswordInput v-model>` (tombol tampil/sembunyi)
  - `<ConfirmDialog v-model:open title description confirm-label destructive? @confirm>`
  - `<DataTable :columns="{key,label,class?}[]" :rows="T[]" :loading? >` dengan slot `cell-{key}` (props `{ row }`) dan slot `empty`.

- [ ] **Step 1: Test `StatusBadge` (gagal dulu)**

Create `resources/js/Components/shared/StatusBadge.test.ts`:
```ts
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusBadge from './StatusBadge.vue'

describe('StatusBadge', () => {
  it('menampilkan label teks (tidak hanya warna)', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'menunggu' } })
    expect(wrapper.text()).toContain('Menunggu aktivasi')
    expect(wrapper.classes().join(' ')).toContain('bg-warning-soft')
  })

  it('status tak dikenal tetap tampil netral', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'aneh' } })
    expect(wrapper.text()).toContain('aneh')
    expect(wrapper.classes().join(' ')).toContain('bg-neutral-soft')
  })
})
```
Run: `npm test -- StatusBadge` → FAIL.

- [ ] **Step 2: Implementasi `StatusBadge`**

Create `resources/js/Components/shared/StatusBadge.vue`:
```vue
<script setup lang="ts">
import { computed } from 'vue'
import { userStatus, type BadgeVariant } from '@/lib/status'

const props = defineProps<{ status: string }>()

const classes: Record<BadgeVariant, string> = {
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-warning',
  danger: 'bg-danger-soft text-danger',
  neutral: 'bg-neutral-soft text-muted-foreground',
  info: 'bg-primary-soft text-primary',
}

const meta = computed(() => userStatus(props.status))
</script>

<template>
  <span
    class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium"
    :class="classes[meta.variant]"
  >
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
    {{ meta.label }}
  </span>
</template>
```
Run: `npm test -- StatusBadge` → PASS.

- [ ] **Step 3: Test `DataTable` (gagal dulu)**

Create `resources/js/Components/shared/DataTable.test.ts`:
```ts
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from './DataTable.vue'

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'role', label: 'Role' },
]

describe('DataTable', () => {
  it('merender header dan baris', () => {
    const wrapper = mount(DataTable, {
      props: { columns, rows: [{ name: 'Budi', role: 'Operator' }] },
    })
    expect(wrapper.text()).toContain('Nama')
    expect(wrapper.text()).toContain('Budi')
  })

  it('memakai slot sel kustom', () => {
    const wrapper = mount(DataTable, {
      props: { columns, rows: [{ name: 'Budi', role: 'Operator' }] },
      slots: { 'cell-role': '<template #cell-role="{ row }">[{{ row.role }}]</template>' },
    })
    expect(wrapper.text()).toContain('[Operator]')
  })

  it('menampilkan keadaan kosong saat tidak ada baris', () => {
    const wrapper = mount(DataTable, { props: { columns, rows: [] } })
    expect(wrapper.text()).toContain('Tidak ada data')
  })

  it('menampilkan skeleton saat loading', () => {
    const wrapper = mount(DataTable, { props: { columns, rows: [], loading: true } })
    expect(wrapper.findAll('[data-skeleton-row]').length).toBeGreaterThan(0)
    expect(wrapper.text()).not.toContain('Tidak ada data')
  })
})
```
Run: `npm test -- DataTable` → FAIL.

- [ ] **Step 4: Implementasi `EmptyState`, `DataTable`**

Create `resources/js/Components/shared/EmptyState.vue`:
```vue
<script setup lang="ts">
import { Inbox } from 'lucide-vue-next'

defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
    <Inbox class="size-10 text-muted-foreground" aria-hidden="true" />
    <p class="text-sm font-medium">{{ title }}</p>
    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
    <div class="mt-2"><slot name="action" /></div>
  </div>
</template>
```
Create `resources/js/Components/shared/DataTable.vue`:
```vue
<script setup lang="ts" generic="T extends Record<string, unknown>">
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Skeleton } from '@/Components/ui/skeleton'
import EmptyState from './EmptyState.vue'

interface Column {
  key: string
  label: string
  class?: string
}

defineProps<{ columns: Column[]; rows: T[]; loading?: boolean }>()
</script>

<template>
  <div class="overflow-x-auto rounded-lg border bg-card">
    <Table>
      <TableHeader class="bg-secondary">
        <TableRow>
          <TableHead
            v-for="column in columns"
            :key="column.key"
            :class="['text-xs font-medium text-muted-foreground', column.class]"
          >
            {{ column.label }}
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <template v-if="loading">
          <TableRow v-for="n in 5" :key="n" data-skeleton-row>
            <TableCell v-for="column in columns" :key="column.key">
              <Skeleton class="h-4 w-full" />
            </TableCell>
          </TableRow>
        </template>
        <template v-else-if="rows.length > 0">
          <TableRow v-for="(row, index) in rows" :key="index" class="h-12 hover:bg-primary-soft">
            <TableCell v-for="column in columns" :key="column.key" :class="column.class">
              <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
            </TableCell>
          </TableRow>
        </template>
        <TableRow v-else>
          <TableCell :colspan="columns.length">
            <slot name="empty"><EmptyState title="Tidak ada data" /></slot>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>
```
Run: `npm test` → PASS (termasuk slot kustom dan loading).

- [ ] **Step 5: `PageHeader`, `PasswordInput`, `ConfirmDialog`**

Create `resources/js/Components/shared/PageHeader.vue`:
```vue
<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
      <h1 class="text-2xl font-semibold leading-8">{{ title }}</h1>
      <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
    </div>
    <div class="flex items-center gap-2"><slot name="actions" /></div>
  </div>
</template>
```
Create `resources/js/Components/shared/PasswordInput.vue`:
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import { Input } from '@/Components/ui/input'

const model = defineModel<string>({ default: '' })
const visible = ref(false)
</script>

<template>
  <div class="relative">
    <Input v-model="model" :type="visible ? 'text' : 'password'" class="h-10 pr-10" v-bind="$attrs" />
    <button
      type="button"
      class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted-foreground hover:text-foreground"
      :aria-label="visible ? 'Sembunyikan password' : 'Tampilkan password'"
      @click="visible = !visible"
    >
      <EyeOff v-if="visible" class="size-4" />
      <Eye v-else class="size-4" />
    </button>
  </div>
</template>
```
Create `resources/js/Components/shared/ConfirmDialog.vue`:
```vue
<script setup lang="ts">
import { Button } from '@/Components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'

const open = defineModel<boolean>('open', { required: true })

withDefaults(
  defineProps<{
    title: string
    description: string
    confirmLabel?: string
    destructive?: boolean
  }>(),
  { confirmLabel: 'Ya, lanjutkan', destructive: false },
)

const emit = defineEmits<{ confirm: [] }>()

function confirm() {
  emit('confirm')
  open.value = false
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="rounded-xl">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="open = false">Batal</Button>
        <Button :variant="destructive ? 'destructive' : 'default'" @click="confirm">
          {{ confirmLabel }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
```

- [ ] **Step 6: Verifikasi dan commit**

```bash
npm test && npm run typecheck && npm run lint && npm run build
git add -A
git commit -m "feat: tambah komponen shared StatusBadge, DataTable, dan lainnya" -m "StatusBadge, EmptyState, PageHeader, PasswordInput, ConfirmDialog, DataTable (loading, kosong, slot sel) dengan test untuk StatusBadge dan DataTable."
```

### Task 12: Layout (`AuthLayout`, `AppLayout`) dan penyaring navigasi

**Files:**
- Create: `resources/js/config/navigation.ts`, `navigation.test.ts`, `resources/js/Layouts/AuthLayout.vue`, `Layouts/AppLayout.vue`, `Layouts/partials/Sidebar.vue`, `Layouts/partials/Topbar.vue`
- Modify: `resources/js/Pages/Auth/Login.vue`, `resources/js/Pages/Dashboard.vue`

**Interfaces:**
- Consumes: `usePermission`, `useFlash`, `PERMISSIONS`, `initials`, komponen shadcn dan shared.
- Produces:
  - `visibleNavigation(items: NavItem[], can: (p: Permission) => boolean): { group: string; items: NavItem[] }[]`
  - `<AuthLayout>` (slot default = form) dan `<AppLayout>` (slot default = konten; slot `breadcrumb`).

- [ ] **Step 1: Test penyaring navigasi (gagal dulu)**

Create `resources/js/config/navigation.test.ts`:
```ts
import { describe, expect, it } from 'vitest'
import { Home, Users } from 'lucide-vue-next'
import { PERMISSIONS } from '@/lib/permissions'
import { visibleNavigation, type NavItem } from './navigation'

const items: NavItem[] = [
  { group: 'Utama', label: 'Beranda', href: '/dashboard', icon: Home },
  { group: 'Manajemen Akses', label: 'Pengguna', href: '/pengguna', icon: Users, permission: PERMISSIONS.UserLihat },
]

describe('visibleNavigation', () => {
  it('menyembunyikan item tanpa izin dan grup yang kosong', () => {
    const result = visibleNavigation(items, () => false)
    expect(result).toHaveLength(1)
    expect(result[0].group).toBe('Utama')
  })

  it('menampilkan item berizin', () => {
    const result = visibleNavigation(items, (p) => p === PERMISSIONS.UserLihat)
    expect(result.map((g) => g.group)).toEqual(['Utama', 'Manajemen Akses'])
  })
})
```
Run: `npm test -- navigation` → FAIL.

- [ ] **Step 2: Implementasi `navigation.ts`**

Create `resources/js/config/navigation.ts`:
```ts
import type { Component } from 'vue'
import { Home, School, ShieldCheck, Users } from 'lucide-vue-next'
import { PERMISSIONS, type Permission } from '@/lib/permissions'

export interface NavItem {
  group: string
  label: string
  href: string
  icon: Component
  permission?: Permission
}

export const navigation: NavItem[] = [
  { group: 'Utama', label: 'Beranda', href: '/dashboard', icon: Home },
  { group: 'Manajemen Akses', label: 'Pengguna', href: '/pengguna', icon: Users, permission: PERMISSIONS.UserLihat },
  { group: 'Manajemen Akses', label: 'Role & Permission', href: '/role', icon: ShieldCheck, permission: PERMISSIONS.RoleLihat },
  { group: 'Master Data', label: 'Sekolah', href: '/sekolah', icon: School, permission: PERMISSIONS.SekolahLihat },
]

export function visibleNavigation(
  items: NavItem[],
  can: (permission: Permission) => boolean,
): { group: string; items: NavItem[] }[] {
  const groups = new Map<string, NavItem[]>()
  for (const item of items) {
    if (item.permission && !can(item.permission)) continue
    groups.set(item.group, [...(groups.get(item.group) ?? []), item])
  }
  return [...groups].map(([group, groupItems]) => ({ group, items: groupItems }))
}
```
Run: `npm test -- navigation` → PASS. (Menu statis ini diganti menu dari server di Fase 1.)

- [ ] **Step 3: `AuthLayout`**

Create `resources/js/Layouts/AuthLayout.vue`:
```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

defineProps<{ title?: string }>()
</script>

<template>
  <Head :title="title" />
  <div class="flex min-h-screen flex-col bg-card lg:flex-row">
    <aside
      class="flex flex-col justify-between bg-sidebar p-6 text-sidebar-foreground lg:w-[45%] lg:p-12"
    >
      <div class="text-xl font-semibold tracking-tight">SIJEBOL</div>
      <div class="hidden lg:block">
        <h1 class="text-3xl font-semibold leading-tight">
          Sistem Jemput Bola<br />Perekaman KTP-El Pelajar
        </h1>
        <p class="mt-3 text-sidebar-muted">Pendaftaran digital yang lebih mudah untuk sekolah dan pelajar.</p>
      </div>
      <p class="hidden text-sm text-sidebar-muted lg:block">Kecamatan Sungai Beduk</p>
    </aside>
    <main class="flex flex-1 items-center justify-center px-4 py-10">
      <div class="w-full max-w-[400px]"><slot /></div>
    </main>
  </div>
</template>
```

- [ ] **Step 4: `Sidebar`, `Topbar`, `AppLayout`**

Create `resources/js/Layouts/partials/Sidebar.vue`:
```vue
<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { usePermission } from '@/composables/usePermission'
import { navigation, visibleNavigation } from '@/config/navigation'

defineProps<{ collapsed: boolean }>()

const { can } = usePermission()
const groups = computed(() => visibleNavigation(navigation, can))
const page = usePage()

function isActive(href: string) {
  return page.url === href || page.url.startsWith(`${href}/`)
}
</script>

<template>
  <nav class="flex h-full flex-col bg-sidebar text-sidebar-foreground" aria-label="Menu utama">
    <div class="flex h-16 items-center px-4 text-lg font-semibold">
      <span v-if="!collapsed">SIJEBOL</span>
      <span v-else class="mx-auto">S</span>
    </div>
    <div class="flex-1 space-y-5 overflow-y-auto px-3 py-2">
      <div v-for="group in groups" :key="group.group">
        <p
          v-if="!collapsed"
          class="px-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-sidebar-muted"
        >
          {{ group.group }}
        </p>
        <Link
          v-for="item in group.items"
          :key="item.href"
          :href="item.href"
          :title="collapsed ? item.label : undefined"
          class="flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-sidebar-accent"
          :class="isActive(item.href) ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'text-sidebar-foreground'"
        >
          <component :is="item.icon" class="size-5 shrink-0" aria-hidden="true" />
          <span v-if="!collapsed">{{ item.label }}</span>
        </Link>
      </div>
    </div>
  </nav>
</template>
```
Create `resources/js/Layouts/partials/Topbar.vue`:
```vue
<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Menu, PanelLeft } from 'lucide-vue-next'
import { Avatar, AvatarFallback } from '@/Components/ui/avatar'
import { Button } from '@/Components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { initials } from '@/lib/format'
import type { PageProps } from '@/types'

defineEmits<{ toggleSidebar: []; openDrawer: [] }>()

const page = usePage<PageProps>()
const user = computed(() => page.props.auth.user)
</script>

<template>
  <header class="flex h-16 items-center justify-between border-b bg-card px-4">
    <div class="flex items-center gap-2">
      <Button variant="ghost" size="icon" class="lg:hidden" aria-label="Buka menu" @click="$emit('openDrawer')">
        <Menu class="size-5" />
      </Button>
      <Button variant="ghost" size="icon" class="hidden lg:inline-flex" aria-label="Lipat sidebar" @click="$emit('toggleSidebar')">
        <PanelLeft class="size-5" />
      </Button>
      <div class="text-xs text-muted-foreground"><slot name="breadcrumb" /></div>
    </div>
    <DropdownMenu v-if="user">
      <DropdownMenuTrigger as-child>
        <Button variant="ghost" class="h-10 gap-2 px-2">
          <Avatar class="size-8">
            <AvatarFallback class="bg-primary-soft text-primary">{{ initials(user.name) }}</AvatarFallback>
          </Avatar>
          <span class="hidden text-sm font-medium sm:inline">{{ user.name }}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem as-child><Link :href="route('profile.edit')">Profil</Link></DropdownMenuItem>
        <DropdownMenuItem as-child>
          <Link :href="route('logout')" method="post" as="button" class="w-full">Keluar</Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </header>
</template>
```
Create `resources/js/Layouts/AppLayout.vue`:
```vue
<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Toaster } from '@/Components/ui/sonner'
import { useFlash } from '@/composables/useFlash'
import Sidebar from './partials/Sidebar.vue'
import Topbar from './partials/Topbar.vue'

defineProps<{ title?: string }>()

const collapsed = ref(false)
const drawerOpen = ref(false)

const { flash } = useFlash()
watch(
  flash,
  (value) => {
    if (value.success) toast.success(value.success)
    if (value.error) toast.error(value.error)
  },
  { immediate: true },
)
</script>

<template>
  <Head :title="title" />
  <div class="flex min-h-screen bg-background">
    <aside class="hidden shrink-0 transition-[width] lg:block" :class="collapsed ? 'w-[72px]' : 'w-[264px]'">
      <div class="sticky top-0 h-screen"><Sidebar :collapsed="collapsed" /></div>
    </aside>

    <div v-if="drawerOpen" class="fixed inset-0 z-40 lg:hidden">
      <div class="absolute inset-0 bg-black/40" @click="drawerOpen = false" />
      <div class="absolute inset-y-0 left-0 w-[264px]"><Sidebar :collapsed="false" /></div>
    </div>

    <div class="flex min-w-0 flex-1 flex-col">
      <Topbar @toggle-sidebar="collapsed = !collapsed" @open-drawer="drawerOpen = true">
        <template #breadcrumb><slot name="breadcrumb" /></template>
      </Topbar>
      <main class="mx-auto w-full max-w-[1280px] flex-1 p-4 sm:p-6"><slot /></main>
    </div>
    <Toaster />
  </div>
</template>
```
Catatan: jika komponen `sonner` dari shadcn-vue mengekspor `Sonner` bukan `Toaster`, sesuaikan impor sesuai file di `Components/ui/sonner`.

- [ ] **Step 5: Pindahkan Login dan Dashboard ke layout baru**

Di `resources/js/Pages/Auth/Login.vue`: ganti `import GuestLayout ...` menjadi `import AuthLayout from '@/Layouts/AuthLayout.vue'`, dan ganti tag `<GuestLayout>` menjadi `<AuthLayout title="Masuk">` (beserta penutupnya); hapus `<Head ...>` ganda jika ada. Konten form dibiarkan; didesain ulang di Fase 1.

Ganti isi `resources/js/Pages/Dashboard.vue`:
```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/shared/EmptyState.vue'
import PageHeader from '@/Components/shared/PageHeader.vue'
</script>

<template>
  <AppLayout title="Beranda">
    <PageHeader title="Beranda" description="Ringkasan akan tampil di sini." />
    <div class="rounded-lg border bg-card">
      <EmptyState title="Belum ada data" description="Blok beranda dibangun bersama modul terkait." />
    </div>
  </AppLayout>
</template>
```

- [ ] **Step 6: Verifikasi otomatis**

```bash
npm test && npm run typecheck && npm run lint && npm run build
composer test && composer analyse && composer lint
```
Expected: semuanya sukses.

- [ ] **Step 7: Verifikasi visual manual**

```bash
php artisan serve
```
Di terminal lain `npm run dev`. Buat satu pengguna uji: `php artisan tinker --execute="App\Models\User::factory()->create(['email'=>'uji@sijebol.test','password'=>bcrypt('password')]);"`. Buka `/login`: harus split screen (panel kiri biru tua `#0B2A5B`, form putih di kanan). Login, harus mendarat di `/dashboard` dengan sidebar biru tua 264 px, topbar putih 64 px, latar `#F4F6F9`, menu hanya "Beranda" (pengguna uji belum punya permission lain). Ciutkan sidebar dengan tombol di topbar; persempit jendela <1024 px, tombol hamburger harus membuka drawer. Bandingkan dengan panduan UI bagian 4 dan 5.1.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: tambah AuthLayout, AppLayout, dan penyaring navigasi" -m "Layout split screen untuk auth dan layout aplikasi dengan sidebar biru tua, topbar, drawer mobile, toast flash. Menu disaring berdasarkan permission dengan test. Login dan Dashboard dipindah ke layout baru."
```

### Gerbang FE dan penutupan Fase 0

- [ ] `npm test`, `npm run typecheck`, `npm run lint`, `npm run build` lulus.
- [ ] `composer test`, `composer analyse`, `composer lint` lulus.
- [ ] Review: `ponytail:ponytail-review` pada seluruh perubahan Fase 0 dan `impeccable` pada perubahan FE (token, komponen, layout); perbaiki temuan valid.
- [ ] `superpowers:verification-before-completion`: jalankan ulang semua perintah di atas dan lampirkan keluarannya sebelum menyatakan Fase 0 selesai.
- [ ] Perbarui `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`: centang Fase 0 selesai dan catat penyimpangan (penundaan `BaseRepository`/`RepositoryServiceProvider`/`ScopedBySchool` ke Fase 1; penamaan folder `Components` mengikuti Breeze).

---

## Self-Review

**Cakupan sprint plan Fase 0:** BE 1 (git/commit awal) → Task 1; BE 2 (skeleton, `.env`, queue, disk privat `local` bawaan) → Task 1; BE 3 (Breeze) → Task 2; BE 4 (package) → Task 3; BE 5 (Pest/Larastan/Pint/skrip) → Task 1–2 (Pest via Breeze) dan Task 4; BE 6–7 (struktur domain, base class, enum) → enum di Task 5, sisanya **ditunda** dengan alasan YAGNI tercatat di Global Constraints; BE 8 (hapus registrasi) → Task 2; BE 9 (smoke test) → Task 1–2. FE 1–2 (token, shadcn) → Task 10; FE 3 (lib) → Task 8; FE 4 (composable) → Task 9; FE 5–6 (komponen) → Task 10–11; FE 7 (layout) → Task 12; FE 8 (tooling) → Task 7. Penyesuaian MySQL (Task 0) dan lokalisasi (Task 1) ditambahkan dari keputusan terbaru.

**Placeholder scan:** langkah dengan kode memuat kode; langkah yang bergantung keluaran generator (flag Breeze/shadcn, nama ekspor `sonner`, isi file hasil generate) memuat perintah verifikasi eksplisit dan aturan keputusan.

**Konsistensi tipe:** `Permission` (PHP enum, `values()`) ↔ `PERMISSIONS`/`Permission` (TS) dijaga test sinkron; `usePermission().can` menerima `Permission` yang sama dengan `NavItem.permission`; `userStatus` mengembalikan `BadgeVariant` yang dipakai `StatusBadge`; `PageProps` memuat `auth.permissions` dan `flash` yang dibagikan Task 6.
