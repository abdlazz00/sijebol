# Fase 4 — Pendaftaran dan Verifikasi: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, tanpa subagent) untuk menjalankan rencana ini tugas demi tugas. Steps memakai checkbox (`- [ ]`).

**Goal:** Operator sekolah membuat draf pendaftaran, memilih siswa eligible, dan mengajukannya; petugas kecamatan memutuskan per siswa (setujui, minta perbaikan, tolak); siswa yang diminta perbaikan diajukan ulang. Satu siswa tidak pernah punya dua entri aktif.

**Architecture:** Domain baru `app/Domains/Registrations` (Controller → FormRequest → Service → Repository interface + Eloquent). Mesin status berupa enum dengan peta transisi dan satu pintu perubahan (`StatusTransitioner`). Kolom generated `active_student_id` + indeks unik menjaga satu entri aktif per siswa di basis data. Eligibility dan kunci data siswa memperluas domain Students.

**Tech Stack:** Laravel 13 / PHP 8.5 / MySQL 8, spatie permission + activitylog + query-builder, Inertia v2 + Vue 3 + TS + Tailwind v4, Pest, Larastan 5, Pint, Vitest, vue-tsc, ESLint.

**Spec:** `docs/superpowers/specs/2026-10-08-fase-4-pendaftaran-verifikasi-design.md`

## Global Constraints

- Bahasa UI, pesan galat, dan komentar: Indonesia. Pola service-repository (interface di `Repositories/Contracts` + `Eloquent*`, diikat di `app/Providers/RepositoryServiceProvider.php`).
- Commit message detail (ringkasan di baris pertama, isi menjelaskan apa dan mengapa). **Dilarang** `Co-Authored-By` atau atribusi tool/AI apa pun. Commit mengikuti langkah commit di rencana ini.
- Berkas PHP/Vue besar ditulis dengan Write (bukan heredoc bash); jangan memakai `sed -i` untuk teks berbahasa Indonesia.
- Nama prop halaman Inertia tidak boleh `auth`, `flash`, `menu`, `notifications`.
- activitylog v5 menyimpan perubahan model di `attribute_changes`; urutan kunci JSON MySQL tidak terjaga (jangan bergantung padanya di tes).
- Rute baru dimuat dari `routes/web.php` sebelum `Route::any('{path}')` fallback. POST memakai `throttle:60,1`.
- Role terikat sekolah dilarang memegang `log.lihat`. Entri/pendaftaran sekolah lain harus 404 lewat global scope `ScopedBySchool`.
- Status aktif (indeks unik): `diajukan`, `perlu_perbaikan`, `terverifikasi`, `terjadwal`, `sudah_rekaman`. Status mengunci data siswa: `diajukan`, `terverifikasi`, `terjadwal`, `sudah_rekaman`.
- Playwright di sesi ini tidak meneruskan input keyboard/mouse: gunakan `browser_evaluate` dengan `element.click()`/`dispatchEvent`; `setInputFiles` berfungsi.

## Rulings terhadap spec (dicatat sebelum eksekusi)

- **R1:** Kolom `schedule_id` dan transisi Fase 5 (`terverifikasi → terjadwal`, dst.) tidak dibuat sekarang; Fase 5 menambah migrasi dan transisinya sendiri. Kasus enum tetap lengkap (label dan status). Biayanya: satu migrasi kecil di Fase 5.
- **R2:** Tombol **Ajukan** berada di halaman detail draf (dengan dialog konfirmasi), bukan di form; form hanya "Simpan draf". Alasan: pengajuan yang gagal setelah draf baru dibuat membuat draf ganda bila diulang dari form. Galat per siswa muncul di tabel detail.
- **R3:** Galat pengajuan per siswa memakai kunci `student_ids.{id}` (Inertia hanya meneruskan satu pesan per kunci), bukan satu daftar `{name, reasons}`.
- **R4:** `from_status` pada riwayat tidak nullable dan riwayat dimulai pada transisi pertama (pembuatan draf tidak menulis riwayat).
- **R5:** Satu `VerificationService::decide()` menggantikan tiga metode (`approve/requestFix/reject`); `RegistrationStatus` dipetakan dari kata keputusan.
- **R6:** Label status untuk FE dipetakan di `lib/status.ts` (seperti `userStatus`), server tidak mengirim `status_label`.

## Review Focus

Kondisi yang tidak dicakup spec secara eksplisit tetapi paling mungkin menggigit, dan tugas yang menguncinya dengan tes:

1. Dua pengajuan bersamaan berisi siswa yang sama: pemeriksaan eligibility gagal bila sudah terlihat; bila lolos karena balapan, indeks unik menolak dan pengguna mendapat pesan jelas, tanpa entri setengah jadi (Task 5, 1).
2. Siswa yang menjadi tidak eligible setelah masuk draf (dokumen dihapus, umur lewat) gagal saat Ajukan, dengan alasan per siswa dan tanpa perubahan apa pun (Task 5).
3. Dua petugas memutuskan entri yang sama: yang kedua ditolak, tanpa riwayat ganda (Task 2, 6).
4. Pengajuan tanpa siswa, dan staf kecamatan membuat draf tanpa memilih sekolah (Task 5, 7).
5. Operator sekolah A memanggil URL entri/pendaftaran sekolah B, termasuk entri yang bukan milik pendaftaran pada URL bertingkat → 404 (Task 7).

---

## Peta berkas

**Baru (backend):** `app/Domains/Registrations/{Enums/RegistrationStatus.php, Models/{Registration,RegistrationStudent,RegistrationStatusHistory}.php, Services/{StatusTransitioner,RegistrationService,VerificationService}.php, Repositories/Contracts/RegistrationRepositoryInterface.php, Repositories/EloquentRegistrationRepository.php, Http/Controllers/{RegistrationController,VerificationController}.php, Http/Requests/{RegistrationRequest,DecisionRequest}.php, Support/EntryPresenter.php}`, `app/Domains/Notifications/Notifications/{RegistrationSubmittedNotification,EntryDecidedNotification}.php`, `database/migrations/2026_10_08_20000{1,2,3}_*.php`, `database/factories/{RegistrationFactory,RegistrationStudentFactory}.php`, `routes/registrations.php`.

**Diubah (backend):** `app/Domains/Students/{Models/Student.php, Services/{EligibilityService,StudentService,StudentDocumentService}.php, Repositories/{Contracts/StudentRepositoryInterface,EloquentStudentRepository}.php, Http/Controllers/StudentController.php}`, `app/Domains/Schools/Services/SchoolService.php`, `app/Domains/Notifications/{Services/NotificationService.php, Repositories/Contracts/NotificationRepositoryInterface.php, Repositories/EloquentNotificationRepository.php}`, `app/Domains/Access/Services/MenuService.php`, `app/Providers/RepositoryServiceProvider.php`, `routes/web.php`, `lang/id/activity.php`, `tests/Pest.php`, `tests/Feature/Access/{RoutePermissionMatrixTest,MenuTest}.php`.

**Baru (frontend):** `resources/js/types/registration.ts`, `resources/js/Components/shared/{StatusTimeline,StudentMultiSelect}.vue` (+ `StudentMultiSelect.test.ts`), `resources/js/Pages/Pendaftaran/{Index,Form,Show}.vue`, `resources/js/Pages/Verifikasi/{Index,Show}.vue`.

**Diubah (frontend/docs):** `resources/js/lib/status.ts` (+test), `resources/js/Components/shared/StatusBadge.vue`, `resources/js/config/menuIcons.ts`, `resources/js/Pages/Siswa/Show.vue`, `resources/js/types/student.ts`, panduan UI, rencana sprint, memory `deferred-items`.

---

## BACKEND

### Task 1: Enum status, migrasi, model, factory

**Files:**
- Create: `app/Domains/Registrations/Enums/RegistrationStatus.php`, `app/Domains/Registrations/Models/{Registration,RegistrationStudent,RegistrationStatusHistory}.php`, `database/migrations/2026_10_08_200001_create_registrations_table.php`, `..._200002_create_registration_students_table.php`, `..._200003_create_registration_status_histories_table.php`, `database/factories/RegistrationFactory.php`, `database/factories/RegistrationStudentFactory.php`
- Modify: `app/Domains/Students/Models/Student.php`, `tests/Pest.php`
- Test: `tests/Feature/Registrations/RegistrationModelTest.php`

**Interfaces:**
- Produces: `RegistrationStatus` (cases `Draf, Diajukan, PerluPerbaikan, Terverifikasi, Terjadwal, SudahRekaman, TidakHadir, BelumRekaman, Ditolak`; `label(): string`, `next(): list<self>`, `isActive(): bool`, `locksStudent(): bool`, `static active(): list<self>`, `static locking(): list<self>`, `static values(list<self>): list<string>`). Model `Registration` (`isDraft(): bool`, relasi `school`, `creator`, `entries`), `RegistrationStudent` (cast `status`, relasi `registration`, `student`, `histories`), `RegistrationStatusHistory` (cast `from_status/to_status`, relasi `user`). `Student::registrationEntries()`, `Student::isLocked(): bool`, `Student::LOCKED_MESSAGE`. Helper tes global `makeEntry(RegistrationStatus $status = Draf, ?School $school = null, ?Student $student = null): RegistrationStudent` dan `eligibleStudent(?School $school = null): Student`.

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan di akhir `tests/Pest.php` (dan impor di bagian `use` atas: `App\Domains\Registrations\Enums\RegistrationStatus`, `App\Domains\Registrations\Models\Registration`, `App\Domains\Registrations\Models\RegistrationStudent`, `App\Domains\Students\Models\Student`):

```php
/** Entri pendaftaran dengan status tertentu; siswa dan sekolah dibuat bila tidak diberikan. */
function makeEntry(RegistrationStatus $status = RegistrationStatus::Draf, ?School $school = null, ?Student $student = null): RegistrationStudent
{
    $school ??= $student?->school ?? School::factory()->create();
    $student ??= Student::factory()->for($school)->create();
    $registration = Registration::factory()->for($school)->create([
        'submitted_at' => $status === RegistrationStatus::Draf ? null : now(),
    ]);

    return RegistrationStudent::factory()->create([
        'registration_id' => $registration->id,
        'school_id' => $school->id,
        'student_id' => $student->id,
        'status' => $status,
    ]);
}

/** Siswa umur 16 dengan KK dan akta: memenuhi semua syarat eligibility. */
function eligibleStudent(?School $school = null): Student
{
    $student = Student::factory()->for($school ?? School::factory()->create())->create();
    foreach (['kk', 'akta_lahir'] as $type) {
        $student->documents()->create(['type' => $type, 'path' => "students/{$student->id}/{$type}.pdf", 'mime' => 'application/pdf', 'size' => 1]);
    }

    return $student->load('documents');
}
```

Buat `tests/Feature/Registrations/RegistrationModelTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Students\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;

test('indeks unik menolak dua entri aktif untuk siswa yang sama', function () {
    $student = Student::factory()->create();
    makeEntry(S::Diajukan, student: $student);

    expect(fn () => makeEntry(S::Terverifikasi, student: $student))->toThrow(UniqueConstraintViolationException::class);
});

test('entri draf dan ditolak tidak dihitung aktif', function () {
    $student = Student::factory()->create();
    makeEntry(S::Draf, student: $student);
    makeEntry(S::Draf, student: $student);
    makeEntry(S::Ditolak, student: $student);
    makeEntry(S::Diajukan, student: $student);

    expect(RegistrationStudent::where('student_id', $student->id)->count())->toBe(4);
});

test('peta status dan label enum', function () {
    expect(S::Draf->next())->toBe([S::Diajukan])
        ->and(S::Diajukan->next())->toBe([S::Terverifikasi, S::PerluPerbaikan, S::Ditolak])
        ->and(S::PerluPerbaikan->next())->toBe([S::Diajukan])
        ->and(S::Ditolak->next())->toBe([])
        ->and(S::PerluPerbaikan->isActive())->toBeTrue()
        ->and(S::PerluPerbaikan->locksStudent())->toBeFalse()
        ->and(S::Ditolak->isActive())->toBeFalse()
        ->and(S::PerluPerbaikan->label())->toBe('Perlu perbaikan');
});

test('siswa terkunci hanya saat entri sedang diproses', function (S $status, bool $locked) {
    $student = Student::factory()->create();
    makeEntry($status, student: $student);

    expect($student->isLocked())->toBe($locked);
})->with([
    [S::Draf, false], [S::Diajukan, true], [S::PerluPerbaikan, false],
    [S::Terverifikasi, true], [S::Ditolak, false],
]);
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Registrations/RegistrationModelTest.php`
Expected: FAIL (class `RegistrationStatus` / `Registration` tidak ditemukan).

- [ ] **Step 3: Implementasi enum**

`app/Domains/Registrations/Enums/RegistrationStatus.php`:

```php
<?php

namespace App\Domains\Registrations\Enums;

enum RegistrationStatus: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case PerluPerbaikan = 'perlu_perbaikan';
    case Terverifikasi = 'terverifikasi';
    case Terjadwal = 'terjadwal';
    case SudahRekaman = 'sudah_rekaman';
    case TidakHadir = 'tidak_hadir';
    case BelumRekaman = 'belum_rekaman';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::PerluPerbaikan => 'Perlu perbaikan',
            self::Terverifikasi => 'Terverifikasi',
            self::Terjadwal => 'Terjadwal',
            self::SudahRekaman => 'Sudah rekaman',
            self::TidakHadir => 'Tidak hadir',
            self::BelumRekaman => 'Belum rekaman',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * Transisi yang sah dari status ini. Transisi penjadwalan dan rekaman ditambahkan di Fase 5.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Draf => [self::Diajukan],
            self::Diajukan => [self::Terverifikasi, self::PerluPerbaikan, self::Ditolak],
            self::PerluPerbaikan => [self::Diajukan],
            default => [],
        };
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function locksStudent(): bool
    {
        return in_array($this, self::locking(), true);
    }

    /**
     * Status yang menghitung sebagai satu-satunya entri aktif siswa (sama dengan kolom `active_student_id`).
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Diajukan, self::PerluPerbaikan, self::Terverifikasi, self::Terjadwal, self::SudahRekaman];
    }

    /**
     * Status yang mengunci data dan dokumen siswa.
     *
     * @return list<self>
     */
    public static function locking(): array
    {
        return [self::Diajukan, self::Terverifikasi, self::Terjadwal, self::SudahRekaman];
    }

    /**
     * @param  list<self>  $cases
     * @return list<string>
     */
    public static function values(array $cases): array
    {
        return array_map(fn (self $case) => $case->value, $cases);
    }
}
```

- [ ] **Step 4: Migrasi**

`2026_10_08_200001_create_registrations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->string('title', 150);
            $table->unsignedSmallInteger('expected_count')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
```

`2026_10_08_200002_create_registration_students_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draf');
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            // Terisi hanya saat status aktif: indeks unik di bawah menjaga satu entri aktif per siswa.
            // Daftar status ini harus sama dengan RegistrationStatus::active().
            $table->unsignedBigInteger('active_student_id')->nullable()->storedAs(
                "CASE WHEN status IN ('diajukan','perlu_perbaikan','terverifikasi','terjadwal','sudah_rekaman') THEN student_id END"
            );
            $table->timestamps();

            $table->unique(['registration_id', 'student_id']);
            $table->unique('active_student_id');
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_students');
    }
};
```

`2026_10_08_200003_create_registration_status_histories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_student_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->foreignId('by_user_id')->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_status_histories');
    }
};
```

- [ ] **Step 5: Model dan factory**

`app/Domains/Registrations/Models/Registration.php`:

```php
<?php

namespace App\Domains\Registrations\Models;

use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property string $title
 * @property int|null $expected_count
 * @property int $created_by
 * @property Carbon|null $submitted_at
 * @property Carbon $created_at
 */
#[UseFactory(RegistrationFactory::class)]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = ['school_id', 'title', 'expected_count', 'created_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function isDraft(): bool
    {
        return $this->submitted_at === null;
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<RegistrationStudent, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(RegistrationStudent::class);
    }
}
```

`RegistrationStudent.php`:

```php
<?php

namespace App\Domains\Registrations\Models;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Students\Models\Student;
use App\Models\Concerns\ScopedBySchool;
use Carbon\Carbon;
use Database\Factories\RegistrationStudentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $registration_id
 * @property int $school_id
 * @property int $student_id
 * @property RegistrationStatus $status
 * @property string|null $note
 * @property Carbon|null $decided_at
 * @property Carbon $updated_at
 */
#[UseFactory(RegistrationStudentFactory::class)]
class RegistrationStudent extends Model
{
    /** @use HasFactory<RegistrationStudentFactory> */
    use HasFactory, ScopedBySchool;

    protected $fillable = ['registration_id', 'school_id', 'student_id', 'status', 'note', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class, 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Registration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return HasMany<RegistrationStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(RegistrationStatusHistory::class)->orderBy('id');
    }
}
```

`RegistrationStatusHistory.php`:

```php
<?php

namespace App\Domains\Registrations\Models;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property RegistrationStatus $from_status
 * @property RegistrationStatus $to_status
 * @property int $by_user_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class RegistrationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['registration_student_id', 'from_status', 'to_status', 'by_user_id', 'note'];

    protected function casts(): array
    {
        return ['from_status' => RegistrationStatus::class, 'to_status' => RegistrationStatus::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_user_id');
    }
}
```

`database/factories/RegistrationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domains\Registrations\Models\Registration;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Registration> */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => 'Perekaman '.fake()->words(2, true),
            'expected_count' => null,
            'created_by' => User::factory(),
            'submitted_at' => null,
        ];
    }
}
```

`database/factories/RegistrationStudentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistrationStudent> */
class RegistrationStudentFactory extends Factory
{
    protected $model = RegistrationStudent::class;

    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'status' => RegistrationStatus::Draf,
        ];
    }
}
```

Tambahkan ke `Student.php` (impor `RegistrationStatus`, `RegistrationStudent`):

