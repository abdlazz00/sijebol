# Fase 1 BE — Akses dan Autentikasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Backend RBAC dinamis, aktivasi akun, login yang aman, serta CRUD Sekolah/User/Role dengan scope sekolah, menu server-side, dan audit log, siap dipakai Track FE Fase 1.

**Architecture:** Kode di `app/Domains/{Access,Schools}`: `Controller → FormRequest → Service → Repository (interface + Eloquent) → Model`, `Policy` hanya bila ada aturan per-model (User). Aturan bisnis melempar `AccessRuleException` yang dipetakan satu handler ke redirect dengan `flash.error`. Izin disimpan di spatie; hak "boleh memberi role/izin" dihitung di satu kelas `AccessGuard`.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL, spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-query-builder, Inertia, Pest.

**Spec:** `docs/superpowers/specs/2026-10-07-fase-1-backend-design.md` (induk: `2026-10-07-sijebol-v1-design.md`).

## Global Constraints

- Database dev `sijebol`, test `sijebol_test` (MySQL, Laragon). Zona `Asia/Jakarta`, locale `id`; pesan error dan flash berbahasa Indonesia.
- Token aktivasi: acak 64 karakter, **hanya hash SHA-256 disimpan**, berlaku 3×24 jam, sekali pakai.
- Password: minimal 8 karakter, huruf dan angka (`Password::defaults()` diatur sekali di `AppServiceProvider`).
- Satu user satu role; role `super-admin` dilindungi (tak dapat diubah/dihapus; super-admin aktif terakhir tak dapat dinonaktifkan atau diganti rolenya).
- Data sekolah lain → **404**; akses ditolak → **403**.
- Login gagal selalu pesan umum `trans('auth.failed')` (tidak membedakan alasan).
- Penyimpangan dari spec (diputuskan di rencana ini): tidak ada `SchoolPolicy` dan `RolePolicy` (tidak ada aturan per-model; middleware `can:` + service cukup); `ActivationService` memakai model langsung (tanpa repository untuk tabel pendukung); command super-admin ada di `app/Console/Commands` agar ditemukan otomatis.
- Halaman Inertia belum ada: test memakai `inertia.testing.ensure_pages_exist=false` (diatur di `tests/Pest.php`).
- Kontrak query daftar mengikuti spatie/query-builder: filter dikirim sebagai `filter[search]=…`, `filter[role]=…`, `filter[school_id]=…`, `filter[status]=…`, `filter[level]=…`; `useDataTable` (FE) menyesuaikan di Track FE.
- Commit: pesan detail, **tanpa `Co-Authored-By`/atribusi AI** (`CLAUDE.md`). Eksekutor meminta persetujuan sekali di awal bahwa langkah commit di rencana ini boleh dijalankan.
- Verifikasi tiap task: `composer test`, `composer analyse`, `composer lint` hijau. Jalankan `./vendor/bin/pint` sebelum `composer lint`.
- Review akhir: `ponytail:ponytail-review`, lalu `superpowers:verification-before-completion`.

## Review Focus

Kondisi yang tidak disebut spec tetapi mudah menggigit; semuanya punya test di task pemiliknya:

1. User dinonaktifkan **saat masih punya remember-cookie atau sesi terbuka**: harus ter-logout, bukan hidup lagi (Task 6).
2. Link aktivasi untuk user yang dinonaktifkan sebelum aktivasi: tidak berlaku (Task 4).
3. Email dengan huruf besar atau spasi: dinormalisasi sebelum cek unik, jadi tidak lolos sebagai duplikat (Task 11).
4. Role berubah menjadi `is_school_scoped` padahal user di dalamnya tanpa sekolah: ditolak selama role dipakai (Task 9).
5. Operator membuka detail/ubah user sekolah lain lewat URL: 404, bukan 403 atau data bocor (Task 12).
6. Aktivasi diklik ganda: hanya satu yang berhasil, kedua menampilkan state tidak berlaku (Task 4, 5).
7. Super-admin terakhir menonaktifkan atau menurunkan dirinya: ditolak (Task 11).

## File Structure

```
app/Console/Commands/CreateSuperAdmin.php
app/Domains/Access/
  Enums/{Permission(ada),UserStatus}.php
  Exceptions/{AccessRuleException,InvalidActivationException}.php
  Http/Controllers/{ActivationController,RoleController,UserController}.php
  Http/Requests/{ActivateRequest,RoleRequest,UserRequest}.php
  Listeners/LogAuthEvents.php
  Models/{Role,AccountActivation}.php
  Repositories/Contracts/{UserRepositoryInterface,RoleRepositoryInterface}.php
  Repositories/{EloquentUserRepository,EloquentRoleRepository}.php
  Services/{AccessGuard,ActivationService,MenuService,PermissionCatalog,RoleService,UserService}.php
app/Domains/Schools/
  Http/Controllers/SchoolController.php, Http/Requests/SchoolRequest.php
  Models/School.php
  Repositories/Contracts/SchoolRepositoryInterface.php, Repositories/EloquentSchoolRepository.php
  Services/SchoolService.php
app/Http/Middleware/EnsureAccountIsActive.php
app/Models/Concerns/ScopedBySchool.php
app/Providers/RepositoryServiceProvider.php
database/migrations/2026_10_08_00000{1..4}_*.php
database/factories/{SchoolFactory,UserFactory}.php
database/seeders/{AccessSeeder,DatabaseSeeder}.php
routes/access.php
tests/Feature/Access/*, tests/Feature/Schools/*
```

---

### Task 1: Migrasi, model dasar, factory, helper test

**Files:**
- Create: `database/migrations/2026_10_08_000001_create_schools_table.php`, `…000002_add_access_columns_to_users_table.php`, `…000003_add_scope_columns_to_roles_table.php`, `…000004_create_account_activations_table.php`
- Create: `app/Domains/Schools/Models/School.php`, `app/Domains/Access/Models/Role.php`, `app/Domains/Access/Models/AccountActivation.php`, `database/factories/SchoolFactory.php`
- Modify: `config/permission.php`, `database/factories/UserFactory.php`, `tests/Pest.php`
- Test: `tests/Feature/Access/SchemaTest.php`

**Interfaces:**
- Produces: `School` (fillable `name, npsn, level, address`; `users(): HasMany`), `Role` (extends spatie Role; `const SUPER_ADMIN = 'super-admin'`; cast `is_school_scoped` boolean; fillable `description`), `AccountActivation` (`user(): BelongsTo`, `scopePending($q)`), `UserFactory::pending()`/`inactive()`, `SchoolFactory`, helper test `makeUserWith(array $permissions = [], ?School $school = null, bool $scoped = false, string $roleName = 'uji'): User`.

- [ ] **Step 1: Test skema (gagal dulu)**

Create `tests/Feature/Access/SchemaTest.php`:
```php
<?php

use App\Domains\Access\Models\Role;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

test('skema akses tersedia', function () {
    expect(Schema::hasColumns('schools', ['name', 'npsn', 'level', 'address']))->toBeTrue()
        ->and(Schema::hasColumns('users', ['phone', 'school_id', 'is_active', 'activated_at', 'last_login_at', 'created_by']))->toBeTrue()
        ->and(Schema::hasColumns('roles', ['description', 'is_school_scoped']))->toBeTrue()
        ->and(Schema::hasColumns('account_activations', ['user_id', 'token_hash', 'expires_at', 'used_at']))->toBeTrue();
});

test('spatie memakai model Role kita dan kolom scope ter-cast', function () {
    expect(app(PermissionRegistrar::class)->getRoleClass())->toBe(Role::class);

    $role = Role::create(['name' => 'x', 'is_school_scoped' => true]);

    expect($role->fresh()->is_school_scoped)->toBeTrue();
});
```
Run: `php artisan test --filter=SchemaTest` → Expected: FAIL.

- [ ] **Step 2: Migrasi**

`database/migrations/2026_10_08_000001_create_schools_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('npsn', 20)->unique();
            $table->string('level', 10);
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
```
`…000002_add_access_columns_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->foreignId('school_id')->nullable()->after('phone')->constrained('schools')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('school_id');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('school_id');
            $table->dropColumn(['phone', 'is_active', 'activated_at', 'last_login_at']);
        });
    }
};
```
`…000003_add_scope_columns_to_roles_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description')->nullable()->after('guard_name');
            $table->boolean('is_school_scoped')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_school_scoped']);
        });
    }
};
```
`…000004_create_account_activations_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_activations');
    }
};
```

- [ ] **Step 3: Model**

`app/Domains/Schools/Models/School.php`:
```php
<?php

namespace App\Domains\Schools\Models;

use App\Models\User;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(SchoolFactory::class)]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $fillable = ['name', 'npsn', 'level', 'address'];

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```
`app/Domains/Access/Models/Role.php`:
```php
<?php

namespace App\Domains\Access\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public const SUPER_ADMIN = 'super-admin';

    protected function casts(): array
    {
        return ['is_school_scoped' => 'boolean'];
    }

    public function isProtected(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }
}
```
`app/Domains/Access/Models/AccountActivation.php`:
```php
<?php

namespace App\Domains\Access\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountActivation extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<AccountActivation> $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
```
Di `config/permission.php` ubah `'role' => Role::class` menjadi model kita: tambahkan `use App\Domains\Access\Models\Role;` di atas (ganti `use Spatie\Permission\Models\Role;` bila ada) dan pastikan `'models' => ['permission' => Permission::class, 'role' => Role::class]`.

- [ ] **Step 4: Factory dan helper test**

