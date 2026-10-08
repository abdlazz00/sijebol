# Fase 3 — Siswa dan Dokumen Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, tanpa subagent, pilihan user). Steps memakai checkbox (`- [ ]`).

**Goal:** Operator sekolah dapat mengelola data siswa (satu per satu atau impor Excel), mengunggah KK dan akta lahir ke penyimpanan privat, dan melihat siswa yang eligible; staf kecamatan melihat semua sekolah.

**Architecture:** Domain baru `app/Domains/Students` (Controller → FormRequest → Service → Repository interface + Eloquent) memakai trait `ScopedBySchool` yang ada. Aturan validasi dipusatkan di `StudentRules` dan dipakai form maupun impor; kelayakan di `EligibilityService` (murni) sehingga Fase 4 cukup menambah syarat. Dokumen di disk `local` privat dengan nama acak dan diunduh lewat controller yang memeriksa izin dan scope.

**Tech Stack:** Laravel 13, maatwebsite/excel v4 (kontrak: `ToArray::array(array)`, `FromArray::array()`, `WithHeadings`, `WithColumnFormatting`), spatie query-builder + activitylog v5, Inertia v2, Vue 3 + TS, Pest, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-08-fase-3-siswa-dokumen-design.md`

## Global Constraints

- NIK `digits:16` unik se-kecamatan (pesan "NIK sudah terdaftar." tanpa menyebut sekolah); NISN `digits:10`; `gender` `L`|`P`; `birth_date` tidak di masa depan.
- Dokumen: tipe `kk` | `akta_lahir`; mime `pdf,jpg,jpeg,png`; maksimal 5 MB (`StudentDocumentService::MAX_KB = 5120`); disk `local` (privat); tanpa URL publik.
- Impor: maksimal 500 baris data, berkas maksimal 2 MB; baris benar masuk, baris salah dilaporkan `{row, reason}` (nomor baris = nomor di berkas, judul = baris 1).
- Siswa sekolah lain → 404 (global scope). Operator: `school_id` dipaksa ke sekolahnya.
- NIK dimasker di tampilan daftar/detail dan di log; form ubah menampilkan penuh.
- Nama prop halaman Inertia **tidak boleh** `auth`, `flash`, `menu`, `notifications`; pakai `students`, `student`, `documents`, `eligibility`.
- activitylog v5: perubahan model di kolom `attribute_changes`; urutan kunci JSON MySQL tidak terjaga (tes tak boleh bergantung urutan).
- Tulis berkas PHP/Vue besar dengan Write (bukan heredoc bash); Playwright: input keyboard tidak sampai ke halaman, pakai `dispatchEvent`/`element.click()`.
- FE: Prettier/ESLint Breeze (semicolon, 4 spasi), `npm run format` sebelum cek; tanpa dependensi baru; warna lewat token.
- Commit: pesan detail, **tanpa** `Co-Authored-By`/atribusi AI (aturan `CLAUDE.md`).
- Setelah semua task: review `impeccable` (FE) dan `ponytail:ponytail-review`, perbaiki temuan valid, verifikasi akhir, perbarui memory `deferred-items`.

## Review Focus

- Sel Excel bertipe angka untuk NIK/NISN kehilangan presisi (float): baris harus gagal dengan alasan jelas, bukan menyimpan NIK salah (Task 5).
- Operator menyuntikkan `school_id` sekolah lain lewat form atau parameter impor: harus diabaikan/ditolak (Task 3, 5).
- Unggah gagal di tengah jalan (simpan baris gagal) tidak meninggalkan berkas yatim; hapus siswa menghapus berkas dari disk (Task 4).
- Mengunduh dokumen siswa sekolah lain atau `type` tak dikenal → 404, bukan 403/500 (Task 4).
- Batas umur tepat di hari ulang tahun ke-16 dan ke-18 (Task 2).
- Berkas Excel dengan kolom wajib hilang, baris kosong, atau lebih dari 500 baris (Task 5).
- Log siswa tidak membocorkan NIK penuh di respons `/log` (Task 3).

## File Structure

| File | Tanggung jawab |
|---|---|
| `database/migrations/*_create_students_table.php`, `*_create_student_documents_table.php` | Skema |
| `app/Domains/Students/Models/Student.php`, `StudentDocument.php`, `database/factories/StudentFactory.php` | Model + factory |
| `app/Support/Nik.php` | `Nik::mask(string)` (dipakai masker log dan tampilan) |
| `app/Domains/Students/Support/StudentRules.php` | Aturan + pesan validasi bersama form dan impor |
| `app/Domains/Students/Services/EligibilityService.php` | `check`, `birthDateBounds` |
| `app/Domains/Students/Repositories/*` | Query daftar + CRUD |
| `app/Domains/Students/Services/StudentService.php`, `StudentDocumentService.php`, `StudentImportService.php` | Logika bisnis |
| `app/Domains/Students/Exports/StudentTemplateExport.php`, `Imports/StudentSheetImport.php` | Template dan pembaca xlsx |
| `app/Domains/Students/Http/Controllers/StudentController.php`, `StudentDocumentController.php`, `StudentImportController.php`, `Http/Requests/StudentRequest.php` | HTTP |
| `routes/students.php` | Rute `/siswa*` |
| `resources/js/Pages/Siswa/Index.vue`, `Form.vue`, `Show.vue` | Halaman |
| `resources/js/Components/shared/FileUpload.vue`, `ImportStudentsDialog.vue` | Komponen |
| `resources/js/types/student.ts` | Tipe |

---

### Task 1: Skema, model, factory, `StudentRules`, `Nik`

**Files:**
- Create: 2 migrasi, `app/Domains/Students/Models/Student.php`, `StudentDocument.php`, `database/factories/StudentFactory.php`, `app/Support/Nik.php`, `app/Domains/Students/Support/StudentRules.php`
- Modify: `app/Domains/Audit/Services/ActivityPropertyMasker.php` (pakai `Nik::mask`)
- Test: `tests/Feature/Students/StudentModelTest.php`, `tests/Feature/Students/StudentRulesTest.php`

**Interfaces:**
- Produces: `Student` (`fillable`: `school_id,nik,nisn,name,birth_date,gender`; relasi `school()`, `documents()`; `ScopedBySchool`), `StudentDocument` (`student_id,type,path,mime,size`), `StudentFactory` (`nik` 16 digit unik, `birth_date` umur 16–17, `gender`), `Nik::mask(string): string`, `StudentRules::fields(?int $ignoreId = null): array<string, array>`, `StudentRules::messages(): array<string,string>`, `StudentRules::DOCUMENT_TYPES = ['kk','akta_lahir']`.

- [ ] **Step 1: Tes yang gagal** (`StudentModelTest.php`)

```php
<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;

test('NIK unik dan dokumen unik per jenis', function () {
    $student = Student::factory()->create(['nik' => '3201123412341234']);

    expect(fn () => Student::factory()->create(['nik' => '3201123412341234']))->toThrow(QueryException::class);

    $student->documents()->create(['type' => 'kk', 'path' => 'a', 'mime' => 'application/pdf', 'size' => 10]);
    expect(fn () => $student->documents()->create(['type' => 'kk', 'path' => 'b', 'mime' => 'application/pdf', 'size' => 10]))
        ->toThrow(QueryException::class);
});

test('operator hanya melihat siswa sekolahnya, staf tanpa sekolah melihat semua', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    Student::factory()->for($a)->create();
    Student::factory()->for($b)->create();
    $operator = User::factory()->create(['school_id' => $a->id]);

    $this->actingAs($operator);
    expect(Student::count())->toBe(1);

    $this->actingAs(User::factory()->create(['school_id' => null]));
    expect(Student::count())->toBe(2);
});

test('menghapus siswa menghapus dokumennya (cascade) dan perubahan tercatat', function () {
    $this->actingAs(User::factory()->create());
    $student = Student::factory()->create(['name' => 'Awal']);
    $student->documents()->create(['type' => 'kk', 'path' => 'a', 'mime' => 'application/pdf', 'size' => 10]);
    $student->update(['name' => 'Akhir']);

    $student->delete();

    expect(\DB::table('student_documents')->count())->toBe(0)
        ->and(\Spatie\Activitylog\Models\Activity::where('log_name', 'siswa')->where('event', 'updated')->count())->toBe(1);
});
```

`StudentRulesTest.php`:

```php
<?php

use App\Domains\Students\Models\Student;
use App\Domains\Students\Support\StudentRules;
use App\Support\Nik;
use Illuminate\Support\Facades\Validator;

function validasi(array $data, ?int $ignore = null): \Illuminate\Validation\Validator
{
    return Validator::make($data, StudentRules::fields($ignore), StudentRules::messages());
}

$valid = ['nik' => '3201123412341234', 'nisn' => '0012345678', 'name' => 'Budi', 'birth_date' => '2009-05-01', 'gender' => 'L'];

test('data lengkap valid', function () use ($valid) {
    expect(validasi($valid)->passes())->toBeTrue();
});

test('NIK harus 16 digit dan unik, pesan tidak menyebut sekolah', function () use ($valid) {
    expect(validasi([...$valid, 'nik' => '320112341234123'])->errors()->first('nik'))->toBe('NIK harus 16 digit angka.')
        ->and(validasi([...$valid, 'nik' => '32011234123412345'])->passes())->toBeFalse();

    Student::factory()->create(['nik' => '3201123412341234']);
    expect(validasi($valid)->errors()->first('nik'))->toBe('NIK sudah terdaftar.');
});

test('mengabaikan siswa sendiri saat ubah', function () use ($valid) {
    $s = Student::factory()->create(['nik' => '3201123412341234']);
    expect(validasi($valid, $s->id)->passes())->toBeTrue();
});

test('NISN, tanggal lahir masa depan, dan gender tak valid ditolak', function () use ($valid) {
    expect(validasi([...$valid, 'nisn' => '123'])->errors()->first('nisn'))->toBe('NISN harus 10 digit angka.')
        ->and(validasi([...$valid, 'birth_date' => now()->addDay()->toDateString()])->errors()->has('birth_date'))->toBeTrue()
        ->and(validasi([...$valid, 'gender' => 'X'])->errors()->first('gender'))->toBe('Pilih jenis kelamin.');
});

test('Nik::mask menyisakan 4 awal dan 4 akhir', function () {
    expect(Nik::mask('3201123412341234'))->toBe('3201********1234')
        ->and(Nik::mask('12345'))->toBe('*****');
});
```
- [ ] **Step 2: Jalankan** `php artisan test --filter="StudentModelTest|StudentRulesTest"` → FAIL (kelas belum ada).
- [ ] **Step 3: Implementasi.**

Migrasi `students` (`Schema::create`): `id`; `foreignId('school_id')->constrained()->cascadeOnDelete()`; `string('nik', 16)->unique()`; `string('nisn', 10)`; `string('name', 150)`; `date('birth_date')`; `string('gender', 1)`; `timestamps()`; `index(['school_id','name'])`. Migrasi `student_documents`: `id`; `foreignId('student_id')->constrained()->cascadeOnDelete()`; `string('type', 20)`; `string('path')`; `string('mime', 100)`; `unsignedInteger('size')`; `timestamps()`; `unique(['student_id','type'])`. Jalankan `php artisan migrate`.

```php
<?php // app/Support/Nik.php

namespace App\Support;

final class Nik
{
    /** "3201123412341234" → "3201********1234"; NIK pendek dimasker penuh. */
    public static function mask(string $nik): string
    {
        $length = strlen($nik);

        return $length <= 8
            ? str_repeat('*', $length)
            : substr($nik, 0, 4).str_repeat('*', $length - 8).substr($nik, -4);
    }
}
```
Di `ActivityPropertyMasker`, ganti `self::maskNik($value)` dengan `Nik::mask($value)` dan hapus metode privat `maskNik` (tes `ActivityPropertyMaskerTest` yang ada tetap menjaga perilaku).

```php
<?php // app/Domains/Students/Models/Student.php