```php
    public const LOCKED_MESSAGE = 'Data siswa terkunci karena pendaftarannya sedang diproses.';

    /** @return HasMany<RegistrationStudent, $this> */
    public function registrationEntries(): HasMany
    {
        return $this->hasMany(RegistrationStudent::class);
    }

    /** Terkunci selama ada entri yang sedang diproses petugas (lihat RegistrationStatus::locking). */
    public function isLocked(): bool
    {
        return $this->registrationEntries()
            ->whereIn('status', RegistrationStatus::values(RegistrationStatus::locking()))
            ->exists();
    }
```

- [ ] **Step 6: Jalankan tes, lulus**

Run: `php artisan test tests/Feature/Registrations/RegistrationModelTest.php`
Expected: PASS (4 tes + dataset). Bila generated column gagal di MySQL, baca pesan galat dan perbaiki migrasi (jangan mengganti ke validasi aplikasi).

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Registrations database tests/Pest.php tests/Feature/Registrations app/Domains/Students/Models/Student.php
git commit -m "feat: tambah model, skema, dan enum status pendaftaran" -m "Tiga tabel baru: registrations, registration_students (kolom generated active_student_id + indeks unik agar satu siswa hanya punya satu entri aktif), dan registration_status_histories. Enum RegistrationStatus memuat peta transisi Fase 4, status aktif, dan status pengunci data siswa. Student mendapat relasi registrationEntries dan isLocked. Kolom schedule_id dan transisi penjadwalan sengaja ditunda ke Fase 5."
```

---

### Task 2: StatusTransitioner

**Files:**
- Create: `app/Domains/Registrations/Services/StatusTransitioner.php`
- Test: `tests/Feature/Registrations/StatusTransitionerTest.php`

**Interfaces:**
- Consumes: `RegistrationStatus::next()`, `makeEntry()` (Task 1).
- Produces: `StatusTransitioner::move(RegistrationStudent $entry, RegistrationStatus $to, User $actor, ?string $note = null): RegistrationStudent` (mengembalikan entri segar; melempar `AccessRuleException` untuk transisi ilegal; mengisi `decided_by/decided_at` saat keluar dari `diajukan`; menulis satu baris riwayat; `note` entri diganti dengan catatan transisi terbaru).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('transisi sah mengubah status dan menulis riwayat', function (S $from, S $to) {
    $actor = User::factory()->create();
    $entry = makeEntry($from);

    $moved = app(StatusTransitioner::class)->move($entry, $to, $actor, 'catatan');
    $history = $moved->histories()->first();

    expect($moved->status)->toBe($to)
        ->and($moved->note)->toBe('catatan')
        ->and($moved->histories()->count())->toBe(1)
        ->and($history->from_status)->toBe($from)
        ->and($history->to_status)->toBe($to)
        ->and($history->by_user_id)->toBe($actor->id);
})->with([
    [S::Draf, S::Diajukan], [S::Diajukan, S::Terverifikasi], [S::Diajukan, S::PerluPerbaikan],
    [S::Diajukan, S::Ditolak], [S::PerluPerbaikan, S::Diajukan],
]);

test('transisi ilegal ditolak tanpa mengubah apa pun', function (S $from, S $to) {
    $entry = makeEntry($from);

    expect(fn () => app(StatusTransitioner::class)->move($entry, $to, User::factory()->create()))
        ->toThrow(AccessRuleException::class);
    expect($entry->fresh()->status)->toBe($from)->and($entry->histories()->count())->toBe(0);
})->with([
    [S::Draf, S::Terverifikasi], [S::Terverifikasi, S::Diajukan], [S::Ditolak, S::Diajukan],
    [S::Diajukan, S::Draf], [S::PerluPerbaikan, S::Terverifikasi], [S::Diajukan, S::Diajukan],
]);

test('keputusan petugas mengisi decided_by dan decided_at, pengajuan tidak', function () {
    $petugas = User::factory()->create();
    $entry = makeEntry(S::Diajukan);

    $moved = app(StatusTransitioner::class)->move($entry, S::Terverifikasi, $petugas);
    expect($moved->decided_by)->toBe($petugas->id)->and($moved->decided_at)->not->toBeNull();

    $baru = app(StatusTransitioner::class)->move(makeEntry(S::Draf), S::Diajukan, $petugas);
    expect($baru->decided_by)->toBeNull();
});

test('keputusan kedua pada entri yang sama ditolak walau modelnya basi', function () {
    $entry = makeEntry(S::Diajukan);
    $basi = RegistrationStudent::find($entry->id);
    $actor = User::factory()->create();

    app(StatusTransitioner::class)->move($entry, S::Terverifikasi, $actor);

    expect(fn () => app(StatusTransitioner::class)->move($basi, S::Ditolak, $actor))->toThrow(AccessRuleException::class);
    expect($entry->histories()->count())->toBe(1);
});

test('mengajukan siswa yang sudah punya entri aktif lain dihentikan indeks unik', function () {
    $aktif = makeEntry(S::Diajukan);
    $draf = makeEntry(S::Draf, student: $aktif->student);

    expect(fn () => app(StatusTransitioner::class)->move($draf, S::Diajukan, User::factory()->create()))
        ->toThrow(UniqueConstraintViolationException::class);
    expect($draf->fresh()->status)->toBe(S::Draf)->and($draf->histories()->count())->toBe(0);
});
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Registrations/StatusTransitionerTest.php`
Expected: FAIL (class `StatusTransitioner` tidak ada).

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatusTransitioner
{
    /**
     * Satu-satunya pintu perubahan status entri: memeriksa peta transisi di bawah kunci baris
     * (dua petugas tidak bisa sama-sama memutuskan status lama) dan menulis riwayat.
     */
    public function move(RegistrationStudent $entry, RegistrationStatus $to, User $actor, ?string $note = null): RegistrationStudent
    {
        return DB::transaction(function () use ($entry, $to, $actor, $note) {
            $fresh = RegistrationStudent::query()->lockForUpdate()->findOrFail($entry->id);
            $from = $fresh->status;

            if (! in_array($to, $from->next(), true)) {
                throw new AccessRuleException("Status tidak dapat diubah dari {$from->label()} ke {$to->label()}.");
            }

            $fresh->update([
                'status' => $to,
                'note' => $note,
                ...($from === RegistrationStatus::Diajukan ? ['decided_by' => $actor->id, 'decided_at' => now()] : []),
            ]);
            $fresh->histories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'by_user_id' => $actor->id,
                'note' => $note,
            ]);

            return $fresh;
        });
    }
}
```

- [ ] **Step 4: Jalankan, lulus**

Run: `php artisan test tests/Feature/Registrations/StatusTransitionerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Registrations/Services/StatusTransitioner.php tests/Feature/Registrations/StatusTransitionerTest.php
git commit -m "feat: tambah StatusTransitioner sebagai satu pintu perubahan status entri" -m "Transisi divalidasi terhadap peta enum di bawah kunci baris sehingga dua keputusan bersamaan tidak sama-sama lolos, riwayat ditulis pada tiap perubahan, dan keputusan petugas mengisi decided_by/decided_at. Pelanggaran indeks unik saat mengajukan siswa yang sudah aktif di tempat lain diteruskan ke pemanggil."
```

---

### Task 3: Eligibility entri aktif dan kunci data siswa

**Files:**
- Modify: `app/Domains/Students/Services/EligibilityService.php`, `app/Domains/Students/Services/StudentService.php`, `app/Domains/Students/Services/StudentDocumentService.php`, `app/Domains/Students/Repositories/Contracts/StudentRepositoryInterface.php`, `app/Domains/Students/Repositories/EloquentStudentRepository.php`
- Test: `tests/Feature/Students/EligibilityServiceTest.php` (tambah), `tests/Feature/Students/StudentLockTest.php` (baru), `tests/Feature/Students/StudentHttpTest.php` (tambah satu tes filter)

**Interfaces:**
- Consumes: `Student::registrationEntries()`, `isLocked()`, `LOCKED_MESSAGE`, `makeEntry`, `eligibleStudent` (Task 1).
- Produces: `EligibilityService::check(Student $student, ?CarbonInterface $on = null, ?int $exceptRegistrationId = null)` (alasan tambahan "Siswa sudah terdaftar di pendaftaran lain."); `StudentRepositoryInterface::forSchool(int $schoolId): Collection` (siswa sekolah itu dengan `documents` dan `registrationEntries`, urut nama) dan `StudentService::forSchool(int $schoolId)`.

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan di akhir `tests/Feature/Students/EligibilityServiceTest.php` (tambah `use App\Domains\Registrations\Enums\RegistrationStatus as S;`):

```php
test('entri aktif membuat siswa tidak eligible, draf dan ditolak tidak', function (S $status, bool $eligible) {
    $siswa = eligibleStudent();
    makeEntry($status, student: $siswa);

    $check = app(EligibilityService::class)->check($siswa->fresh('documents'));

    expect($check['eligible'])->toBe($eligible);
    if (! $eligible) {
        expect($check['reasons'])->toContain('Siswa sudah terdaftar di pendaftaran lain.');
    }
})->with([
    [S::Draf, true], [S::Ditolak, true], [S::Diajukan, false],
    [S::PerluPerbaikan, false], [S::Terverifikasi, false],
]);

test('entri aktif pada pendaftaran yang sedang diperiksa tidak dihitung', function () {
    $siswa = eligibleStudent();
    $entry = makeEntry(S::PerluPerbaikan, student: $siswa);

    expect(app(EligibilityService::class)->check($siswa->fresh('documents'), null, $entry->registration_id)['eligible'])->toBeTrue();
});
```

Buat `tests/Feature/Students/StudentLockTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->siswa = eligibleStudent($this->school);
    $this->op = makeUserWith(['siswa.lihat', 'siswa.ubah', 'siswa.hapus', 'dokumen.unggah', 'dokumen.hapus'], $this->school, true, 'op-kunci');
    $this->payload = fn (string $nama) => [
        'nik' => $this->siswa->nik, 'nisn' => $this->siswa->nisn, 'name' => $nama,
        'birth_date' => $this->siswa->birth_date->toDateString(), 'gender' => $this->siswa->gender,
    ];
});

test('ubah, unggah, dan hapus dokumen ditolak saat entri diproses', function (S $status) {
    makeEntry($status, student: $this->siswa);
    $this->actingAs($this->op);

    $this->put("/siswa/{$this->siswa->id}", ($this->payload)('Nama Baru'))->assertSessionHas('error', Student::LOCKED_MESSAGE);
    $this->post("/siswa/{$this->siswa->id}/dokumen/kk", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHas('error', Student::LOCKED_MESSAGE);
    $this->delete("/siswa/{$this->siswa->id}/dokumen/kk")->assertSessionHas('error', Student::LOCKED_MESSAGE);

    expect($this->siswa->fresh()->name)->not->toBe('Nama Baru')
        ->and($this->siswa->documents()->count())->toBe(2);
})->with([[S::Diajukan], [S::Terverifikasi]]);

test('ubah dan dokumen diizinkan saat draf atau perlu perbaikan', function (S $status) {
    makeEntry($status, student: $this->siswa);
    $this->actingAs($this->op);

    $this->put("/siswa/{$this->siswa->id}", ($this->payload)('Nama Baru'))->assertSessionMissing('error');
    $this->delete("/siswa/{$this->siswa->id}/dokumen/kk")->assertSessionMissing('error');

    expect($this->siswa->fresh()->name)->toBe('Nama Baru')->and($this->siswa->documents()->count())->toBe(1);
})->with([[S::Draf], [S::PerluPerbaikan]]);

test('siswa dengan entri apa pun tidak dapat dihapus, tanpa entri dapat', function () {
    makeEntry(S::Ditolak, student: $this->siswa);
    $this->actingAs($this->op)->delete("/siswa/{$this->siswa->id}")
        ->assertSessionHas('error', 'Siswa sudah memiliki pendaftaran; keluarkan dari draf atau hubungi admin.');
    expect(Student::find($this->siswa->id))->not->toBeNull();

    $lain = Student::factory()->for($this->school)->create();
    $this->delete("/siswa/{$lain->id}")->assertRedirect('/siswa');
    expect(Student::find($lain->id))->toBeNull();
});
```

Tambahkan di `tests/Feature/Students/StudentHttpTest.php` (periksa impor `Assert` di berkas itu; tambahkan bila belum ada) satu tes:

```php
test('filter eligible tidak menampilkan siswa dengan entri aktif', function () {
    $sekolah = School::factory()->create();
    $bebas = eligibleStudent($sekolah);
    $sibuk = eligibleStudent($sekolah);
    makeEntry(\App\Domains\Registrations\Enums\RegistrationStatus::Diajukan, student: $sibuk);
    $user = makeUserWith(['siswa.lihat'], $sekolah, true, 'op-filter');

    $this->actingAs($user)->get('/siswa?filter[eligible]=1')->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
        ->has('students.data', 1)
        ->where('students.data.0.id', $bebas->id));
});
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Students`
Expected: FAIL pada tes baru (alasan belum ada, kunci belum ada).

- [ ] **Step 3: Implementasi**

`EligibilityService::check` — ubah tanda tangan dan tambah pemeriksaan sebelum `return`:

```php
    /** @return array{eligible: bool, reasons: list<string>} */
    public function check(Student $student, ?CarbonInterface $on = null, ?int $exceptRegistrationId = null): array
    {
        // ... (kode umur dan dokumen tetap)

        $busy = $student->registrationEntries->contains(
            fn ($entry) => $entry->status->isActive() && $entry->registration_id !== $exceptRegistrationId
        );
        if ($busy) {
            $reasons[] = 'Siswa sudah terdaftar di pendaftaran lain.';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }
```

`EloquentStudentRepository`: ubah `with([...])` di `paginate` menjadi `['school', 'documents', 'registrationEntries']`; pada callback filter `eligible` tambahkan setelah dua `whereHas`:

```php
                        ->whereDoesntHave('registrationEntries', fn ($entries) => $entries->whereIn('status', RegistrationStatus::values(RegistrationStatus::active())));
```

(impor `App\Domains\Registrations\Enums\RegistrationStatus`). Tambah metode:

```php
    public function forSchool(int $schoolId): Collection
    {
        return Student::query()->where('school_id', $schoolId)
            ->with(['documents', 'registrationEntries'])->orderBy('name')->orderBy('id')->get();
    }
```

(impor `Illuminate\Database\Eloquent\Collection`). Tambah ke `StudentRepositoryInterface`:

```php
    /** @return Collection<int, Student> */
    public function forSchool(int $schoolId): Collection;
```

`StudentService`: tambah impor `AccessRuleException`, `forSchool`, dan penjaga:

```php
    /** @return Collection<int, Student> */
    public function forSchool(int $schoolId): Collection
    {
        return $this->students->forSchool($schoolId);
    }
```

`update` — baris pertama: `if ($student->isLocked()) { throw new AccessRuleException(Student::LOCKED_MESSAGE); }`.
`delete` — baris pertama:

```php
        if ($student->registrationEntries()->exists()) {
            throw new AccessRuleException('Siswa sudah memiliki pendaftaran; keluarkan dari draf atau hubungi admin.');
        }
```

`StudentDocumentService::store` dan `::delete` — baris pertama masing-masing: `if ($student->isLocked()) { throw new AccessRuleException(Student::LOCKED_MESSAGE); }` (impor `App\Domains\Access\Exceptions\AccessRuleException`).

- [ ] **Step 4: Jalankan, lulus**

Run: `php artisan test tests/Feature/Students tests/Feature/Registrations`
Expected: PASS (tes lama tetap hijau; periksa `StudentHttpTest`/`StudentImportTest` tidak rusak).

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Students tests/Feature/Students
git commit -m "feat: tambah syarat entri aktif pada eligibility dan kunci data siswa" -m "EligibilityService menolak siswa yang masih punya entri aktif di pendaftaran lain (dengan pengecualian pendaftaran yang sedang diperiksa), dan filter eligible di daftar siswa memakai aturan yang sama. Data dan dokumen siswa tidak dapat diubah selama entrinya diajukan/terverifikasi/terjadwal/sudah rekaman; siswa dengan entri apa pun tidak dapat dihapus. Ditambah StudentRepository::forSchool untuk pemilih siswa."
```

---

### Task 4: Notifikasi pendaftaran dan toSchool

**Files:**
- Create: `app/Domains/Notifications/Notifications/{RegistrationSubmittedNotification,EntryDecidedNotification}.php`
- Modify: `app/Domains/Notifications/Services/NotificationService.php`, `app/Domains/Notifications/Repositories/Contracts/NotificationRepositoryInterface.php`, `app/Domains/Notifications/Repositories/EloquentNotificationRepository.php`
- Test: `tests/Feature/Notifications/RegistrationNotificationTest.php`

**Interfaces:**
- Consumes: `BaseNotification`, `RegistrationStatus`.
- Produces: `NotificationService::toSchool(string $permission, BaseNotification $n, int $schoolId): int`; `recipientsWithPermission(string $permission, ?int $schoolId, bool $schoolOnly = false)`; `RegistrationSubmittedNotification(string $school, int $count, bool $again = false)`; `EntryDecidedNotification(RegistrationStatus $decision, string $student, int $registrationId, ?string $note)`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Notifications\Notifications\RegistrationSubmittedNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;

test('toSchool hanya menjangkau pengguna sekolah itu yang berizin', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $opA = makeUserWith(['pendaftaran.lihat'], $a, true, 'op');
    $opB = makeUserWith(['pendaftaran.lihat'], $b, true, 'op');
    $staf = makeUserWith(['pendaftaran.lihat'], roleName: 'staf');
    $tanpaIzin = makeUserWith(['siswa.lihat'], $a, true, 'op-lain');
    $admin = makeUserWith([], roleName: 'super-admin');

    $count = app(NotificationService::class)->toSchool('pendaftaran.lihat', new EntryDecidedNotification(S::Terverifikasi, 'Budi', 1, null), $a->id);

    expect($count)->toBe(1);
    Notification::assertSentTo($opA, EntryDecidedNotification::class);
    Notification::assertNotSentTo([$opB, $staf, $tanpaIzin, $admin], EntryDecidedNotification::class);
});