`database/factories/SchoolFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Domains\Schools\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<School> */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'name' => 'SMAN '.fake()->unique()->numberBetween(1, 999).' Sungai Beduk',
            'npsn' => (string) fake()->unique()->numerify('########'),
            'level' => fake()->randomElement(['SMA', 'SMK', 'MA']),
            'address' => fake()->streetAddress(),
        ];
    }
}
```
Ganti isi `database/factories/UserFactory.php` (hilangkan `email_verified_at`/`unverified`):
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'activated_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function pending(): static
    {
        return $this->state(['password' => null, 'activated_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```
Jalankan `grep -rn "unverified\|email_verified_at" tests app resources/js | grep -v node_modules` dan hapus pemakaian yang tersisa (kolom `email_verified_at` tetap ada di tabel tetapi tidak dipakai).

Di `tests/Pest.php`: ganti blok `pest()->extend(...)` menjadi
```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => config(['inertia.testing.ensure_pages_exist' => false]))
    ->in('Feature');
```
dan ganti fungsi `something()` dengan helper berikut (tambahkan `use` di atas file: `App\Domains\Access\Models\Role`, `App\Domains\Schools\Models\School`, `App\Models\User`, `Spatie\Permission\Models\Permission as PermissionModel`, `Spatie\Permission\PermissionRegistrar`):
```php
/**
 * Buat user aktif dengan role `$roleName` yang memegang `$permissions`.
 * Role yang sama dipakai ulang antar-panggilan, izinnya bertambah.
 */
function makeUserWith(array $permissions = [], ?School $school = null, bool $scoped = false, string $roleName = 'uji'): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $name) {
        PermissionModel::findOrCreate($name, 'web');
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['is_school_scoped' => $scoped]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create(['school_id' => $school?->id]);
    $user->assignRole($role);

    return $user;
}
```

- [ ] **Step 5: Jalankan**

```bash
php artisan migrate:fresh
composer test
```
Expected: `SchemaTest` PASS; seluruh suite lama masih hijau (jika `AuthenticationTest` dkk gagal karena factory, periksa langkah 4).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: tambah skema akses, model dasar, dan helper test" -m "Migrasi schools, kolom akses pada users dan roles, account_activations. Model School, Role (spatie), AccountActivation, factory, dan helper makeUserWith untuk test."
```

---

### Task 2: Seeder role/izin, Gate super-admin, aturan password

**Files:**
- Create: `database/seeders/AccessSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Access/AccessSeederTest.php`

**Interfaces:**
- Consumes: `Permission::values()`, `Role` (Task 1).
- Produces: `AccessSeeder` idempotent (32 permission; role `super-admin`, `admin-kecamatan`, `operator-sekolah`); `Gate::before` meloloskan role `super-admin`; `Password::defaults()` = min 8, huruf, angka.

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/AccessSeederTest.php`:
```php
<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission as PermissionModel;

test('seeder membuat seluruh permission dan tiga role, dan aman diulang', function () {
    $this->seed(AccessSeeder::class);
    $this->seed(AccessSeeder::class);

    expect(PermissionModel::count())->toBe(count(Permission::values()))
        ->and(Role::count())->toBe(3)
        ->and(Role::findByName('super-admin')->permissions)->toHaveCount(count(Permission::values()))
        ->and(Role::findByName('operator-sekolah')->is_school_scoped)->toBeTrue()
        ->and(Role::findByName('admin-kecamatan')->is_school_scoped)->toBeFalse()
        ->and(Role::findByName('admin-kecamatan')->hasPermissionTo('user.buat'))->toBeTrue()
        ->and(Role::findByName('operator-sekolah')->hasPermissionTo('user.buat'))->toBeFalse();
});

test('super-admin lolos gate apa pun, user biasa tidak', function () {
    $this->seed(AccessSeeder::class);
    $super = User::factory()->create()->assignRole('super-admin');
    $plain = User::factory()->create();

    expect(Gate::forUser($super)->allows('apa-saja'))->toBeTrue()
        ->and(Gate::forUser($plain)->allows('user.lihat'))->toBeFalse();
});

test('aturan password: minimal 8, huruf, dan angka', function () {
    $rule = fn (string $value) => validator(['p' => $value], ['p' => Password::defaults()])->passes();

    expect($rule('abcdefgh'))->toBeFalse()
        ->and($rule('12345678'))->toBeFalse()
        ->and($rule('abc1234'))->toBeFalse()
        ->and($rule('abcd1234'))->toBeTrue();
});
```
Run: `php artisan test --filter=AccessSeederTest` → Expected: FAIL.

- [ ] **Step 2: Seeder**

`database/seeders/AccessSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::values() as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        $this->role(Role::SUPER_ADMIN, 'Akses penuh sistem', false, Permission::values());

        $this->role('admin-kecamatan', 'Admin kecamatan', false, [
            ...$this->modules(['user', 'role', 'sekolah', 'verifikasi', 'jadwal', 'rekaman']),
            'laporan.lihat', 'log.lihat', 'siswa.lihat', 'pendaftaran.lihat', 'dokumen.lihat',
        ]);

        $this->role('operator-sekolah', 'Operator sekolah', true, [
            ...$this->modules(['siswa', 'dokumen', 'pendaftaran']),
            'jadwal.lihat',
        ]);
    }

    /** @param list<string> $modules @return list<string> */
    private function modules(array $modules): array
    {
        return array_values(array_filter(
            Permission::values(),
            fn (string $name) => in_array(explode('.', $name)[0], $modules, true),
        ));
    }

    /** @param list<string> $permissions */
    private function role(string $name, string $description, bool $scoped, array $permissions): void
    {
        $role = Role::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $description, 'is_school_scoped' => $scoped],
        );
        $role->syncPermissions($permissions);
    }
}
```
`database/seeders/DatabaseSeeder.php`: ganti isi `run()` menjadi `$this->call(AccessSeeder::class);` (hapus pembuatan user contoh bawaan).

- [ ] **Step 3: Gate dan aturan password**

Di `app/Providers/AppServiceProvider.php` tambahkan di dalam `boot()`:
```php
Gate::before(fn ($user) => $user->hasRole(Role::SUPER_ADMIN) ? true : null);

Password::defaults(fn () => Password::min(8)->letters()->numbers());
```
dengan `use` untuk `App\Domains\Access\Models\Role`, `Illuminate\Support\Facades\Gate`, `Illuminate\Validation\Rules\Password`.

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=AccessSeederTest` → Expected: PASS. Lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah seeder role dan izin, gate super-admin, aturan password" -m "AccessSeeder idempoten untuk 32 permission dan role super-admin, admin-kecamatan, operator-sekolah. Gate::before meloloskan super-admin. Password::defaults minimal 8 karakter dengan huruf dan angka."
```

---

### Task 3: Model User (status, relasi) dan scope sekolah

**Files:**
- Create: `app/Domains/Access/Enums/UserStatus.php`, `app/Models/Concerns/ScopedBySchool.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Access/UserModelTest.php`

**Interfaces:**
- Consumes: `AccountActivation::pending()` (Task 1).
- Produces: `UserStatus` enum (`Aktif='aktif'`, `Menunggu='menunggu'`, `Kedaluwarsa='kedaluwarsa'`, `Nonaktif='nonaktif'`); `User::$status` (UserStatus), `User::scopeWhereStatus(UserStatus)`, `User::school()`, `User::activations()`; trait `ScopedBySchool` (global scope `school`, memakai `Auth::hasUser()` agar tidak rekursif).

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/UserModelTest.php`:
```php
<?php

use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Models\AccountActivation;
use App\Domains\Schools\Models\School;
use App\Models\User;

function activationFor(User $user, string $expires = '+1 day', bool $used = false): AccountActivation
{
    return AccountActivation::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', fake()->unique()->uuid()),
        'expires_at' => now()->modify($expires),
        'used_at' => $used ? now() : null,
    ]);
}

test('status dihitung dengan prioritas nonaktif, aktif, menunggu, kedaluwarsa', function () {
    $aktif = User::factory()->create();
    $nonaktif = User::factory()->inactive()->create();
    $menunggu = User::factory()->pending()->create();
    activationFor($menunggu);
    $kedaluwarsa = User::factory()->pending()->create();
    activationFor($kedaluwarsa, '-1 minute');
    $tanpaLink = User::factory()->pending()->create();

    expect($aktif->status)->toBe(UserStatus::Aktif)
        ->and($nonaktif->status)->toBe(UserStatus::Nonaktif)
        ->and($menunggu->status)->toBe(UserStatus::Menunggu)
        ->and($kedaluwarsa->status)->toBe(UserStatus::Kedaluwarsa)
        ->and($tanpaLink->status)->toBe(UserStatus::Kedaluwarsa);
});

test('scope whereStatus memfilter di SQL dan konsisten dengan accessor', function () {
    User::factory()->create();
    User::factory()->inactive()->create();
    activationFor(User::factory()->pending()->create());
    activationFor(User::factory()->pending()->create(), '-1 minute');

    foreach (UserStatus::cases() as $status) {
        $ids = User::whereStatus($status)->get();
        expect($ids)->toHaveCount(1, $status->value)
            ->and($ids->first()->status)->toBe($status);
    }
});

test('operator terikat sekolahnya, staf tanpa sekolah melihat semua', function () {
    [$a, $b] = School::factory()->count(2)->create();
    $opA = User::factory()->create(['school_id' => $a->id]);
    $opB = User::factory()->create(['school_id' => $b->id]);
    $staf = User::factory()->create();

    $this->actingAs($opA);
    expect(User::pluck('id')->all())->toEqual([$opA->id]);
    expect(User::find($opB->id))->toBeNull();

    $this->actingAs($staf);
    expect(User::count())->toBe(3);
});
```
Run: `php artisan test --filter=UserModelTest` → Expected: FAIL.

- [ ] **Step 2: Enum dan trait**

`app/Domains/Access/Enums/UserStatus.php`:
```php
<?php

namespace App\Domains\Access\Enums;

enum UserStatus: string
{
    case Aktif = 'aktif';
    case Menunggu = 'menunggu';
    case Kedaluwarsa = 'kedaluwarsa';
    case Nonaktif = 'nonaktif';
}
```
`app/Models/Concerns/ScopedBySchool.php`:
```php
<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Membatasi query ke sekolah pengguna yang sedang login (jika terikat sekolah). */
trait ScopedBySchool
{
    protected static function bootScopedBySchool(): void
    {
        static::addGlobalScope('school', function (Builder $query) {
            // hasUser() mencegah rekursi: me-resolve user login memakai model ini juga.
            $schoolId = Auth::hasUser() ? Auth::user()->school_id : null;

            if ($schoolId !== null) {
                $query->where($query->getModel()->qualifyColumn('school_id'), $schoolId);
            }
        });
    }
}
```

- [ ] **Step 3: Ubah `app/Models/User.php`**

Ganti atribut `#[Fillable([...])]` menjadi `#[Fillable(['name', 'email', 'password', 'phone', 'school_id', 'is_active', 'activated_at', 'created_by'])]`, tambahkan trait `ScopedBySchool` pada baris `use` trait, perluas `casts()` dan tambahkan relasi, scope, serta accessor:
```php
// use di atas file:
use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Models\AccountActivation;
use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// trait:
use HasFactory, HasRoles, Notifiable, ScopedBySchool;

protected function casts(): array
{
    return [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];
}

/** @return BelongsTo<School, $this> */
public function school(): BelongsTo
{
    return $this->belongsTo(School::class);
}

/** @return HasMany<AccountActivation, $this> */
public function activations(): HasMany
{
    return $this->hasMany(AccountActivation::class);
}

/** @param Builder<User> $query */
public function scopeWhereStatus(Builder $query, UserStatus $status): void
{
    $pending = fn (Builder $q) => $q->pending();

    match ($status) {
        UserStatus::Nonaktif => $query->where('is_active', false),
        UserStatus::Aktif => $query->where('is_active', true)->whereNotNull('activated_at'),
        UserStatus::Menunggu => $query->where('is_active', true)->whereNull('activated_at')->whereHas('activations', $pending),
        UserStatus::Kedaluwarsa => $query->where('is_active', true)->whereNull('activated_at')->whereDoesntHave('activations', $pending),
    };
}

/** @return Attribute<UserStatus, never> */
protected function status(): Attribute
{
    return Attribute::get(function (): UserStatus {
        if (! $this->is_active) {
            return UserStatus::Nonaktif;
        }
        if ($this->activated_at !== null) {
            return UserStatus::Aktif;
        }
        $pending = $this->attributes['has_pending_activation'] ?? $this->activations()->pending()->exists();

        return $pending ? UserStatus::Menunggu : UserStatus::Kedaluwarsa;
    });
}
```
(Hapus `'email_verified_at' => 'datetime'` dari casts bila ada.)

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=UserModelTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah status user dan scope sekolah" -m "UserStatus dihitung dari is_active, activated_at, dan aktivasi pending, lengkap dengan scope SQL yang konsisten. Trait ScopedBySchool membatasi query ke sekolah pengguna login dan aman dari rekursi."
```

---

### Task 4: ActivationService

**Files:**
- Create: `app/Domains/Access/Exceptions/InvalidActivationException.php`, `app/Domains/Access/Services/ActivationService.php`
- Test: `tests/Feature/Access/ActivationServiceTest.php`

**Interfaces:**
- Consumes: `AccountActivation`, `User`.
- Produces: `ActivationService::issue(User $user): string` (token polos; mencabut yang belum terpakai), `findValid(string $token): ?AccountActivation` (relasi `user` sudah dimuat), `complete(string $token, string $password): User` (lempar `InvalidActivationException`); `ActivationService::TTL_DAYS = 3`. Mencatat log aktivitas `aktivasi.dibuat` dan `aktivasi.selesai` (log `akses`).

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/ActivationServiceTest.php`:
```php
<?php

use App\Domains\Access\Exceptions\InvalidActivationException;
use App\Domains\Access\Models\AccountActivation;
use App\Domains\Access\Services\ActivationService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => $this->service = app(ActivationService::class));

test('issue menyimpan hash, bukan token, berlaku 3 hari', function () {
    $user = User::factory()->pending()->create();

    $token = $this->service->issue($user);

    expect($token)->toHaveLength(64);
    $row = AccountActivation::firstOrFail();
    expect($row->token_hash)->toBe(hash('sha256', $token))
        ->and($row->token_hash)->not->toBe($token)
        ->and($row->expires_at->isSameDay(now()->addDays(3)))->toBeTrue();
});

test('token baru mencabut token lama', function () {
    $user = User::factory()->pending()->create();
    $old = $this->service->issue($user);
    $new = $this->service->issue($user);

    expect($this->service->findValid($old))->toBeNull()
        ->and($this->service->findValid($new))->not->toBeNull()
        ->and(AccountActivation::count())->toBe(1);
});

test('complete mengisi password, mengaktifkan, dan menandai token terpakai', function () {
    $user = User::factory()->pending()->create();
    $token = $this->service->issue($user);

    $result = $this->service->complete($token, 'rahasia123');

    expect($result->is($user))->toBeTrue()
        ->and($user->fresh()->activated_at)->not->toBeNull()
        ->and(Hash::check('rahasia123', $user->fresh()->password))->toBeTrue()
        ->and(AccountActivation::first()->used_at)->not->toBeNull();
});

test('submit kedua dengan token yang sama gagal', function () {
    $user = User::factory()->pending()->create();
    $token = $this->service->issue($user);
    $this->service->complete($token, 'rahasia123');

    expect(fn () => $this->service->complete($token, 'lain12345'))
        ->toThrow(InvalidActivationException::class);
});

test('token kedaluwarsa, tak dikenal, atau milik user nonaktif tidak berlaku', function () {
    $user = User::factory()->pending()->create();
    $token = $this->service->issue($user);

    $this->travel(4)->days();
    expect($this->service->findValid($token))->toBeNull();
    $this->travelBack();

    expect($this->service->findValid('tidak-dikenal'))->toBeNull();

    $user->update(['is_active' => false]);
    expect($this->service->findValid($token))->toBeNull()
        ->and(fn () => $this->service->complete($token, 'rahasia123'))->toThrow(InvalidActivationException::class);
});
```
Run: `php artisan test --filter=ActivationServiceTest` → Expected: FAIL.

- [ ] **Step 2: Implementasi**

`app/Domains/Access/Exceptions/InvalidActivationException.php`:
```php
<?php

namespace App\Domains\Access\Exceptions;

use RuntimeException;

class InvalidActivationException extends RuntimeException {}
```
`app/Domains/Access/Services/ActivationService.php`:
```php
<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Exceptions\InvalidActivationException;
use App\Domains\Access\Models\AccountActivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivationService
{
    public const TTL_DAYS = 3;

    /** Buat token baru dan cabut yang belum terpakai. Mengembalikan token polos (tidak disimpan). */
    public function issue(User $user): string
    {
        $token = Str::random(64);

        DB::transaction(function () use ($user, $token) {
            $user->activations()->whereNull('used_at')->delete();
            $user->activations()->create([
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(self::TTL_DAYS),
            ]);
        });

        activity('akses')->performedOn($user)->causedBy(auth()->user())->log('aktivasi.dibuat');

        return $token;
    }

    public function findValid(string $token): ?AccountActivation
    {
        $activation = AccountActivation::query()
            ->with('user.school')
            ->where('token_hash', hash('sha256', $token))
            ->pending()
            ->first();

        return $activation?->user->is_active && $activation->user->activated_at === null ? $activation : null;
    }

    /** @throws InvalidActivationException */
    public function complete(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password) {
            $activation = AccountActivation::query()
                ->where('token_hash', hash('sha256', $token))
                ->pending()
                ->lockForUpdate()
                ->first();

            $user = $activation?->user;

            if ($user === null || ! $user->is_active || $user->activated_at !== null) {
                throw new InvalidActivationException;
            }

            $user->forceFill(['password' => $password, 'activated_at' => now()])->save();
            $activation->update(['used_at' => now()]);

            activity('akses')->performedOn($user)->causedBy($user)->log('aktivasi.selesai');

            return $user;
        });
    }
}
```

- [ ] **Step 3: Jalankan dan commit**

Run: `php artisan test --filter=ActivationServiceTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah ActivationService" -m "Token acak 64 karakter dengan hash SHA-256, berlaku 3 hari, sekali pakai, token baru mencabut yang lama. Penyelesaian dalam transaksi dengan lock; token kedaluwarsa, terpakai, atau milik user nonaktif ditolak."
```

---

### Task 5: Halaman aktivasi (HTTP)

**Files:**
- Create: `app/Domains/Access/Http/Controllers/ActivationController.php`, `app/Domains/Access/Http/Requests/ActivateRequest.php`, `routes/access.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Access/ActivationHttpTest.php`

**Interfaces:**
- Consumes: `ActivationService` (Task 4).
- Produces: rute `aktivasi.show` (`GET /aktivasi/{token}`), `aktivasi.store` (`POST /aktivasi/{token}`), keduanya `throttle:10,1`. Props `Auth/Activate`: `{valid: true, name, email, school}` atau `{valid: false}`. File `routes/access.php` dimuat dari `web.php` (task berikut menambah rute di file ini).

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/ActivationHttpTest.php`:
```php
<?php

use App\Domains\Access\Services\ActivationService;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function pendingUserWithToken(): array
{
    $user = User::factory()->pending()->create(['name' => 'Budi']);

    return [$user, app(ActivationService::class)->issue($user)];
}

test('link valid menampilkan data akun', function () {
    [$user, $token] = pendingUserWithToken();

    $this->get("/aktivasi/$token")->assertInertia(fn (Assert $p) => $p
        ->component('Auth/Activate')
        ->where('valid', true)
        ->where('name', 'Budi')
        ->where('email', $user->email));
});

test('link tidak dikenal menampilkan state tidak berlaku', function () {
    $this->get('/aktivasi/acak')->assertInertia(fn (Assert $p) => $p->where('valid', false));
});

test('aktivasi sukses mengisi password, login, dan mengarah ke dashboard', function () {
    [$user, $token] = pendingUserWithToken();

    $this->post("/aktivasi/$token", ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('success');

    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->activated_at)->not->toBeNull();
});

test('submit kedua dengan token sama menampilkan state tidak berlaku dan tidak login', function () {
    [$user, $token] = pendingUserWithToken();
    $payload = ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'];
    $this->post("/aktivasi/$token", $payload);
    auth()->logout();
    $this->flushSession();

    $this->post("/aktivasi/$token", $payload)->assertRedirect("/aktivasi/$token");
    $this->assertGuest();
    $this->get("/aktivasi/$token")->assertInertia(fn (Assert $p) => $p->where('valid', false));
});

test('password lemah atau tidak cocok ditolak dan akun tetap belum aktif', function () {
    [$user, $token] = pendingUserWithToken();

    $this->post("/aktivasi/$token", ['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])
        ->assertSessionHasErrors('password');
    $this->post("/aktivasi/$token", ['password' => 'rahasia123', 'password_confirmation' => 'beda12345'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->activated_at)->toBeNull();
});

test('rute aktivasi dibatasi 10 permintaan per menit', function () {
    foreach (range(1, 10) as $_) {
        $this->get('/aktivasi/acak')->assertOk();
    }

    $this->get('/aktivasi/acak')->assertStatus(429);
});
```
Run: `php artisan test --filter=ActivationHttpTest` → Expected: FAIL (404).

- [ ] **Step 2: Implementasi**

`app/Domains/Access/Http/Requests/ActivateRequest.php`:
```php
<?php

namespace App\Domains\Access\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ActivateRequest extends FormRequest
{
    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::defaults()]];
    }
}
```
`app/Domains/Access/Http/Controllers/ActivationController.php`:
```php
<?php

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Exceptions\InvalidActivationException;
use App\Domains\Access\Http\Requests\ActivateRequest;
use App\Domains\Access\Services\ActivationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ActivationController extends Controller
{
    public function __construct(private readonly ActivationService $activations) {}

    public function show(string $token): Response
    {
        $activation = $this->activations->findValid($token);

        return Inertia::render('Auth/Activate', $activation === null
            ? ['valid' => false]
            : [
                'valid' => true,
                'name' => $activation->user->name,
                'email' => $activation->user->email,
                'school' => $activation->user->school?->name,
            ]);
    }

    public function store(ActivateRequest $request, string $token): RedirectResponse
    {
        try {
            $user = $this->activations->complete($token, $request->validated('password'));
        } catch (InvalidActivationException) {
            return redirect()->route('aktivasi.show', $token);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun aktif. Selamat datang.');
    }
}
```
`routes/access.php`:
```php
<?php

use App\Domains\Access\Http\Controllers\ActivationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::get('aktivasi/{token}', [ActivationController::class, 'show'])->name('aktivasi.show');
    Route::post('aktivasi/{token}', [ActivationController::class, 'store'])->name('aktivasi.store');
});
```
Di `routes/web.php` tambahkan di bawah `require __DIR__.'/auth.php';`: `require __DIR__.'/access.php';`.

- [ ] **Step 3: Jalankan dan commit**

Run: `php artisan test --filter=ActivationHttpTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah halaman aktivasi akun" -m "Rute /aktivasi/{token} dengan throttle 10 per menit. Link tidak berlaku menampilkan satu state yang sama; sukses mengisi password, login otomatis, dan mengarah ke dashboard."
```

---

### Task 6: Login aman, middleware akun aktif, log auth

**Files:**
- Create: `app/Http/Middleware/EnsureAccountIsActive.php`, `app/Domains/Access/Listeners/LogAuthEvents.php`
- Modify: `app/Http/Requests/Auth/LoginRequest.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Access/LoginRulesTest.php`

**Interfaces:**
- Produces: login hanya untuk `is_active=true` dan `activated_at` terisi (pesan sama `auth.failed`); `last_login_at` diperbarui; middleware `EnsureAccountIsActive` (di grup `web`) men-logout user nonaktif; log `login`, `logout`, `login.terkunci` (log `akses`).

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/LoginRulesTest.php`:
```php
<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

function attempt(string $email, string $password = 'password')
{
    return test()->post('/login', ['email' => $email, 'password' => $password]);
}

test('login sukses mengisi last_login_at dan mencatat log', function () {
    $user = User::factory()->create(['email' => 'a@x.test']);

    attempt('a@x.test')->assertRedirect('/dashboard');

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(Activity::where('log_name', 'akses')->where('description', 'login')->count())->toBe(1);
});

test('akun nonaktif, belum aktif, dan salah password semuanya menerima pesan yang sama', function () {
    User::factory()->inactive()->create(['email' => 'off@x.test']);
    User::factory()->pending()->create(['email' => 'wait@x.test']);
    User::factory()->create(['email' => 'ok@x.test']);

    foreach ([['off@x.test', 'password'], ['wait@x.test', 'password'], ['ok@x.test', 'salah'], ['none@x.test', 'password']] as [$email, $pw]) {
        attempt($email, $pw)->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }
});

test('lima percobaan gagal mengunci login dan mencatat log', function () {
    User::factory()->create(['email' => 'a@x.test']);

    foreach (range(1, 5) as $_) {
        attempt('a@x.test', 'salah');
    }
    attempt('a@x.test', 'password')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(Activity::where('description', 'login.terkunci')->exists())->toBeTrue();
});

test('user yang dinonaktifkan saat masih login langsung ter-logout', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

test('logout dicatat', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    expect(Activity::where('description', 'logout')->exists())->toBeTrue();
});
```
Run: `php artisan test --filter=LoginRulesTest` → Expected: FAIL.