namespace App\Domains\Students\Models;

use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[UseFactory(StudentFactory::class)]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, LogsActivity, ScopedBySchool;

    protected $fillable = ['school_id', 'nik', 'nisn', 'name', 'birth_date', 'gender'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<StudentDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('siswa')
            ->logOnly(['nik', 'nisn', 'name', 'birth_date', 'gender', 'school_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```
`StudentDocument` (`namespace App\Domains\Students\Models`): `$fillable = ['student_id','type','path','mime','size']`, relasi `student()`. `StudentFactory` (`namespace Database\Factories`, `$model = Student::class`): `school_id => School::factory()`, `nik => fake()->unique()->numerify('################')`, `nisn => fake()->unique()->numerify('##########')`, `name => fake()->name()`, `birth_date => now()->subYears(16)->subDays(fake()->numberBetween(0, 300))->toDateString()` (umur 16), `gender => fake()->randomElement(['L','P'])`.

```php
<?php // app/Domains/Students/Support/StudentRules.php

namespace App\Domains\Students\Support;

use Illuminate\Validation\Rule;

final class StudentRules
{
    public const DOCUMENT_TYPES = ['kk', 'akta_lahir'];

    /** @return array<string, array<int, mixed>> */
    public static function fields(?int $ignoreId = null): array
    {
        return [
            'nik' => ['required', 'digits:16', Rule::unique('students', 'nik')->ignore($ignoreId)],
            'nisn' => ['required', 'digits:10'],
            'name' => ['required', 'string', 'max:150'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['L', 'P'])],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK sudah terdaftar.',
            'nisn.required' => 'NISN wajib diisi.',
            'nisn.digits' => 'NISN harus 10 digit angka.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date' => 'Tanggal lahir tidak valid.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
            'gender.required' => 'Pilih jenis kelamin.',
            'gender.in' => 'Pilih jenis kelamin.',
        ];
    }
}
```
- [ ] **Step 4: Jalankan** tes (target + `ActivityPropertyMaskerTest`) → PASS; `vendor/bin/pint`, `vendor/bin/phpstan analyse --memory-limit=1G` bersih.
- [ ] **Step 5: Commit** — "feat: tambah skema, model, dan aturan validasi siswa".

---

### Task 2: `EligibilityService`

**Files:**
- Create: `app/Domains/Students/Services/EligibilityService.php`
- Test: `tests/Feature/Students/EligibilityServiceTest.php`

**Interfaces:**
- Consumes: `Student`, `StudentDocument` (Task 1).
- Produces: `EligibilityService::check(Student $s, ?CarbonInterface $on = null): array{eligible: bool, reasons: list<string>}`; `EligibilityService::age(Student $s, ?CarbonInterface $on = null): int`; `EligibilityService::birthDateBounds(?CarbonInterface $on = null): array{0: string, 1: string}` = `[tanggal lahir paling awal, paling akhir]` (inklusif, `Y-m-d`) untuk umur 16–17; zona `Asia/Jakarta`.

- [ ] **Step 1: Tes yang gagal**

```php
<?php

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\EligibilityService;
use Carbon\Carbon;

function lengkap(Student $s): Student
{
    foreach (['kk', 'akta_lahir'] as $type) {
        $s->documents()->create(['type' => $type, 'path' => $type, 'mime' => 'application/pdf', 'size' => 1]);
    }

    return $s->load('documents');
}

$hari = Carbon::parse('2026-10-08', 'Asia/Jakarta');

test('batas umur 16 sampai 17 tahun', function (string $lahir, bool $eligible) use ($hari) {
    $s = lengkap(Student::factory()->create(['birth_date' => $lahir]));

    expect(app(EligibilityService::class)->check($s, $hari)['eligible'])->toBe($eligible);
})->with([
    'sehari sebelum ulang tahun ke-16' => ['2010-10-09', false],
    'tepat ulang tahun ke-16' => ['2010-10-08', true],
    'sehari sebelum ulang tahun ke-18' => ['2008-10-09', true],
    'tepat ulang tahun ke-18' => ['2008-10-08', false],
]);

test('alasan jelas bila dokumen belum lengkap atau umur salah', function () use ($hari) {
    $s = Student::factory()->create(['birth_date' => '2000-01-01']);
    $s->documents()->create(['type' => 'kk', 'path' => 'kk', 'mime' => 'application/pdf', 'size' => 1]);

    $hasil = app(EligibilityService::class)->check($s->load('documents'), $hari);

    expect($hasil['eligible'])->toBeFalse()
        ->and($hasil['reasons'])->toBe([
            'Umur harus 16 atau 17 tahun (umur sekarang 25).',
            'Akta kelahiran belum diunggah.',
        ]);
});

test('birthDateBounds konsisten dengan check', function () use ($hari) {
    [$awal, $akhir] = app(EligibilityService::class)->birthDateBounds($hari);

    expect($awal)->toBe('2008-10-09')->and($akhir)->toBe('2010-10-08');
});
```
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class EligibilityService
{
    private const ZONE = 'Asia/Jakarta';

    public const MIN_AGE = 16;

    public const MAX_AGE = 17;

    /** @return array{eligible: bool, reasons: list<string>} */
    public function check(Student $student, ?CarbonInterface $on = null): array
    {
        $reasons = [];
        $age = $this->age($student, $on);

        if ($age < self::MIN_AGE || $age > self::MAX_AGE) {
            $reasons[] = "Umur harus 16 atau 17 tahun (umur sekarang {$age}).";
        }

        $types = $student->documents->pluck('type')->all();
        if (! in_array('kk', $types, true)) {
            $reasons[] = 'Kartu Keluarga belum diunggah.';
        }
        if (! in_array('akta_lahir', $types, true)) {
            $reasons[] = 'Akta kelahiran belum diunggah.';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    public function age(Student $student, ?CarbonInterface $on = null): int
    {
        return (int) $student->birth_date->copy()->startOfDay()->diffInYears($this->day($on)->startOfDay(), true);
    }

    /**
     * Rentang tanggal lahir (inklusif) untuk umur 16–17 pada tanggal acuan.
     *
     * @return array{0: string, 1: string}
     */
    public function birthDateBounds(?CarbonInterface $on = null): array
    {
        $day = $this->day($on)->startOfDay();

        return [
            $day->copy()->subYears(self::MAX_AGE + 1)->addDay()->toDateString(),
            $day->copy()->subYears(self::MIN_AGE)->toDateString(),
        ];
    }

    private function day(?CarbonInterface $on): Carbon
    {
        return Carbon::instance($on ?? now())->setTimezone(self::ZONE);
    }
}
```
- [ ] **Step 4: Jalankan** → PASS (periksa keempat batas umur; bila `diffInYears` menghitung pecahan, `(int)` sudah memotong); Pint + Larastan.
- [ ] **Step 5: Commit** — "feat: tambah layanan eligibility siswa dengan batas umur".

---

### Task 3: CRUD siswa, daftar berfilter, menu, label log

**Files:**
- Create: `app/Domains/Students/Repositories/Contracts/StudentRepositoryInterface.php`, `.../EloquentStudentRepository.php`, `Services/StudentService.php`, `Http/Requests/StudentRequest.php`, `Http/Controllers/StudentController.php`, `routes/students.php`
- Modify: `routes/web.php` (muat `students.php`), `app/Providers/RepositoryServiceProvider.php`, `app/Domains/Access/Services/MenuService.php`, `resources/js/config/menuIcons.ts` (+ `menuIcons.test.ts`: `'graduation-cap'`), `lang/id/activity.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`, `tests/Feature/Access/MenuTest.php`
- Test: `tests/Feature/Students/StudentHttpTest.php`

**Interfaces:**
- Consumes: `Student`, `StudentRules`, `Nik`, `EligibilityService` (Task 1–2).
- Produces: rute `siswa.index|create|store|show|edit|update|destroy` (`/siswa`, `/siswa/buat`, `/siswa/{student}`, `/siswa/{student}/ubah`); Inertia `Siswa/Index` props `{ students: Paginator<StudentRow>, filters, schools: Option[] | null }`, `Siswa/Form` props `{ student: {id,nik,nisn,name,birth_date,gender,school_id}|null, schools: Option[] | null }`, `Siswa/Show` props `{ student: StudentDetail, documents: StudentDocumentRow[], eligibility: {eligible, reasons} }` (documents diisi Task 4); `StudentService::create(array $data, User $actor): Student`, `update(Student, array)`, `delete(Student)`; `StudentRepositoryInterface::paginate(int $perPage = 20)`.
- `StudentRow` = `{ id, name, nisn, nik (dimasker), age, school: ?string, docs: {kk: bool, akta_lahir: bool}, eligible: bool, reasons: list<string> }`; `StudentDetail` = `StudentRow` + `birth_date`, `gender`, `can: {update: bool, delete: bool}`.

- [ ] **Step 1: Tes yang gagal** (`StudentHttpTest.php`; helper `operator($school)` = `makeUserWith(['siswa.lihat','siswa.buat','siswa.ubah','siswa.hapus'], $school, true, 'operator-uji')`)

```php
<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

$siswa = ['nik' => '3201123412341234', 'nisn' => '0012345678', 'name' => 'Budi', 'birth_date' => '2009-05-01', 'gender' => 'L'];

function operatorSekolah(School $school, array $extra = [])
{
    return makeUserWith(['siswa.lihat', 'siswa.buat', 'siswa.ubah', 'siswa.hapus', ...$extra], $school, true, 'operator-uji');
}

test('operator menambah siswa dan sekolah dipaksa ke sekolahnya', function () use ($siswa) {
    $a = School::factory()->create();
    $b = School::factory()->create();

    $this->actingAs(operatorSekolah($a))
        ->post('/siswa', [...$siswa, 'school_id' => $b->id])
        ->assertRedirect();

    expect(Student::withoutGlobalScopes()->where('nik', $siswa['nik'])->first()->school_id)->toBe($a->id);
});

test('staf tanpa sekolah wajib memilih sekolah', function () use ($siswa) {
    $staf = makeUserWith(['siswa.buat'], roleName: 'staf');

    $this->actingAs($staf)->post('/siswa', $siswa)->assertSessionHasErrors('school_id');

    $sekolah = School::factory()->create();
    $this->actingAs($staf)->post('/siswa', [...$siswa, 'school_id' => $sekolah->id])->assertSessionHasNoErrors();
});

test('siswa sekolah lain tidak terlihat, tidak bisa diubah/dihapus (404)', function () {
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
        ->where('students.data.0.nik', fn ($v) => ! str_contains($v, '3201123412341234') || str_contains($v, '*')));

    $this->get('/siswa?filter[docs]=lengkap')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.name', 'Lengkap'));
    $this->get('/siswa?filter[docs]=kurang')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.name', 'Kurang'));
    $this->get('/siswa?filter[eligible]=1')->assertInertia(fn (Assert $p) => $p->has('students.data', 1)->where('students.data.0.eligible', true));
    $this->get('/siswa?filter[search]=Lengk')->assertInertia(fn (Assert $p) => $p->has('students.data', 1));
});

test('perubahan siswa tercatat dan NIK termasking di respons log', function () use ($siswa) {
    $a = School::factory()->create();
    $op = operatorSekolah($a);
    $this->actingAs($op)->post('/siswa', $siswa);

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
```
Perbarui `MenuTest`: operator → `['Utama', 'Data Siswa']`, admin → `['Utama', 'Manajemen Akses', 'Master Data', 'Data Siswa', 'Sistem']`. `RoutePermissionMatrixTest`: tambah baris `GET /siswa siswa.lihat`, `GET /siswa/buat siswa.buat`, `POST /siswa siswa.buat`, `GET /siswa/{student} siswa.lihat`, `GET /siswa/{student}/ubah siswa.ubah`, `PUT /siswa/{student} siswa.ubah`, `DELETE /siswa/{student} siswa.hapus`; perluas `resolveUri` agar mengganti `{student}` dengan `Student::factory()->create()->id` (tambahkan impor `Student`).
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi.**
  - `StudentRequest` (`rules()`: `StudentRules::fields($this->route('student')?->id)` + `school_id` => `['nullable','integer','exists:schools,id', Rule::requiredIf(fn () => $this->user()->school_id === null && $this->isMethod('post'))]`; `messages()`: `StudentRules::messages()` + `'school_id.required' => 'Pilih sekolah.'`). Saat PUT, `school_id` boleh dikirim staf untuk memindahkan sekolah; operator: diabaikan.
  - `StudentRepositoryInterface`: `paginate(int $perPage = 20): LengthAwarePaginator`, `create(array): Student`, `update(Student, array): Student`, `delete(Student): void`. `EloquentStudentRepository::paginate` — `QueryBuilder::for(Student::query()->with(['school','documents'])->orderBy('name'))` dengan filter: `search` (callback LIKE `name`/`nik`/`nisn`), `school_id` (exact), `docs` (callback: `lengkap` → dua `whereHas('documents', fn ($d) => $d->where('type', 'kk'))` dan `akta_lahir`; `kurang` → `where(fn ($q) => $q->whereDoesntHave(... kk)->orWhereDoesntHave(... akta_lahir))`), `eligible` (callback bernilai `1`: `whereBetween('birth_date', app(EligibilityService::class)->birthDateBounds())` + dua `whereHas` dokumen).
  - `StudentService`: `create($data, $actor)` → `school_id = $actor->school_id ?? $data['school_id']`; `update` → operator tidak mengubah `school_id` (hapus kunci bila `$actor->school_id !== null`); `delete` → `Storage::disk('local')->deleteDirectory("students/{$student->id}")` lalu `repo->delete`; semuanya dalam `DB::transaction`.
  - `StudentController` (konstruktor `StudentService`, `EligibilityService`, `SchoolService`): `index` (props `students` dari repository dengan `through` → `StudentRow`, `filters` = `$request->query('filter', [])`, `schools` = `$actor->school_id === null ? $this->schools->options()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]) : null`), `create`, `store` (redirect `route('siswa.show', $student)` + flash success "Siswa ditambahkan."), `show` (`documents` => `[]` sementara; Task 4 mengisinya; `eligibility` dari service), `edit` (NIK penuh), `update` (redirect show, "Siswa diperbarui."), `destroy` (redirect index, "Siswa dihapus."). Izin lewat middleware `can:` di `routes/students.php` (urutan: `siswa/buat` sebelum `siswa/{student}`; rute impor Task 5 didefinisikan sebelum `{student}`).
  - `RepositoryServiceProvider`: binding `StudentRepositoryInterface`.
  - `MenuService::ITEMS`: tambah `['group' => 'Data Siswa', 'label' => 'Siswa', 'href' => '/siswa', 'icon' => 'graduation-cap', 'permission' => 'siswa.lihat']` setelah item Sekolah dan sebelum item Log; `menuIcons.ts`: `'graduation-cap': GraduationCap` (+ asersi di `menuIcons.test.ts`).
  - `lang/id/activity.php`: `types['siswa'] = 'Siswa'`; `subjects['Student'] = 'Siswa'`; `actions['dokumen.diunggah'] = 'Dokumen diunggah'`, `'dokumen.dihapus' => 'Dokumen dihapus'`, `'dokumen.diunduh' => 'Dokumen diunduh'`, `'siswa.diimpor' => 'Siswa diimpor'`.
- [ ] **Step 4: Jalankan** seluruh suite (`php artisan test --compact`) → hijau; Pint + Larastan.
- [ ] **Step 5: Commit** — "feat: tambah CRUD siswa dengan filter, scope sekolah, dan menu".

---

### Task 4: Dokumen siswa (unggah, unduh, hapus)

**Files:**
- Create: `app/Domains/Students/Services/StudentDocumentService.php`, `app/Domains/Students/Http/Controllers/StudentDocumentController.php`
- Modify: `routes/students.php`, `StudentController::show` (isi `documents`), `tests/Feature/Access/RoutePermissionMatrixTest.php` (`{type}` → `kk`)
- Test: `tests/Feature/Students/StudentDocumentTest.php`

**Interfaces:**
- Consumes: `Student`, `StudentDocument`, `StudentRules::DOCUMENT_TYPES`.
- Produces: `StudentDocumentService::MAX_KB = 5120`; `store(Student, string $type, UploadedFile, User $actor): StudentDocument`; `delete(Student, string $type, User $actor): void`; `download(Student, string $type, User $actor): StreamedResponse`; rute `siswa.dokumen.store|download|destroy` (`/siswa/{student}/dokumen/{type}`, `type` dibatasi `whereIn(['kk','akta_lahir'])`); `documents` di `Siswa/Show` = `list<{type: 'kk'|'akta_lahir', name: string, size: int, url: string}>` (`name` = `{type}-{nisn}.{ext}`).

- [ ] **Step 1: Tes yang gagal**

```php
<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->student = Student::factory()->for($this->school)->create(['nisn' => '0012345678']);
    $this->op = makeUserWith(['siswa.lihat', 'dokumen.lihat', 'dokumen.unggah', 'dokumen.hapus'], $this->school, true, 'op-dok');
});

test('unggah menyimpan berkas privat dengan nama acak dan mencatat log', function () {
    $this->actingAs($this->op)
        ->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->create('kk-asli.pdf', 100, 'application/pdf')])
        ->assertRedirect();

    $doc = $this->student->documents()->where('type', 'kk')->firstOrFail();
    Storage::disk('local')->assertExists($doc->path);
    expect($doc->path)->toStartWith("students/{$this->student->id}/")->not->toContain('kk-asli')
        ->and(Activity::where('description', 'dokumen.diunggah')->count())->toBe(1);
});