test('isi notifikasi pengajuan dan keputusan', function () {
    $baru = new RegistrationSubmittedNotification('SMAN 1', 3);
    $ulang = new RegistrationSubmittedNotification('SMAN 1', 1, true);
    $fix = new EntryDecidedNotification(S::PerluPerbaikan, 'Budi', 7, 'KK buram');
    $ok = new EntryDecidedNotification(S::Terverifikasi, 'Budi', 7, null);

    expect($baru->title())->toBe('Pendaftaran baru diajukan')
        ->and($baru->body())->toBe('SMAN 1: 3 siswa menunggu verifikasi.')
        ->and($baru->url())->toBe('/verifikasi')
        ->and($ulang->title())->toBe('Pengajuan ulang diterima')
        ->and($fix->title())->toBe('Perlu perbaikan')
        ->and($fix->body())->toBe('Budi: KK buram')
        ->and($ok->title())->toBe('Pendaftaran diverifikasi')
        ->and($ok->body())->toBe('Budi')
        ->and($ok->url())->toBe('/pendaftaran/7')
        ->and((new EntryDecidedNotification(S::Ditolak, 'Budi', 7, 'x'))->title())->toBe('Pendaftaran ditolak');
});
```

- [ ] **Step 2: Jalankan, gagal**

Run: `php artisan test tests/Feature/Notifications/RegistrationNotificationTest.php` — Expected: FAIL (class tidak ada).

- [ ] **Step 3: Implementasi**

`RegistrationSubmittedNotification.php`:

```php
<?php

namespace App\Domains\Notifications\Notifications;

class RegistrationSubmittedNotification extends BaseNotification
{
    public function __construct(
        private readonly string $school,
        private readonly int $count,
        private readonly bool $again = false,
    ) {}

    public function title(): string
    {
        return $this->again ? 'Pengajuan ulang diterima' : 'Pendaftaran baru diajukan';
    }

    public function body(): string
    {
        return "{$this->school}: {$this->count} siswa menunggu verifikasi.";
    }

    public function url(): string
    {
        return '/verifikasi';
    }
}
```

`EntryDecidedNotification.php`:

```php
<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Registrations\Enums\RegistrationStatus;
use Illuminate\Support\Str;

class EntryDecidedNotification extends BaseNotification
{
    public function __construct(
        private readonly RegistrationStatus $decision,
        private readonly string $student,
        private readonly int $registrationId,
        private readonly ?string $note,
    ) {}

    public function title(): string
    {
        return match ($this->decision) {
            RegistrationStatus::Terverifikasi => 'Pendaftaran diverifikasi',
            RegistrationStatus::PerluPerbaikan => 'Perlu perbaikan',
            default => 'Pendaftaran ditolak',
        };
    }

    public function body(): string
    {
        return $this->note ? "{$this->student}: ".Str::limit($this->note, 120) : $this->student;
    }

    public function url(): string
    {
        return "/pendaftaran/{$this->registrationId}";
    }
}
```

`NotificationRepositoryInterface::recipientsWithPermission` → tambah parameter `bool $schoolOnly = false` (perbarui docblock). `EloquentNotificationRepository::recipientsWithPermission`:

```php
    public function recipientsWithPermission(string $permission, ?int $schoolId, bool $schoolOnly = false): Collection
    {
        // Tanpa global scope: pengirim operator sekolah tidak boleh membatasi daftar penerima.
        return User::query()->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->permission($permission)
                ->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SUPER_ADMIN)))
            ->when($schoolId !== null, fn (Builder $q) => $schoolOnly
                // Hanya pengguna yang terikat ke sekolah itu (staf kecamatan dan super-admin tidak ikut).
                ? $q->where('school_id', $schoolId)
                : $q->where(fn (Builder $w) => $w->whereNull('school_id')->orWhere('school_id', $schoolId)))
            ->get();
    }
```

`NotificationService` tambah:

```php
    /** Mengirim hanya ke pengguna terikat sekolah tersebut yang memegang permission; mengembalikan jumlah penerima. */
    public function toSchool(string $permission, BaseNotification $notification, int $schoolId): int
    {
        $recipients = $this->notifications->recipientsWithPermission($permission, $schoolId, schoolOnly: true);
        Notification::send($recipients, $notification);

        return $recipients->count();
    }
```

- [ ] **Step 4: Jalankan, lulus**

Run: `php artisan test tests/Feature/Notifications` — Expected: PASS (tes `toPermission` lama tetap hijau).

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Notifications tests/Feature/Notifications/RegistrationNotificationTest.php
git commit -m "feat: tambah notifikasi pengajuan dan keputusan pendaftaran" -m "NotificationService::toSchool mengirim hanya ke pengguna terikat sekolah terkait yang berizin (staf, super-admin, dan sekolah lain tidak ikut). Dua kelas notifikasi baru: pengajuan (juga untuk pengajuan ulang) ke petugas, dan keputusan per siswa ke operator sekolah."
```

---

### Task 5: Repository dan RegistrationService (draf, ajukan, ajukan ulang)

**Files:**
- Create: `app/Domains/Registrations/Repositories/Contracts/RegistrationRepositoryInterface.php`, `app/Domains/Registrations/Repositories/EloquentRegistrationRepository.php`, `app/Domains/Registrations/Services/RegistrationService.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Feature/Registrations/RegistrationServiceTest.php`

**Interfaces:**
- Consumes: `StatusTransitioner::move`, `EligibilityService::check`, `NotificationService::toPermission`, notifikasi Task 4, `makeEntry`, `eligibleStudent`.
- Produces: `RegistrationRepositoryInterface` (`paginate(int $perPage = 20)`, `queue(int $perPage = 20)`, `create(array)`, `update(Registration, array)`, `delete(Registration)`, `syncDraftEntries(Registration, array $studentIds)`); `RegistrationService` (`paginate()`, `createDraft(array $data, User $actor): Registration`, `updateDraft(Registration, array): Registration`, `deleteDraft(Registration): void`, `submit(Registration, User): void`, `resubmit(RegistrationStudent, User): void`). `$data` berisi `title`, `expected_count?`, `school_id?`, `student_ids`. Galat eligibility: `ValidationException` dengan kunci `student_ids` dan `student_ids.{studentId}`.

- [ ] **Step 1: Tulis tes yang gagal** — `tests/Feature/Registrations/RegistrationServiceTest.php`:

```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationSubmittedNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Services\EligibilityService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.buat', 'pendaftaran.ajukan'], $this->school, true, 'op-reg');
    $this->petugas = makeUserWith(['verifikasi.putuskan'], roleName: 'petugas');
    $this->service = app(RegistrationService::class);
    $this->draf = function (array $siswa, ?string $judul = 'Gelombang 1') {
        return $this->service->createDraft(['title' => $judul, 'student_ids' => collect($siswa)->pluck('id')->all()], $this->op);
    };
});

test('draf dibuat di sekolah operator dengan entri berstatus draf', function () {
    $a = eligibleStudent($this->school);
    $b = eligibleStudent($this->school);

    $r = ($this->draf)([$a, $b]);

    expect($r->school_id)->toBe($this->school->id)
        ->and($r->created_by)->toBe($this->op->id)
        ->and($r->submitted_at)->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Draf]);
});

test('staf kecamatan memilih sekolah lewat data', function () {
    $staf = makeUserWith(['pendaftaran.buat'], roleName: 'staf-reg');
    $a = eligibleStudent($this->school);

    $r = $this->service->createDraft(['title' => 'X', 'school_id' => $this->school->id, 'student_ids' => [$a->id]], $staf);

    expect($r->school_id)->toBe($this->school->id);
});

test('ubah draf menyinkronkan daftar siswa', function () {
    [$a, $b, $c] = [eligibleStudent($this->school), eligibleStudent($this->school), eligibleStudent($this->school)];
    $r = ($this->draf)([$a, $b]);

    $this->service->updateDraft($r, ['title' => 'Baru', 'expected_count' => 30, 'student_ids' => [$b->id, $c->id]]);

    expect($r->fresh()->title)->toBe('Baru')->and($r->fresh()->expected_count)->toBe(30)
        ->and($r->entries()->pluck('student_id')->sort()->values()->all())->toBe(collect([$b->id, $c->id])->sort()->values()->all());
});

test('pengajuan berhasil: semua entri diajukan, riwayat, log, dan petugas diberi tahu', function () {
    $a = eligibleStudent($this->school);
    $b = eligibleStudent($this->school);
    $r = ($this->draf)([$a, $b]);

    $this->service->submit($r, $this->op);

    expect($r->fresh()->submitted_at)->not->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Diajukan])
        ->and($r->entries->first()->histories()->count())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diajukan')->count())->toBe(1);
    Notification::assertSentTo($this->petugas, RegistrationSubmittedNotification::class);
    Notification::assertNotSentTo($this->op, RegistrationSubmittedNotification::class);
});

test('satu siswa tidak eligible menggagalkan semua dengan alasan per siswa', function () {
    $ok = eligibleStudent($this->school);
    $buruk = eligibleStudent($this->school);
    $buruk->documents()->where('type', 'kk')->delete();
    $r = ($this->draf)([$ok, $buruk]);

    try {
        $this->service->submit($r, $this->op);
        $this->fail('Seharusnya gagal.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['student_ids', "student_ids.{$buruk->id}"])
            ->and($e->errors()["student_ids.{$buruk->id}"][0])->toContain('Kartu Keluarga belum diunggah.')
            ->and($e->errors())->not->toHaveKey("student_ids.{$ok->id}");
    }

    expect($r->fresh()->submitted_at)->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Draf]);
    Notification::assertNothingSent();
});

test('pengajuan tanpa siswa ditolak', function () {
    $r = ($this->draf)([]);

    expect(fn () => $this->service->submit($r, $this->op))->toThrow(ValidationException::class);
});

test('siswa yang sudah aktif di pendaftaran lain ditolak dan pengajuan kedua gagal', function () {
    $a = eligibleStudent($this->school);
    $pertama = ($this->draf)([$a], 'Pertama');
    $kedua = ($this->draf)([$a], 'Kedua');

    $this->service->submit($pertama, $this->op);

    try {
        $this->service->submit($kedua, $this->op);
        $this->fail('Seharusnya gagal.');
    } catch (ValidationException $e) {
        expect($e->errors()["student_ids.{$a->id}"][0])->toContain('Siswa sudah terdaftar di pendaftaran lain.');
    }
});

test('balapan yang lolos pemeriksaan tetap dihentikan indeks unik dan dibatalkan utuh', function () {
    $a = eligibleStudent($this->school);
    $b = eligibleStudent($this->school);
    makeEntry(S::Diajukan, student: $a); // entri aktif milik pendaftaran lain
    // Eligibility dibuat buta untuk mensimulasikan pengajuan lain yang baru saja commit.
    $this->mock(EligibilityService::class, fn ($m) => $m->shouldReceive('check')->andReturn(['eligible' => true, 'reasons' => []]));
    $r = app(RegistrationService::class)->createDraft(['title' => 'Balapan', 'student_ids' => [$b->id, $a->id]], $this->op);

    expect(fn () => app(RegistrationService::class)->submit($r, $this->op))
        ->toThrow(AccessRuleException::class, 'Sebagian siswa sudah terdaftar di pendaftaran lain.');
    expect($r->fresh()->submitted_at)->toBeNull()
        ->and($r->entries()->pluck('status')->unique()->all())->toBe([S::Draf]);
});

test('pendaftaran yang sudah diajukan tidak dapat diubah atau dihapus', function () {
    $r = ($this->draf)([eligibleStudent($this->school)]);
    $this->service->submit($r, $this->op);

    expect(fn () => $this->service->updateDraft($r->fresh(), ['title' => 'x', 'student_ids' => []]))->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->deleteDraft($r->fresh()))->toThrow(AccessRuleException::class);
});

test('draf dapat dihapus beserta entrinya', function () {
    $r = ($this->draf)([eligibleStudent($this->school)]);

    $this->service->deleteDraft($r);

    expect(Registration::find($r->id))->toBeNull();
});

test('ajukan ulang hanya untuk perlu perbaikan dan memeriksa eligibility lagi', function () {
    $siswa = eligibleStudent($this->school);
    $entry = makeEntry(S::PerluPerbaikan, student: $siswa);

    $this->service->resubmit($entry, $this->op);

    expect($entry->fresh()->status)->toBe(S::Diajukan)
        ->and(Activity::where('description', 'pendaftaran.diajukan-ulang')->count())->toBe(1);
    Notification::assertSentTo($this->petugas, RegistrationSubmittedNotification::class);

    expect(fn () => $this->service->resubmit($entry->fresh(), $this->op))->toThrow(AccessRuleException::class);
});

test('ajukan ulang gagal bila dokumen masih kurang', function () {
    $siswa = eligibleStudent($this->school);
    $siswa->documents()->where('type', 'akta_lahir')->delete();
    $entry = makeEntry(S::PerluPerbaikan, student: $siswa);

    expect(fn () => $this->service->resubmit($entry, $this->op))->toThrow(ValidationException::class);
    expect($entry->fresh()->status)->toBe(S::PerluPerbaikan);
});
```

- [ ] **Step 2: Jalankan, gagal**

Run: `php artisan test tests/Feature/Registrations/RegistrationServiceTest.php` — Expected: FAIL (class tidak ada).

- [ ] **Step 3: Implementasi repository**

`Contracts/RegistrationRepositoryInterface.php`:

```php
<?php

namespace App\Domains\Registrations\Repositories\Contracts;

use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use Illuminate\Pagination\LengthAwarePaginator;

interface RegistrationRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Registration> */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /** Antrean verifikasi: entri berstatus diajukan, terlama dulu. @return LengthAwarePaginator<int, RegistrationStudent> */
    public function queue(int $perPage = 20): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Registration;

    /** @param array<string, mixed> $attributes */
    public function update(Registration $registration, array $attributes): Registration;

    public function delete(Registration $registration): void;

    /**
     * Menyamakan entri draf dengan daftar siswa: yang tidak ada dihapus, yang baru ditambahkan.
     *
     * @param  list<int>  $studentIds
     */
    public function syncDraftEntries(Registration $registration, array $studentIds): void;
}
```

`EloquentRegistrationRepository.php`:

```php
<?php

namespace App\Domains\Registrations\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentRegistrationRepository implements RegistrationRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $base = Registration::query()->with(['school', 'entries:id,registration_id,status'])->latest('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->where('title', 'like', "%{$v}%")),
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('status', function (Builder $q, $v) {
                    // Draf = pendaftaran yang belum diajukan (bisa saja belum berisi entri).
                    $v === RegistrationStatus::Draf->value
                        ? $q->whereNull('submitted_at')
                        : $q->whereHas('entries', fn (Builder $e) => $e->where('status', (string) $v));
                }),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function queue(int $perPage = 20): LengthAwarePaginator
    {
        $base = RegistrationStudent::query()
            ->where('status', RegistrationStatus::Diajukan->value)
            ->with(['student', 'registration.school'])
            ->orderBy('updated_at')->orderBy('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->whereHas(
                    'student',
                    fn (Builder $s) => $s->where(fn (Builder $w) => $w->where('name', 'like', "%{$v}%")->orWhere('nisn', 'like', "%{$v}%"))
                )),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): Registration
    {
        return Registration::create($attributes);
    }

    public function update(Registration $registration, array $attributes): Registration
    {
        $registration->update($attributes);

        return $registration;
    }

    public function delete(Registration $registration): void
    {
        $registration->delete();
    }

    public function syncDraftEntries(Registration $registration, array $studentIds): void
    {
        $registration->entries()->whereNotIn('student_id', $studentIds)->delete();
        $existing = $registration->entries()->pluck('student_id')->all();

        foreach (array_diff($studentIds, $existing) as $id) {
            $registration->entries()->create([
                'school_id' => $registration->school_id,
                'student_id' => $id,
                'status' => RegistrationStatus::Draf,
            ]);
        }
    }
}
```

Daftarkan di `RepositoryServiceProvider::$bindings`: `RegistrationRepositoryInterface::class => EloquentRegistrationRepository::class` (+ impor).

- [ ] **Step 4: Implementasi `RegistrationService`**