- [ ] **Step 2: LoginRequest**

Di `app/Http/Requests/Auth/LoginRequest.php`, tambahkan `use Illuminate\Database\Eloquent\Builder;` lalu ganti pemanggilan `Auth::attempt(...)` pada `authenticate()` dengan:
```php
$credentials = $this->only('email', 'password') + ['is_active' => true];
$credentials[] = fn (Builder $query) => $query->whereNotNull('activated_at');

if (! Auth::attempt($credentials, $this->boolean('remember'))) {
```
(sisa blok `RateLimiter::hit` dan `ValidationException` tidak berubah.)

- [ ] **Step 3: Middleware dan listener**

`app/Http/Middleware/EnsureAccountIsActive.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Menutup celah remember-cookie/sesi lama milik akun yang sudah dinonaktifkan. */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
```
Di `bootstrap/app.php` tambahkan `EnsureAccountIsActive::class` ke array `append` pada `$middleware->web(...)` (setelah `HandleInertiaRequests::class`) dengan `use App\Http\Middleware\EnsureAccountIsActive;`.

`app/Domains/Access/Listeners/LogAuthEvents.php`:
```php
<?php

namespace App\Domains\Access\Listeners;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthEvents
{
    public function handleLogin(Login $event): void
    {
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity('akses')->performedOn($event->user)->causedBy($event->user)->log('login');
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user !== null) {
            activity('akses')->performedOn($event->user)->causedBy($event->user)->log('logout');
        }
    }

    public function handleLockout(Lockout $event): void
    {
        activity('akses')
            ->withProperties(['email' => $event->request->input('email'), 'ip' => $event->request->ip()])
            ->log('login.terkunci');
    }
}
```
Di `AppServiceProvider::boot()` daftarkan:
```php
Event::listen(Login::class, [LogAuthEvents::class, 'handleLogin']);
Event::listen(Logout::class, [LogAuthEvents::class, 'handleLogout']);
Event::listen(Lockout::class, [LogAuthEvents::class, 'handleLockout']);
```
dengan `use` untuk `Illuminate\Support\Facades\Event`, ketiga event `Illuminate\Auth\Events\*`, dan listener.

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=LoginRulesTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`. Jika kelas `Spatie\Activitylog\Models\Activity` tidak ada, baca `config/activitylog.php` (`activity_model`) dan pakai kelas itu di test.
```bash
git add -A
git commit -m "feat: perketat login dan catat log autentikasi" -m "Login hanya untuk akun aktif dan sudah diaktivasi dengan pesan kegagalan yang sama. last_login_at diperbarui. Middleware EnsureAccountIsActive menutup remember-cookie akun nonaktif. Log login, logout, dan penguncian login."
```

---

### Task 7: Profil (nama dan HP)

**Files:**
- Modify: `app/Http/Requests/ProfileUpdateRequest.php`, `app/Http/Controllers/ProfileController.php`, `tests/Feature/ProfileTest.php`

**Interfaces:**
- Produces: `PATCH /profile` hanya mengubah `name` dan `phone`; email tidak dapat diubah dari profil.

- [ ] **Step 1: Ubah test (gagal dulu)**

Di `tests/Feature/ProfileTest.php`, ganti test `profile information can be updated` dan hapus test `email verification status is unchanged…`:
```php
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
```
Run: `php artisan test --filter=ProfileTest` → Expected: FAIL.

- [ ] **Step 2: Implementasi**

`app/Http/Requests/ProfileUpdateRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^08[0-9]{8,12}$/'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Nomor HP harus diawali 08 dan berisi 10–14 digit.'];
    }
}
```
Di `ProfileController::update` ganti isi menjadi:
```php
$request->user()->update($request->validated());