test('unggah ulang mengganti berkas lama (hanya satu berkas di disk)', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
    $lama = $this->student->documents()->first()->path;
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->image('b.jpg')]);

    Storage::disk('local')->assertMissing($lama);
    expect($this->student->documents()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles("students/{$this->student->id}"))->toHaveCount(1);
});

test('tipe dan ukuran ditolak, type tak dikenal 404', function () {
    $this->actingAs($this->op);
    $url = "/siswa/{$this->student->id}/dokumen/kk";

    $this->post($url, ['file' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
    $this->post($url, ['file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('file');
    $this->post("/siswa/{$this->student->id}/dokumen/lain", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertNotFound();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('kegagalan menyimpan baris tidak meninggalkan berkas yatim', function () {
    $this->actingAs($this->op);
    // Memaksa kegagalan: tabel dokumen dihapus tepat sebelum upsert lewat event kolom tak ada.
    \Illuminate\Support\Facades\Schema::drop('student_documents');

    try {
        $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
    } catch (Throwable) {
    }

    expect(Storage::disk('local')->allFiles())->toBe([]);
})->skip('diganti tes service di bawah bila Schema::drop tidak praktis');

test('unduh memeriksa izin dan scope, serta mencatat log', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/akta_lahir", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);

    $res = $this->get("/siswa/{$this->student->id}/dokumen/akta_lahir")->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('akta_lahir-0012345678.pdf')
        ->and(Activity::where('description', 'dokumen.diunduh')->count())->toBe(1);

    $tanpaIzin = makeUserWith(['siswa.lihat'], $this->school, true, 'tanpa-dok');
    $this->actingAs($tanpaIzin)->get("/siswa/{$this->student->id}/dokumen/akta_lahir")->assertForbidden();

    $sekolahLain = School::factory()->create();
    $this->actingAs(makeUserWith(['siswa.lihat', 'dokumen.lihat'], $sekolahLain, true, 'op-lain'))
        ->get("/siswa/{$this->student->id}/dokumen/akta_lahir")->assertNotFound();
});

test('berkas hilang di disk memberi 404 dan hapus dokumen menghapus berkas', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
    $path = $this->student->documents()->first()->path;

    $this->delete("/siswa/{$this->student->id}/dokumen/kk")->assertRedirect();
    Storage::disk('local')->assertMissing($path);
    $this->get("/siswa/{$this->student->id}/dokumen/kk")->assertNotFound();
});

test('menghapus siswa menghapus berkas dokumennya', function () {
    $this->actingAs(makeUserWith(['siswa.lihat', 'siswa.hapus', 'dokumen.unggah'], $this->school, true, 'op-hapus'));
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);

    $this->delete("/siswa/{$this->student->id}");

    expect(Storage::disk('local')->allFiles())->toBe([]);
});
```
(Hapus tes bertanda `->skip(...)`; kegagalan tengah-jalan diuji lewat service: tambahkan tes yang memanggil `StudentDocumentService::store` setelah `StudentDocument::creating(fn () => throw new RuntimeException)` didaftarkan di tes — dengan `expect(fn () => ...)->toThrow(RuntimeException::class)` lalu `Storage::disk('local')->allFiles()` kosong.)
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi** `StudentDocumentService`:

```php
public const MAX_KB = 5120;

public function store(Student $student, string $type, UploadedFile $file, User $actor): StudentDocument
{
    $path = $file->storeAs("students/{$student->id}", Str::uuid().'.'.$file->extension(), 'local');

    try {
        [$document, $oldPath] = DB::transaction(function () use ($student, $type, $file, $path) {
            $old = $student->documents()->where('type', $type)->first();
            $document = $student->documents()->updateOrCreate(
                ['type' => $type],
                ['path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize()],
            );

            return [$document, $old?->path];
        });
    } catch (Throwable $e) {
        Storage::disk('local')->delete($path);

        throw $e;
    }

    if ($oldPath !== null && $oldPath !== $path) {
        Storage::disk('local')->delete($oldPath);
    }
    activity('siswa')->performedOn($student)->causedBy($actor)->withProperties(['jenis' => $type])->log('dokumen.diunggah');

    return $document;
}
```
`delete()` hapus berkas + baris + log `dokumen.dihapus`; `download()` → `abort_unless($document && Storage::disk('local')->exists($path), 404)`, log `dokumen.diunduh`, `Storage::disk('local')->download($path, "{$type}-{$student->nisn}.{ext}")` dengan `ext` dari `pathinfo($path, PATHINFO_EXTENSION)`.
Controller: `store` memvalidasi `['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.StudentDocumentService::MAX_KB]]` dengan pesan `file.required` "Pilih berkas.", `file.mimes` "Berkas harus PDF, JPG, atau PNG.", `file.max` "Ukuran berkas maksimal 5 MB."; redirect back + flash "Dokumen diunggah." Rute: `->whereIn('type', StudentRules::DOCUMENT_TYPES)` pada ketiga rute dokumen; izin `can:dokumen.unggah|dokumen.lihat|dokumen.hapus`. `StudentController::show`: `documents` = `$student->documents->map(fn ($d) => ['type' => $d->type, 'name' => "{$d->type}-{$student->nisn}.".pathinfo($d->path, PATHINFO_EXTENSION), 'size' => $d->size, 'url' => route('siswa.dokumen.download', [$student, $d->type])])` hanya bila pengguna `can('dokumen.lihat')`, selain itu array kosong. Tambahkan `{type}` → `kk` pada `resolveUri` matriks izin dan baris `POST/GET/DELETE /siswa/{student}/dokumen/{type}`.
- [ ] **Step 4: Jalankan** seluruh suite → hijau; Pint + Larastan.
- [ ] **Step 5: Commit** — "feat: tambah unggah, unduh, dan hapus dokumen siswa di disk privat".

---

### Task 5: Impor Excel

**Files:**
- Create: `app/Domains/Students/Exports/StudentTemplateExport.php`, `app/Domains/Students/Imports/StudentSheetImport.php`, `app/Domains/Students/Services/StudentImportService.php`, `app/Domains/Students/Http/Controllers/StudentImportController.php`
- Modify: `routes/students.php` (sebelum `{student}`), `tests/Feature/Access/RoutePermissionMatrixTest.php`
- Test: `tests/Feature/Students/StudentImportTest.php`

**Interfaces:**
- Consumes: `StudentRules`, `Student`.
- Produces: `StudentImportService::MAX_ROWS = 500`; `import(UploadedFile $file, User $actor, ?int $schoolId): array{imported: int, failed: list<array{row: int, reason: string}>}`; rute `siswa.impor.template` (GET `/siswa/impor/template`), `siswa.impor.store` (POST `/siswa/impor`, field `file`, `school_id` untuk staf; hasil dikirim sebagai flash `import_result` + redirect back; **bukan** prop bernama `flash`).
- Heading dikenali (tak peka huruf besar, urutan bebas): `NIK`, `NISN`, `Nama`, `Tanggal lahir`, `Jenis kelamin`.

- [ ] **Step 1: Verifikasi API xlsx** — buat berkas contoh dengan `Excel::raw(new StudentTemplateExport, \Maatwebsite\Excel\Excel::XLSX)` di tinker; baca kembali memakai `Excel::toArray(new StudentSheetImport, $path)` dan catat bentuk kunci hasil (diharapkan `nik, nisn, nama, tanggal_lahir, jenis_kelamin` dengan `WithHeadingRow`) serta apakah sel tanggal datang sebagai angka serial. Sesuaikan peta kunci di Step 3 bila berbeda; catat sebagai Ruling di ledger.
- [ ] **Step 2: Tes yang gagal** (`StudentImportTest.php`; helper membuat xlsx lewat PhpSpreadsheet)

```php
<?php

use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;

function xlsx(array $rows, array $heading = ['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin']): UploadedFile
{
    $sheet = new Spreadsheet;
    $ws = $sheet->getActiveSheet();
    $ws->fromArray([$heading, ...$rows], null, 'A1', true);
    foreach ($ws->getRowIterator(2) as $row) {
        foreach (['A', 'B'] as $col) {
            $cell = $ws->getCell($col.$row->getRowIndex());
            $cell->setValueExplicit((string) $cell->getValue(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($sheet))->save($path);

    return new UploadedFile($path, 'siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['siswa.lihat', 'siswa.impor'], $this->school, true, 'op-impor');
});

test('sebagian baris gagal: yang benar masuk, yang salah dilaporkan dengan nomor baris', function () {
    $file = xlsx([
        ['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L'],
        ['123', '0012345679', 'NIK Salah', '2009-05-01', 'P'],
        ['3201123412341235', '0012345680', 'Siti', '02/06/2009', 'Perempuan'],
        ['3201123412341234', '0012345681', 'Ganda', '2009-05-01', 'L'],
    ]);

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => $file])
        ->assertRedirect()->assertSessionHas('import_result', fn ($r) => $r['imported'] === 2
            && $r['failed'] === [
                ['row' => 3, 'reason' => 'NIK harus 16 digit angka.'],
                ['row' => 5, 'reason' => 'NIK ganda di berkas.'],
            ]);

    expect(Student::count())->toBe(2)
        ->and(Student::where('name', 'Siti')->first()->birth_date->toDateString())->toBe('2009-06-02');
});

test('NIK bertipe angka (presisi hilang) gagal dengan alasan jelas', function () {
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin'], [3201123412341234, 12345678, 'Angka', '2009-05-01', 'L']]);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($sheet))->save($path);
    $file = new UploadedFile($path, 'a.xlsx', null, null, true);

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => $file])
        ->assertSessionHas('import_result', fn ($r) => $r['imported'] === 0 && count($r['failed']) === 1 && $r['failed'][0]['row'] === 2);
});

test('kolom wajib hilang, lebih dari 500 baris, dan berkas bukan xlsx ditolak', function () {
    $this->actingAs($this->op);
    $this->post('/siswa/impor', ['file' => xlsx([['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L']], ['NIK', 'Nama'])])
        ->assertSessionHasErrors('file');

    $baris = array_map(fn ($i) => [str_pad((string) $i, 16, '3', STR_PAD_LEFT), str_pad((string) $i, 10, '0', STR_PAD_LEFT), "S$i", '2009-05-01', 'L'], range(1, 501));
    $this->post('/siswa/impor', ['file' => xlsx($baris)])->assertSessionHasErrors('file');

    $this->post('/siswa/impor', ['file' => UploadedFile::fake()->create('a.txt', 1, 'text/plain')])->assertSessionHasErrors('file');
    expect(Student::count())->toBe(0);
});

test('operator tidak bisa menyuntikkan sekolah lain; staf wajib memilih sekolah', function () {
    $lain = School::factory()->create();
    $baris = [['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L']];

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => xlsx($baris), 'school_id' => $lain->id]);
    expect(Student::withoutGlobalScopes()->first()->school_id)->toBe($this->school->id);

    Student::withoutGlobalScopes()->delete();
    $staf = makeUserWith(['siswa.impor'], roleName: 'staf-impor');
    $this->actingAs($staf)->post('/siswa/impor', ['file' => xlsx($baris)])->assertSessionHasErrors('school_id');
    $this->actingAs($staf)->post('/siswa/impor', ['file' => xlsx($baris), 'school_id' => $lain->id])->assertSessionHasNoErrors();
    expect(Student::withoutGlobalScopes()->first()->school_id)->toBe($lain->id);
});

test('satu log ringkas per impor dan template dapat diunduh dengan judul yang benar', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => xlsx([['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L']])]);

    expect(Activity::where('description', 'siswa.diimpor')->count())->toBe(1)
        ->and(Activity::where('log_name', 'siswa')->where('event', 'created')->count())->toBe(0);

    $res = $this->get('/siswa/impor/template')->assertOk();
    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($res->baseResponse->getFile()->getPathname());
    expect($sheet->getActiveSheet()->rangeToArray('A1:E1')[0])->toBe(['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin']);
});
```
- [ ] **Step 3: Jalankan** → FAIL.
- [ ] **Step 4: Implementasi.**
  - `StudentTemplateExport implements FromArray, WithHeadings, WithColumnFormatting` (`array()` → satu baris contoh `['3201123412341234', '0012345678', 'Contoh Nama', '2009-05-01', 'L']`, `headings()` → judul di atas, `columnFormats()` → `['A' => NumberFormat::FORMAT_TEXT, 'B' => NumberFormat::FORMAT_TEXT, 'D' => NumberFormat::FORMAT_TEXT]`); controller `Excel::download(new StudentTemplateExport, 'template-siswa.xlsx')`.
  - `StudentSheetImport implements ToArray, WithHeadingRow` — `array(array $array): void {}` kosong (hasil dibaca lewat `Excel::toArray`).
  - `StudentImportService::import()`: `$sheet = Excel::toArray(new StudentSheetImport, $file)[0] ?? []`; pastikan kolom wajib `nik, nisn, nama, tanggal_lahir, jenis_kelamin` ada di kunci baris pertama → bila tidak, `throw ValidationException::withMessages(['file' => 'Kolom wajib tidak lengkap: '.implode(', ', $missing).'.'])`; `count($sheet) > self::MAX_ROWS` → `ValidationException` "Maksimal 500 baris per impor."; lewati baris yang seluruh selnya kosong; untuk tiap baris (`$rowNo = $i + 2`): normalisasi `nik`/`nisn` → `trim((string) $v)`, `birth_date` → `parseDate()` (angka → `Date::excelToDateTimeObject`; string `Y-m-d` atau `d/m/Y` via `Carbon::createFromFormat` dengan `!` dan pemeriksaan galat; selain itu `null`), `gender` → `L`/`P` dari `L|P|laki-laki|perempuan` (tak peka huruf besar); validasi `Validator::make($data, StudentRules::fields(), StudentRules::messages())`; NIK yang sudah terlihat dalam berkas → gagal "NIK ganda di berkas."; error pertama sebagai `reason`; baris valid disimpan lewat `$student = new Student([...+'school_id']); $student->disableLogging(); $student->save();`. NIK numerik presisi-hilang gagal otomatis karena `trim((string) 3.2011234123412E+15)` bukan 16 digit (pesan "NIK harus 16 digit angka."; catatan di UI/template: kolom NIK format teks). Akhiri dengan `activity('siswa')->causedBy($actor)->withProperties(['berhasil' => $imported, 'gagal' => count($failed)])->log('siswa.diimpor')`. `school_id` = `$actor->school_id ?? $schoolId`; bila keduanya null → `ValidationException` pada `school_id` "Pilih sekolah."
  - `StudentImportController::store`: validasi `file` `required|file|mimes:xlsx|max:2048` (pesan: "Pilih berkas Excel (.xlsx).", "Berkas harus berformat .xlsx.", "Ukuran berkas maksimal 2 MB."), `school_id` `nullable|integer|exists:schools,id`; hasil `back()->with('import_result', $result)`; `HandleInertiaRequests` membagikan `flash.import_result` ke FE: tambahkan `'import_result' => fn () => $request->session()->get('import_result')` ke array `flash` (ubah tipe `PageProps.flash` di Task 6).
  - Rute impor didefinisikan **sebelum** `siswa/{student}`; izin `can:siswa.impor`; tambahkan baris `GET /siswa/impor/template` dan `POST /siswa/impor` ke dataset matriks izin.
- [ ] **Step 5: Jalankan** seluruh suite → hijau; Pint + Larastan. Bila `Excel::toArray` mengembalikan kunci berbeda dari asumsi, ubah peta kunci dan catat Ruling.
- [ ] **Step 6: Commit** — "feat: tambah impor siswa dari Excel dengan laporan baris gagal".

---

### Task 6: Utilitas FE, tipe, dan panduan UI 5.18–5.20

**Files:**
- Modify: `resources/js/lib/date.ts` + `date.test.ts` (`calculateAge`), `resources/js/lib/format.ts` + `format.test.ts` (`maskNik`), `resources/js/types/index.d.ts` (`flash.import_result`), `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`
- Create: `resources/js/types/student.ts`

**Interfaces:**
- Produces: `calculateAge(birthDate: string, on?: string | Date): number | null`, `maskNik(nik?: string | null): string`; tipe `StudentRow`, `StudentDetail`, `StudentDocumentRow`, `ImportResult = { imported: number; failed: { row: number; reason: string }[] }`.

- [ ] **Step 1: Tes yang gagal** — `date.test.ts`: `calculateAge('2009-10-07', '2026-10-06')` → 16; `('2009-10-07','2026-10-07')` → 17; `('2009-10-07','2027-10-06')` → 17; `('', '2026-10-07')` → null; `('abc','2026-10-07')` → null. `format.test.ts`: `maskNik('3201123412341234')` → `'3201********1234'`; `maskNik('12345')` → `'*****'`; `maskNik(null)` → `'—'`.
- [ ] **Step 2: Jalankan** `npm test` → FAIL.
- [ ] **Step 3: Implementasi.**

```ts
// lib/date.ts (tambahan)
function ymd(value: string | Date): [number, number, number] | null {
    if (typeof value === 'string') {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
        return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
    }
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: TZ })
            .formatToParts(value).map((p) => [p.type, p.value]),
    );
    return [Number(parts.year), Number(parts.month), Number(parts.day)];
}

/** Hanya untuk tampilan; kelayakan umur ditentukan server. */
export function calculateAge(birthDate: string, on: string | Date = new Date()): number | null {
    const b = ymd(birthDate);
    const o = ymd(on);
    if (!b || !o) return null;
    let age = o[0] - b[0];
    if (o[1] < b[1] || (o[1] === b[1] && o[2] < b[2])) age--;
    return age;
}
```
```ts
// lib/format.ts (tambahan)
export function maskNik(nik?: string | null): string {
    if (!nik) return '—';
    if (nik.length <= 8) return '*'.repeat(nik.length);
    return nik.slice(0, 4) + '*'.repeat(nik.length - 8) + nik.slice(-4);
}
```
`types/student.ts`: `StudentRow = { id: number; name: string; nisn: string; nik: string; age: number; school: string | null; docs: { kk: boolean; akta_lahir: boolean }; eligible: boolean; reasons: string[] }`; `StudentDetail = StudentRow & { birth_date: string; gender: 'L' | 'P'; can: { update: boolean; delete: boolean } }`; `StudentDocumentRow = { type: 'kk' | 'akta_lahir'; name: string; size: number; url: string }`; `ImportResult`. `PageProps.flash`: tambahkan `import_result?: ImportResult | null`.
- [ ] **Step 4: Panduan UI** — tambahkan **5.18 Siswa (daftar, form, detail)**, **5.19 Dokumen siswa**, **5.20 Impor Excel** sebelum "## 6. Pola lintas halaman" dengan isi spec §4.1–4.5; tambahkan menu "Data Siswa > Siswa" ke daftar menu bila ada.
- [ ] **Step 5: Jalankan** `npm test`, `npm run typecheck`, `npm run lint`, `npm run build` → hijau.
- [ ] **Step 6: Commit** — "feat: tambah utilitas umur dan masker NIK serta panduan UI siswa".

---

### Task 7: Daftar dan form siswa

**Files:**
- Create: `resources/js/Pages/Siswa/Index.vue`, `resources/js/Pages/Siswa/Form.vue`

**Interfaces:**
- Consumes: props Task 3; `StudentRow`; `useDataTable('/siswa', {search, docs, eligible, school_id})`; `DataTable`, `SearchInput`, `SelectField`, `EmptyState`, `Pagination`, `RowActions`, `ConfirmDialog`, `FormField`, `InlineAlert`, `focusFirstInvalid`.
- Produces: rute halaman `/siswa`, `/siswa/buat`, `/siswa/{id}/ubah`.

- [ ] **Step 1: `Siswa/Index.vue`** (salin struktur `Pengguna/Index.vue`): `PageHeader title="Siswa" description="Kelola data siswa dan kelengkapan dokumennya."` dengan tombol "Tambah siswa" (`can(SiswaBuat)`) dan tombol outline "Impor Excel" (`can(SiswaImpor)`, membuka `ImportStudentsDialog` Task 9; sampai Task 9 selesai tombol memakai `ref` dialog yang belum ada — **tambahkan tombol dan dialog bersamaan di Task 9**, jadi di task ini hanya tombol Tambah). Filter: `SearchInput` "Cari nama, NIK, atau NISN"; `SelectField` Kelengkapan dokumen (`lengkap` "Lengkap", `kurang` "Belum lengkap", all-label "Semua dokumen"); `SelectField` Eligible (`1` "Eligible", all-label "Semua status"); `SelectField` Sekolah (hanya bila prop `schools` bukan null). `useDataTable('/siswa', {...})`: `watch` pada filter selain `search` → `apply`, `search` debounce 300 ms. Kolom: Siswa (nama tebal, "NISN {nisn}" kecil), NIK (`font-mono text-xs`, sudah dimasker server), Umur (`{age} th`), Sekolah (hanya bila `schools` ada), Dokumen (dua indikator berteks: ikon `Check`/`X` + "KK" dan "Akta" dengan `text-success`/`text-muted-foreground`, `sr-only` "terunggah"/"belum"), Status (`<span>` badge `bg-success-soft text-success` "Eligible" atau `bg-neutral-soft text-neutral-foreground` "Belum eligible" dengan `title` = alasan digabung), Aksi (`RowActions`: Lihat detail, Ubah bila `can(SiswaUbah)`, Hapus bila `can(SiswaHapus)` → `ConfirmDialog` destructive "Hapus {nama}?" "Data siswa dan dokumennya akan dihapus permanen." → `router.delete(route('siswa.destroy', id), { preserveScroll: true })`). State: loading, kosong ("Belum ada siswa" + tombol Tambah/Impor), hasil filter kosong + "Reset filter"; petunjuk "↔ Geser tabel" mobile; `table-class="min-w-[1000px]"`.
- [ ] **Step 2: `Siswa/Form.vue`** (salin `Pengguna/Form.vue`): judul "Tambah siswa"/"Ubah siswa"; kartu "Data siswa": NIK (`inputmode="numeric"`, `maxlength=16`, hint "16 digit angka."), NISN (`maxlength=10`), Nama lengkap, Tanggal lahir (`Input type="date"`, `max` = hari ini), Jenis kelamin (`SelectField`: `L` "Laki-laki", `P` "Perempuan"), Sekolah (`SelectField`, hanya bila `schools` tidak null; wajib). Validasi klien (pesan sama dengan server dari `StudentRules::messages()`): NIK `/^\d{16}$/` → "NIK harus 16 digit angka.", NISN `/^\d{10}$/` → "NISN harus 10 digit angka.", nama wajib → "Nama lengkap wajib diisi.", tanggal wajib → "Tanggal lahir wajib diisi.", gender → "Pilih jenis kelamin.", sekolah → "Pilih sekolah."; `focusFirstInvalid`; `useForm` POST `route('siswa.store')` / PUT `route('siswa.update', id)`; Batal ke `siswa.index`; breadcrumb Siswa > Tambah/Ubah siswa. Prop `student` untuk ubah memuat NIK penuh.
- [ ] **Step 3: Cek** `npm run format && npm run typecheck && npm run lint && npm run build`.
- [ ] **Step 4: Verifikasi browser** (seed 3–4 siswa lewat tinker dengan `Student::factory()`; login `sari@example.com`/`password`): daftar tampil, NIK termasking, filter dokumen/eligible/cari mengubah `?filter[...]`, hapus lewat dialog, tambah siswa baru (validasi klien lalu simpan → redirect ke detail; detail dibuat di Task 8, jadi sementara redirect akan menampilkan galat komponen — verifikasi simpan lewat respons dan data di DB), ubah menampilkan NIK penuh, mobile 390 tanpa overflow.
- [ ] **Step 5: Commit** — "feat: tambah daftar dan form siswa".

---

### Task 8: `FileUpload` dan detail siswa

**Files:**
- Create: `resources/js/Components/shared/FileUpload.vue`, `resources/js/lib/files.ts` (+ `files.test.ts`), `resources/js/Pages/Siswa/Show.vue`

**Interfaces:**
- Produces: `validateFile(file: File, opts: {accept: string[]; maxBytes: number}): string | null` (pesan: "Berkas harus PDF, JPG, atau PNG." / "Ukuran berkas maksimal 5 MB."), `formatBytes(n: number): string` ("1,2 MB"); `<FileUpload label type :existing :can-upload :can-delete :upload-url :delete-url />`.

- [ ] **Step 1: Tes yang gagal** (`files.test.ts`): `validateFile` menerima `application/pdf`, `image/jpeg`, `image/png`; menolak `text/plain` dengan pesan tipe; menolak 6 MB dengan pesan ukuran; `formatBytes(1536)` → `'1,5 KB'`, `formatBytes(5 * 1024 * 1024)` → `'5,0 MB'` (format `id-ID`).
- [ ] **Step 2: Implementasi util** (`files.ts`): konstanta `ACCEPT = ['application/pdf', 'image/jpeg', 'image/png']`, `MAX_BYTES = 5 * 1024 * 1024`; `formatBytes` memakai `Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })`.
- [ ] **Step 3: `FileUpload.vue`** — props `label: string`, `type: 'kk'|'akta_lahir'`, `existing: StudentDocumentRow | null`, `canUpload`, `canDelete`, `uploadUrl: string`, `deleteUrl: string`. Memakai `useForm({ file: null as File | null })` dan `form.post(uploadUrl, { forceFormData: true, preserveScroll: true, onSuccess: reset })` (progress dari `form.progress?.percentage`). Tampilan: judul `label`; bila `existing`: baris nama berkas, ukuran, tautan "Unduh" (`<a :href="existing.url">` karena respons berkas), tombol "Ganti" (membuka dialog pilih berkas) dan "Hapus" (`ConfirmDialog` destructive → `router.delete(deleteUrl, { preserveScroll: true })`); bila kosong dan `canUpload`: area `<label>` berborder putus-putus dengan `<input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png">` ("Pilih berkas atau seret ke sini", "PDF, JPG, atau PNG, maks 5 MB"), mendukung `dragover/drop`; setelah memilih: validasi `validateFile` → galat di bawah area; bila valid tampil nama + ukuran + tombol "Unggah" dan "Batal"; saat mengunggah tampil progress (`<progress>` + `role="status"` "Mengunggah {n}%"); galat server `form.errors.file`. Tanpa izin dan tanpa berkas: teks "Belum diunggah." Semua kontrol dapat difokuskan dan memiliki label.
- [ ] **Step 4: `Siswa/Show.vue`** (pola `Pengguna/Show.vue`): breadcrumb Siswa > Detail siswa; kartu profil (avatar inisial 64px, nama `text-2xl`, "NISN {nisn}", badge eligible dan—bila belum—daftar alasan `ul text-sm text-muted-foreground` di bawah badge; tombol "Ubah" (`student.can.update`) dan menu "…" berisi "Hapus" (`student.can.delete`, `ConfirmDialog`); `InfoList` Informasi siswa (NIK dimasker, Tanggal lahir `formatDate` + "({age} th)", Jenis kelamin "Laki-laki"/"Perempuan", Sekolah); kartu "Dokumen" berisi dua `FileUpload` (Kartu Keluarga, Akta Kelahiran) dengan `existing = documents.find(d => d.type === ...)`, `canUpload = can(DokumenUnggah)`, `canDelete = can(DokumenHapus)`, `uploadUrl = route('siswa.dokumen.store', [student.id, type])`, `deleteUrl = route('siswa.dokumen.destroy', [student.id, type])`. Tambahkan `DokumenLihat/DokumenUnggah/DokumenHapus` ke `lib/permissions.ts` bila belum ada.
- [ ] **Step 5: Cek** format/typecheck/lint/build + `npm test`.
- [ ] **Step 6: Verifikasi browser** — unggah PDF (siapkan berkas kecil via tinker/`fsutil` atau `page.setInputFiles`), badge berubah menjadi Eligible setelah dua dokumen dan umur sesuai, ganti berkas, unduh (respons berkas), hapus, tipe/ukuran salah menampilkan pesan klien, mobile 390 tanpa overflow.
- [ ] **Step 7: Commit** — "feat: tambah detail siswa dan komponen unggah dokumen".

---

### Task 9: Dialog impor Excel

**Files:**
- Create: `resources/js/Components/shared/ImportStudentsDialog.vue`
- Modify: `resources/js/Pages/Siswa/Index.vue` (tombol "Impor Excel" + dialog)

**Interfaces:**
- Consumes: rute `siswa.impor.template|store`, `ImportResult`, `usePage().props.flash.import_result`, `formatBytes`.

- [ ] **Step 1: Komponen** — props `schools: Option[] | null`; `v-model:open`. Isi: `DialogTitle` "Impor siswa dari Excel"; langkah 1: tautan "Unduh template (.xlsx)" (`<a :href="route('siswa.impor.template')">`) dengan catatan "Kolom NIK, NISN, dan Tanggal lahir berformat teks; isi tanggal sebagai YYYY-MM-DD atau DD/MM/YYYY."; langkah 2: `SelectField` Sekolah (hanya bila `schools`; wajib) dan input berkas `.xlsx` (maks 2 MB, validasi klien "Berkas harus berformat .xlsx." / "Ukuran berkas maksimal 2 MB."); tombol "Impor" (`form.post(route('siswa.impor.store'), { forceFormData: true, preserveScroll: true })`, nonaktif saat memproses atau tanpa berkas). Setelah selesai, dialog beralih ke tampilan hasil dari `flash.import_result`: ringkasan "{imported} siswa berhasil ditambahkan, {failed.length} baris gagal." dan bila ada yang gagal tabel Baris / Alasan (`max-h-[320px] overflow-auto`), tombol "Tutup" dan "Impor lagi". Galat server (`form.errors.file`, `form.errors.school_id`) tampil `InlineAlert`.
- [ ] **Step 2: Index.vue** — tombol outline "Impor Excel" (`can(SiswaImpor)`) di `PageHeader` actions yang membuka dialog; dialog menerima `schools`.
- [ ] **Step 3: Cek** format/typecheck/lint/build.
- [ ] **Step 4: Verifikasi browser** — buat berkas contoh dengan baris benar dan salah (via PhpSpreadsheet di tinker ke `scratchpad`), unggah dengan `page.setInputFiles`, hasil menampilkan ringkasan dan baris gagal, daftar siswa ter-refresh, unduh template (respons berkas), mobile 390.
- [ ] **Step 5: Commit** — "feat: tambah dialog impor siswa dari Excel".

---

### Task 10: Verifikasi akhir, review, dan penutupan

- [ ] **Step 1:** `php artisan test --compact`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npm test`, `npm run typecheck`, `npm run lint`, `npm run build` → semua hijau.
- [ ] **Step 2:** End-to-end browser (desktop 1440 + mobile 390): login operator sekolah (buat via tinker dengan role `operator-sekolah` dan `school_id`), tambah siswa, unggah KK dan akta, badge eligible, unduh dokumen, impor Excel campuran, lihat `/log` sebagai admin (NIK termasking, aksi dokumen tercatat); operator sekolah lain tidak melihat/mengunduh siswa tersebut (404).
- [ ] **Step 3:** Jalankan skill `impeccable` (critique + audit, laporan saja) pada halaman baru dan `ponytail:ponytail-review` atas seluruh perubahan Fase 3; perbaiki temuan valid dalam satu batch dengan TDD untuk perilaku backend; konfirmasi sekali.
- [ ] **Step 4:** Perbarui `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (Fase 3 selesai + penyimpangan), memory `deferred-items` (hapus item Fase 3 yang selesai; tambahkan item baru yang tertunda), dan ledger; commit akhir tanpa `Co-Authored-By`; ringkasan ke user beserta daftar Ruling dan minor tertunda.