```php
<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\RegistrationSubmittedNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function __construct(
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly StatusTransitioner $transitions,
        private readonly EligibilityService $eligibility,
        private readonly NotificationService $notifications,
    ) {}

    /** @return LengthAwarePaginator<int, Registration> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->registrations->paginate();
    }

    /** @param array{title: string, expected_count?: ?int, school_id?: ?int, student_ids: list<int>} $data */
    public function createDraft(array $data, User $actor): Registration
    {
        return DB::transaction(function () use ($data, $actor) {
            $registration = $this->registrations->create([
                // Operator sekolah dikunci ke sekolahnya; staf kecamatan memilih sekolah.
                'school_id' => $actor->school_id ?? $data['school_id'],
                'title' => $data['title'],
                'expected_count' => $data['expected_count'] ?? null,
                'created_by' => $actor->id,
            ]);
            $this->registrations->syncDraftEntries($registration, $data['student_ids']);

            return $registration;
        });
    }

    /** @param array{title: string, expected_count?: ?int, student_ids: list<int>} $data */
    public function updateDraft(Registration $registration, array $data): Registration
    {
        $this->assertDraft($registration);

        return DB::transaction(function () use ($registration, $data) {
            $this->registrations->update($registration, [
                'title' => $data['title'],
                'expected_count' => $data['expected_count'] ?? null,
            ]);
            $this->registrations->syncDraftEntries($registration, $data['student_ids']);

            return $registration;
        });
    }

    public function deleteDraft(Registration $registration): void
    {
        $this->assertDraft($registration);
        $this->registrations->delete($registration);
    }

    public function submit(Registration $registration, User $actor): void
    {
        $this->assertDraft($registration);

        try {
            $count = DB::transaction(function () use ($registration, $actor) {
                $ids = $registration->entries()->pluck('student_id')->all();
                if ($ids === []) {
                    throw ValidationException::withMessages(['student_ids' => 'Pilih minimal satu siswa.']);
                }

                // Kunci baris siswa (urut id) agar dua pengajuan bersamaan tidak saling menyalip;
                // bila tetap lolos, indeks unik active_student_id yang menghentikan.
                Student::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $entries = $registration->entries()->with(['student.documents', 'student.registrationEntries'])->get();
                $this->assertEligible($entries, $registration->id);

                foreach ($entries as $entry) {
                    $this->transitions->move($entry, RegistrationStatus::Diajukan, $actor);
                }
                $registration->update(['submitted_at' => now()]);
                activity('pendaftaran')->performedOn($registration)->causedBy($actor)
                    ->withProperties(['jumlah' => $entries->count()])->log('pendaftaran.diajukan');

                return $entries->count();
            });
        } catch (UniqueConstraintViolationException) {
            throw new AccessRuleException('Sebagian siswa sudah terdaftar di pendaftaran lain.');
        }

        $this->notifications->toPermission('verifikasi.putuskan', new RegistrationSubmittedNotification($registration->school->name, $count));
    }

    public function resubmit(RegistrationStudent $entry, User $actor): void
    {
        if ($entry->status !== RegistrationStatus::PerluPerbaikan) {
            throw new AccessRuleException('Hanya siswa berstatus Perlu perbaikan yang dapat diajukan ulang.');
        }

        DB::transaction(function () use ($entry, $actor) {
            Student::query()->whereKey($entry->student_id)->lockForUpdate()->first();
            $entry->load(['student.documents', 'student.registrationEntries']);
            $this->assertEligible(new Collection([$entry]), $entry->registration_id);

            $this->transitions->move($entry, RegistrationStatus::Diajukan, $actor);
            activity('pendaftaran')->performedOn($entry->registration)->causedBy($actor)
                ->withProperties(['siswa_id' => $entry->student_id])->log('pendaftaran.diajukan-ulang');
        });

        $this->notifications->toPermission('verifikasi.putuskan', new RegistrationSubmittedNotification($entry->registration->school->name, 1, again: true));
    }

    private function assertDraft(Registration $registration): void
    {
        if (! $registration->isDraft()) {
            throw new AccessRuleException('Pendaftaran yang sudah diajukan tidak dapat diubah.');
        }
    }

    /** @param Collection<int, RegistrationStudent> $entries */
    private function assertEligible(Collection $entries, int $registrationId): void
    {
        $errors = [];
        foreach ($entries as $entry) {
            $check = $this->eligibility->check($entry->student, null, $registrationId);
            if (! $check['eligible']) {
                $errors["student_ids.{$entry->student_id}"] = $entry->student->name.': '.implode(' ', $check['reasons']);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['student_ids' => 'Beberapa siswa belum memenuhi syarat.'] + $errors);
        }
    }
}
```

- [ ] **Step 5: Jalankan, lulus**

Run: `php artisan test tests/Feature/Registrations`
Expected: PASS. Bila tes "balapan" gagal karena mock tidak terpakai, pastikan `RegistrationService` di-resolve setelah `$this->mock(...)` (sudah memakai `app(...)` setelahnya).

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Registrations app/Providers/RepositoryServiceProvider.php tests/Feature/Registrations/RegistrationServiceTest.php
git commit -m "feat: tambah layanan draf, pengajuan, dan pengajuan ulang pendaftaran" -m "RegistrationService membuat dan mengubah draf, mengajukan seluruh pendaftaran secara atomik (kunci baris siswa, validasi ulang eligibility semua siswa pada saat submit, galat per siswa), dan mengajukan ulang entri perlu perbaikan. Pengajuan yang lolos tetapi bentrok di indeks unik dibatalkan utuh dengan pesan jelas. Petugas diberi tahu lewat notifikasi dan log pendaftaran ditulis."
```

---

### Task 6: VerificationService

**Files:**
- Create: `app/Domains/Registrations/Services/VerificationService.php`
- Test: `tests/Feature/Registrations/VerificationServiceTest.php`

**Interfaces:**
- Consumes: `StatusTransitioner`, `NotificationService::toSchool`, `RegistrationRepositoryInterface::queue`.
- Produces: `VerificationService::queue()`, `decide(RegistrationStudent $entry, string $decision, ?string $note, User $actor): void` dengan `$decision` ∈ `approve|fix|reject`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.lihat'], $this->school, true, 'op-ver');
    $this->petugas = makeUserWith(['verifikasi.putuskan'], roleName: 'petugas-ver');
    $this->service = app(VerificationService::class);
});

test('keputusan dipetakan ke status, mencatat riwayat, log, dan memberi tahu operator sekolah', function (string $keputusan, S $status) {
    $entry = makeEntry(S::Diajukan, $this->school);

    $this->service->decide($entry, $keputusan, 'catatan', $this->petugas);

    expect($entry->fresh()->status)->toBe($status)
        ->and($entry->fresh()->decided_by)->toBe($this->petugas->id)
        ->and($entry->histories()->count())->toBe(1)
        ->and(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(1);
    Notification::assertSentTo($this->op, EntryDecidedNotification::class);
    Notification::assertNotSentTo($this->petugas, EntryDecidedNotification::class);
})->with([['approve', S::Terverifikasi], ['fix', S::PerluPerbaikan], ['reject', S::Ditolak]]);

test('operator sekolah lain tidak diberi tahu', function () {
    $lain = makeUserWith(['pendaftaran.lihat'], School::factory()->create(), true, 'op-ver');
    $this->service->decide(makeEntry(S::Diajukan, $this->school), 'approve', null, $this->petugas);

    Notification::assertNotSentTo($lain, EntryDecidedNotification::class);
});

test('entri yang bukan berstatus diajukan tidak dapat diputuskan, dan keputusan kedua ditolak', function () {
    $draf = makeEntry(S::Draf, $this->school);
    expect(fn () => $this->service->decide($draf, 'approve', null, $this->petugas))->toThrow(AccessRuleException::class);

    $entry = makeEntry(S::Diajukan, $this->school);
    $this->service->decide($entry, 'approve', null, $this->petugas);
    expect(fn () => $this->service->decide($entry, 'reject', 'x', $this->petugas))->toThrow(AccessRuleException::class);
    expect($entry->histories()->count())->toBe(1);
});

test('antrean hanya memuat entri diajukan', function () {
    makeEntry(S::Diajukan, $this->school);
    makeEntry(S::Draf, $this->school);
    makeEntry(S::Terverifikasi, $this->school);

    expect($this->service->queue()->total())->toBe(1);
});
```

- [ ] **Step 2: Jalankan, gagal** — `php artisan test tests/Feature/Registrations/VerificationServiceTest.php` → FAIL.

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Domains\Registrations\Services;

use App\Domains\Notifications\Notifications\EntryDecidedNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Repositories\Contracts\RegistrationRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VerificationService
{
    /** Kata keputusan di request -> status tujuan. */
    private const DECISIONS = [
        'approve' => RegistrationStatus::Terverifikasi,
        'fix' => RegistrationStatus::PerluPerbaikan,
        'reject' => RegistrationStatus::Ditolak,
    ];

    public function __construct(
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly StatusTransitioner $transitions,
        private readonly NotificationService $notifications,
    ) {}

    /** @return LengthAwarePaginator<int, RegistrationStudent> */
    public function queue(): LengthAwarePaginator
    {
        return $this->registrations->queue();
    }

    /** @param 'approve'|'fix'|'reject' $decision */
    public function decide(RegistrationStudent $entry, string $decision, ?string $note, User $actor): void
    {
        $to = self::DECISIONS[$decision];

        DB::transaction(function () use ($entry, $to, $note, $actor) {
            $this->transitions->move($entry, $to, $actor, $note);
            activity('pendaftaran')->performedOn($entry->registration)->causedBy($actor)
                ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => $to->value])->log('pendaftaran.diputuskan');
        });

        $this->notifications->toSchool(
            'pendaftaran.lihat',
            new EntryDecidedNotification($to, $entry->student->name, $entry->registration_id, $note),
            $entry->school_id,
        );
    }
}
```

- [ ] **Step 4: Jalankan, lulus** — `php artisan test tests/Feature/Registrations` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Registrations/Services/VerificationService.php tests/Feature/Registrations/VerificationServiceTest.php
git commit -m "feat: tambah layanan keputusan verifikasi per siswa" -m "VerificationService::decide memetakan setujui/minta perbaikan/tolak ke transisi status lewat StatusTransitioner, menulis log, dan memberi tahu operator sekolah terkait. Keputusan ganda atau pada entri yang bukan berstatus diajukan ditolak. Antrean memuat entri diajukan, terlama dulu."
```

---

### Task 7: HTTP — request, controller, rute, menu, label log, matriks izin

**Files:**
- Create: `app/Domains/Registrations/Http/Requests/{RegistrationRequest,DecisionRequest}.php`, `app/Domains/Registrations/Http/Controllers/{RegistrationController,VerificationController}.php`, `app/Domains/Registrations/Support/EntryPresenter.php`, `routes/registrations.php`
- Modify: `routes/web.php`, `app/Domains/Access/Services/MenuService.php`, `lang/id/activity.php`, `app/Domains/Schools/Services/SchoolService.php`, `app/Domains/Students/Http/Controllers/StudentController.php`, `app/Domains/Students/Services/StudentDocumentService.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`, `tests/Feature/Access/MenuTest.php`
- Test: `tests/Feature/Registrations/{RegistrationHttpTest,VerificationHttpTest,StudentRegistrationCardTest}.php`

**Interfaces:**
- Consumes: semua service Task 1–6, `SchoolService::options()`.
- Produces: rute bernama `pendaftaran.{index,create,store,show,edit,update,destroy,submit,resubmit}` dan `verifikasi.{index,show,decide}`; `SchoolService::optionsFor(User): ?Collection` (daftar `{id,name}` untuk staf, `null` untuk operator); `StudentDocumentService::rows(Student): array` (`[type,name,size,url]`); prop halaman seperti di bawah.

**Kontrak prop (dipakai FE):**
- `Pendaftaran/Index`: `registrations` (paginator `{id,title,school,created_at,submitted_at,students_count,counts}`), `filters`, `schools`.
- `Pendaftaran/Form`: `registration` (`{id,title,expected_count}` | null), `candidates` (`{id,name,nisn,age,eligible,reasons,missing_docs,selected}`), `schools`, `school_id`.
- `Pendaftaran/Show`: `registration` (ringkasan + `expected_count`, `created_by`), `entries` (`EntryPresenter::row`).
- `Verifikasi/Index`: `queue` (paginator `{id,student:{id,name,nisn},school,registration,since}`), `filters`, `schools`.
- `Verifikasi/Show`: `entry` (`EntryPresenter::row` + `registration:{id,title}`), `student` (`{id,name,nisn,nik,birth_date,age,gender,school}`), `documents`, `eligibility`.
- `Siswa/Show` tambahan: `locked` (bool), `registrations` (`[{id,title,status}]`).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Registrations/RegistrationHttpTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.lihat', 'pendaftaran.buat', 'pendaftaran.ajukan'], $this->school, true, 'op-http');
    $this->siswa = eligibleStudent($this->school);
});

test('operator membuat draf lalu melihat detailnya', function () {
    $res = $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'Gelombang 1', 'expected_count' => 20, 'student_ids' => [$this->siswa->id]]);

    $r = Registration::firstOrFail();
    $res->assertRedirect("/pendaftaran/{$r->id}");
    expect($r->school_id)->toBe($this->school->id);

    $this->get("/pendaftaran/{$r->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Pendaftaran/Show')
        ->where('registration.title', 'Gelombang 1')
        ->has('entries', 1)
        ->where('entries.0.status', 'draf')
        ->where('entries.0.student.id', $this->siswa->id));
});

test('judul wajib dan siswa sekolah lain ditolak', function () {
    $asing = eligibleStudent();
    $this->actingAs($this->op);

    $this->post('/pendaftaran', ['title' => '', 'student_ids' => []])->assertSessionHasErrors('title');
    $this->post('/pendaftaran', ['title' => 'X', 'student_ids' => [$asing->id]])->assertSessionHasErrors('student_ids.0');
    expect(Registration::count())->toBe(0);
});

test('staf kecamatan wajib memilih sekolah dan melihat kandidat sekolah itu', function () {
    $staf = makeUserWith(['pendaftaran.buat'], roleName: 'staf-http');
    $this->actingAs($staf);

    $this->post('/pendaftaran', ['title' => 'X', 'student_ids' => []])->assertSessionHasErrors('school_id');
    $this->get("/pendaftaran/buat?school_id={$this->school->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Pendaftaran/Form')
        ->has('schools', School::count())
        ->has('candidates', 1)
        ->where('candidates.0.eligible', true));
    $this->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p->has('candidates', 0));
});

test('operator melihat kandidat sekolahnya tanpa daftar sekolah', function () {
    $this->actingAs($this->op)->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p
        ->where('schools', null)->has('candidates', 1)->where('candidates.0.selected', false));
});

test('ubah dan hapus draf; pendaftaran terajukan tidak dapat diubah', function () {
    $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'A', 'student_ids' => [$this->siswa->id]]);
    $r = Registration::firstOrFail();

    $this->put("/pendaftaran/{$r->id}", ['title' => 'B', 'student_ids' => []])->assertRedirect("/pendaftaran/{$r->id}");
    expect($r->fresh()->title)->toBe('B')->and($r->entries()->count())->toBe(0);

    $this->put("/pendaftaran/{$r->id}", ['title' => 'B', 'student_ids' => [$this->siswa->id]]);
    $this->post("/pendaftaran/{$r->id}/ajukan")->assertRedirect()->assertSessionHas('success');
    expect($r->entries()->first()->status)->toBe(S::Diajukan);

    $this->put("/pendaftaran/{$r->id}", ['title' => 'C', 'student_ids' => []])->assertSessionHas('error');
    $this->get("/pendaftaran/{$r->id}/ubah")->assertRedirect("/pendaftaran/{$r->id}");
    $this->delete("/pendaftaran/{$r->id}")->assertSessionHas('error');
    expect(Registration::find($r->id))->not->toBeNull();
});

test('draf dapat dihapus', function () {
    $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'A', 'student_ids' => []]);
    $r = Registration::firstOrFail();

    $this->delete("/pendaftaran/{$r->id}")->assertRedirect('/pendaftaran');
    expect(Registration::count())->toBe(0);
});

test('pengajuan yang gagal mengembalikan galat per siswa', function () {
    $this->siswa->documents()->where('type', 'kk')->delete();
    $this->actingAs($this->op)->post('/pendaftaran', ['title' => 'A', 'student_ids' => [$this->siswa->id]]);
    $r = Registration::firstOrFail();

    $this->post("/pendaftaran/{$r->id}/ajukan")
        ->assertSessionHasErrors(['student_ids', "student_ids.{$this->siswa->id}"]);
    expect($r->fresh()->submitted_at)->toBeNull();
});

test('ajukan ulang lewat URL bertingkat dan entri milik pendaftaran lain 404', function () {
    $entry = makeEntry(S::PerluPerbaikan, $this->school, eligibleStudent($this->school));
    $lain = makeEntry(S::PerluPerbaikan, $this->school, eligibleStudent($this->school));
    $this->actingAs($this->op);

    $this->post("/pendaftaran/{$entry->registration_id}/entri/{$lain->id}/ajukan-ulang")->assertNotFound();
    $this->post("/pendaftaran/{$entry->registration_id}/entri/{$entry->id}/ajukan-ulang")->assertRedirect()->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::Diajukan);
});

test('operator tidak dapat mengakses pendaftaran sekolah lain dan daftar hanya milik sendiri', function () {
    $milik = makeEntry(S::Draf, $this->school);
    $asing = makeEntry(S::Draf);
    $this->actingAs($this->op);

    $this->get("/pendaftaran/{$asing->registration_id}")->assertNotFound();
    $this->get("/pendaftaran/{$asing->registration_id}/ubah")->assertNotFound();
    $this->put("/pendaftaran/{$asing->registration_id}", ['title' => 'x', 'student_ids' => []])->assertNotFound();
    $this->delete("/pendaftaran/{$asing->registration_id}")->assertNotFound();
    $this->post("/pendaftaran/{$asing->registration_id}/ajukan")->assertNotFound();
    $this->get('/pendaftaran')->assertInertia(fn (Assert $p) => $p
        ->has('registrations.data', 1)->where('registrations.data.0.id', $milik->registration_id));
});

test('daftar dapat difilter menurut status', function () {
    makeEntry(S::Draf, $this->school);
    $diajukan = makeEntry(S::Diajukan, $this->school);
    $this->actingAs($this->op)->get('/pendaftaran?filter[status]=diajukan')->assertInertia(fn (Assert $p) => $p
        ->has('registrations.data', 1)->where('registrations.data.0.id', $diajukan->registration_id)
        ->where('registrations.data.0.counts.diajukan', 1));
});
```

`tests/Feature/Registrations/VerificationHttpTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->petugas = makeUserWith(['verifikasi.lihat', 'verifikasi.putuskan', 'dokumen.lihat'], roleName: 'petugas-http');
});

test('petugas melihat antrean lintas sekolah dan halaman keputusan', function () {
    $a = makeEntry(S::Diajukan, student: eligibleStudent());
    makeEntry(S::Diajukan, student: eligibleStudent());
    makeEntry(S::Draf);
    $this->actingAs($this->petugas);

    $this->get('/verifikasi')->assertInertia(fn (Assert $p) => $p->component('Verifikasi/Index')->has('queue.data', 2));
    $this->get("/verifikasi/{$a->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Verifikasi/Show')
        ->where('entry.id', $a->id)->where('entry.status', 'diajukan')
        ->where('student.nik', fn ($nik) => str_contains($nik, '*'))
        ->has('documents', 2)->where('eligibility.eligible', true));
});

test('keputusan: catatan wajib untuk minta perbaikan dan tolak', function () {
    $entry = makeEntry(S::Diajukan, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'fix'])->assertSessionHasErrors('note');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'reject', 'note' => ''])->assertSessionHasErrors('note');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'lain'])->assertSessionHasErrors('decision');
    expect($entry->fresh()->status)->toBe(S::Diajukan);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'fix', 'note' => 'KK buram'])
        ->assertRedirect('/verifikasi')->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe(S::PerluPerbaikan)->and($entry->fresh()->note)->toBe('KK buram');
});