return Redirect::route('profile.edit')->with('success', 'Profil diperbarui.');
```

- [ ] **Step 3: Jalankan dan commit**

Run: `php artisan test --filter=ProfileTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: profil hanya mengubah nama dan nomor HP" -m "Email terkunci dari profil. Nomor HP divalidasi format 08 dengan 10-14 digit."
```

---

### Task 8: Sekolah (CRUD)

**Files:**
- Create: `app/Domains/Schools/Repositories/Contracts/SchoolRepositoryInterface.php`, `app/Domains/Schools/Repositories/EloquentSchoolRepository.php`, `app/Domains/Schools/Services/SchoolService.php`, `app/Domains/Schools/Http/Requests/SchoolRequest.php`, `app/Domains/Schools/Http/Controllers/SchoolController.php`, `app/Providers/RepositoryServiceProvider.php`
- Modify: `bootstrap/providers.php`, `routes/access.php`
- Test: `tests/Feature/Schools/SchoolTest.php`

**Interfaces:**
- Produces: `SchoolRepositoryInterface { paginate(int $perPage = 20): LengthAwarePaginator; options(): Collection; create(array): School; update(School, array): School }`; `SchoolService { list(): LengthAwarePaginator; options(): Collection; create(array): School; update(School, array): School }`; `RepositoryServiceProvider` (binding; task berikut menambah). Rute `sekolah.index/create/store/edit/update`. Props `Sekolah/Index`: `{schools: paginator(id,name,npsn,level,address,users_count), filters, }`; `Sekolah/Form`: `{school: null|{id,name,npsn,level,address}}`.

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Schools/SchoolTest.php`:
```php
<?php

use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

test('daftar sekolah dengan pencarian dan jumlah operator', function () {
    $a = School::factory()->create(['name' => 'SMAN 1 Sungai Beduk', 'npsn' => '11111111']);
    School::factory()->create(['name' => 'SMKN 2 Lain', 'npsn' => '22222222']);
    makeUserWith([], $a);

    $this->actingAs(makeUserWith(['sekolah.lihat']))
        ->get('/sekolah?filter[search]=Sungai')
        ->assertInertia(fn (Assert $p) => $p
            ->component('Sekolah/Index')
            ->has('schools.data', 1)
            ->where('schools.data.0.name', 'SMAN 1 Sungai Beduk')
            ->where('schools.data.0.users_count', 1));
});

test('tambah dan ubah sekolah', function () {
    $this->actingAs(makeUserWith(['sekolah.buat', 'sekolah.ubah']));

    $this->post('/sekolah', ['name' => 'MAN 1', 'npsn' => '33333333', 'level' => 'MA', 'address' => 'Jl. A'])
        ->assertRedirect('/sekolah');
    $school = School::firstWhere('npsn', '33333333');
    expect($school->name)->toBe('MAN 1');

    $this->put("/sekolah/{$school->id}", ['name' => 'MAN 1 Baru', 'npsn' => '33333333', 'level' => 'MA'])
        ->assertRedirect('/sekolah');
    expect($school->fresh()->name)->toBe('MAN 1 Baru');
});

test('validasi: NPSN unik dan jenjang terbatas', function () {
    School::factory()->create(['npsn' => '44444444']);
    $this->actingAs(makeUserWith(['sekolah.buat']));

    $this->post('/sekolah', ['name' => 'X', 'npsn' => '44444444', 'level' => 'SD'])
        ->assertSessionHasErrors(['npsn', 'level']);
});

test('halaman form tambah dan ubah', function () {
    $school = School::factory()->create();
    $this->actingAs(makeUserWith(['sekolah.buat', 'sekolah.ubah']));

    $this->get('/sekolah/buat')->assertInertia(fn (Assert $p) => $p->component('Sekolah/Form')->where('school', null));
    $this->get("/sekolah/{$school->id}/ubah")->assertInertia(fn (Assert $p) => $p->where('school.id', $school->id));
});
```
Run: `php artisan test --filter=SchoolTest` → Expected: FAIL.

- [ ] **Step 2: Repository dan provider**

`app/Domains/Schools/Repositories/Contracts/SchoolRepositoryInterface.php`:
```php
<?php

namespace App\Domains\Schools\Repositories\Contracts;

use App\Domains\Schools\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SchoolRepositoryInterface
{
    /** @return LengthAwarePaginator<int, School> */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /** @return Collection<int, School> */
    public function options(): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): School;

    /** @param array<string, mixed> $attributes */
    public function update(School $school, array $attributes): School;
}
```
`app/Domains/Schools/Repositories/EloquentSchoolRepository.php`:
```php
<?php

namespace App\Domains\Schools\Repositories;

use App\Domains\Schools\Models\School;
use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(School::query()->withCount('users')->orderBy('name'))
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $w) => $w->where('name', 'like', "%{$value}%")->orWhere('npsn', 'like', "%{$value}%")
                )),
                AllowedFilter::exact('level'),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function options(): Collection
    {
        return School::query()->orderBy('name')->get(['id', 'name']);
    }

    public function create(array $attributes): School
    {
        return School::create($attributes);
    }

    public function update(School $school, array $attributes): School
    {
        $school->update($attributes);

        return $school;
    }
}
```
`app/Providers/RepositoryServiceProvider.php`:
```php
<?php

namespace App\Providers;

use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use App\Domains\Schools\Repositories\EloquentSchoolRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        SchoolRepositoryInterface::class => EloquentSchoolRepository::class,
    ];
}
```
Tambahkan `RepositoryServiceProvider::class` ke `bootstrap/providers.php`.
- [ ] **Step 3: Service, request, controller, rute**

`app/Domains/Schools/Services/SchoolService.php`:
```php
<?php

namespace App\Domains\Schools\Services;

use App\Domains\Schools\Models\School;
use App\Domains\Schools\Repositories\Contracts\SchoolRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SchoolService
{
    public function __construct(private readonly SchoolRepositoryInterface $schools) {}

    /** @return LengthAwarePaginator<int, School> */
    public function list(): LengthAwarePaginator
    {
        return $this->schools->paginate();
    }

    /** @return Collection<int, School> */
    public function options(): Collection
    {
        return $this->schools->options();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): School
    {
        return $this->schools->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(School $school, array $data): School
    {
        return $this->schools->update($school, $data);
    }
}
```
`app/Domains/Schools/Http/Requests/SchoolRequest.php`:
```php
<?php

namespace App\Domains\Schools\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['required', 'digits:8', Rule::unique('schools', 'npsn')->ignore($this->route('school'))],
            'level' => ['required', Rule::in(['SMA', 'SMK', 'MA'])],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```
`app/Domains/Schools/Http/Controllers/SchoolController.php`:
```php
<?php

namespace App\Domains\Schools\Http\Controllers;

use App\Domains\Schools\Http\Requests\SchoolRequest;
use App\Domains\Schools\Models\School;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function __construct(private readonly SchoolService $schools) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Sekolah/Index', [
            'schools' => $this->schools->list()->through(fn (School $s) => $s->only(['id', 'name', 'npsn', 'level', 'address', 'users_count'])),
            'filters' => $request->query('filter', []),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sekolah/Form', ['school' => null]);
    }

    public function store(SchoolRequest $request): RedirectResponse
    {
        $this->schools->create($request->validated());

        return redirect()->route('sekolah.index')->with('success', 'Sekolah ditambahkan.');
    }

    public function edit(School $school): Response
    {
        return Inertia::render('Sekolah/Form', ['school' => $school->only(['id', 'name', 'npsn', 'level', 'address'])]);
    }

    public function update(SchoolRequest $request, School $school): RedirectResponse
    {
        $this->schools->update($school, $request->validated());

        return redirect()->route('sekolah.index')->with('success', 'Sekolah diperbarui.');
    }
}
```
Tambahkan di `routes/access.php` (dengan `use` untuk `SchoolController`):
```php
Route::middleware('auth')->group(function () {
    Route::get('sekolah', [SchoolController::class, 'index'])->middleware('can:sekolah.lihat')->name('sekolah.index');
    Route::get('sekolah/buat', [SchoolController::class, 'create'])->middleware('can:sekolah.buat')->name('sekolah.create');
    Route::post('sekolah', [SchoolController::class, 'store'])->middleware('can:sekolah.buat')->name('sekolah.store');
    Route::get('sekolah/{school}/ubah', [SchoolController::class, 'edit'])->middleware('can:sekolah.ubah')->name('sekolah.edit');
    Route::put('sekolah/{school}', [SchoolController::class, 'update'])->middleware('can:sekolah.ubah')->name('sekolah.update');
});
```

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=SchoolTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah CRUD sekolah" -m "Repository, service, request, dan controller sekolah dengan pencarian lewat query-builder, jumlah operator, validasi NPSN 8 digit unik dan jenjang SMA/SMK/MA. RepositoryServiceProvider mengikat repository."
```

---

### Task 9: AccessGuard, PermissionCatalog, RoleService, repository role

**Files:**
- Create: `app/Domains/Access/Exceptions/AccessRuleException.php`, `app/Domains/Access/Services/AccessGuard.php`, `app/Domains/Access/Services/PermissionCatalog.php`, `app/Domains/Access/Services/RoleService.php`, `app/Domains/Access/Repositories/Contracts/RoleRepositoryInterface.php`, `app/Domains/Access/Repositories/EloquentRoleRepository.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`, `bootstrap/app.php`
- Test: `tests/Feature/Access/AccessGuardTest.php`, `tests/Feature/Access/RoleServiceTest.php`

**Interfaces:**
- Consumes: `Role`, `Permission` enum, `makeUserWith` (Task 1).
- Produces:
  - `AccessRuleException extends RuntimeException` (pesan Indonesia); dipetakan di `bootstrap/app.php` ke `back()->with('error', $e->getMessage())`.
  - `AccessGuard::permissionsOf(User): array` (list string; super-admin = semua), `AccessGuard::canGrantRole(User $actor, Role $role): bool`, `AccessGuard::canManageUser(User $actor, User $target): bool`.
  - `PermissionCatalog::forActor(User): array` = `[['module' => 'user', 'actions' => [['value' => 'user.lihat', 'action' => 'lihat', 'editable' => bool], …]], …]`.
  - `RoleRepositoryInterface { all(): Collection (withCount users, permissions); find(int): Role; create(array): Role; update(Role, array): Role; delete(Role): void; userCount(Role): int }`.
  - `RoleService { list(): Collection; create(array $data, User $actor): Role; update(Role $role, array $data, User $actor): Role; delete(Role $role, User $actor): void }`; `$data` = `name, description?, is_school_scoped (bool), permissions (list<string>)`.

- [ ] **Step 1: Test AccessGuard (gagal dulu)**

`tests/Feature/Access/AccessGuardTest.php`:
```php
<?php

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\AccessGuard;
use Database\Seeders\AccessSeeder;

beforeEach(fn () => $this->seed(AccessSeeder::class));

test('super-admin memegang semua izin dan boleh memberi role apa pun', function () {
    $super = makeUserWith([], roleName: 'tmp')->syncRoles('super-admin');

    expect(AccessGuard::permissionsOf($super))->toHaveCount(32)
        ->and(AccessGuard::canGrantRole($super, Role::findByName('super-admin')))->toBeTrue();
});

test('role hanya boleh diberi bila seluruh izinnya dimiliki pemberi', function () {
    $admin = makeUserWith([], roleName: 'tmp')->syncRoles('admin-kecamatan');
    $operator = makeUserWith([], roleName: 'tmp2')->syncRoles('operator-sekolah');

    expect(AccessGuard::canGrantRole($admin, Role::findByName('admin-kecamatan')))->toBeTrue()
        ->and(AccessGuard::canGrantRole($admin, Role::findByName('operator-sekolah')))->toBeFalse() // admin tak punya siswa.buat
        ->and(AccessGuard::canGrantRole($admin, Role::findByName('super-admin')))->toBeFalse()
        ->and(AccessGuard::canGrantRole($operator, Role::findByName('admin-kecamatan')))->toBeFalse();
});

test('akun berhak lebih tinggi tidak dapat dikelola non-super-admin', function () {
    $super = makeUserWith([], roleName: 'tmp')->syncRoles('super-admin');
    $admin = makeUserWith([], roleName: 'tmp2')->syncRoles('admin-kecamatan');

    expect(AccessGuard::canManageUser($admin, $super))->toBeFalse()
        ->and(AccessGuard::canManageUser($super, $admin))->toBeTrue()
        ->and(AccessGuard::canManageUser($admin, $admin))->toBeTrue();
});
```
Catatan: seeder `admin-kecamatan` tidak memegang `siswa.buat`; jika seeder berubah, sesuaikan asersi `operator-sekolah` di atas.

Run: `php artisan test --filter=AccessGuardTest` → Expected: FAIL.

- [ ] **Step 2: Implementasi AccessGuard, exception, handler**

`app/Domains/Access/Exceptions/AccessRuleException.php`:
```php
<?php

namespace App\Domains\Access\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis akses; pesannya ditampilkan ke pengguna. */
class AccessRuleException extends RuntimeException {}
```
`app/Domains/Access/Services/AccessGuard.php`:
```php
<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Models\User;