test('setujui tanpa catatan; keputusan kedua menghasilkan galat', function () {
    $entry = makeEntry(S::Diajukan, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'approve'])->assertSessionHas('success');
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'reject', 'note' => 'x'])->assertSessionHas('error');
    expect($entry->fresh()->status)->toBe(S::Terverifikasi);
});

test('pengguna terikat sekolah lain mendapat 404 untuk entri sekolah berbeda', function () {
    $entry = makeEntry(S::Diajukan, student: eligibleStudent());
    $terikat = makeUserWith(['verifikasi.lihat', 'verifikasi.putuskan'], School::factory()->create(), true, 'terikat');
    $this->actingAs($terikat);

    $this->get("/verifikasi/{$entry->id}")->assertNotFound();
    $this->post("/verifikasi/{$entry->id}/putuskan", ['decision' => 'approve'])->assertNotFound();
    $this->get('/verifikasi')->assertInertia(fn (Assert $p) => $p->has('queue.data', 0));
});
```

`tests/Feature/Registrations/StudentRegistrationCardTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

test('detail siswa memuat status kunci dan daftar pendaftarannya', function () {
    $sekolah = School::factory()->create();
    $siswa = eligibleStudent($sekolah);
    $entry = makeEntry(S::Diajukan, student: $siswa);
    $user = makeUserWith(['siswa.lihat', 'dokumen.lihat'], $sekolah, true, 'op-kartu');

    $this->actingAs($user)->get("/siswa/{$siswa->id}")->assertInertia(fn (Assert $p) => $p
        ->where('locked', true)
        ->has('registrations', 1)
        ->where('registrations.0.id', $entry->registration_id)
        ->where('registrations.0.status', 'diajukan'));

    $bebas = eligibleStudent($sekolah);
    $this->get("/siswa/{$bebas->id}")->assertInertia(fn (Assert $p) => $p->where('locked', false)->has('registrations', 0));
});
```

Ubah `RoutePermissionMatrixTest.php`: tambahkan baris dataset (di akhir `dataset('rute', [...])`):

```php
    ['GET', '/pendaftaran', 'pendaftaran.lihat'],
    ['GET', '/pendaftaran/buat', 'pendaftaran.buat'],
    ['POST', '/pendaftaran', 'pendaftaran.buat'],
    ['GET', '/pendaftaran/{registration}', 'pendaftaran.lihat'],
    ['GET', '/pendaftaran/{registration}/ubah', 'pendaftaran.buat'],
    ['PUT', '/pendaftaran/{registration}', 'pendaftaran.buat'],
    ['DELETE', '/pendaftaran/{registration}', 'pendaftaran.buat'],
    ['POST', '/pendaftaran/{registration}/ajukan', 'pendaftaran.ajukan'],
    ['POST', '/pendaftaran/{registration}/entri/{entry}/ajukan-ulang', 'pendaftaran.ajukan'],
    ['GET', '/verifikasi', 'verifikasi.lihat'],
    ['GET', '/verifikasi/{entry}', 'verifikasi.lihat'],
    ['POST', '/verifikasi/{entry}/putuskan', 'verifikasi.putuskan'],
```

dan ganti `resolveUri`:

```php
function resolveUri(string $uri): string
{
    $target = User::factory()->create();
    $school = School::factory()->create();
    $role = Role::create(['name' => 'sasaran-'.uniqid()]);
    $student = Student::factory()->create();
    $entry = makeEntry();

    return str_replace(
        ['{user}', '{school}', '{role}', '{student}', '{registration}', '{entry}'],
        [$target->id, $school->id, $role->id, $student->id, $entry->registration_id, $entry->id],
        $uri,
    );
}
```

`MenuTest.php`: ubah ekspektasi grup admin menjadi `['Utama', 'Manajemen Akses', 'Master Data', 'Data Siswa', 'Verifikasi', 'Sistem']`; operator tetap `['Utama', 'Data Siswa']`. Tambahkan tes:

```php
test('menu Pendaftaran dan Antrean verifikasi mengikuti izin', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));
    $admin = app(MenuService::class)->forUser(User::factory()->create()->assignRole('admin-kecamatan'));

    expect(collect($operator)->firstWhere('group', 'Data Siswa')['items'])->toContain(['label' => 'Pendaftaran', 'href' => '/pendaftaran', 'icon' => 'clipboard-list'])
        ->and(collect($operator)->pluck('group'))->not->toContain('Verifikasi')
        ->and(collect($admin)->firstWhere('group', 'Verifikasi')['items'][0])->toBe(['label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check']);
});
```

- [ ] **Step 2: Jalankan, gagal**

Run: `php artisan test tests/Feature/Registrations tests/Feature/Access/MenuTest.php tests/Feature/Access/RoutePermissionMatrixTest.php`
Expected: FAIL (rute 404/controller tidak ada).

- [ ] **Step 3: Implementasi — pendukung**

`SchoolService` tambah (impor `App\Models\User` dan `Illuminate\Support\Collection` bila belum):

```php
    /** Daftar sekolah hanya untuk staf tanpa sekolah; operator terkunci ke sekolahnya. */
    public function optionsFor(User $actor): ?Collection
    {
        return $actor->school_id === null
            ? $this->options()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()
            : null;
    }
```

`StudentController`: ganti pemakaian `$this->schoolOptions($request->user())` dengan `$this->schools->optionsFor($request->user())`, hapus metode privat `schoolOptions`, dan impor `User` yang tak terpakai. Pada `show`: ganti pembentukan `documents` dengan `$request->user()->can('dokumen.lihat') ? $this->documents->rows($student) : []`; muat `registrationEntries.registration`; tambahkan ke prop:

```php
            'locked' => $student->isLocked(),
            'registrations' => $student->registrationEntries->map(fn ($e) => [
                'id' => $e->registration_id,
                'title' => $e->registration->title,
                'status' => $e->status->value,
            ])->values(),
```

(`$student->load(['school', 'documents', 'registrationEntries.registration'])`.) `StudentDocumentService::rows` seperti pada kontrak:

```php
    /** @return list<array{type: string, name: string, size: int, url: string}> */
    public function rows(Student $student): array
    {
        return $student->documents->map(fn (StudentDocument $d) => [
            'type' => $d->type,
            'name' => $this->fileName($student, $d),
            'size' => $d->size,
            'url' => route('siswa.dokumen.download', [$student, $d->type]),
        ])->values()->all();
    }
```

`MenuService::ITEMS`: tambahkan setelah item Siswa `['group' => 'Data Siswa', 'label' => 'Pendaftaran', 'href' => '/pendaftaran', 'icon' => 'clipboard-list', 'permission' => 'pendaftaran.lihat']` dan sebelum Log: `['group' => 'Verifikasi', 'label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check', 'permission' => 'verifikasi.lihat']`.

`lang/id/activity.php`: `types` tambah `'pendaftaran' => 'Pendaftaran'` (sebelum `'default'`), `subjects` tambah `'Registration' => 'Pendaftaran'`, `actions` tambah `'pendaftaran.diajukan' => 'Pendaftaran diajukan'`, `'pendaftaran.diajukan-ulang' => 'Siswa diajukan ulang'`, `'pendaftaran.diputuskan' => 'Siswa diputuskan'`.

`routes/web.php`: tambah `require __DIR__.'/registrations.php';` setelah `students.php`.

`routes/registrations.php`:

```php
<?php

use App\Domains\Registrations\Http\Controllers\RegistrationController;
use App\Domains\Registrations\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('pendaftaran', [RegistrationController::class, 'index'])->middleware('can:pendaftaran.lihat')->name('pendaftaran.index');
    Route::get('pendaftaran/buat', [RegistrationController::class, 'create'])->middleware('can:pendaftaran.buat')->name('pendaftaran.create');
    Route::post('pendaftaran', [RegistrationController::class, 'store'])->middleware(['can:pendaftaran.buat', 'throttle:60,1'])->name('pendaftaran.store');
    Route::get('pendaftaran/{registration}', [RegistrationController::class, 'show'])->middleware('can:pendaftaran.lihat')->name('pendaftaran.show');
    Route::get('pendaftaran/{registration}/ubah', [RegistrationController::class, 'edit'])->middleware('can:pendaftaran.buat')->name('pendaftaran.edit');
    Route::put('pendaftaran/{registration}', [RegistrationController::class, 'update'])->middleware('can:pendaftaran.buat')->name('pendaftaran.update');
    Route::delete('pendaftaran/{registration}', [RegistrationController::class, 'destroy'])->middleware('can:pendaftaran.buat')->name('pendaftaran.destroy');
    Route::post('pendaftaran/{registration}/ajukan', [RegistrationController::class, 'submit'])->middleware(['can:pendaftaran.ajukan', 'throttle:60,1'])->name('pendaftaran.submit');
    // scopeBindings: entri harus milik pendaftaran pada URL, selain itu 404.
    Route::post('pendaftaran/{registration}/entri/{entry}/ajukan-ulang', [RegistrationController::class, 'resubmit'])
        ->middleware(['can:pendaftaran.ajukan', 'throttle:60,1'])->scopeBindings()->name('pendaftaran.resubmit');

    Route::get('verifikasi', [VerificationController::class, 'index'])->middleware('can:verifikasi.lihat')->name('verifikasi.index');
    Route::get('verifikasi/{entry}', [VerificationController::class, 'show'])->middleware('can:verifikasi.lihat')->name('verifikasi.show');
    Route::post('verifikasi/{entry}/putuskan', [VerificationController::class, 'decide'])->middleware(['can:verifikasi.putuskan', 'throttle:60,1'])->name('verifikasi.decide');
});
```

Bila `scopeBindings()` tidak menemukan relasi `entries` untuk parameter `entry`, gunakan `->scopeBindings()` bersama `Route::scopeBindings` kustom: ganti dengan `abort_unless($entry->registration_id === $registration->id, 404);` di `resubmit`. Tes "entri milik pendaftaran lain 404" menentukan.

`Support/EntryPresenter.php`:

```php
<?php

namespace App\Domains\Registrations\Support;

use App\Domains\Registrations\Models\RegistrationStatusHistory;
use App\Domains\Registrations\Models\RegistrationStudent;

final class EntryPresenter
{
    /**
     * Butuh relasi `student` dan `histories.user` sudah dimuat.
     *
     * @return array<string, mixed>
     */
    public static function row(RegistrationStudent $entry): array
    {
        return [
            'id' => $entry->id,
            'student' => ['id' => $entry->student->id, 'name' => $entry->student->name, 'nisn' => $entry->student->nisn],
            'status' => $entry->status->value,
            'note' => $entry->note,
            'decided_at' => $entry->decided_at?->toIso8601String(),
            'history' => $entry->histories->map(fn (RegistrationStatusHistory $h) => [
                'from' => $h->from_status->value,
                'to' => $h->to_status->value,
                'by' => $h->user->name,
                'note' => $h->note,
                'at' => $h->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
```

`RegistrationRequest.php`:

```php
<?php

namespace App\Domains\Registrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Satu kelas untuk buat dan ubah draf; `$this->route('registration')` kosong saat buat. */
class RegistrationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schoolId = $this->route('registration')?->school_id ?? $this->user()->school_id ?? $this->input('school_id');

        return [
            'title' => ['required', 'string', 'max:150'],
            'expected_count' => ['nullable', 'integer', 'min:1', 'max:5000'],
            // Operator sekolah dikunci ke sekolahnya; hanya staf memilih sekolah, dan hanya saat buat.
            'school_id' => $this->user()->school_id === null && $this->isMethod('post')
                ? ['required', 'integer', 'exists:schools,id']
                : ['nullable'],
            'student_ids' => ['present', 'array'],
            'student_ids.*' => ['integer', 'distinct', Rule::exists('students', 'id')->where('school_id', $schoolId)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul pendaftaran wajib diisi.',
            'school_id.required' => 'Pilih sekolah.',
            'student_ids.*.exists' => 'Siswa tidak ditemukan di sekolah ini.',
        ];
    }
}
```

`DecisionRequest.php`:

```php
<?php

namespace App\Domains\Registrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'fix', 'reject'])],
            'note' => ['nullable', 'string', 'max:500', Rule::requiredIf(fn () => in_array($this->input('decision'), ['fix', 'reject'], true))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Pilih keputusan.',
            'decision.in' => 'Pilih keputusan.',
            'note.required' => 'Catatan wajib diisi untuk keputusan ini.',
        ];
    }
}
```

- [ ] **Step 4: Implementasi controller**

`RegistrationController.php`:

```php
<?php

namespace App\Domains\Registrations\Http\Controllers;

use App\Domains\Registrations\Http\Requests\RegistrationRequest;
use App\Domains\Registrations\Models\Registration;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Registrations\Support\EntryPresenter;
use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use App\Domains\Students\Services\StudentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrations,
        private readonly StudentService $students,
        private readonly EligibilityService $eligibility,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Pendaftaran/Index', [
            'registrations' => $this->registrations->paginate()->through(fn (Registration $r) => $this->summary($r)),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $schoolId = $user->school_id ?? ($request->integer('school_id') ?: null);

        return Inertia::render('Pendaftaran/Form', [
            'registration' => null,
            'candidates' => $this->candidates($schoolId, null),
            'schools' => $this->schools->optionsFor($user),
            'school_id' => $schoolId,
        ]);
    }

    public function store(RegistrationRequest $request): RedirectResponse
    {
        $registration = $this->registrations->createDraft($request->validated(), $request->user());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Draf pendaftaran disimpan.');
    }

    public function show(Registration $registration): Response
    {
        $registration->load(['school', 'creator', 'entries.student', 'entries.histories.user']);

        return Inertia::render('Pendaftaran/Show', [
            'registration' => $this->summary($registration) + [
                'expected_count' => $registration->expected_count,
                'created_by' => $registration->creator?->name,
            ],
            'entries' => $registration->entries->map(fn (RegistrationStudent $e) => EntryPresenter::row($e))->values(),
        ]);
    }

    public function edit(Request $request, Registration $registration): Response|RedirectResponse
    {
        if (! $registration->isDraft()) {
            return redirect()->route('pendaftaran.show', $registration)
                ->with('error', 'Pendaftaran yang sudah diajukan tidak dapat diubah.');
        }

        $registration->load('entries');

        return Inertia::render('Pendaftaran/Form', [
            'registration' => ['id' => $registration->id, 'title' => $registration->title, 'expected_count' => $registration->expected_count],
            'candidates' => $this->candidates($registration->school_id, $registration),
            'schools' => null,
            'school_id' => $registration->school_id,
        ]);
    }

    public function update(RegistrationRequest $request, Registration $registration): RedirectResponse
    {
        $this->registrations->updateDraft($registration, $request->validated());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Draf pendaftaran diperbarui.');
    }

    public function destroy(Registration $registration): RedirectResponse
    {
        $this->registrations->deleteDraft($registration);

        return redirect()->route('pendaftaran.index')->with('success', 'Draf pendaftaran dihapus.');
    }

    public function submit(Request $request, Registration $registration): RedirectResponse
    {
        $this->registrations->submit($registration, $request->user());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Pendaftaran diajukan untuk diverifikasi.');
    }

    public function resubmit(Request $request, Registration $registration, RegistrationStudent $entry): RedirectResponse
    {
        $this->registrations->resubmit($entry, $request->user());

        return redirect()->route('pendaftaran.show', $registration)->with('success', 'Siswa diajukan ulang.');
    }

    /** @return array<string, mixed> */
    private function summary(Registration $r): array
    {
        return [
            'id' => $r->id,
            'title' => $r->title,
            'school' => $r->school?->name,
            'created_at' => $r->created_at->toIso8601String(),
            'submitted_at' => $r->submitted_at?->toIso8601String(),
            'students_count' => $r->entries->count(),
            'counts' => $r->entries->countBy(fn (RegistrationStudent $e) => $e->status->value)->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function candidates(?int $schoolId, ?Registration $registration): array
    {
        if ($schoolId === null) {
            return [];
        }
        $selected = $registration?->entries->pluck('student_id')->all() ?? [];

        return $this->students->forSchool($schoolId)->map(function (Student $s) use ($registration, $selected) {
            $check = $this->eligibility->check($s, null, $registration?->id);

            return [
                'id' => $s->id,
                'name' => $s->name,
                'nisn' => $s->nisn,
                'age' => $this->eligibility->age($s),
                'eligible' => $check['eligible'],
                'reasons' => $check['reasons'],
                'missing_docs' => collect(['kk', 'akta_lahir'])->diff($s->documents->pluck('type'))->isNotEmpty(),
                'selected' => in_array($s->id, $selected, true),
            ];
        })->values()->all();
    }
}
```

`VerificationController.php`:

```php
<?php

namespace App\Domains\Registrations\Http\Controllers;

use App\Domains\Registrations\Http\Requests\DecisionRequest;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\VerificationService;
use App\Domains\Registrations\Support\EntryPresenter;
use App\Domains\Schools\Services\SchoolService;
use App\Domains\Students\Services\EligibilityService;
use App\Domains\Students\Services\StudentDocumentService;
use App\Http\Controllers\Controller;
use App\Support\Nik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function __construct(
        private readonly VerificationService $verification,
        private readonly EligibilityService $eligibility,
        private readonly StudentDocumentService $documents,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Verifikasi/Index', [
            'queue' => $this->verification->queue()->through(fn (RegistrationStudent $e) => [
                'id' => $e->id,
                'student' => ['id' => $e->student->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn],
                'school' => $e->registration->school?->name,
                'registration' => $e->registration->title,
                'since' => $e->updated_at->toIso8601String(),
            ]),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function show(Request $request, RegistrationStudent $entry): Response
    {
        $entry->load(['student.school', 'student.documents', 'student.registrationEntries', 'registration', 'histories.user']);
        $student = $entry->student;

        return Inertia::render('Verifikasi/Show', [
            'entry' => EntryPresenter::row($entry) + ['registration' => ['id' => $entry->registration->id, 'title' => $entry->registration->title]],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nisn' => $student->nisn,
                'nik' => Nik::mask($student->nik),
                'birth_date' => $student->birth_date->toDateString(),
                'age' => $this->eligibility->age($student),
                'gender' => $student->gender,
                'school' => $student->school?->name,
            ],
            'documents' => $request->user()->can('dokumen.lihat') ? $this->documents->rows($student) : [],
            'eligibility' => $this->eligibility->check($student, null, $entry->registration_id),
        ]);
    }

    public function decide(DecisionRequest $request, RegistrationStudent $entry): RedirectResponse
    {
        $this->verification->decide($entry, $request->validated('decision'), $request->validated('note'), $request->user());

        return redirect()->route('verifikasi.index')->with('success', 'Keputusan disimpan.');
    }
}
```

- [ ] **Step 5: Jalankan seluruh suite backend**

Run: `php artisan test`
Expected: PASS semua (tes lama termasuk `SharedPropsTest`, `ActivityLogHttpTest`, `StudentHttpTest` tetap hijau; bila ada ekspektasi daftar jenis log atau menu yang berubah, perbarui ekspektasi itu sesuai perubahan sengaja).

- [ ] **Step 6: Pint dan Larastan**

Run: `vendor/bin/pint` lalu `composer analyse`
Expected: Pint merapikan tanpa galat; Larastan level 5 bersih. Perbaiki temuan dengan docblock/closure seperti pola Fase 3 (jangan menurunkan level).

- [ ] **Step 7: Commit**

```bash
git add app routes lang tests
git commit -m "feat: tambah halaman pendaftaran dan verifikasi, menu, serta label log" -m "RegistrationController dan VerificationController dengan FormRequest, rute berizin (pendaftaran.* dan verifikasi.*), menu Pendaftaran dan Antrean verifikasi, label log pendaftaran, serta prop locked dan daftar pendaftaran pada detail siswa. Ajukan ulang memakai scoped binding agar entri milik pendaftaran lain 404. SchoolService::optionsFor dan StudentDocumentService::rows diekstrak agar tidak diduplikasi antar-controller. Matriks izin diperluas untuk semua rute baru."
```

---

## FRONTEND

### Task 8: Panduan UI, tipe, status, ikon, StatusBadge

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (sisip 5.21–5.24 sebelum `## 6. Pola lintas halaman`), `resources/js/lib/status.ts`, `resources/js/lib/status.test.ts`, `resources/js/Components/shared/StatusBadge.vue`, `resources/js/config/menuIcons.ts`
- Create: `resources/js/types/registration.ts`

**Interfaces:**
- Produces: `registrationStatus(status: string): StatusMeta`; `StatusBadge` prop `kind?: 'user' | 'registration'` (default `user`); ikon menu `clipboard-list` dan `badge-check`; tipe `RegistrationStatus, HistoryRow, EntryRow, RegistrationRow, RegistrationDetail, CandidateRow, QueueRow`.

- [ ] **Step 1: Tulis tes yang gagal** — tambah di `status.test.ts` (impor `registrationStatus`):

```ts
describe('registrationStatus', () => {
    it('memetakan status entri ke label dan varian', () => {
        expect(registrationStatus('draf')).toEqual({ label: 'Draf', variant: 'neutral' });
        expect(registrationStatus('diajukan')).toEqual({ label: 'Diajukan', variant: 'info' });
        expect(registrationStatus('perlu_perbaikan')).toEqual({ label: 'Perlu perbaikan', variant: 'warning' });
        expect(registrationStatus('terverifikasi')).toEqual({ label: 'Terverifikasi', variant: 'success' });
        expect(registrationStatus('ditolak')).toEqual({ label: 'Ditolak', variant: 'danger' });
    });

    it('status tak dikenal jatuh ke netral', () => {
        expect(registrationStatus('aneh')).toEqual({ label: 'aneh', variant: 'neutral' });
    });
});
```

Run: `npm test -- status` — Expected: FAIL.

- [ ] **Step 2: Implementasi**

`status.ts` tambah:

```ts
const REGISTRATION_STATUS: Record<string, StatusMeta> = {
    draf: { label: 'Draf', variant: 'neutral' },
    diajukan: { label: 'Diajukan', variant: 'info' },
    perlu_perbaikan: { label: 'Perlu perbaikan', variant: 'warning' },
    terverifikasi: { label: 'Terverifikasi', variant: 'success' },
    terjadwal: { label: 'Terjadwal', variant: 'info' },
    sudah_rekaman: { label: 'Sudah rekaman', variant: 'success' },
    tidak_hadir: { label: 'Tidak hadir', variant: 'danger' },
    belum_rekaman: { label: 'Belum rekaman', variant: 'warning' },
    ditolak: { label: 'Ditolak', variant: 'danger' },
};

export function registrationStatus(status: string): StatusMeta {
    return REGISTRATION_STATUS[status] ?? { label: status, variant: 'neutral' };
}
```

`StatusBadge.vue`: ubah `<script>`:

```ts
import { registrationStatus, userStatus, type BadgeVariant } from '@/lib/status';
...
const props = defineProps<{ status: string; kind?: 'user' | 'registration' }>();
...
const meta = computed(() =>
    (props.kind === 'registration' ? registrationStatus : userStatus)(props.status),
);
```

`menuIcons.ts`: impor `BadgeCheck`, `ClipboardList` dan tambahkan `'clipboard-list': ClipboardList, 'badge-check': BadgeCheck`.

`types/registration.ts`:

```ts
export type RegistrationStatus =
    | 'draf' | 'diajukan' | 'perlu_perbaikan' | 'terverifikasi' | 'terjadwal'
    | 'sudah_rekaman' | 'tidak_hadir' | 'belum_rekaman' | 'ditolak';

export type HistoryRow = {
    from: RegistrationStatus;
    to: RegistrationStatus;
    by: string;
    note: string | null;
    at: string;
};

export type EntryRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    status: RegistrationStatus;
    note: string | null;
    decided_at: string | null;
    history: HistoryRow[];
};

export type RegistrationRow = {
    id: number;
    title: string;
    school: string | null;
    created_at: string;
    submitted_at: string | null;
    students_count: number;
    counts: Partial<Record<RegistrationStatus, number>>;
};

export type RegistrationDetail = RegistrationRow & {
    expected_count: number | null;
    created_by: string | null;
};

export type CandidateRow = {
    id: number;
    name: string;
    nisn: string;
    age: number;
    eligible: boolean;
    reasons: string[];
    missing_docs: boolean;
    selected: boolean;
};

export type QueueRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    school: string | null;
    registration: string;
    since: string;
};
```

Panduan UI: sisipkan sebelum `## 6. Pola lintas halaman` (tulis dengan Edit; teks Indonesia):

```
### 5.21 Pendaftaran: daftar dan form `/pendaftaran` (`pendaftaran.lihat`)
- **Daftar:** judul "Pendaftaran", deskripsi "Kelompokkan siswa eligible untuk diajukan ke verifikasi." Tombol **+ Buat pendaftaran** (`pendaftaran.buat`). Filter (pola 5.7): cari judul, Status (Semua / Draf / Diajukan / Perlu perbaikan / Terverifikasi / Ditolak), Sekolah (hanya staf). Tabel: **Pendaftaran** (judul, tanggal dibuat), **Sekolah** (hanya staf), **Peserta** (jumlah), **Ringkasan status** (daftar berteks mis. "Diajukan 12 · Terverifikasi 3"), **Aksi** (Lihat; Ubah dan Hapus hanya untuk draf). Hapus draf memakai konfirmasi "Draf dan daftar siswanya akan dihapus." State: loading, kosong ("Belum ada pendaftaran" + tombol buat), hasil filter kosong.
- **Form** (`/pendaftaran/buat`, `/pendaftaran/{id}/ubah`, pola 5.8): kartu **Detail pendaftaran** (Judul wajib, Perkiraan jumlah peserta opsional, Sekolah hanya staf saat buat; mengganti sekolah memuat ulang daftar siswa) dan kartu **Pilih siswa** (5.22). Bar aksi bawah: Batal dan **Simpan draf** (menuju detail, tempat **Ajukan** berada).

### 5.22 Pemilih siswa (`StudentMultiSelect`)
- Kolom cari (nama atau NISN), tombol **Pilih semua yang eligible**, dan penghitung "N dipilih" (`role="status"`, diumumkan). Tiap baris: kotak centang, nama, NISN dan umur. Baris belum eligible dinonaktifkan dan menampilkan alasannya sebagai teks (bukan hanya warna), disertai tautan "Lengkapi dokumen" ke detail siswa bila dokumen kurang. Siswa yang sudah terpilih tetapi menjadi tidak eligible tetap dapat dibatalkan pilihannya. Galat per siswa (dari server) tampil di bawah barisnya. Dapat dioperasikan dengan keyboard; mobile: baris menumpuk.

### 5.23 Pendaftaran: detail `/pendaftaran/{id}`
- Header: judul, badge ringkasan, tombol sesuai keadaan. **Draf:** Ubah, **Ajukan** (dialog "Ajukan N siswa untuk diverifikasi? Setelah diajukan pendaftaran tidak dapat diubah.") dan menu Hapus. Kartu **Informasi** (sekolah, dibuat oleh, perkiraan peserta, waktu diajukan). Tabel entri: **Siswa**, **Status** (badge berteks), **Catatan petugas** (untuk perlu perbaikan/ditolak), **Aksi** (Riwayat membuka dialog dengan timeline; untuk perlu perbaikan: Perbaiki data ke detail siswa dan **Ajukan ulang**). Galat pengajuan tampil sebagai banner dan alasan per siswa di baris terkait.
- **Timeline riwayat:** daftar vertikal berpenanda titik: "Status lama → baru", lalu waktu · pelaku, lalu catatan bila ada.

### 5.24 Verifikasi: antrean dan keputusan `/verifikasi` (`verifikasi.lihat`)
- **Antrean:** filter cari nama/NISN dan Sekolah (staf). Tabel **Siswa** (nama, NISN), **Sekolah**, **Pendaftaran**, **Diajukan** (waktu relatif), **Aksi** (tombol "Periksa"). State: loading, kosong ("Tidak ada antrean verifikasi"), hasil filter kosong.
- **Keputusan** (`/verifikasi/{id}`): kartu **Data siswa** (NIK dimasker, tanggal lahir dan umur, jenis kelamin, sekolah, pendaftaran), kartu **Dokumen** (tautan unduh KK dan akta), banner eligibility bila belum memenuhi syarat, kartu **Riwayat**, dan kartu **Keputusan** (hanya `verifikasi.putuskan` dan status Diajukan): pilihan radio berteks **Setujui / Minta perbaikan / Tolak** dengan keterangan singkat, kolom Catatan (wajib untuk dua yang terakhir), tombol **Simpan keputusan** (Tolak meminta konfirmasi). Setelah simpan kembali ke antrean dengan toast. Entri yang sudah diputuskan menampilkan keputusan terakhir tanpa formulir.
```

- [ ] **Step 3: Jalankan**

Run: `npm test -- status` → PASS; `npm run typecheck` → bersih.

- [ ] **Step 4: Commit**

```bash
git add docs resources/js
git commit -m "docs: tambah panduan UI Fase 4 dan fondasi status pendaftaran di FE" -m "Panduan UI 5.21-5.24 (pendaftaran, pemilih siswa, detail, antrean dan keputusan verifikasi). Peta label/varian status entri, StatusBadge mendukung jenis registration, ikon menu baru, dan tipe TypeScript pendaftaran."
```

---

### Task 9: StatusTimeline dan StudentMultiSelect

**Files:**
- Create: `resources/js/Components/shared/StatusTimeline.vue`, `resources/js/Components/shared/StudentMultiSelect.vue`, `resources/js/Components/shared/StudentMultiSelect.test.ts`

**Interfaces:**
- Consumes: tipe `HistoryRow`, `CandidateRow`; `registrationStatus`.
- Produces: `<StatusTimeline :items="HistoryRow[]" />`; `<StudentMultiSelect v-model="number[]" :candidates="CandidateRow[]" :errors="Record<number, string>" />`.

- [ ] **Step 1: Tulis tes yang gagal** — `StudentMultiSelect.test.ts`:

```ts
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { CandidateRow } from '@/types/registration';
import StudentMultiSelect from './StudentMultiSelect.vue';

const base = { age: 16, missing_docs: false, selected: false, reasons: [] };
const candidates: CandidateRow[] = [
    { ...base, id: 1, name: 'Ani', nisn: '0000000001', eligible: true },
    { ...base, id: 2, name: 'Budi', nisn: '0000000002', eligible: true },
    { ...base, id: 3, name: 'Citra', nisn: '0000000003', eligible: false, missing_docs: true, reasons: ['Kartu Keluarga belum diunggah.'] },
];

function mountSelect(modelValue: number[] = []) {
    return mount(StudentMultiSelect, {
        props: { candidates, modelValue, 'onUpdate:modelValue': () => {} },
        global: { stubs: { Link: { template: '<a><slot /></a>' } } },
    });
}

describe('StudentMultiSelect', () => {
    it('menampilkan alasan sebagai teks untuk siswa belum eligible', () => {
        const text = mountSelect().text();
        expect(text).toContain('Kartu Keluarga belum diunggah.');
        expect(text).toContain('Lengkapi dokumen');
    });

    it('"Pilih semua yang eligible" hanya memilih yang eligible', async () => {
        const wrapper = mountSelect();
        const button = wrapper.findAll('button').find((b) => b.text().includes('Pilih semua'))!;
        await button.trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[1, 2]]);
    });

    it('penghitung mengikuti jumlah terpilih', () => {
        expect(mountSelect([1, 2]).find('[role="status"]').text()).toContain('2 dipilih');
    });

    it('pencarian menyaring menurut nama atau NISN', async () => {
        const wrapper = mountSelect();
        await wrapper.find('input[type="text"], input:not([type])').setValue('budi');
        expect(wrapper.text()).toContain('Budi');
        expect(wrapper.text()).not.toContain('Ani');
    });
});
```

Run: `npm test -- StudentMultiSelect` → FAIL.

- [ ] **Step 2: Implementasi**

`StatusTimeline.vue`:

```vue
<script setup lang="ts">
import { formatDateTime } from '@/lib/date';
import { registrationStatus } from '@/lib/status';
import type { HistoryRow } from '@/types/registration';

defineProps<{ items: HistoryRow[] }>();
</script>

<template>
    <ol v-if="items.length" class="flex flex-col gap-4">
        <li v-for="(item, i) in items" :key="i" class="flex gap-3">
            <span
                class="bg-primary mt-1.5 size-2 shrink-0 rounded-full"
                aria-hidden="true"
            />
            <div class="min-w-0 text-sm leading-5">
                <p class="font-medium">
                    {{ registrationStatus(item.from).label }}
                    →
                    {{ registrationStatus(item.to).label }}
                </p>
                <p class="text-muted-foreground text-xs leading-4">
                    {{ formatDateTime(item.at) }} · {{ item.by }}
                </p>
                <p v-if="item.note" class="mt-1 break-words">
                    {{ item.note }}
                </p>
            </div>
        </li>
    </ol>
    <p v-else class="text-muted-foreground text-sm">
        Belum ada perubahan status.
    </p>
</template>
```

`StudentMultiSelect.vue`:

```vue
<script setup lang="ts">
import SearchInput from '@/Components/shared/SearchInput.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import type { CandidateRow } from '@/types/registration';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const selected = defineModel<number[]>({ required: true });
const props = defineProps<{
    candidates: CandidateRow[];
    errors?: Record<number, string>;
}>();

const search = ref('');
const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? props.candidates.filter(
              (c) => c.name.toLowerCase().includes(q) || c.nisn.includes(q),
          )
        : props.candidates;
});

// Siswa yang terpilih tetapi sudah tidak eligible tetap bisa dibatalkan.
const isDisabled = (c: CandidateRow) =>
    !c.eligible && !selected.value.includes(c.id);

function toggle(c: CandidateRow, on: boolean) {
    selected.value = on
        ? [...selected.value, c.id]
        : selected.value.filter((id) => id !== c.id);
}

function selectAllEligible() {
    const ids = new Set(selected.value);
    props.candidates.filter((c) => c.eligible).forEach((c) => ids.add(c.id));
    selected.value = [...ids];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <SearchInput
                    v-model="search"
                    placeholder="Cari nama atau NISN"
                    aria-label="Cari siswa"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                class="h-10 px-4"
                @click="selectAllEligible"
            >
                Pilih semua yang eligible
            </Button>
        </div>

        <p role="status" class="text-muted-foreground text-sm">
            {{ selected.length }} dipilih
        </p>

        <ul v-if="visible.length" class="divide-y rounded-lg border">
            <li
                v-for="c in visible"
                :key="c.id"
                class="flex items-start gap-3 px-4 py-3"
                :class="{ 'bg-secondary/50': isDisabled(c) }"
            >
                <Checkbox
                    :id="`siswa-${c.id}`"
                    class="mt-0.5"
                    :model-value="selected.includes(c.id)"
                    :disabled="isDisabled(c)"
                    @update:model-value="toggle(c, $event === true)"
                />
                <div class="min-w-0 flex-1 text-sm leading-5">
                    <label
                        :for="`siswa-${c.id}`"
                        class="block font-medium break-words"
                    >
                        {{ c.name }}
                        <span class="text-muted-foreground font-normal">
                            · NISN {{ c.nisn }} · {{ c.age }} th
                        </span>
                    </label>
                    <ul
                        v-if="!c.eligible"
                        class="text-muted-foreground mt-1 list-disc pl-4 text-xs leading-4"
                    >
                        <li v-for="reason in c.reasons" :key="reason">
                            {{ reason }}
                        </li>
                    </ul>
                    <Link
                        v-if="c.missing_docs"
                        :href="route('siswa.show', c.id)"
                        class="text-primary mt-1 inline-block text-xs underline"
                    >
                        Lengkapi dokumen
                    </Link>
                    <p v-if="errors?.[c.id]" class="text-danger mt-1 text-xs">
                        {{ errors[c.id] }}
                    </p>
                </div>
            </li>
        </ul>
        <p v-else class="text-muted-foreground text-sm">
            {{
                candidates.length
                    ? 'Tidak ada siswa yang sesuai pencarian.'
                    : 'Belum ada siswa di sekolah ini.'
            }}
        </p>
    </div>
</template>
```