final class AccessGuard
{
    /** @return list<string> */
    public static function permissionsOf(User $user): array
    {
        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return Permission::values();
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }

    /** Hanya boleh memberi role yang seluruh izinnya sudah dimiliki; super-admin hanya oleh super-admin. */
    public static function canGrantRole(User $actor, Role $role): bool
    {
        if ($actor->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        if ($role->isProtected()) {
            return false;
        }

        return array_diff($role->permissions->pluck('name')->all(), self::permissionsOf($actor)) === [];
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        $role = $target->roles->first();

        return $role === null || self::canGrantRole($actor, $role);
    }
}
```
Di `bootstrap/app.php`, dalam `withExceptions` tambahkan sebelum `shouldRenderJsonWhen`:
```php
$exceptions->render(function (AccessRuleException $e) {
    return back()->with('error', $e->getMessage());
});
```
dengan `use App\Domains\Access\Exceptions\AccessRuleException;`.

- [ ] **Step 3: Test RoleService (gagal dulu)**

`tests/Feature/Access/RoleServiceTest.php`:
```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\PermissionCatalog;
use App\Domains\Access\Services\RoleService;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->service = app(RoleService::class);
    $this->super = User::factory()->create()->assignRole('super-admin');
    $this->admin = User::factory()->create()->assignRole('admin-kecamatan');
});

test('membuat role dengan izin yang valid', function () {
    $role = $this->service->create([
        'name' => 'verifikator', 'description' => 'Memverifikasi', 'is_school_scoped' => false,
        'permissions' => ['verifikasi.lihat', 'verifikasi.putuskan'],
    ], $this->admin);

    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['verifikasi.lihat', 'verifikasi.putuskan']);
});

test('tidak boleh memberi izin yang tidak dimiliki pembuat', function () {
    expect(fn () => $this->service->create([
        'name' => 'x', 'is_school_scoped' => false, 'permissions' => ['siswa.buat'],
    ], $this->admin))->toThrow(AccessRuleException::class);
});

test('update menambah dan mencabut hanya izin milik penyunting, sisanya tidak tersentuh', function () {
    $role = Role::create(['name' => 'campuran']);
    $role->givePermissionTo(['siswa.buat', 'user.lihat', 'user.buat']); // siswa.buat di luar milik admin

    $this->service->update($role, [
        'name' => 'campuran', 'is_school_scoped' => false,
        'permissions' => ['siswa.buat', 'user.lihat', 'jadwal.lihat'], // cabut user.buat, tambah jadwal.lihat
    ], $this->admin);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['jadwal.lihat', 'siswa.buat', 'user.lihat']);
});

test('menambah izin baru di luar milik penyunting ditolak', function () {
    $role = Role::create(['name' => 'r']);

    expect(fn () => $this->service->update($role, [
        'name' => 'r', 'is_school_scoped' => false, 'permissions' => ['siswa.buat'],
    ], $this->admin))->toThrow(AccessRuleException::class);
});

test('izin di luar enum ditolak', function () {
    expect(fn () => $this->service->create([
        'name' => 'x', 'is_school_scoped' => false, 'permissions' => ['bukan.izin'],
    ], $this->super))->toThrow(AccessRuleException::class);
});

test('super-admin tidak dapat diubah atau dihapus', function () {
    $role = Role::findByName('super-admin');

    expect(fn () => $this->service->update($role, ['name' => 'lain', 'is_school_scoped' => false, 'permissions' => []], $this->super))
        ->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->delete($role, $this->super))->toThrow(AccessRuleException::class);
});

test('hapus ditolak bila role masih dipakai', function () {
    $role = Role::create(['name' => 'dipakai']);
    User::factory()->create()->assignRole($role);

    expect(fn () => $this->service->delete($role, $this->super))->toThrow(AccessRuleException::class);

    $role->users()->detach();
    $this->service->delete($role, $this->super);
    expect(Role::where('name', 'dipakai')->exists())->toBeFalse();
});

test('is_school_scoped terkunci saat role dipakai', function () {
    $role = Role::create(['name' => 'dipakai']);
    User::factory()->create()->assignRole($role);

    expect(fn () => $this->service->update($role, ['name' => 'dipakai', 'is_school_scoped' => true, 'permissions' => []], $this->super))
        ->toThrow(AccessRuleException::class);

    $this->service->update($role, ['name' => 'dipakai', 'is_school_scoped' => false, 'description' => 'ok', 'permissions' => []], $this->super);
    expect($role->fresh()->description)->toBe('ok');
});

test('perubahan izin mengosongkan cache izin', function () {
    $role = Role::create(['name' => 'r']);
    $user = User::factory()->create()->assignRole($role);
    expect($user->can('jadwal.lihat'))->toBeFalse();

    $this->service->update($role, ['name' => 'r', 'is_school_scoped' => false, 'permissions' => ['jadwal.lihat']], $this->admin);

    expect(User::find($user->id)->can('jadwal.lihat'))->toBeTrue();
});

test('perubahan izin tercatat di log aktivitas', function () {
    $role = Role::create(['name' => 'r']);

    $this->service->update($role, ['name' => 'r', 'is_school_scoped' => false, 'permissions' => ['jadwal.lihat']], $this->admin);

    $log = Spatie\Activitylog\Models\Activity::where('description', 'role.izin_diubah')->firstOrFail();
    expect($log->properties['ditambah'])->toBe(['jadwal.lihat'])->and($log->causer_id)->toBe($this->admin->id);
});

test('katalog menandai izin yang tidak dapat diedit oleh penyunting', function () {
    $catalog = collect(PermissionCatalog::forActor($this->admin));
    $siswa = $catalog->firstWhere('module', 'siswa')['actions'];

    expect(collect($siswa)->firstWhere('value', 'siswa.lihat')['editable'])->toBeTrue()
        ->and(collect($siswa)->firstWhere('value', 'siswa.buat')['editable'])->toBeFalse();
});
```
Run: `php artisan test --filter=RoleServiceTest` → Expected: FAIL.

- [ ] **Step 4: Repository, katalog, service**

`app/Domains/Access/Repositories/Contracts/RoleRepositoryInterface.php`:
```php
<?php

namespace App\Domains\Access\Repositories\Contracts;

use App\Domains\Access\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    /** @return Collection<int, Role> */
    public function all(): Collection;

    public function find(int $id): Role;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Role;

    /** @param array<string, mixed> $attributes */
    public function update(Role $role, array $attributes): Role;

    public function delete(Role $role): void;

    public function userCount(Role $role): int;
}
```
`app/Domains/Access/Repositories/EloquentRoleRepository.php`:
```php
<?php

namespace App\Domains\Access\Repositories;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get();
    }

    public function find(int $id): Role
    {
        return Role::query()->with('permissions')->findOrFail($id);
    }

    public function create(array $attributes): Role
    {
        return Role::create($attributes + ['guard_name' => 'web']);
    }

    public function update(Role $role, array $attributes): Role
    {
        $role->update($attributes);

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function userCount(Role $role): int
    {
        return $role->users()->count();
    }
}
```
Tambahkan binding `RoleRepositoryInterface::class => EloquentRoleRepository::class` di `RepositoryServiceProvider`.

`app/Domains/Access/Services/PermissionCatalog.php`:
```php
<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Enums\Permission;
use App\Models\User;

final class PermissionCatalog
{
    /** @return list<array{module: string, actions: list<array{value: string, action: string, editable: bool}>}> */
    public static function forActor(User $actor): array
    {
        $owned = AccessGuard::permissionsOf($actor);

        return collect(Permission::values())
            ->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($names, string $module) => [
                'module' => $module,
                'actions' => $names->map(fn (string $name) => [
                    'value' => $name,
                    'action' => explode('.', $name, 2)[1],
                    'editable' => in_array($name, $owned, true),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
```
`app/Domains/Access/Services/RoleService.php`:
```php
<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    /** @return Collection<int, Role> */
    public function list(): Collection
    {
        return $this->roles->all();
    }

    /** @param array{name: string, description?: ?string, is_school_scoped: bool, permissions: list<string>} $data */
    public function create(array $data, User $actor): Role
    {
        $permissions = $this->assertKnown($data['permissions']);
        $this->assertOwned($permissions, $actor);

        return DB::transaction(function () use ($data, $permissions, $actor) {
            $role = $this->roles->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_school_scoped' => $data['is_school_scoped'],
            ]);
            $this->sync($role, $permissions, $actor);

            return $role;
        });
    }

    /** @param array{name: string, description?: ?string, is_school_scoped: bool, permissions: list<string>} $data */
    public function update(Role $role, array $data, User $actor): Role
    {
        if ($role->isProtected()) {
            throw new AccessRuleException('Role super-admin dilindungi dan tidak dapat diubah.');
        }
        if ($data['is_school_scoped'] !== $role->is_school_scoped && $this->roles->userCount($role) > 0) {
            throw new AccessRuleException('Pengaturan "terikat sekolah" tidak dapat diubah selama role masih dipakai pengguna.');
        }

        $requested = $this->assertKnown($data['permissions']);
        $current = $role->permissions->pluck('name')->all();
        $this->assertOwned(array_diff($requested, $current), $actor);

        $owned = AccessGuard::permissionsOf($actor);
        // Izin yang tidak dimiliki penyunting dibiarkan apa adanya; sisanya mengikuti permintaan.
        $final = array_values(array_unique([
            ...array_diff($current, $owned),
            ...array_intersect($requested, $owned),
        ]));

        return DB::transaction(function () use ($role, $data, $final, $current, $actor) {
            $this->roles->update($role, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_school_scoped' => $data['is_school_scoped'],
            ]);
            $this->sync($role, $final, $actor, $current);

            return $role;
        });
    }

    public function delete(Role $role, User $actor): void
    {
        if ($role->isProtected()) {
            throw new AccessRuleException('Role super-admin dilindungi dan tidak dapat dihapus.');
        }
        if ($this->roles->userCount($role) > 0) {
            throw new AccessRuleException('Role masih dipakai pengguna dan tidak dapat dihapus.');
        }

        $this->roles->delete($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        activity('akses')->causedBy($actor)->withProperties(['role' => $role->name])->log('role.dihapus');
    }

    /** @param list<string> $names @return list<string> */
    private function assertKnown(array $names): array
    {
        if (array_diff($names, Permission::values()) !== []) {
            throw new AccessRuleException('Terdapat izin yang tidak dikenal.');
        }

        return array_values(array_unique($names));
    }

    /** @param array<int, string> $added */
    private function assertOwned(array $added, User $actor): void
    {
        if (array_diff($added, AccessGuard::permissionsOf($actor)) !== []) {
            throw new AccessRuleException('Anda tidak dapat memberikan izin yang tidak Anda miliki.');
        }
    }

    /** @param list<string> $permissions @param list<string> $before */
    private function sync(Role $role, array $permissions, User $actor, array $before = []): void
    {
        $role->syncPermissions($permissions);

        $added = array_values(array_diff($permissions, $before));
        $removed = array_values(array_diff($before, $permissions));

        if ($added !== [] || $removed !== []) {
            activity('akses')->performedOn($role)->causedBy($actor)
                ->withProperties(['ditambah' => $added, 'dicabut' => $removed])
                ->log('role.izin_diubah');
        }
    }
}
```
(`syncPermissions` otomatis mengosongkan cache izin spatie; test cache memastikannya.)

- [ ] **Step 5: Jalankan dan commit**

Run: `php artisan test --filter="AccessGuardTest|RoleServiceTest"` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah AccessGuard, katalog izin, dan RoleService" -m "AccessGuard menghitung hak memberi role dan mengelola user. RoleService menegakkan anti eskalasi (hanya izin milik penyunting), proteksi super-admin, kunci is_school_scoped, dan larangan hapus role terpakai. Perubahan izin dicatat ke log akses. AccessRuleException dipetakan ke redirect dengan flash error."
```

---

### Task 10: Role (HTTP)

**Files:**
- Create: `app/Domains/Access/Http/Requests/RoleRequest.php`, `app/Domains/Access/Http/Controllers/RoleController.php`
- Modify: `routes/access.php`
- Test: `tests/Feature/Access/RoleHttpTest.php`

**Interfaces:**
- Consumes: `RoleService`, `PermissionCatalog`.
- Produces: rute `role.index/create/store/edit/update/destroy`. Props `Role/Index`: `{roles: [{id,name,description,users_count,permissions_count,is_school_scoped,protected}]}`; `Role/Form`: `{role: null|{id,name,description,is_school_scoped,permissions: list<string>,protected}, catalog: PermissionCatalog}`.

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/RoleHttpTest.php`:
```php
<?php

use App\Domains\Access\Models\Role;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->actor = makeUserWith(['role.lihat', 'role.buat', 'role.ubah', 'role.hapus', 'jadwal.lihat', 'jadwal.buat']);
});

test('daftar role memuat jumlah dan penanda protected', function () {
    $this->actingAs($this->actor)->get('/role')->assertInertia(fn (Assert $p) => $p
        ->component('Role/Index')
        ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'super-admin')['protected'] === true));
});