Catatan: tes menaruh `route()` global; di Vitest `route` mungkin tidak terdefinisi. Jika tes gagal dengan "route is not defined", tambahkan stub global di tes: `global: { mocks: { route: (n: string, p: unknown) => `/${n}/${p}` }, stubs: {...} }`, atau ikuti pola yang dipakai `DataTable.test.ts`/komponen lain yang memanggil `route`.

- [ ] **Step 3: Jalankan**

Run: `npm test -- StudentMultiSelect` → PASS; `npm run typecheck` dan `npm run lint` → bersih.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/shared
git commit -m "feat: tambah StatusTimeline dan pemilih siswa multi-pilih" -m "StatusTimeline menampilkan riwayat status secara kronologis. StudentMultiSelect: pencarian nama/NISN, pilih semua yang eligible, penghitung role=status, alasan ketidak-eligiblean sebagai teks, tautan lengkapi dokumen, dan pembatalan pilihan siswa yang sudah tidak eligible."
```

---

### Task 10: Halaman Pendaftaran (daftar, form, detail)

**Files:**
- Create: `resources/js/Pages/Pendaftaran/Index.vue`, `resources/js/Pages/Pendaftaran/Form.vue`, `resources/js/Pages/Pendaftaran/Show.vue`

**Interfaces:**
- Consumes: kontrak prop Task 7, `StatusBadge kind="registration"`, `StatusTimeline`, `StudentMultiSelect`, pola `Siswa/Index.vue`, `Siswa/Form.vue`, `Siswa/Show.vue`.

- [ ] **Step 1: `Pendaftaran/Index.vue`** (ikuti pola `Siswa/Index.vue`; impor sama kecuali yang tidak dipakai):

```vue
<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import SearchInput from '@/Components/shared/SearchInput.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { useDataTable } from '@/composables/useDataTable';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type { Paginator } from '@/types/access';
import type { RegistrationRow } from '@/types/registration';
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ClipboardList, Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    registrations: Paginator<RegistrationRow>;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
}>();

const { can } = usePermission();
const { filters, loading, apply, reset } = useDataTable('/pendaftaran', {
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    school_id: props.filters.school_id ?? '',
});

watch(() => [filters.status, filters.school_id], apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const statusOptions = [
    'draf',
    'diajukan',
    'perlu_perbaikan',
    'terverifikasi',
    'ditolak',
].map((value) => ({ value, label: registrationStatus(value).label }));
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

const columns = computed(() => [
    { key: 'title', label: 'Pendaftaran', class: 'w-[280px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'students_count', label: 'Peserta', class: 'w-[96px]' },
    { key: 'counts', label: 'Ringkasan status' },
    { key: 'actions', label: 'Aksi', class: 'w-[72px] text-right' },
]);

const isDraft = (row: RegistrationRow) => row.submitted_at === null;
const confirmOpen = ref(false);
const target = ref<RegistrationRow | null>(null);

function askDelete(row: RegistrationRow) {
    target.value = row;
    confirmOpen.value = true;
}

function destroy() {
    if (target.value)
        router.delete(route('pendaftaran.destroy', target.value.id), {
            preserveScroll: true,
        });
}
</script>

<template>
    <AppLayout title="Pendaftaran">
        <PageHeader
            title="Pendaftaran"
            description="Kelompokkan siswa eligible untuk diajukan ke verifikasi."
        >
            <template #actions>
                <Button
                    v-if="can(PERMISSIONS.PendaftaranBuat)"
                    as-child
                    class="h-10 px-4"
                >
                    <Link :href="route('pendaftaran.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Buat pendaftaran
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari pendaftaran"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari judul pendaftaran"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Status" class="lg:w-[200px]">
                    <SelectField
                        :id="id"
                        v-model="filters.status"
                        :options="statusOptions"
                        all-label="Semua status"
                    />
                </FormField>
                <FormField
                    v-if="schools"
                    v-slot="{ id }"
                    label="Sekolah"
                    class="lg:w-[240px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.school_id"
                        :options="schoolOptions"
                        all-label="Semua sekolah"
                    />
                </FormField>
            </div>

            <div class="flex flex-col gap-4">
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="columns"
                    :rows="registrations.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[800px]"
                >
                    <template #cell-title="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('pendaftaran.show', row.id)"
                                class="block truncate text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ row.title }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                Dibuat {{ formatDate(row.created_at) }}
                            </p>
                        </div>
                    </template>
                    <template #cell-school="{ row }">{{ row.school }}</template>
                    <template #cell-counts="{ row }">
                        <p
                            v-if="isDraft(row)"
                            class="text-muted-foreground text-xs"
                        >
                            Draf · belum diajukan
                        </p>
                        <ul v-else class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                            <li
                                v-for="[status, total] in Object.entries(
                                    row.counts,
                                )"
                                :key="status"
                            >
                                {{ registrationStatus(status).label }}
                                {{ total }}
                            </li>
                        </ul>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <RowActions :label="`Aksi untuk ${row.title}`">
                                <DropdownMenuItem
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="route('pendaftaran.show', row.id)"
                                    >
                                        <Eye class="size-4" aria-hidden="true" />
                                        Lihat detail
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="
                                        isDraft(row) &&
                                        can(PERMISSIONS.PendaftaranBuat)
                                    "
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="route('pendaftaran.edit', row.id)"
                                    >
                                        <Pencil
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Ubah
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="
                                        isDraft(row) &&
                                        can(PERMISSIONS.PendaftaranBuat)
                                    "
                                    class="text-danger focus:text-danger h-10 gap-3 px-3 text-sm font-medium"
                                    @select="askDelete(row)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    Hapus
                                </DropdownMenuItem>
                            </RowActions>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada pendaftaran yang sesuai dengan pencarian atau filter Anda."
                        >
                            <template #action>
                                <Button
                                    variant="outline"
                                    class="h-9"
                                    @click="reset"
                                    >Reset filter</Button
                                >
                            </template>
                        </EmptyState>
                        <EmptyState
                            v-else
                            :icon="ClipboardList"
                            title="Belum ada pendaftaran"
                            description="Buat pendaftaran untuk mengelompokkan siswa eligible dan mengajukannya ke verifikasi."
                        >
                            <template
                                v-if="can(PERMISSIONS.PendaftaranBuat)"
                                #action
                            >
                                <Button as-child class="h-9">
                                    <Link :href="route('pendaftaran.create')"
                                        >Buat pendaftaran</Link
                                    >
                                </Button>
                            </template>
                        </EmptyState>
                    </template>
                </DataTable>
                <Pagination :paginator="registrations" />
            </div>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Hapus ${target?.title ?? ''}?`"
            description="Draf dan daftar siswanya akan dihapus."
            confirm-label="Hapus"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
```

- [ ] **Step 2: `Pendaftaran/Form.vue`**:

```vue
<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import StudentMultiSelect from '@/Components/shared/StudentMultiSelect.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import type { CandidateRow } from '@/types/registration';
import { Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    registration: {
        id: number;
        title: string;
        expected_count: number | null;
    } | null;
    candidates: CandidateRow[];
    schools: { id: number; name: string }[] | null;
    school_id: number | null;
}>();

const isEdit = computed(() => props.registration !== null);
const title = computed(() =>
    isEdit.value ? 'Ubah pendaftaran' : 'Buat pendaftaran',
);

const form = useForm({
    title: props.registration?.title ?? '',
    expected_count: (props.registration?.expected_count ?? '') as number | '',
    school_id: (props.school_id ?? '') as number | '',
    student_ids: props.candidates.filter((c) => c.selected).map((c) => c.id),
});

const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

// Staf memilih sekolah: muat ulang hanya daftar kandidat, pilihan lama dikosongkan.
watch(
    () => form.school_id,
    (schoolId) => {
        if (isEdit.value || !props.schools || schoolId === '') return;
        router.get(
            route('pendaftaran.create'),
            { school_id: schoolId },
            {
                only: ['candidates'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => (form.student_ids = []),
            },
        );
    },
);

const clientErrors = ref<Record<string, string>>({});
const error = (key: string) =>
    clientErrors.value[key] ?? (form.errors as Record<string, string>)[key];
const rowErrors = computed(() =>
    Object.fromEntries(
        Object.entries(form.errors as Record<string, string>)
            .filter(([key]) => key.startsWith('student_ids.'))
            .map(([key, message]) => [Number(key.split('.')[1]), message]),
    ),
);
const hasErrors = computed(
    () =>
        Object.keys(clientErrors.value).length > 0 ||
        Object.keys(form.errors).length > 0,
);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.title.trim()) errors.title = 'Judul pendaftaran wajib diisi.';
    if (props.schools && !isEdit.value && form.school_id === '')
        errors.school_id = 'Pilih sekolah.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    const options = { onError: focusFirstInvalid };
    form.transform((data) => ({
        ...data,
        expected_count: data.expected_count === '' ? null : data.expected_count,
    }));

    if (props.registration)
        form.put(route('pendaftaran.update', props.registration.id), options);
    else form.post(route('pendaftaran.store'), options);
}

const crumbs = computed(() => [
    { label: 'Pendaftaran', href: route('pendaftaran.index') },
    { label: title.value },
]);
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">{{ title }}</h1>
            <p class="text-muted-foreground text-sm leading-5">
                Simpan sebagai draf, lalu ajukan dari halaman detail setelah
                daftar siswa sudah benar.
            </p>
        </div>

        <form
            class="flex max-w-[720px] flex-col gap-6"
            novalidate
            @submit.prevent="submit"
        >
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Detail pendaftaran
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>

                <InlineAlert v-if="hasErrors"
                    >Periksa kembali data yang Anda isi.</InlineAlert
                >

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Judul / keterangan"
                    required
                    :error="error('title')"
                >
                    <Input
                        :id="id"
                        v-model="form.title"
                        class="h-10"
                        placeholder="Contoh: Perekaman gelombang 1"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Perkiraan jumlah peserta"
                    :error="error('expected_count')"
                    hint="Opsional."
                >
                    <Input
                        :id="id"
                        v-model="form.expected_count"
                        type="number"
                        min="1"
                        class="h-10"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-if="schools && !isEdit"
                    v-slot="{ id, describedBy, invalid }"
                    label="Sekolah"
                    required
                    :error="error('school_id')"
                >
                    <SelectField
                        :id="id"
                        v-model="form.school_id"
                        :options="schoolOptions"
                        placeholder="Pilih sekolah"
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </FormField>
            </div>

            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Pilih siswa
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Hanya siswa eligible yang dapat dipilih.
                    </p>
                </div>
                <p
                    v-if="schools && !isEdit && form.school_id === ''"
                    class="text-muted-foreground text-sm"
                >
                    Pilih sekolah terlebih dahulu untuk menampilkan siswa.
                </p>
                <StudentMultiSelect
                    v-else
                    v-model="form.student_ids"
                    :candidates="candidates"
                    :errors="rowErrors"
                />
            </div>

            <div class="flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link
                        :href="
                            registration
                                ? route('pendaftaran.show', registration.id)
                                : route('pendaftaran.index')
                        "
                        >Batal</Link
                    >
                </Button>
                <Button
                    type="submit"
                    class="h-10 px-4"
                    :disabled="form.processing"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan draf' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
```

- [ ] **Step 3: `Pendaftaran/Show.vue`**:

```vue
<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import RowActions from '@/Components/shared/RowActions.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import StatusTimeline from '@/Components/shared/StatusTimeline.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type { EntryRow, RegistrationDetail } from '@/types/registration';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Send, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    registration: RegistrationDetail;
    entries: EntryRow[];
}>();

const { can } = usePermission();
const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const entryError = (e: EntryRow) => errors.value[`student_ids.${e.student.id}`];

const isDraft = computed(() => props.registration.submitted_at === null);
const submitOpen = ref(false);
const deleteOpen = ref(false);
const historyOf = ref<EntryRow | null>(null);

const info = computed(() => [
    { label: 'Sekolah', value: props.registration.school },
    { label: 'Dibuat oleh', value: props.registration.created_by },
    {
        label: 'Perkiraan peserta',
        value: props.registration.expected_count?.toString() ?? null,
    },
    {
        label: 'Diajukan',
        value: props.registration.submitted_at
            ? formatDateTime(props.registration.submitted_at)
            : 'Belum diajukan',
    },
]);

const columns = [
    { key: 'student', label: 'Siswa', class: 'w-[240px]' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
    { key: 'note', label: 'Catatan petugas' },
    { key: 'actions', label: 'Aksi', class: 'w-[200px] text-right' },
];

function submit() {
    router.post(
        route('pendaftaran.submit', props.registration.id),
        {},
        { preserveScroll: true },
    );
}

function destroy() {
    router.delete(route('pendaftaran.destroy', props.registration.id));
}

function resubmit(entry: EntryRow) {
    router.post(
        route('pendaftaran.resubmit', [props.registration.id, entry.id]),
        {},
        { preserveScroll: true },
    );
}

const crumbs = computed(() => [
    { label: 'Pendaftaran', href: route('pendaftaran.index') },
    { label: props.registration.title },
]);
</script>

<template>
    <AppLayout :title="registration.title" :breadcrumbs="crumbs">
        <div
            class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-2xl leading-8 font-semibold break-words">
                    {{ registration.title }}
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    {{ registration.students_count }} siswa ·
                    {{ isDraft ? 'Draf' : 'Sudah diajukan' }}
                </p>
            </div>
            <div
                v-if="isDraft"
                class="flex w-full items-center gap-2 sm:w-auto [&>*]:flex-1 sm:[&>*]:flex-none"
            >
                <Button
                    v-if="can(PERMISSIONS.PendaftaranBuat)"
                    as-child
                    variant="outline"
                    class="h-10 px-4"
                >
                    <Link :href="route('pendaftaran.edit', registration.id)">
                        <Pencil class="size-4" aria-hidden="true" />
                        Ubah
                    </Link>
                </Button>
                <Button
                    v-if="can(PERMISSIONS.PendaftaranAjukan)"
                    type="button"
                    class="h-10 px-4"
                    @click="submitOpen = true"
                >
                    <Send class="size-4" aria-hidden="true" />
                    Ajukan
                </Button>
                <RowActions
                    v-if="can(PERMISSIONS.PendaftaranBuat)"
                    :label="`Aksi lainnya untuk ${registration.title}`"
                    outlined
                >
                    <DropdownMenuItem
                        class="text-danger focus:text-danger h-10 gap-3 px-3 text-sm font-medium"
                        @select="deleteOpen = true"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Hapus
                    </DropdownMenuItem>
                </RowActions>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <InlineAlert v-if="errors.student_ids">
                {{ errors.student_ids }} Perbaiki data siswa yang ditandai,
                lalu ajukan lagi.
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Informasi</h2>
                <InfoList :items="info" />
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-base leading-6 font-semibold">Siswa</h2>
                <DataTable
                    :columns="columns"
                    :rows="entries"
                    row-class="h-16"
                    table-class="min-w-[800px]"
                >
                    <template #cell-student="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('siswa.show', row.student.id)"
                                class="block truncate text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ row.student.name }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                NISN {{ row.student.nisn }}
                            </p>
                            <p
                                v-if="entryError(row)"
                                class="text-danger text-xs leading-4"
                            >
                                {{ entryError(row) }}
                            </p>
                        </div>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" kind="registration" />
                    </template>
                    <template #cell-note="{ row }">
                        <span
                            v-if="row.note"
                            class="text-sm leading-5 break-words"
                            >{{ row.note }}</span
                        >
                        <span v-else class="text-muted-foreground">—</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                v-if="row.status === 'perlu_perbaikan'"
                                as-child
                                variant="outline"
                                class="h-9 px-3"
                            >
                                <Link :href="route('siswa.show', row.student.id)"
                                    >Perbaiki data</Link
                                >
                            </Button>
                            <Button
                                v-if="
                                    row.status === 'perlu_perbaikan' &&
                                    can(PERMISSIONS.PendaftaranAjukan)
                                "
                                type="button"
                                class="h-9 px-3"
                                @click="resubmit(row)"
                            >
                                Ajukan ulang
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                class="h-9 px-3"
                                @click="historyOf = row"
                            >
                                Riwayat
                                <span class="sr-only"
                                    >untuk {{ row.student.name }}</span
                                >
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <p class="text-muted-foreground p-6 text-sm">
                            Belum ada siswa di pendaftaran ini.
                        </p>
                    </template>
                </DataTable>
            </section>
        </div>

        <Dialog :open="historyOf !== null" @update:open="historyOf = null">
            <DialogContent class="rounded-xl">
                <DialogHeader>
                    <DialogTitle>Riwayat status</DialogTitle>
                    <DialogDescription>
                        {{ historyOf?.student.name }} ·
                        {{
                            historyOf
                                ? registrationStatus(historyOf.status).label
                                : ''
                        }}
                    </DialogDescription>
                </DialogHeader>
                <StatusTimeline :items="historyOf?.history ?? []" />
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="submitOpen"
            :title="`Ajukan ${registration.students_count} siswa untuk diverifikasi?`"
            description="Setelah diajukan, pendaftaran tidak dapat diubah."
            confirm-label="Ajukan"
            @confirm="submit"
        />
        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`Hapus ${registration.title}?`"
            description="Draf dan daftar siswanya akan dihapus."
            confirm-label="Hapus"
            destructive
            @confirm="destroy"
        />
    </AppLayout>
</template>
```

Periksa: `DataTable` memakai slot `empty` seperti di `Siswa/Index.vue` (sama). `Send` ada di `@lucide/vue` (bila tidak, pakai `SendHorizontal`/`Check`).

- [ ] **Step 4: Jalankan**

Run: `npm run typecheck`, `npm run lint`, `npm test`
Expected: bersih. Perbaiki galat tipe (mis. `form.errors` cast, `EntryRow` pada `DataTable` generic).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Pendaftaran
git commit -m "feat: tambah halaman daftar, form, dan detail pendaftaran" -m "Daftar dengan filter judul/status/sekolah dan ringkasan status per pendaftaran; form draf dengan pemilih siswa (staf memilih sekolah dan kandidat dimuat ulang sebagian); detail dengan tabel entri, catatan petugas, galat pengajuan per siswa, dialog riwayat, Ajukan (dengan konfirmasi), Ubah/Hapus draf, serta Ajukan ulang dan Perbaiki data untuk entri perlu perbaikan."
```

---

### Task 11: Halaman Verifikasi dan kartu pendaftaran di detail siswa

**Files:**
- Create: `resources/js/Pages/Verifikasi/Index.vue`, `resources/js/Pages/Verifikasi/Show.vue`
- Modify: `resources/js/Pages/Siswa/Show.vue`, `resources/js/types/student.ts`

- [ ] **Step 1: `Verifikasi/Index.vue`**:

```vue
<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import Pagination from '@/Components/shared/Pagination.vue';
import SearchInput from '@/Components/shared/SearchInput.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { useDataTable } from '@/composables/useDataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatWhen } from '@/lib/date';
import type { Paginator } from '@/types/access';
import type { QueueRow } from '@/types/registration';
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { BadgeCheck, Search } from '@lucide/vue';
import { computed, watch } from 'vue';

const props = defineProps<{
    queue: Paginator<QueueRow>;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
}>();

const { filters, loading, apply, reset } = useDataTable('/verifikasi', {
    search: props.filters.search ?? '',
    school_id: props.filters.school_id ?? '',
});

watch(() => filters.school_id, apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

const columns = computed(() => [
    { key: 'student', label: 'Siswa', class: 'w-[240px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'registration', label: 'Pendaftaran' },
    { key: 'since', label: 'Diajukan', class: 'w-[160px]' },
    { key: 'actions', label: 'Aksi', class: 'w-[120px] text-right' },
]);
</script>

<template>
    <AppLayout title="Antrean verifikasi">
        <PageHeader
            title="Antrean verifikasi"
            description="Periksa siswa yang diajukan sekolah dan putuskan satu per satu."
        />

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari siswa"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari nama atau NISN"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField
                    v-if="schools"
                    v-slot="{ id }"
                    label="Sekolah"
                    class="col-span-2 lg:w-[240px]"
                >
                    <SelectField
                        :id="id"
                        v-model="filters.school_id"
                        :options="schoolOptions"
                        all-label="Semua sekolah"
                    />
                </FormField>
            </div>

            <div class="flex flex-col gap-4">
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="columns"
                    :rows="queue.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[800px]"
                >
                    <template #cell-student="{ row }">
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm leading-5 font-semibold"
                            >
                                {{ row.student.name }}
                            </p>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                NISN {{ row.student.nisn }}
                            </p>
                        </div>
                    </template>
                    <template #cell-since="{ row }">{{
                        formatWhen(row.since)
                    }}</template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Button as-child class="h-9 px-3">
                                <Link :href="route('verifikasi.show', row.id)">
                                    Periksa
                                    <span class="sr-only"
                                        >{{ row.student.name }}</span
                                    >
                                </Link>
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada siswa yang sesuai dengan pencarian atau filter Anda."
                        >
                            <template #action>
                                <Button
                                    variant="outline"
                                    class="h-9"
                                    @click="reset"
                                    >Reset filter</Button
                                >
                            </template>
                        </EmptyState>
                        <EmptyState
                            v-else
                            :icon="BadgeCheck"
                            title="Tidak ada antrean verifikasi"
                            description="Siswa yang diajukan sekolah akan muncul di sini."
                        />
                    </template>
                </DataTable>
                <Pagination :paginator="queue" />
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 2: `Verifikasi/Show.vue`**:

```vue
<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import FormField from '@/Components/shared/FormField.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import StatusTimeline from '@/Components/shared/StatusTimeline.vue';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';
import { formatBytes } from '@/lib/files';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { PERMISSIONS } from '@/lib/permissions';
import type { StudentDocumentRow } from '@/types/student';
import type { EntryRow } from '@/types/registration';
import { Link, useForm } from '@inertiajs/vue3';
import { Download, LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    entry: EntryRow & { registration: { id: number; title: string } };
    student: {
        id: number;
        name: string;
        nisn: string;
        nik: string;
        birth_date: string;
        age: number;
        gender: 'L' | 'P';
        school: string | null;
    };
    documents: StudentDocumentRow[];
    eligibility: { eligible: boolean; reasons: string[] };
}>();

const { can } = usePermission();
const canDecide = computed(
    () =>
        props.entry.status === 'diajukan' &&
        can(PERMISSIONS.VerifikasiPutuskan),
);

const decisions = [
    { value: 'approve', label: 'Setujui', hint: 'Siswa lanjut ke penjadwalan.' },
    {
        value: 'fix',
        label: 'Minta perbaikan',
        hint: 'Operator diminta membetulkan data atau dokumen.',
    },
    {
        value: 'reject',
        label: 'Tolak',
        hint: 'Siswa tidak dapat didaftarkan pada pendaftaran ini.',
    },
];

const form = useForm({ decision: '', note: '' });
const clientErrors = ref<Record<string, string>>({});
const error = (key: 'decision' | 'note') =>
    clientErrors.value[key] ?? form.errors[key];
const rejectOpen = ref(false);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.decision) errors.decision = 'Pilih keputusan.';
    else if (form.decision !== 'approve' && !form.note.trim())
        errors.note = 'Catatan wajib diisi untuk keputusan ini.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function send() {
    form.post(route('verifikasi.decide', props.entry.id), {
        onError: focusFirstInvalid,
    });
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();
    if (form.decision === 'reject') rejectOpen.value = true;
    else send();
}

const info = computed(() => [
    { label: 'NIK', value: props.student.nik },
    {
        label: 'Tanggal lahir',
        value: `${formatDate(props.student.birth_date)} (${props.student.age} th)`,
    },
    {
        label: 'Jenis kelamin',
        value: props.student.gender === 'L' ? 'Laki-laki' : 'Perempuan',
    },
    { label: 'Sekolah', value: props.student.school },
    { label: 'Pendaftaran', value: props.entry.registration.title },
]);

const crumbs = computed(() => [
    { label: 'Antrean verifikasi', href: route('verifikasi.index') },
    { label: props.student.name },
]);
</script>

<template>
    <AppLayout title="Verifikasi siswa" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold break-words">
                {{ student.name }}
            </h1>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-muted-foreground text-sm leading-5">
                    NISN {{ student.nisn }}
                </p>
                <StatusBadge :status="entry.status" kind="registration" />
            </div>
        </div>

        <div class="flex max-w-[720px] flex-col gap-6">
            <InlineAlert v-if="!eligibility.eligible" variant="warning">
                Siswa belum memenuhi syarat:
                {{ eligibility.reasons.join(' ') }}
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Data siswa</h2>
                <InfoList :items="info" />
            </section>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Dokumen</h2>
                <ul v-if="documents.length" class="flex flex-col gap-2">
                    <li
                        v-for="doc in documents"
                        :key="doc.type"
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="min-w-0 truncate"
                            >{{
                                doc.type === 'kk'
                                    ? 'Kartu Keluarga'
                                    : 'Akta Kelahiran'
                            }}
                            <span class="text-muted-foreground"
                                >· {{ formatBytes(doc.size) }}</span
                            ></span
                        >
                        <Button as-child variant="outline" class="h-9 px-3">
                            <a :href="doc.url">
                                <Download class="size-4" aria-hidden="true" />
                                Unduh
                                <span class="sr-only">{{ doc.name }}</span>
                            </a>
                        </Button>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">
                    Tidak ada dokumen yang dapat ditampilkan.
                </p>
            </section>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Riwayat</h2>
                <StatusTimeline :items="entry.history" />
            </section>

            <section
                v-if="canDecide"
                class="bg-card flex flex-col gap-4 rounded-lg border p-6"
            >
                <h2 class="text-base leading-6 font-semibold">Keputusan</h2>
                <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                    <fieldset class="flex flex-col gap-3">
                        <legend class="sr-only">Keputusan</legend>
                        <label
                            v-for="d in decisions"
                            :key="d.value"
                            class="flex cursor-pointer items-start gap-3 text-sm leading-5"
                        >
                            <input
                                v-model="form.decision"
                                type="radio"
                                name="decision"
                                :value="d.value"
                                class="accent-primary mt-1 size-4"
                                :aria-invalid="!!error('decision')"
                            />
                            <span>
                                <span class="block font-medium">{{
                                    d.label
                                }}</span>
                                <span class="text-muted-foreground text-xs">{{
                                    d.hint
                                }}</span>
                            </span>
                        </label>
                        <p
                            v-if="error('decision')"
                            class="text-danger text-xs"
                            role="alert"
                        >
                            {{ error('decision') }}
                        </p>
                    </fieldset>

                    <FormField
                        v-slot="{ id, describedBy, invalid }"
                        label="Catatan"
                        :error="error('note')"
                        hint="Wajib untuk Minta perbaikan dan Tolak."
                    >
                        <Textarea
                            :id="id"
                            v-model="form.note"
                            rows="3"
                            maxlength="500"
                            :aria-invalid="invalid"
                            :aria-describedby="describedBy"
                        />
                    </FormField>

                    <div class="flex justify-end gap-2">
                        <Button as-child variant="outline" class="h-10 px-4">
                            <Link :href="route('verifikasi.index')">Batal</Link>
                        </Button>
                        <Button
                            type="submit"
                            class="h-10 px-4"
                            :disabled="form.processing"
                        >
                            <LoaderCircle
                                v-if="form.processing"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan keputusan'
                            }}
                        </Button>
                    </div>
                </form>
            </section>

            <section
                v-else-if="entry.decided_at"
                class="bg-card flex flex-col gap-2 rounded-lg border p-6"
            >
                <h2 class="text-base leading-6 font-semibold">Keputusan</h2>
                <p class="text-sm leading-5">
                    Sudah diputuskan: {{ entry.note ?? 'tanpa catatan' }}
                </p>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="rejectOpen"
            title="Tolak siswa ini?"
            description="Siswa tidak dapat didaftarkan pada pendaftaran ini. Operator diberi tahu beserta catatan Anda."
            confirm-label="Tolak"
            destructive
            @confirm="send"
        />
    </AppLayout>
</template>
```

Periksa nama ekspor `formatBytes` di `resources/js/lib/files.ts` (jika berbeda, gunakan yang ada); `InlineAlert variant="warning"` valid.

- [ ] **Step 3: Kartu pendaftaran dan kunci di `Siswa/Show.vue`**

Tambahkan di `types/student.ts`:

```ts
export type StudentRegistrationRef = {
    id: number;
    title: string;
    status: string;
};
```

Di `Siswa/Show.vue`: impor `StatusBadge`, `InlineAlert`, tipe `StudentRegistrationRef`; tambah props `locked: boolean; registrations: StudentRegistrationRef[]`. Ubah:
- tombol **Ubah**: `v-if="can(PERMISSIONS.SiswaUbah) && !locked"`.
- `RowActions` Hapus: `v-if="can(PERMISSIONS.SiswaHapus) && registrations.length === 0"`.
- `FileUpload`: `:can-upload="can(PERMISSIONS.DokumenUnggah) && !locked"` dan `:can-delete="can(PERMISSIONS.DokumenHapus) && !locked"`.
- Di atas kartu pertama dalam `<div class="flex flex-col gap-6">` tambahkan:

```vue
            <InlineAlert v-if="locked" variant="warning">
                Data siswa dikunci karena pendaftarannya sedang diproses.
                Perubahan dibuka kembali bila petugas meminta perbaikan.
            </InlineAlert>
```

- Sebelum `</div>` penutup kolom, tambahkan kartu:

```vue
            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Pendaftaran</h2>
                <ul v-if="registrations.length" class="flex flex-col gap-3">
                    <li
                        v-for="r in registrations"
                        :key="r.id"
                        class="flex flex-wrap items-center justify-between gap-2 text-sm"
                    >
                        <Link
                            v-if="can(PERMISSIONS.PendaftaranLihat)"
                            :href="route('pendaftaran.show', r.id)"
                            class="font-medium hover:underline"
                            >{{ r.title }}</Link
                        >
                        <span v-else class="font-medium">{{ r.title }}</span>
                        <StatusBadge :status="r.status" kind="registration" />
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">
                    Belum pernah didaftarkan.
                </p>
            </section>
```

Pesan konfirmasi hapus tetap. 

- [ ] **Step 4: Jalankan**

Run: `npm run typecheck`, `npm run lint`, `npm test`, `npm run build`
Expected: semua bersih; build sukses.

- [ ] **Step 5: Commit**

```bash
git add resources/js
git commit -m "feat: tambah antrean dan keputusan verifikasi serta kartu pendaftaran siswa" -m "Antrean verifikasi dengan filter nama/NISN dan sekolah; halaman keputusan menampilkan data siswa, dokumen, eligibility, dan riwayat, dengan form setujui/minta perbaikan/tolak (catatan wajib dan konfirmasi untuk tolak). Detail siswa memuat kartu Pendaftaran, banner kunci data, dan menyembunyikan Ubah/Hapus/unggah dokumen saat terkunci atau sudah punya pendaftaran."
```

---

### Task 12: Verifikasi browser, review, dan penutupan fase

**Files:**
- Modify: `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (catatan Status Fase 4), memory `deferred-items.md`
- Bila review menemukan temuan valid: perbaikan dengan TDD (BE) atau tes Vitest.

- [ ] **Step 1: Verifikasi suite penuh**

Run: `php artisan test`, `vendor/bin/pint --test`, `composer analyse`, `npm run typecheck`, `npm run lint`, `npm test`, `npm run build`
Expected: semua hijau; catat jumlah tes.

- [ ] **Step 2: Verifikasi browser (Playwright, desktop 1440 dan mobile 390)**

Jalankan `php artisan serve --port=8099` di latar belakang. Pakai akun demo (`op.siswa@example.com` operator, `sari@example.com` admin kecamatan, sandi `password`). Siapkan 3–4 siswa eligible (KK dan akta terunggah) untuk sekolah operator lewat tinker/seeder lokal bila belum ada. Alur: operator buat draf → pilih siswa (pemilih: cari, pilih semua eligible, alasan siswa tidak eligible) → simpan → Ajukan (dialog) → kembali sebagai admin: notifikasi di lonceng, antrean, periksa → Setujui satu, Minta perbaikan satu (catatan), Tolak satu (konfirmasi) → kembali sebagai operator: notifikasi, status di detail, Perbaiki data (data tidak terkunci), Ajukan ulang → admin setujui. Periksa: detail siswa terkunci saat diajukan, timeline riwayat, state kosong/loading, tidak ada overflow horizontal di 390 px, console tanpa galat, input dapat dioperasikan dengan keyboard (gunakan `dispatchEvent`/`element.click()` karena keyboard asli tidak sampai). Ambil tangkapan layar sebagai bukti; hentikan server.

- [ ] **Step 3: Review ponytail dan impeccable**

Panggil skill `ponytail:ponytail-review` untuk seluruh diff Fase 4 (`git diff 9327c69~1..HEAD`, abaikan spec/rencana) dan skill `impeccable` untuk perubahan frontend (halaman Pendaftaran, Verifikasi, `StudentMultiSelect`, `StatusTimeline`, `Siswa/Show`), dengan panduan `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`. Nilai setiap temuan menurut efek bagi pengguna; perbaiki yang valid (Critical/Important) dengan TDD satu putaran (tes merah → hijau → suite hijau), catat yang ditolak sebagai ruling dan minor sebagai "Deferred minors". Commit perbaikan: `refactor: terapkan hasil review Fase 4` dengan isi yang menjelaskan temuan yang diterapkan.

- [ ] **Step 4: Perbarui rencana sprint dan memory**

Sisipkan catatan setelah heading `## Fase 4 — Pendaftaran dan verifikasi` di rencana sprint (gunakan Edit):

```
**Status Fase 4 (2026-10-08): SELESAI** (spec: `specs/2026-10-08-fase-4-pendaftaran-verifikasi-design.md`, rencana: `plans/2026-10-08-fase-4-pendaftaran-verifikasi.md`). BE: domain `Registrations` (pendaftaran draf, entri per siswa, riwayat status), mesin status enum dengan `StatusTransitioner`, indeks unik `active_student_id` (satu entri aktif per siswa), pengajuan atomik dengan validasi eligibility ulang, ajukan ulang, keputusan per siswa, eligibility entri aktif, kunci data siswa saat diproses, larangan hapus siswa berpendaftaran, notifikasi (`toSchool`), label log. FE: daftar/form/detail pendaftaran, `StudentMultiSelect`, `StatusTimeline`, antrean dan keputusan verifikasi, kartu pendaftaran di detail siswa, panduan UI 5.21–5.24. Penyimpangan dari spec: lihat bagian Rulings di rencana (R1–R6) dan ledger akhir.
```

Perbarui memory `deferred-items.md`: hapus seluruh bagian "Fase 4" (semua item selesai), ubah kalimat pembuka menjadi "per tanggal selesai Fase 4", tambahkan ke "Fase 5": kolom `registration_students.schedule_id` dan transisi `terverifikasi → terjadwal → sudah_rekaman | tidak_hadir | belum_rekaman` (enum sudah punya statusnya; tambahkan ke `RegistrationStatus::next()` dan `locking()/active()` sudah sesuai), notifikasi jadwal; tambahkan ke catatan teknis: kunci data siswa (tambahan di luar spec v1, longgarkan bila terlalu ketat), satu notifikasi per keputusan siswa (penggabungan ditunda), daftar kandidat memuat semua siswa sekolah dalam satu respons. Perbarui `MEMORY.md` bila deskripsi berubah.

- [ ] **Step 5: Commit dan penutup**

```bash
git add docs
git commit -m "docs: tandai Fase 4 selesai pada rencana sprint" -m "Catatan status Fase 4 beserta ringkasan cakupan dan penyimpangan dari spec."
```

Susun pesan akhir untuk pengguna: ringkasan hasil (jumlah tes, build), daftar **Rulings I made** (R1–R6 plus keputusan saat eksekusi, masing-masing dengan biaya bila salah) dan **Deferred minors**, lalu hapus workspace ledger bila dibuat (`.superpowers/sdd/<nama-rencana>/`).