test('buat role lewat form', function () {
    $this->actingAs($this->actor)->post('/role', [
        'name' => 'penjadwal', 'description' => 'Atur jadwal', 'is_school_scoped' => false,
        'permissions' => ['jadwal.lihat', 'jadwal.buat'],
    ])->assertRedirect('/role');

    expect(Role::findByName('penjadwal')->permissions)->toHaveCount(2);
});

test('izin yang tidak dimiliki menghasilkan flash error, bukan crash', function () {
    $this->actingAs($this->actor)->from('/role/buat')->post('/role', [
        'name' => 'bocor', 'is_school_scoped' => false, 'permissions' => ['siswa.buat'],
    ])->assertRedirect('/role/buat')->assertSessionHas('error');

    expect(Role::where('name', 'bocor')->exists())->toBeFalse();
});

test('validasi nama unik dan izin harus anggota enum', function () {
    $this->actingAs($this->actor)->post('/role', ['name' => 'operator-sekolah', 'is_school_scoped' => false, 'permissions' => ['x.y']])
        ->assertSessionHasErrors(['name', 'permissions.0']);
});

test('form ubah memuat katalog dan izin saat ini', function () {
    $role = Role::create(['name' => 'r']);
    $role->givePermissionTo('jadwal.lihat');

    $this->actingAs($this->actor)->get("/role/{$role->id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->component('Role/Form')
        ->where('role.permissions', ['jadwal.lihat'])
        ->has('catalog'));
});

test('ubah dan hapus role', function () {
    $role = Role::create(['name' => 'r']);

    $this->actingAs($this->actor)->put("/role/{$role->id}", ['name' => 'r2', 'is_school_scoped' => false, 'permissions' => ['jadwal.lihat']])
        ->assertRedirect('/role');
    expect($role->fresh()->name)->toBe('r2');

    $this->actingAs($this->actor)->delete("/role/{$role->id}")->assertRedirect('/role');
    expect(Role::where('name', 'r2')->exists())->toBeFalse();
});

test('menghapus role super-admin menghasilkan flash error', function () {
    $super = Role::findByName('super-admin');

    $this->actingAs($this->actor)->delete("/role/{$super->id}")->assertSessionHas('error');
    expect(Role::where('name', 'super-admin')->exists())->toBeTrue();
});
```
Run: `php artisan test --filter=RoleHttpTest` → Expected: FAIL.

- [ ] **Step 2: Request**

`app/Domains/Access/Http/Requests/RoleRequest.php`:
```php
<?php

namespace App\Domains\Access\Http\Requests;

use App\Domains\Access\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'is_school_scoped' => ['required', 'boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }
}
```
Satu `RoleRequest` dipakai untuk tambah dan ubah (aturan `unique` mengabaikan role dari rute).

- [ ] **Step 3: Controller dan rute**

`app/Domains/Access/Http/Controllers/RoleController.php`:
```php
<?php

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Requests\RoleRequest;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\PermissionCatalog;
use App\Domains\Access\Services\RoleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): Response
    {
        return Inertia::render('Role/Index', [
            'roles' => $this->roles->list()->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'users_count' => $r->users_count,
                'permissions_count' => $r->permissions_count,
                'is_school_scoped' => $r->is_school_scoped,
                'protected' => $r->isProtected(),
            ])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Role/Form', ['role' => null, 'catalog' => PermissionCatalog::forActor($request->user())]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->roles->create($request->validated(), $request->user());

        return redirect()->route('role.index')->with('success', 'Role ditambahkan.');
    }

    public function edit(Request $request, Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('Role/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_school_scoped' => $role->is_school_scoped,
                'permissions' => $role->permissions->pluck('name')->values(),
                'protected' => $role->isProtected(),
            ],
            'catalog' => PermissionCatalog::forActor($request->user()),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->validated(), $request->user());

        return redirect()->route('role.index')->with('success', 'Role diperbarui.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->roles->delete($role, $request->user());

        return redirect()->route('role.index')->with('success', 'Role dihapus.');
    }
}
```
Tambahkan di grup `auth` pada `routes/access.php` (dengan `use RoleController`):
```php
Route::get('role', [RoleController::class, 'index'])->middleware('can:role.lihat')->name('role.index');
Route::get('role/buat', [RoleController::class, 'create'])->middleware('can:role.buat')->name('role.create');
Route::post('role', [RoleController::class, 'store'])->middleware('can:role.buat')->name('role.store');
Route::get('role/{role}/ubah', [RoleController::class, 'edit'])->middleware('can:role.ubah')->name('role.edit');
Route::put('role/{role}', [RoleController::class, 'update'])->middleware('can:role.ubah')->name('role.update');
Route::delete('role/{role}', [RoleController::class, 'destroy'])->middleware('can:role.hapus')->name('role.destroy');
```

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=RoleHttpTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah CRUD role dan matriks izin" -m "Controller dan request role, katalog izin per modul dengan penanda editable, pelanggaran aturan menjadi flash error."
```

---

### Task 11: UserService dan repository user

**Files:**
- Create: `app/Domains/Access/Repositories/Contracts/UserRepositoryInterface.php`, `app/Domains/Access/Repositories/EloquentUserRepository.php`, `app/Domains/Access/Services/UserService.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Feature/Access/UserServiceTest.php`

**Interfaces:**
- Consumes: `ActivationService`, `AccessGuard`, `RoleRepositoryInterface`, `AccessRuleException`.
- Produces:
  - `UserRepositoryInterface { paginate(int $perPage = 20): LengthAwarePaginator; create(array): User; update(User, array): User; activeSuperAdminCount(): int }`.
  - `UserService { list(): LengthAwarePaginator; create(array $data, User $actor): array{user: User, token: string}; update(User $user, array $data, User $actor): User; deactivate(User $user, User $actor): void; activate(User $user): void; resendActivation(User $user): string }`; `$data` = `name, email, phone?, role_id, school_id?`.

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/UserServiceTest.php`:
```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\UserService;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Database\Seeders\AccessSeeder;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->service = app(UserService::class);
    $this->super = User::factory()->create()->assignRole('super-admin');
    $this->admin = User::factory()->create()->assignRole('admin-kecamatan');
    $this->school = School::factory()->create();
    $this->operatorRole = Role::findByName('operator-sekolah');
});

function payload(array $o = []): array
{
    return array_merge(['name' => 'Budi', 'email' => 'budi@x.test', 'phone' => null], $o);
}

test('membuat user menghasilkan akun belum aktif, role, pembuat, dan token', function () {
    ['user' => $user, 'token' => $token] = $this->service->create(
        payload(['role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);

    expect($user->password)->toBeNull()
        ->and($user->activated_at)->toBeNull()
        ->and($user->created_by)->toBe($this->super->id)
        ->and($user->hasRole('operator-sekolah'))->toBeTrue()
        ->and($user->school_id)->toBe($this->school->id)
        ->and($token)->toHaveLength(64)
        ->and($user->fresh()->status->value)->toBe('menunggu');
});

test('email dinormalisasi: huruf kecil dan tanpa spasi', function () {
    ['user' => $user] = $this->service->create(
        payload(['email' => '  Budi@X.TEST ', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);

    expect($user->email)->toBe('budi@x.test');
});

test('aturan sekolah mengikuti is_school_scoped role', function () {
    expect(fn () => $this->service->create(payload(['role_id' => $this->operatorRole->id]), $this->super))
        ->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->create(
            payload(['role_id' => Role::findByName('admin-kecamatan')->id, 'school_id' => $this->school->id]), $this->super))
        ->toThrow(AccessRuleException::class);
});

test('tidak boleh memberi role di luar hak pemberi, dan super-admin hanya oleh super-admin', function () {
    expect(fn () => $this->service->create(payload(['role_id' => Role::findByName('super-admin')->id]), $this->admin))
        ->toThrow(AccessRuleException::class);

    ['user' => $u] = $this->service->create(payload(['role_id' => Role::findByName('super-admin')->id]), $this->super);
    expect($u->hasRole('super-admin'))->toBeTrue();
});

test('email tidak dapat diubah setelah akun aktif, tetapi dapat sebelum aktif', function () {
    $aktif = User::factory()->create(['email' => 'lama@x.test'])->assignRole('admin-kecamatan');
    $this->service->update($aktif, payload(['email' => 'baru@x.test', 'role_id' => Role::findByName('admin-kecamatan')->id]), $this->super);
    expect($aktif->fresh()->email)->toBe('lama@x.test');

    ['user' => $pending] = $this->service->create(
        payload(['email' => 'p@x.test', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);
    $this->service->update($pending, payload(['email' => 'p2@x.test', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);
    expect($pending->fresh()->email)->toBe('p2@x.test');
});

test('tidak dapat mengubah role diri sendiri', function () {
    expect(fn () => $this->service->update($this->admin, payload([
        'email' => $this->admin->email, 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id,
    ]), $this->admin))->toThrow(AccessRuleException::class);
});

test('nonaktifkan dan aktifkan kembali, tidak boleh diri sendiri', function () {
    $target = User::factory()->create()->assignRole('operator-sekolah');

    $this->service->deactivate($target, $this->admin);

    expect($target->fresh()->is_active)->toBeFalse()
        ->and(fn () => $this->service->deactivate($this->admin, $this->admin))->toThrow(AccessRuleException::class);

    $this->service->activate($target);
    expect($target->fresh()->is_active)->toBeTrue();
});

test('super-admin aktif terakhir tidak dapat dinonaktifkan atau diturunkan', function () {
    $other = User::factory()->create()->assignRole('admin-kecamatan');

    expect(fn () => $this->service->deactivate($this->super, $other))->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->update($this->super, payload([
            'email' => $this->super->email, 'role_id' => Role::findByName('admin-kecamatan')->id,
        ]), $other))->toThrow(AccessRuleException::class);

    $second = User::factory()->create()->assignRole('super-admin');
    $this->service->deactivate($second, $this->super); // masih ada satu lagi → boleh
    expect($second->fresh()->is_active)->toBeFalse();
});

test('kirim ulang aktivasi hanya untuk akun belum aktif dan mencabut token lama', function () {
    ['user' => $user, 'token' => $old] = $this->service->create(
        payload(['role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);

    $new = $this->service->resendActivation($user);
    expect($new)->not->toBe($old);

    $aktif = User::factory()->create();
    expect(fn () => $this->service->resendActivation($aktif))->toThrow(AccessRuleException::class);
});
```
Catatan: test "super-admin aktif terakhir" memakai `$other` (admin-kecamatan) sebagai pelaku menonaktifkan super; pemeriksaan "anti eskalasi" terhadap target berhak lebih tinggi dilakukan di Policy (Task 12), bukan di service, sehingga service hanya menegakkan aturan super-admin terakhir.

Run: `php artisan test --filter=UserServiceTest` → Expected: FAIL.

- [ ] **Step 2: Repository**

`app/Domains/Access/Repositories/Contracts/UserRepositoryInterface.php`:
```php
<?php

namespace App\Domains\Access\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /** @return LengthAwarePaginator<int, User> */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): User;

    /** @param array<string, mixed> $attributes */
    public function update(User $user, array $attributes): User;

    public function activeSuperAdminCount(): int;
}
```
`app/Domains/Access/Repositories/EloquentUserRepository.php`:
```php
<?php

namespace App\Domains\Access\Repositories;

use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $base = User::query()
            ->with(['roles', 'school'])
            ->withExists(['activations as has_pending_activation' => fn (Builder $q) => $q->pending()])
            ->orderBy('name');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $w) => $w->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")
                )),
                AllowedFilter::callback('role', fn (Builder $q, $value) => $q->whereHas('roles', fn (Builder $r) => $r->where('roles.id', $value))),
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('status', function (Builder $q, $value) {
                    if ($status = UserStatus::tryFrom((string) $value)) {
                        $q->whereStatus($status);
                    }
                }),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }

    public function activeSuperAdminCount(): int
    {
        return User::role(Role::SUPER_ADMIN)->where('is_active', true)->count();
    }
}
```
Tambahkan binding `UserRepositoryInterface::class => EloquentUserRepository::class` di `RepositoryServiceProvider`.

- [ ] **Step 3: UserService**

`app/Domains/Access/Services/UserService.php`:
```php
<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Repositories\Contracts\RoleRepositoryInterface;
use App\Domains\Access\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly ActivationService $activations,
    ) {}

    /** @return LengthAwarePaginator<int, User> */
    public function list(): LengthAwarePaginator
    {
        return $this->users->paginate();
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, role_id: int, school_id?: ?int}  $data
     * @return array{user: User, token: string}
     */
    public function create(array $data, User $actor): array
    {
        $role = $this->roles->find($data['role_id']);
        $this->assertRoleAllowed($role, $data['school_id'] ?? null, $actor);

        return DB::transaction(function () use ($data, $role, $actor) {
            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $this->normalizeEmail($data['email']),
                'phone' => $data['phone'] ?? null,
                'school_id' => $role->is_school_scoped ? $data['school_id'] : null,
                'created_by' => $actor->id,
            ]);
            $user->assignRole($role);

            return ['user' => $user, 'token' => $this->activations->issue($user)];
        });
    }

    /** @param array{name: string, email: string, phone?: ?string, role_id: int, school_id?: ?int} $data */
    public function update(User $user, array $data, User $actor): User
    {
        $role = $this->roles->find($data['role_id']);
        $current = $user->roles->first();
        $roleChanged = $current?->id !== $role->id;

        if ($roleChanged) {
            if ($user->is($actor)) {
                throw new AccessRuleException('Anda tidak dapat mengubah role diri sendiri.');
            }
            $this->assertNotLastSuperAdmin($user, 'Super-admin aktif terakhir tidak dapat diturunkan role-nya.');
        }
        $this->assertRoleAllowed($role, $data['school_id'] ?? null, $actor);

        return DB::transaction(function () use ($user, $data, $role, $roleChanged) {
            $attributes = [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'school_id' => $role->is_school_scoped ? $data['school_id'] : null,
            ];
            if ($user->activated_at === null) {
                $attributes['email'] = $this->normalizeEmail($data['email']);
            }

            $this->users->update($user, $attributes);

            if ($roleChanged) {
                $user->syncRoles($role);
            }

            return $user;
        });
    }

    public function deactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new AccessRuleException('Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $this->assertNotLastSuperAdmin($user, 'Super-admin aktif terakhir tidak dapat dinonaktifkan.');

        DB::transaction(function () use ($user, $actor) {
            $this->users->update($user, ['is_active' => false]);
            activity('akses')->performedOn($user)->causedBy($actor)->log('user.dinonaktifkan');
        });
    }

    public function activate(User $user): void
    {
        $this->users->update($user, ['is_active' => true]);
        activity('akses')->performedOn($user)->causedBy(auth()->user())->log('user.diaktifkan');
    }

    public function resendActivation(User $user): string
    {
        if ($user->activated_at !== null) {
            throw new AccessRuleException('Akun ini sudah aktif; link aktivasi tidak diperlukan.');
        }

        return $this->activations->issue($user);
    }

    private function assertRoleAllowed(Role $role, ?int $schoolId, User $actor): void
    {
        if (! AccessGuard::canGrantRole($actor, $role)) {
            throw new AccessRuleException('Anda tidak dapat memberikan role dengan izin di luar izin Anda.');
        }
        if ($role->is_school_scoped && $schoolId === null) {
            throw new AccessRuleException('Role ini terikat sekolah; pilih sekolah.');
        }
        if (! $role->is_school_scoped && $schoolId !== null) {
            throw new AccessRuleException('Role ini tidak terikat sekolah; kosongkan sekolah.');
        }
    }

    private function assertNotLastSuperAdmin(User $user, string $message): void
    {
        if ($user->hasRole(Role::SUPER_ADMIN) && $user->is_active && $this->users->activeSuperAdminCount() <= 1) {
            throw new AccessRuleException($message);
        }
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
```

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=UserServiceTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah UserService dan repository user" -m "Pembuatan user dengan aktivasi, normalisasi email, aturan sekolah menurut role, anti eskalasi, email terkunci setelah aktif, nonaktifkan dengan penghapusan sesi, proteksi super-admin terakhir, dan kirim ulang aktivasi."
```

---

### Task 12: User (HTTP)

**Files:**
- Create: `app/Domains/Access/Http/Requests/UserRequest.php`, `app/Domains/Access/Http/Controllers/UserController.php`
- Modify: `app/Models/User.php` (relasi `creator()`), `app/Providers/AppServiceProvider.php` (Gate `manage-user`)
- Modify: `routes/access.php`
- Test: `tests/Feature/Access/UserHttpTest.php`

**Interfaces:**
- Consumes: `UserService`, `AccessGuard`, `RoleService::list()`, `SchoolService::options()`.
- Produces: rute `pengguna.index/create/store/show/edit/update/deactivate/activate/resend/done`. Props: `Pengguna/Index`: `{users: paginator(id,name,email,phone,role,school,status,last_login_at), filters, roles: [{id,name}], schools: [{id,name}], statuses: list<string>}`; `Pengguna/Form`: `{user: null|{id,name,email,phone,role_id,school_id,activated}, roles: [{id,name,is_school_scoped}], schools}`; `Pengguna/Show`: `{user: {…, created_by, created_at}}`; `Pengguna/Berhasil`: `{user: {id,name,role,school}, link: string}`. Gate `manage-user` (`AccessGuard::canManageUser`); `User::creator(): BelongsTo`.

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/UserHttpTest.php`:
```php
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
```
Run: `php artisan test --filter=UserHttpTest` → Expected: FAIL.

- [ ] **Step 2: Gate dan request**

Hak mengelola akun lain cukup satu `Gate`; tidak perlu kelas Policy. Di `AppServiceProvider::boot()` tambahkan (dengan `use App\Domains\Access\Services\AccessGuard;`):
```php
Gate::define('manage-user', fn (User $actor, User $target) => AccessGuard::canManageUser($actor, $target));
```
(`Gate::before` milik super-admin tetap lebih dahulu, jadi super-admin selalu lolos.)

`app/Domains/Access/Http/Requests/UserRequest.php` (satu kelas untuk tambah dan ubah; `$this->route('user')` kosong saat tambah):
```php
<?php

namespace App\Domains\Access\Http\Requests;

use App\Domains\Access\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $scoped = Role::find($this->input('role_id'))?->is_school_scoped ?? false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'regex:/^08[0-9]{8,12}$/'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'school_id' => [
                'nullable', 'integer', 'exists:schools,id',
                Rule::requiredIf($scoped),
                Rule::prohibitedIf(! $scoped),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'phone.regex' => 'Nomor HP harus diawali 08 dan berisi 10–14 digit.',
            'school_id.required' => 'Role ini terikat sekolah; pilih sekolah.',
            'school_id.prohibited' => 'Role ini tidak terikat sekolah; kosongkan sekolah.',
        ];
    }
}
```

- [ ] **Step 3: Relasi `creator()` dan controller**

Di `app/Models/User.php` tambahkan (tanpa scope sekolah, agar pembuat dari sekolah lain tetap terbaca):
```php
/** @return BelongsTo<User, $this> */
public function creator(): BelongsTo
{
    return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
}
```

`app/Domains/Access/Http/Controllers/UserController.php`:
```php
<?php

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Enums\UserStatus;
use App\Domains\Access\Http\Requests\UserRequest;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\RoleService;
use App\Domains\Access\Services\UserService;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly RoleService $roles,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Pengguna/Index', [
            'users' => $this->users->list()->through(fn (User $u) => $this->row($u)),
            'filters' => $request->query('filter', []),
            'roles' => $this->roles->list()->map->only(['id', 'name'])->values(),
            'schools' => $this->schools->options(),
            'statuses' => array_map(fn (UserStatus $s) => $s->value, UserStatus::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Pengguna/Form', ['user' => null] + $this->formOptions());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        ['user' => $user, 'token' => $token] = $this->users->create($request->validated(), $request->user());

        return $this->toSuccess($user, $token);
    }

    public function show(Request $request, User $user): Response
    {
        return Inertia::render('Pengguna/Show', ['user' => $this->row($user) + [
            'created_by' => $user->creator?->name,
            'created_at' => $user->created_at,
            'can_manage' => $request->user()->can('manage-user', $user),
        ]]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('manage-user', $user);

        return Inertia::render('Pengguna/Form', [
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                'role_id' => $user->roles->first()?->id, 'school_id' => $user->school_id,
                'activated' => $user->activated_at !== null,
            ],
        ] + $this->formOptions());
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('manage-user', $user);
        $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('pengguna.show', $user)->with('success', 'Pengguna diperbarui.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage-user', $user);
        $this->users->deactivate($user, $request->user());

        return back()->with('success', 'Pengguna dinonaktifkan.');
    }

    public function activate(User $user): RedirectResponse
    {
        $this->authorize('manage-user', $user);
        $this->users->activate($user);

        return back()->with('success', 'Pengguna diaktifkan.');
    }

    public function resend(User $user): RedirectResponse
    {
        $this->authorize('manage-user', $user);

        return $this->toSuccess($user, $this->users->resendActivation($user));
    }

    /** Link aktivasi hanya tampil sekali: dibaca dari flash session. */
    public function done(User $user): Response|RedirectResponse
    {
        $link = session('activation_link');

        if ($link === null) {
            return redirect()->route('pengguna.show', $user);
        }

        return Inertia::render('Pengguna/Berhasil', [
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'phone' => $user->phone,
                'role' => $user->roles->first()?->name, 'school' => $user->school?->name,
            ],
            'link' => $link,
        ]);
    }

    private function toSuccess(User $user, string $token): RedirectResponse
    {
        return redirect()->route('pengguna.done', $user)->with('activation_link', route('aktivasi.show', $token));
    }

    /** @return array<string, mixed> */
    private function row(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'role' => $u->roles->first()?->name, 'school' => $u->school?->name,
            'status' => $u->status->value, 'last_login_at' => $u->last_login_at,
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'roles' => $this->roles->list()->map(fn (Role $r) => $r->only(['id', 'name', 'is_school_scoped']))->values(),
            'schools' => $this->schools->options(),
        ];
    }
}
```
Tambahkan di grup `auth` pada `routes/access.php` (dengan `use UserController`):
```php
Route::get('pengguna', [UserController::class, 'index'])->middleware('can:user.lihat')->name('pengguna.index');
Route::get('pengguna/buat', [UserController::class, 'create'])->middleware('can:user.buat')->name('pengguna.create');
Route::post('pengguna', [UserController::class, 'store'])->middleware('can:user.buat')->name('pengguna.store');
Route::get('pengguna/{user}', [UserController::class, 'show'])->middleware('can:user.lihat')->name('pengguna.show');
Route::get('pengguna/{user}/berhasil', [UserController::class, 'done'])->middleware('can:user.lihat')->name('pengguna.done');
Route::get('pengguna/{user}/ubah', [UserController::class, 'edit'])->middleware('can:user.ubah')->name('pengguna.edit');
Route::put('pengguna/{user}', [UserController::class, 'update'])->middleware('can:user.ubah')->name('pengguna.update');
Route::post('pengguna/{user}/nonaktifkan', [UserController::class, 'deactivate'])->middleware('can:user.nonaktifkan')->name('pengguna.deactivate');
Route::post('pengguna/{user}/aktifkan', [UserController::class, 'activate'])->middleware('can:user.nonaktifkan')->name('pengguna.activate');
Route::post('pengguna/{user}/kirim-ulang-aktivasi', [UserController::class, 'resend'])->middleware('can:user.kirim-ulang-aktivasi')->name('pengguna.resend');
```
Urutan: `pengguna/buat` didefinisikan sebelum `pengguna/{user}`.

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=UserHttpTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah CRUD pengguna dengan halaman berhasil" -m "Daftar dengan filter, form tambah/ubah, link aktivasi tampil sekali lewat flash session, nonaktifkan dan aktifkan, kirim ulang aktivasi. Gate manage-user menolak (403) pengelolaan akun berhak lebih tinggi; data sekolah lain 404 lewat scope sekolah."
```

---

### Task 13: Menu server-side

**Files:**
- Create: `app/Domains/Access/Services/MenuService.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Access/MenuTest.php`

**Interfaces:**
- Consumes: `AccessGuard::permissionsOf`.
- Produces: `MenuService::forUser(?User): list<array{group: string, items: list<array{label: string, href: string, icon: string}>}>`; shared prop `menu`.

- [ ] **Step 1: Test (gagal dulu)**

`tests/Feature/Access/MenuTest.php`:
```php
<?php

use App\Domains\Access\Services\MenuService;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('menu disaring menurut izin dan grup kosong dibuang', function () {
    $this->seed(AccessSeeder::class);
    $operator = User::factory()->create()->assignRole('operator-sekolah');
    $admin = User::factory()->create()->assignRole('admin-kecamatan');

    $menuOperator = app(MenuService::class)->forUser($operator);
    $menuAdmin = app(MenuService::class)->forUser($admin);

    expect(collect($menuOperator)->pluck('group')->all())->toBe(['Utama'])
        ->and(collect($menuAdmin)->pluck('group')->all())->toBe(['Utama', 'Manajemen Akses', 'Master Data'])
        ->and(collect($menuAdmin)->firstWhere('group', 'Manajemen Akses')['items'][0])
        ->toBe(['label' => 'Pengguna', 'href' => '/pengguna', 'icon' => 'users']);
});

test('tamu tidak menerima menu dan menu dibagikan sebagai shared prop', function () {
    expect(app(MenuService::class)->forUser(null))->toBe([]);

    $user = makeUserWith(['sekolah.lihat']);
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->where('menu.1.group', 'Master Data')
        ->where('menu.1.items.0.href', '/sekolah'));
});
```
Run: `php artisan test --filter=MenuTest` → Expected: FAIL.

- [ ] **Step 2: Implementasi**

`app/Domains/Access/Services/MenuService.php`:
```php
<?php

namespace App\Domains\Access\Services;

use App\Models\User;

class MenuService
{
    /** @var list<array{group: string, label: string, href: string, icon: string, permission: ?string}> */
    private const ITEMS = [
        ['group' => 'Utama', 'label' => 'Beranda', 'href' => '/dashboard', 'icon' => 'home', 'permission' => null],
        ['group' => 'Manajemen Akses', 'label' => 'Pengguna', 'href' => '/pengguna', 'icon' => 'users', 'permission' => 'user.lihat'],
        ['group' => 'Manajemen Akses', 'label' => 'Role & Permission', 'href' => '/role', 'icon' => 'shield-check', 'permission' => 'role.lihat'],
        ['group' => 'Master Data', 'label' => 'Sekolah', 'href' => '/sekolah', 'icon' => 'school', 'permission' => 'sekolah.lihat'],
    ];

    /** @return list<array{group: string, items: list<array{label: string, href: string, icon: string}>}> */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $owned = AccessGuard::permissionsOf($user);
        $groups = [];

        foreach (self::ITEMS as $item) {
            if ($item['permission'] !== null && ! in_array($item['permission'], $owned, true)) {
                continue;
            }
            $groups[$item['group']][] = ['label' => $item['label'], 'href' => $item['href'], 'icon' => $item['icon']];
        }

        return array_map(fn (string $group, array $items) => ['group' => $group, 'items' => $items], array_keys($groups), $groups);
    }
}
```
Di `HandleInertiaRequests::share()` tambahkan kunci `'menu' => fn () => app(MenuService::class)->forUser($user),` setelah `'flash'` (dengan `use`).

- [ ] **Step 3: Jalankan dan commit**

Run: `php artisan test --filter=MenuTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: kirim menu sidebar dari server" -m "MenuService menyaring daftar menu menurut izin pengguna dan dibagikan sebagai shared prop menu dengan kunci ikon."
```

---

### Task 14: Audit log otomatis pada model

**Files:**
- Modify: `app/Models/User.php`, `app/Domains/Access/Models/Role.php`, `app/Domains/Schools/Models/School.php`
- Test: `tests/Feature/Access/AuditLogTest.php`

**Interfaces:**
- Produces: `LogsActivity` pada `User`, `Role`, `School` (log name `akses`, hanya atribut berubah, tanpa entri kosong, tanpa `password`/token).

- [ ] **Step 1: Cari nama trait dan opsi pada versi terpasang**

```bash
grep -rn "trait LogsActivity\|class LogOptions" vendor/spatie/laravel-activitylog/src
grep -n "activity_model\|^use " config/activitylog.php | head
```
Catat namespace trait `LogsActivity` dan kelas `LogOptions` (pada v5 kemungkinan `Spatie\Activitylog\Models\Concerns\LogsActivity` dan `Spatie\Activitylog\Support\LogOptions`; pakai yang ditemukan).

- [ ] **Step 2: Test (gagal dulu)**

`tests/Feature/Access/AuditLogTest.php` (sesuaikan `use Activity` dengan `activity_model` hasil langkah 1):
```php
<?php

use App\Domains\Access\Models\Role;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('perubahan user, role, dan sekolah tercatat dengan pelaku', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $school = School::factory()->create(['name' => 'Awal']);
    $school->update(['name' => 'Akhir']);
    $role = Role::create(['name' => 'baru']);

    expect(Activity::where('subject_type', School::class)->where('event', 'updated')->count())->toBe(1)
        ->and(Activity::where('subject_type', Role::class)->where('event', 'created')->count())->toBe(1)
        ->and(Activity::where('subject_type', School::class)->where('event', 'updated')->first()->causer_id)->toBe($actor->id);
});

test('password dan token tidak pernah masuk log', function () {
    $user = User::factory()->create(['password' => 'rahasia-banget-123']);
    $user->update(['password' => 'rahasia-baru-456', 'name' => 'Ganti']);

    $json = Activity::all()->toJson();

    expect($json)->not->toContain('rahasia')
        ->and($json)->not->toContain('remember_token');
});

test('update tanpa perubahan tidak membuat entri kosong', function () {
    $school = School::factory()->create();
    $before = Activity::count();

    $school->update(['name' => $school->name]);

    expect(Activity::count())->toBe($before);
});
```
Run: `php artisan test --filter=AuditLogTest` → Expected: FAIL.

- [ ] **Step 3: Pasang trait**

Pada ketiga model tambahkan trait `LogsActivity` (namespace dari langkah 1) dan metode (contoh untuk `User`; `Role`: `['name', 'description', 'is_school_scoped']`; `School`: `['name', 'npsn', 'level', 'address']`):
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->useLogName('akses')
        ->logOnly(['name', 'email', 'phone', 'school_id', 'is_active'])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```
`password`, `remember_token`, dan token tidak ada di `logOnly`, jadi tidak pernah tercatat.

- [ ] **Step 4: Jalankan dan commit**

Run: `php artisan test --filter=AuditLogTest` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: catat perubahan user, role, dan sekolah ke audit log" -m "LogsActivity pada User, Role, dan School dengan log name akses, hanya atribut berubah, tanpa entri kosong, dan tanpa password atau token."
```

---

### Task 15: Command super-admin pertama dan matriks rute × izin

**Files:**
- Create: `app/Console/Commands/CreateSuperAdmin.php`
- Test: `tests/Feature/Access/CreateSuperAdminTest.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`

**Interfaces:**
- Consumes: `ActivationService::issue`, `AccessSeeder`.
- Produces: `php artisan sijebol:create-super-admin {email} {name?}` (mencetak link aktivasi).

- [ ] **Step 1: Test command (gagal dulu)**

`tests/Feature/Access/CreateSuperAdminTest.php`:
```php
<?php

use App\Domains\Access\Models\AccountActivation;
use App\Models\User;
use Database\Seeders\AccessSeeder;

test('membuat super-admin pertama dan mencetak link aktivasi', function () {
    $this->seed(AccessSeeder::class);

    $this->artisan('sijebol:create-super-admin', ['email' => 'Root@Kecamatan.test', 'name' => 'Root'])
        ->expectsOutputToContain('/aktivasi/')
        ->assertSuccessful();

    $user = User::firstWhere('email', 'root@kecamatan.test');
    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->activated_at)->toBeNull()
        ->and(AccountActivation::where('user_id', $user->id)->exists())->toBeTrue();
});

test('gagal dengan pesan jelas bila role belum di-seed atau email sudah ada', function () {
    $this->artisan('sijebol:create-super-admin', ['email' => 'a@x.test'])->assertFailed();

    $this->seed(AccessSeeder::class);
    User::factory()->create(['email' => 'a@x.test']);
    $this->artisan('sijebol:create-super-admin', ['email' => 'a@x.test'])->assertFailed();
});
```
Run: `php artisan test --filter=CreateSuperAdminTest` → Expected: FAIL.

- [ ] **Step 2: Command**

`app/Console/Commands/CreateSuperAdmin.php`:
```php
<?php

namespace App\Console\Commands;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\ActivationService;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'sijebol:create-super-admin {email} {name?}';

    protected $description = 'Buat super-admin pertama dan cetak link aktivasinya';

    public function handle(ActivationService $activations): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $role = Role::where('name', Role::SUPER_ADMIN)->first();

        if ($role === null) {
            $this->error('Role super-admin belum ada. Jalankan: php artisan db:seed --class=AccessSeeder');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("Email {$email} sudah terdaftar.");

            return self::FAILURE;
        }

        $user = User::create(['name' => $this->argument('name') ?? 'Super Admin', 'email' => $email]);
        $user->assignRole($role);

        $this->info('Super-admin dibuat. Kirim link ini (berlaku 3 hari):');
        $this->line(route('aktivasi.show', $activations->issue($user)));

        return self::SUCCESS;
    }
}
```
Command ini sengaja tidak lewat `UserService` karena tidak ada pelaku yang login.

- [ ] **Step 3: Test matriks rute × izin**

`tests/Feature/Access/RoutePermissionMatrixTest.php`:
```php
<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Schools\Models\School;
use App\Models\User;

dataset('rute', [
    ['GET', '/sekolah', 'sekolah.lihat'],
    ['GET', '/sekolah/buat', 'sekolah.buat'],
    ['POST', '/sekolah', 'sekolah.buat'],
    ['GET', '/sekolah/{school}/ubah', 'sekolah.ubah'],
    ['PUT', '/sekolah/{school}', 'sekolah.ubah'],
    ['GET', '/pengguna', 'user.lihat'],
    ['GET', '/pengguna/buat', 'user.buat'],
    ['POST', '/pengguna', 'user.buat'],
    ['GET', '/pengguna/{user}', 'user.lihat'],
    ['GET', '/pengguna/{user}/berhasil', 'user.lihat'],
    ['GET', '/pengguna/{user}/ubah', 'user.ubah'],
    ['PUT', '/pengguna/{user}', 'user.ubah'],
    ['POST', '/pengguna/{user}/nonaktifkan', 'user.nonaktifkan'],
    ['POST', '/pengguna/{user}/aktifkan', 'user.nonaktifkan'],
    ['POST', '/pengguna/{user}/kirim-ulang-aktivasi', 'user.kirim-ulang-aktivasi'],
    ['GET', '/role', 'role.lihat'],
    ['GET', '/role/buat', 'role.buat'],
    ['POST', '/role', 'role.buat'],
    ['GET', '/role/{role}/ubah', 'role.ubah'],
    ['PUT', '/role/{role}', 'role.ubah'],
    ['DELETE', '/role/{role}', 'role.hapus'],
]);

function resolveUri(string $uri): string
{
    $target = User::factory()->create();
    $school = School::factory()->create();
    $role = Role::create(['name' => 'sasaran-'.uniqid()]);

    return str_replace(['{user}', '{school}', '{role}'], [$target->id, $school->id, $role->id], $uri);
}

it('menolak tanpa izin dan tidak menolak dengan izin', function (string $method, string $uri, string $permission) {
    $uri = resolveUri($uri);
    $others = array_values(array_diff(Permission::values(), [$permission]));

    $this->actingAs(makeUserWith($others, roleName: 'tanpa'))->call($method, $uri)->assertForbidden();

    $status = $this->actingAs(makeUserWith([$permission], roleName: 'dengan'))->call($method, $uri)->getStatusCode();
    expect($status)->not->toBe(403);
})->with('rute');
```

- [ ] **Step 4: Jalankan semua dan commit**

Run: `php artisan test --filter="CreateSuperAdminTest|RoutePermissionMatrixTest"` → PASS; lalu `composer test && composer analyse && ./vendor/bin/pint`.
```bash
git add -A
git commit -m "feat: tambah command super-admin pertama dan test matriks rute-izin" -m "sijebol:create-super-admin membuat super-admin tanpa password dan mencetak link aktivasi. Test matriks memastikan setiap rute sensitif 403 tanpa izin."
```

---

## Gerbang BE Fase 1 (setelah Task 15)

- [ ] `composer test`, `composer analyse`, `composer lint` lulus.
- [ ] `php artisan migrate:fresh --seed` sukses; `php artisan sijebol:create-super-admin dev@sijebol.test` mencetak link; membuka link di browser (setelah FE ada) atau `curl` menunjukkan `Auth/Activate` props valid.
- [ ] `php artisan route:list --path=pengguna` (dan `role`, `sekolah`, `aktivasi`) menampilkan middleware `can:*` pada seluruh rute kecuali `aktivasi`.
- [ ] Review: `ponytail:ponytail-review` pada seluruh perubahan; perbaiki temuan valid; lalu `superpowers:verification-before-completion`.
- [ ] Perbarui `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`: tandai Track BE Fase 1 selesai dan catat penyimpangan.

---

## Self-Review

**Cakupan spec:** §2 keputusan → Task 1–3 (kolom, status, password, super-admin), Task 6 (login), Task 11 (email); §3 data/seeder/command → Task 1, 2, 15; §4 scope → Task 3, 12; §5 rute/izin → Task 5, 8, 10, 12, 15 (matriks); §6 aktivasi → Task 4–5, 12; pengguna → Task 11–12; anti eskalasi → Task 9, 11, 12; role → Task 9–10; menu → Task 13; §7 audit → Task 4, 6, 9, 11, 14; §8 error → Task 9 (handler), 12 (flash test); §9 pengujian → tersebar plus Task 15. Celah yang disengaja: `SchoolPolicy`/`RolePolicy` ditiadakan (tercatat di Global Constraints).

**Placeholder:** tidak ada TBD; langkah yang bergantung pada versi paket (nama trait activitylog, varian command) memuat perintah penentu dan aturan keputusan eksplisit.

**Konsistensi tipe:** `AccessGuard::permissionsOf/canGrantRole/canManageUser` dipakai sama di Task 9, 11, 12, 13; `ActivationService::issue/findValid/complete` di Task 4, 5, 11, 15; `UserStatus` dan `User::whereStatus` di Task 3 dan 11; payload `role_id/school_id` konsisten di `UserService`, request, dan controller.
