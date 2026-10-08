# Fase 5 — Jadwal dan Penandaan Rekaman: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, tanpa subagent) untuk menjalankan rencana ini tugas demi tugas. Steps memakai checkbox (`- [ ]`).

**Goal:** Admin kecamatan membuat jadwal per sekolah, menempatkan siswa `Terverifikasi` ke jadwal, mengubah jadwal (operator diberi tahu), dan menandai kehadiran rekaman secara massal; operator sekolah hanya melihat jadwal sekolahnya.

**Architecture:** Domain baru `app/Domains/Schedules` (Controller → FormRequest → Service → Repository interface + Eloquent) dengan `ScheduleService` (jadwal dan penempatan) dan `RecordingService` (penandaan massal). Semua perubahan status lewat `StatusTransitioner` (diperluas dengan atribut tambahan untuk `schedule_id`); peta transisi di `RegistrationStatus::next()`. Notifikasi lewat `NotificationService::toSchool`.

**Tech Stack:** Laravel 13 / PHP 8.5 / MySQL 8, spatie permission + activitylog + query-builder, Inertia v2 + Vue 3 + TS + Tailwind v4, Pest, Larastan 5, Pint, Vitest, vue-tsc, ESLint.

**Spec:** `docs/superpowers/specs/2026-10-08-fase-5-jadwal-rekaman-design.md`

## Global Constraints

- Bahasa UI, pesan galat, dan komentar: Indonesia. Pola service-repository (interface di `Repositories/Contracts` + `Eloquent*`, diikat di `app/Providers/RepositoryServiceProvider.php`).
- Commit message detail (ringkasan di baris pertama, isi menjelaskan apa dan mengapa). **Dilarang** `Co-Authored-By` atau atribusi tool/AI apa pun. Commit mengikuti langkah commit di rencana ini.
- Berkas PHP/Vue ditulis dengan Write atau Edit (bukan heredoc bash); **jangan** memakai Python dengan backslash namespace PHP (`\U`, `\N` merusak literal); jangan `sed -i` untuk teks Indonesia.
- Nama prop halaman Inertia tidak boleh `auth`, `flash`, `menu`, `notifications`.
- Rute baru dimuat dari `routes/web.php` sebelum `Route::any('{path}')` fallback. POST/DELETE memakai `throttle:60,1`. Rute bertingkat memakai `scopeBindings()`.
- Zona penentu "hari ini": `Asia/Jakarta`. Jadwal baru dan perubahan tanggal harus pada hari ini atau setelahnya; penempatan hanya ke jadwal yang belum lewat; penandaan hanya pada atau setelah tanggal jadwal.
- Status akhir (`sudah_rekaman`, `tidak_hadir`, `belum_rekaman`) final: tanpa koreksi. `active()` dan `locking()` pada `RegistrationStatus` tidak diubah.
- Tes yang memicu notifikasi lewat `toSchool`/`toPermission` harus membuat permission terkait (`makeUserWith([...])` atau `Permission::findOrCreate`), karena scope `permission()` spatie melempar bila permission belum ada di DB.
- Suite penuh backend memakan >120 detik: jalankan di background (`run_in_background`) dan tunggu notifikasinya. Tes terarah (`php artisan test tests/Feature/Schedules`) cukup per langkah.
- Playwright: login lewat `browser_run_code_unsafe` dengan `page.getByLabel('Email').fill(...)`; input lewat `browser_evaluate` tidak mengisi v-model.
- Tabel lebar di mobile wajib punya petunjuk "Geser tabel untuk melihat kolom lainnya" (`md:hidden`).

## Rulings terhadap spec (dicatat sebelum eksekusi)

- **R1:** Kandidat penempatan dan daftar penandaan dikirim dalam bentuk datar `{id, name, nisn, hint}` (hint = judul pendaftaran) agar langsung dipakai komponen `EntryChecklist`; spec menulis bentuk bersarang. Biayanya: tidak ada.
- **R2:** Dialog "Tambah siswa" sekaligus menjadi konfirmasi (tombol "Jadwalkan N siswa"); tidak ada dialog konfirmasi kedua.
- **R3:** Teks notifikasi perubahan jadwal memuat tanggal dan lokasi terbaru saja ("Jadwal sekarang pada {tanggal} di {lokasi}."), bukan tanggal lama; keduanya tetap satu notifikasi per perubahan.
- **R4:** Daftar jadwal diurutkan tanggal menurun (jadwal terjauh dulu); filter Periode "Akan datang" dan "Sudah lewat" menyaring. Biayanya: urutan terdekat-dulu butuh pengurutan khusus bila diminta.
- **R5:** `DashboardController` berada di `app/Http/Controllers` (bersama `ProfileController`), bukan di domain, karena Beranda lintas domain dan Fase 6 akan menambah sumbernya.
- **R6:** Keluarkan dari jadwal diizinkan kapan pun selama status masih `Terjadwal` (termasuk jadwal yang sudah lewat tetapi belum ditandai).

## Review Focus

1. Dua admin menempatkan entri yang sama ke dua jadwal: yang kedua gagal karena status sudah `Terjadwal` (kunci baris + pemeriksaan status); entri tidak pernah berada di dua jadwal (Task 3).
2. Batas hari di zona Asia/Jakarta: pukul 03.00 WIB (masih tanggal sebelumnya di UTC) sudah dihitung "hari ini" untuk penandaan; jadwal besok belum bisa ditandai; jadwal kemarin tidak bisa ditempati (Task 1, 3, 4).
3. Penandaan massal berisi entri campuran (satu sudah berstatus akhir atau milik jadwal lain): seluruhnya gagal, tanpa perubahan setengah jalan (Task 4).
4. Mengubah jadwal tanpa perubahan nyata, atau jadwal tanpa siswa, tidak mengirim notifikasi (Task 2).
5. Operator sekolah memanggil URL jadwal/entri sekolah lain, atau entri milik jadwal lain pada URL bertingkat: 404 (Task 5). Siswa `Tidak hadir` dapat didaftarkan lagi, `Sudah rekaman` tidak (Task 4).

---

## Peta berkas

**Baru (backend):** `app/Domains/Schedules/{Models/Schedule.php, Services/{ScheduleService,RecordingService}.php, Repositories/Contracts/ScheduleRepositoryInterface.php, Repositories/EloquentScheduleRepository.php, Http/Controllers/{ScheduleController,ScheduleStudentController,RecordingController}.php, Http/Requests/{ScheduleRequest,AssignRequest,RecordingRequest}.php}`, `app/Domains/Notifications/Notifications/ScheduleNotification.php`, `app/Http/Controllers/DashboardController.php`, `database/migrations/2026_10_08_30000{1,2}_*.php`, `database/factories/ScheduleFactory.php`, `routes/schedules.php`.

**Diubah (backend):** `app/Domains/Registrations/Enums/RegistrationStatus.php`, `app/Domains/Registrations/Models/RegistrationStudent.php`, `app/Domains/Registrations/Services/StatusTransitioner.php`, `app/Providers/RepositoryServiceProvider.php`, `app/Domains/Access/Services/MenuService.php`, `lang/id/activity.php`, `routes/web.php`, `tests/Pest.php`, `tests/Feature/Access/{MenuTest,RoutePermissionMatrixTest}.php`.

**Baru (frontend):** `resources/js/types/schedule.ts`, `resources/js/Components/shared/EntryChecklist.vue` (+ `EntryChecklist.test.ts`), `resources/js/Pages/Jadwal/{Index,Form,Show,Rekaman}.vue`.

**Diubah (frontend/docs):** `resources/js/config/menuIcons.ts` (+test), panduan UI, rencana sprint, memory `deferred-items`.

---

## BACKEND

### Task 1: Skema, transisi baru, model, helper tes

**Files:**
- Create: `database/migrations/2026_10_08_300001_create_schedules_table.php`, `database/migrations/2026_10_08_300002_add_schedule_id_to_registration_students_table.php`, `app/Domains/Schedules/Models/Schedule.php`, `database/factories/ScheduleFactory.php`
- Modify: `app/Domains/Registrations/Enums/RegistrationStatus.php`, `app/Domains/Registrations/Models/RegistrationStudent.php`, `app/Domains/Registrations/Services/StatusTransitioner.php`, `tests/Pest.php`
- Test: `tests/Feature/Schedules/ScheduleModelTest.php`

**Interfaces:**
- Consumes: `makeEntry`, `RegistrationStatus`, `StatusTransitioner::move` (Fase 4).
- Produces: `Schedule` (`school`, `creator`, `entries()`, `isPast(): bool`, `isOpenForMarking(): bool`); `RegistrationStudent::schedule_id` (fillable) dan `schedule()`; `RegistrationStatus::next()` dengan `Terverifikasi → [Terjadwal]` dan `Terjadwal → [Terverifikasi, SudahRekaman, TidakHadir, BelumRekaman]`; `StatusTransitioner::move(RegistrationStudent $entry, RegistrationStatus $to, User $actor, ?string $note = null, array $attributes = []): RegistrationStudent` (atribut digabung ke update); helper tes global `makeSchedule(?School $school = null, ?string $date = null): Schedule` dan `makeScheduledEntry(RegistrationStatus $status = Terjadwal, ?Schedule $schedule = null): RegistrationStudent`.

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan di akhir `tests/Pest.php` (impor `App\Domains\Schedules\Models\Schedule` di bagian `use` atas):

```php
/** Jadwal untuk sekolah (default: sekolah baru) pada tanggal tertentu (default: 7 hari lagi). */
function makeSchedule(?School $school = null, ?string $date = null): Schedule
{
    return Schedule::factory()->for($school ?? School::factory()->create())->create(array_filter(['date' => $date]));
}

/** Entri bersekolah sama dengan jadwal dan sudah terkait ke jadwal itu. */
function makeScheduledEntry(RegistrationStatus $status = RegistrationStatus::Terjadwal, ?Schedule $schedule = null): RegistrationStudent
{
    $schedule ??= makeSchedule();
    $entry = makeEntry($status, $schedule->school);
    $entry->update(['schedule_id' => $schedule->id]);

    return $entry;
}
```

Buat `tests/Feature/Schedules/ScheduleModelTest.php`:

```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Models\User;
use Carbon\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('peta transisi penjadwalan dan penandaan', function () {
    expect(S::Terverifikasi->next())->toBe([S::Terjadwal])
        ->and(S::Terjadwal->next())->toBe([S::Terverifikasi, S::SudahRekaman, S::TidakHadir, S::BelumRekaman])
        ->and(S::SudahRekaman->next())->toBe([])
        ->and(S::TidakHadir->next())->toBe([])
        ->and(S::BelumRekaman->next())->toBe([]);
});

test('transisi baru sah dan atribut tambahan ikut diperbarui', function () {
    $actor = User::factory()->create();
    $schedule = makeSchedule();
    $entry = makeEntry(S::Terverifikasi, $schedule->school);

    $moved = app(StatusTransitioner::class)->move($entry, S::Terjadwal, $actor, null, ['schedule_id' => $schedule->id]);
    expect($moved->status)->toBe(S::Terjadwal)->and($moved->schedule_id)->toBe($schedule->id);

    $back = app(StatusTransitioner::class)->move($moved, S::Terverifikasi, $actor, null, ['schedule_id' => null]);
    expect($back->status)->toBe(S::Terverifikasi)->and($back->schedule_id)->toBeNull()
        ->and($back->histories()->count())->toBe(2);
});

test('transisi ilegal menuju dan dari penjadwalan ditolak', function (S $from, S $to) {
    $entry = makeEntry($from);

    expect(fn () => app(StatusTransitioner::class)->move($entry, $to, User::factory()->create()))->toThrow(AccessRuleException::class);
})->with([
    [S::Diajukan, S::Terjadwal], [S::PerluPerbaikan, S::Terjadwal], [S::SudahRekaman, S::Terjadwal],
    [S::TidakHadir, S::SudahRekaman], [S::Terverifikasi, S::SudahRekaman], [S::Terjadwal, S::Diajukan],
]);

test('batas hari jadwal memakai zona Asia/Jakarta', function () {
    // 20.00 UTC 8 Okt = 03.00 WIB 9 Okt.
    Carbon::setTestNow(Carbon::parse('2026-10-08 20:00:00', 'UTC'));

    expect(makeSchedule(date: '2026-10-08')->isPast())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-09')->isPast())->toBeFalse()
        ->and(makeSchedule(date: '2026-10-09')->isOpenForMarking())->toBeTrue()
        ->and(makeSchedule(date: '2026-10-10')->isOpenForMarking())->toBeFalse();
});

test('helper menautkan entri ke jadwal sekolah yang sama', function () {
    $entry = makeScheduledEntry();

    expect($entry->schedule->school_id)->toBe($entry->school_id)
        ->and($entry->schedule->entries()->count())->toBe(1);
});
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Schedules/ScheduleModelTest.php`
Expected: FAIL (class `Schedule` tidak ada).

- [ ] **Step 3: Migrasi**

`2026_10_08_300001_create_schedules_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->date('date');
            $table->string('location', 150);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
```

`2026_10_08_300002_add_schedule_id_to_registration_students_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            // Tetap terisi setelah penandaan (jejak jadwal yang diikuti); hanya "keluarkan dari jadwal" yang mengosongkan.
            $table->foreignId('schedule_id')->nullable()->after('decided_at')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
        });
    }
};
```

- [ ] **Step 4: Model, factory, enum, transitioner**

`app/Domains/Schedules/Models/Schedule.php`:

```php
<?php

namespace App\Domains\Schedules\Models;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schools\Models\School;
use App\Models\Concerns\ScopedBySchool;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property Carbon $date
 * @property string $location
 * @property int $created_by
 */
#[UseFactory(ScheduleFactory::class)]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory, ScopedBySchool;

    private const ZONE = 'Asia/Jakarta';

    protected $fillable = ['school_id', 'date', 'location', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /** Tanggal jadwal sudah terlewat (kemarin atau sebelumnya). */
    public function isPast(): bool
    {
        return $this->date->toDateString() < $this->today();
    }

    /** Hari kegiatan sudah tiba (hari ini atau sebelumnya): penandaan boleh dilakukan. */
    public function isOpenForMarking(): bool
    {
        return $this->date->toDateString() <= $this->today();
    }

    private function today(): string
    {
        return Carbon::now(self::ZONE)->toDateString();
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

`database/factories/ScheduleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Schedule> */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'date' => now()->addDays(7)->toDateString(),
            'location' => 'Aula '.fake()->words(2, true),
            'created_by' => User::factory(),
        ];
    }
}
```

`RegistrationStatus::next()` — ganti `match` menjadi:

```php
        return match ($this) {
            self::Draf => [self::Diajukan],
            self::Diajukan => [self::Terverifikasi, self::PerluPerbaikan, self::Ditolak],
            self::PerluPerbaikan => [self::Diajukan],
            self::Terverifikasi => [self::Terjadwal],
            self::Terjadwal => [self::Terverifikasi, self::SudahRekaman, self::TidakHadir, self::BelumRekaman],
            default => [],
        };
```

dan perbarui docblock di atasnya menjadi `Transisi yang sah dari status ini (peta state machine spec v1 §3.7, ditambah keluarkan dari jadwal).`

`RegistrationStudent`: tambahkan `'schedule_id'` ke `$fillable`, `@property int|null $schedule_id` ke docblock, dan relasi (impor `App\Domains\Schedules\Models\Schedule`):

```php
    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
```

`StatusTransitioner::move` — ubah tanda tangan dan update:

```php
    /**
     * Satu-satunya pintu perubahan status entri: memeriksa peta transisi di bawah kunci baris
     * (dua petugas tidak bisa sama-sama memutuskan status lama) dan menulis riwayat.
     * `$attributes` ikut diperbarui dalam transaksi yang sama (mis. `schedule_id`).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function move(RegistrationStudent $entry, RegistrationStatus $to, User $actor, ?string $note = null, array $attributes = []): RegistrationStudent
```

dan pada `$fresh->update([...])` tambahkan `...$attributes,` sebagai elemen terakhir.

- [ ] **Step 5: Jalankan, lulus**

Run: `php artisan test tests/Feature/Schedules tests/Feature/Registrations`
Expected: PASS (tes Fase 4 tetap hijau; `RegistrationModelTest` memeriksa `Ditolak->next()` dan tetap benar). Migrasi DB lokal: `php artisan migrate`.

- [ ] **Step 6: Commit**

```bash
git add app database tests
git commit -m "feat: tambah skema jadwal dan transisi penjadwalan serta penandaan" -m "Tabel schedules dan kolom registration_students.schedule_id. RegistrationStatus::next() memuat Terverifikasi -> Terjadwal dan Terjadwal -> Terverifikasi/Sudah rekaman/Tidak hadir/Belum rekaman. StatusTransitioner::move menerima atribut tambahan agar schedule_id berubah dalam transaksi yang sama dengan status dan riwayat. Model Schedule menghitung batas hari di zona Asia/Jakarta untuk jadwal lewat dan hari penandaan."
```

---

### Task 2: Repository, notifikasi, dan ScheduleService (buat dan ubah)

**Files:**
- Create: `app/Domains/Schedules/Repositories/Contracts/ScheduleRepositoryInterface.php`, `app/Domains/Schedules/Repositories/EloquentScheduleRepository.php`, `app/Domains/Schedules/Services/ScheduleService.php`, `app/Domains/Notifications/Notifications/ScheduleNotification.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Feature/Schedules/ScheduleServiceTest.php`

**Interfaces:**
- Consumes: `Schedule`, `NotificationService::toSchool`, `makeSchedule`, `makeScheduledEntry`.
- Produces: `ScheduleRepositoryInterface` (`paginate(int $perPage = 20)`, `upcoming(int $limit): Collection`, `create(array)`, `update(Schedule, array)`, `candidates(Schedule): Collection<RegistrationStudent>`, `lockEntries(array $ids): Collection<RegistrationStudent>`); `ScheduleService` (`paginate()`, `upcoming(int $limit = 3)`, `create(array $data, User $actor): Schedule`, `update(Schedule, array $data, User $actor): Schedule`, `candidates(Schedule)`); `ScheduleNotification::assigned(int $scheduleId, int $count, string $date, string $location)` dan `::changed(int $scheduleId, string $date, string $location)` (`$date` = `Y-m-d`).

- [ ] **Step 1: Tulis tes yang gagal** — `tests/Feature/Schedules/ScheduleServiceTest.php`:

```php
<?php

use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-jdw');
    $this->admin = makeUserWith(['jadwal.buat', 'jadwal.ubah'], roleName: 'admin-jdw');
    $this->service = app(ScheduleService::class);
});

test('staf membuat jadwal untuk sekolah pilihan dan tercatat di log', function () {
    $s = $this->service->create(['school_id' => $this->school->id, 'date' => '2026-12-01', 'location' => 'Aula SMPN 1'], $this->admin);

    expect($s->school_id)->toBe($this->school->id)
        ->and($s->created_by)->toBe($this->admin->id)
        ->and($s->date->toDateString())->toBe('2026-12-01')
        ->and(Activity::where('description', 'jadwal.dibuat')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('ubah tanggal atau lokasi memberi tahu operator sekolah bila sudah ada siswa', function (array $change) {
    $schedule = makeSchedule($this->school, '2026-12-01');
    makeScheduledEntry(schedule: $schedule);

    $this->service->update($schedule, $change + ['date' => '2026-12-01', 'location' => $schedule->location], $this->admin);

    expect(Activity::where('description', 'jadwal.diubah')->count())->toBe(1);
    Notification::assertSentToTimes($this->op, ScheduleNotification::class, 1);
})->with([[['date' => '2026-12-05']], [['location' => 'Lapangan Utama']]]);

test('ubah tanpa siswa atau tanpa perubahan nyata tidak memberi notifikasi', function () {
    $kosong = makeSchedule($this->school, '2026-12-01');
    $this->service->update($kosong, ['date' => '2026-12-02', 'location' => 'Baru'], $this->admin);

    $berisi = makeSchedule($this->school, '2026-12-01');
    makeScheduledEntry(schedule: $berisi);
    $this->service->update($berisi, ['date' => '2026-12-01', 'location' => $berisi->location], $this->admin);

    Notification::assertNothingSent();
    expect(Activity::where('description', 'jadwal.diubah')->count())->toBe(1);
});

test('isi notifikasi memuat tanggal dan lokasi', function () {
    $assigned = ScheduleNotification::assigned(7, 3, '2026-12-01', 'Aula SMPN 1');
    $changed = ScheduleNotification::changed(7, '2026-12-05', 'Lapangan');

    expect($assigned->title())->toBe('Jadwal ditetapkan')
        ->and($assigned->body())->toBe('3 siswa dijadwalkan pada 1 Desember 2026 di Aula SMPN 1.')
        ->and($assigned->url())->toBe('/jadwal/7')
        ->and($changed->title())->toBe('Jadwal diubah')
        ->and($changed->body())->toBe('Jadwal sekarang pada 5 Desember 2026 di Lapangan.');
});

test('jadwal akan datang hanya milik sekolah pengguna dan tanpa yang lewat', function () {
    $milik = makeSchedule($this->school, now('Asia/Jakarta')->addDays(2)->toDateString());
    makeSchedule($this->school, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeSchedule(null, now('Asia/Jakarta')->addDays(3)->toDateString());

    $this->actingAs($this->op);
    expect($this->service->upcoming()->pluck('id')->all())->toBe([$milik->id]);

    $this->actingAs($this->admin);
    expect($this->service->upcoming()->count())->toBe(2);
});
```

- [ ] **Step 2: Jalankan, gagal** — `php artisan test tests/Feature/Schedules/ScheduleServiceTest.php` → FAIL (class tidak ada).

- [ ] **Step 3: Implementasi**

`ScheduleNotification.php`:

```php
<?php

namespace App\Domains\Notifications\Notifications;

use Carbon\Carbon;

class ScheduleNotification extends BaseNotification
{
    private function __construct(
        private readonly int $scheduleId,
        private readonly string $title,
        private readonly string $body,
    ) {}

    public static function assigned(int $scheduleId, int $count, string $date, string $location): self
    {
        return new self($scheduleId, 'Jadwal ditetapkan', "{$count} siswa dijadwalkan pada ".self::format($date)." di {$location}.");
    }

    public static function changed(int $scheduleId, string $date, string $location): self
    {
        return new self($scheduleId, 'Jadwal diubah', 'Jadwal sekarang pada '.self::format($date)." di {$location}.");
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function url(): string
    {
        return "/jadwal/{$this->scheduleId}";
    }

    private static function format(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('j F Y');
    }
}
```

`Contracts/ScheduleRepositoryInterface.php`:

```php
<?php

namespace App\Domains\Schedules\Repositories\Contracts;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ScheduleRepositoryInterface
{
    /**
     * Filter dari query string: filter[search] (lokasi), filter[school_id], filter[period] = akan-datang|lewat.
     *
     * @return LengthAwarePaginator<int, Schedule>
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    /**
     * Jadwal hari ini atau setelahnya, terdekat dulu, lengkap dengan sekolah dan jumlah entri.
     *
     * @return Collection<int, Schedule>
     */
    public function upcoming(int $limit): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Schedule;

    /** @param array<string, mixed> $attributes */
    public function update(Schedule $schedule, array $attributes): Schedule;

    /**
     * Entri Terverifikasi sekolah jadwal yang belum punya jadwal, dengan siswa dan pendaftarannya.
     *
     * @return Collection<int, RegistrationStudent>
     */
    public function candidates(Schedule $schedule): Collection;

    /**
     * Mengunci baris entri (urut id) agar dua operasi bersamaan tidak sama-sama lolos.
     *
     * @param  list<int>  $ids
     * @return Collection<int, RegistrationStudent>
     */
    public function lockEntries(array $ids): Collection;
}
```

`EloquentScheduleRepository.php`:

```php
<?php

namespace App\Domains\Schedules\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EloquentScheduleRepository implements ScheduleRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $base = Schedule::query()->with(['school', 'entries:id,schedule_id,status'])->orderByDesc('date')->orderByDesc('id');

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $v) => $q->where('location', 'like', "%{$v}%")),
                AllowedFilter::exact('school_id'),
                AllowedFilter::callback('period', function (Builder $q, $v) {
                    match ($v) {
                        'akan-datang' => $q->where('date', '>=', $this->today()),
                        'lewat' => $q->where('date', '<', $this->today()),
                        default => null,
                    };
                }),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function upcoming(int $limit): Collection
    {
        return Schedule::query()->with('school')->withCount('entries')
            ->where('date', '>=', $this->today())->orderBy('date')->orderBy('id')->limit($limit)->get();
    }

    public function create(array $attributes): Schedule
    {
        return Schedule::create($attributes);
    }

    public function update(Schedule $schedule, array $attributes): Schedule
    {
        $schedule->update($attributes);

        return $schedule;
    }

    public function candidates(Schedule $schedule): Collection
    {
        return RegistrationStudent::query()->with(['student', 'registration'])
            ->where('school_id', $schedule->school_id)
            ->where('status', RegistrationStatus::Terverifikasi->value)
            ->whereNull('schedule_id')
            ->orderBy('id')->get();
    }

    public function lockEntries(array $ids): Collection
    {
        return RegistrationStudent::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    private function today(): string
    {
        return Carbon::now('Asia/Jakarta')->toDateString();
    }
}
```

`ScheduleService.php` (konstruktor hanya `ScheduleRepositoryInterface` dan `NotificationService`; ditambah transitioner di Task 3):

```php
<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly NotificationService $notifications,
    ) {}

    /** @return LengthAwarePaginator<int, Schedule> */
    public function paginate(): LengthAwarePaginator
    {
        return $this->schedules->paginate();
    }

    /** @return Collection<int, Schedule> */
    public function upcoming(int $limit = 3): Collection
    {
        return $this->schedules->upcoming($limit);
    }

    /** @return Collection<int, RegistrationStudent> */
    public function candidates(Schedule $schedule): Collection
    {
        return $this->schedules->candidates($schedule);
    }

    /** @param array{school_id?: ?int, date: string, location: string} $data */
    public function create(array $data, User $actor): Schedule
    {
        $schedule = $this->schedules->create([
            // Operator sekolah dikunci ke sekolahnya; staf kecamatan memilih sekolah.
            'school_id' => $actor->school_id ?? $data['school_id'],
            'date' => $data['date'],
            'location' => $data['location'],
            'created_by' => $actor->id,
        ]);
        activity('jadwal')->performedOn($schedule)->causedBy($actor)->log('jadwal.dibuat');

        return $schedule;
    }

    /** @param array{date: string, location: string} $data */
    public function update(Schedule $schedule, array $data, User $actor): Schedule
    {
        $this->schedules->update($schedule, ['date' => $data['date'], 'location' => $data['location']]);

        if ($schedule->wasChanged(['date', 'location'])) {
            activity('jadwal')->performedOn($schedule)->causedBy($actor)->log('jadwal.diubah');

            if ($schedule->entries()->exists()) {
                $this->notifications->toSchool(
                    'jadwal.lihat',
                    ScheduleNotification::changed($schedule->id, $schedule->date->toDateString(), $schedule->location),
                    $schedule->school_id,
                );
            }
        }

        return $schedule;
    }
}
```

Daftarkan `ScheduleRepositoryInterface::class => EloquentScheduleRepository::class` di `RepositoryServiceProvider::$bindings` (+ impor).

- [ ] **Step 4: Jalankan, lulus** — `php artisan test tests/Feature/Schedules` → PASS. Bila terjemahan bulan Indonesia gagal ("December"), pastikan `Carbon::parse(...)->locale('id')->translatedFormat(...)`; locale `id` bawaan Carbon.

- [ ] **Step 5: Commit**

```bash
git add app tests
git commit -m "feat: tambah repository jadwal, notifikasi jadwal, dan layanan buat/ubah" -m "ScheduleService membuat jadwal dan mengubah tanggal atau lokasi; perubahan nyata dicatat di log dan, bila jadwal sudah berisi siswa, operator sekolah diberi satu notifikasi. Repository menyediakan daftar berfilter, jadwal akan datang untuk Beranda, kandidat penempatan, dan penguncian baris entri. Teks notifikasi memuat tanggal dan lokasi dalam bahasa Indonesia."
```

---

### Task 3: Penempatan dan pengeluaran siswa

**Files:**
- Modify: `app/Domains/Schedules/Services/ScheduleService.php`
- Test: `tests/Feature/Schedules/ScheduleAssignmentTest.php`

**Interfaces:**
- Consumes: `StatusTransitioner::move(..., attributes:)`, `ScheduleRepositoryInterface::lockEntries`, `ScheduleNotification::assigned`.
- Produces: `ScheduleService::assign(Schedule $schedule, array $entryIds, User $actor): void` (`ValidationException` pada `entry_ids` untuk entri tidak sah; `AccessRuleException` bila jadwal sudah lewat) dan `ScheduleService::unassign(RegistrationStudent $entry, User $actor): void`.

- [ ] **Step 1: Tulis tes yang gagal** — `tests/Feature/Schedules/ScheduleAssignmentTest.php`:

```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-jdw');
    $this->admin = makeUserWith(['jadwal.buat', 'jadwal.ubah'], roleName: 'admin-jdw');
    $this->service = app(ScheduleService::class);
    $this->schedule = makeSchedule($this->school);
});

test('penempatan memindahkan entri ke Terjadwal dengan riwayat, log, dan satu notifikasi', function () {
    $a = makeEntry(S::Terverifikasi, $this->school);
    $b = makeEntry(S::Terverifikasi, $this->school);
    $c = makeEntry(S::Terverifikasi, $this->school);

    $this->service->assign($this->schedule, [$a->id, $b->id, $c->id], $this->admin);

    foreach ([$a, $b, $c] as $e) {
        expect($e->fresh()->status)->toBe(S::Terjadwal)->and($e->fresh()->schedule_id)->toBe($this->schedule->id)
            ->and($e->histories()->count())->toBe(1);
    }
    expect(Activity::where('description', 'jadwal.penempatan')->count())->toBe(1);
    Notification::assertSentToTimes($this->op, ScheduleNotification::class, 1);
});

test('satu entri tidak sah menggagalkan seluruh penempatan', function (string $kasus) {
    $ok = makeEntry(S::Terverifikasi, $this->school);
    // Dataset memakai nama kasus (bukan closure): Pest memanggil closure dataset tanpa argumen.
    $buruk = match ($kasus) {
        'bukan Terverifikasi' => makeEntry(S::Diajukan, $this->school)->id,
        'sekolah lain' => makeEntry(S::Terverifikasi)->id,
        'id tidak ada' => 999999,
    };

    expect(fn () => $this->service->assign($this->schedule, [$ok->id, $buruk], $this->admin))->toThrow(ValidationException::class);

    expect($ok->fresh()->status)->toBe(S::Terverifikasi)->and($ok->fresh()->schedule_id)->toBeNull();
    Notification::assertNothingSent();
})->with(['bukan Terverifikasi', 'sekolah lain', 'id tidak ada']);

test('jadwal yang sudah lewat tidak dapat ditempati', function () {
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());
    $e = makeEntry(S::Terverifikasi, $this->school);

    expect(fn () => $this->service->assign($lewat, [$e->id], $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->status)->toBe(S::Terverifikasi);
});

test('entri yang sudah dijadwalkan tidak dapat masuk jadwal kedua', function () {
    $e = makeEntry(S::Terverifikasi, $this->school);
    $kedua = makeSchedule($this->school);

    $this->service->assign($this->schedule, [$e->id], $this->admin);

    expect(fn () => $this->service->assign($kedua, [$e->id], $this->admin))->toThrow(ValidationException::class);
    expect($e->fresh()->schedule_id)->toBe($this->schedule->id);
});

test('keluarkan dari jadwal mengembalikan ke Terverifikasi dan dapat dijadwalkan ulang', function () {
    $e = makeEntry(S::Terverifikasi, $this->school);
    $lain = makeSchedule($this->school);
    $this->service->assign($this->schedule, [$e->id], $this->admin);

    $this->service->unassign($e->fresh(), $this->admin);
    expect($e->fresh()->status)->toBe(S::Terverifikasi)->and($e->fresh()->schedule_id)->toBeNull();

    $this->service->assign($lain, [$e->id], $this->admin);
    expect($e->fresh()->schedule_id)->toBe($lain->id);
});

test('hanya entri Terjadwal yang dapat dikeluarkan', function () {
    $final = makeScheduledEntry(S::SudahRekaman, $this->schedule);

    expect(fn () => $this->service->unassign($final, $this->admin))->toThrow(AccessRuleException::class);
    expect($final->fresh()->schedule_id)->toBe($this->schedule->id);
});
```

- [ ] **Step 2: Jalankan, gagal** — `php artisan test tests/Feature/Schedules/ScheduleAssignmentTest.php` → FAIL (`assign` tidak ada).

- [ ] **Step 3: Implementasi** — di `ScheduleService`: tambah `StatusTransitioner $transitions` ke konstruktor (setelah repository), impor `AccessRuleException`, `RegistrationStatus`, `StatusTransitioner`, `DB`, `ValidationException`, lalu tambahkan:

```php
    /** @param list<int> $entryIds */
    public function assign(Schedule $schedule, array $entryIds, User $actor): void
    {
        if ($schedule->isPast()) {
            throw new AccessRuleException('Jadwal sudah lewat; siswa tidak dapat ditambahkan.');
        }

        $count = DB::transaction(function () use ($schedule, $entryIds, $actor) {
            $entries = $this->schedules->lockEntries($entryIds);
            $invalid = $entries->count() !== count(array_unique($entryIds))
                || $entries->contains(fn (RegistrationStudent $e) => $e->school_id !== $schedule->school_id || $e->status !== RegistrationStatus::Terverifikasi);
            if ($invalid) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian siswa tidak dapat dijadwalkan; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, RegistrationStatus::Terjadwal, $actor, null, ['schedule_id' => $schedule->id]);
            }
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['ditambahkan' => $entries->count()])->log('jadwal.penempatan');

            return $entries->count();
        });

        $this->notifications->toSchool(
            'jadwal.lihat',
            ScheduleNotification::assigned($schedule->id, $count, $schedule->date->toDateString(), $schedule->location),
            $schedule->school_id,
        );
    }

    public function unassign(RegistrationStudent $entry, User $actor): void
    {
        DB::transaction(function () use ($entry, $actor) {
            // Dibaca sebelum move() mengosongkan schedule_id.
            $schedule = $entry->schedule;
            $this->transitions->move($entry, RegistrationStatus::Terverifikasi, $actor, null, ['schedule_id' => null]);
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['dikeluarkan' => 1])->log('jadwal.penempatan');
        });
    }
```

- [ ] **Step 4: Jalankan, lulus** — `php artisan test tests/Feature/Schedules` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app tests
git commit -m "feat: tambah penempatan dan pengeluaran siswa pada jadwal" -m "assign memindahkan entri Terverifikasi sekolah itu ke Terjadwal secara atomik di bawah kunci baris, menolak jadwal yang sudah lewat dan entri yang bukan Terverifikasi atau milik sekolah lain, mencatat log, dan memberi satu notifikasi ke operator sekolah. unassign mengembalikan entri Terjadwal ke Terverifikasi sehingga salah penempatan dapat dibatalkan."
```

---

### Task 4: RecordingService (penandaan massal) dan pendaftaran ulang

**Files:**
- Create: `app/Domains/Schedules/Services/RecordingService.php`
- Test: `tests/Feature/Schedules/RecordingServiceTest.php`

**Interfaces:**
- Consumes: `StatusTransitioner`, `ScheduleRepositoryInterface::lockEntries`, `Schedule::isOpenForMarking`, `RegistrationService` (untuk tes pendaftaran ulang).
- Produces: `RecordingService::mark(Schedule $schedule, array $entryIds, RegistrationStatus $to, ?string $note, User $actor): void`.

- [ ] **Step 1: Tulis tes yang gagal** — `tests/Feature/Schedules/RecordingServiceTest.php`:

```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Registrations\Services\RegistrationService;
use App\Domains\Schedules\Services\RecordingService;
use App\Domains\Schools\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['rekaman.tandai'], roleName: 'admin-rek');
    $this->service = app(RecordingService::class);
    $this->hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
});

test('penandaan massal mengubah status, mencatat riwayat, catatan, dan satu log', function (S $status) {
    $a = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $b = makeScheduledEntry(S::Terjadwal, $this->hariIni);

    $this->service->mark($this->hariIni, [$a->id, $b->id], $status, 'catatan bersama', $this->admin);

    foreach ([$a, $b] as $e) {
        expect($e->fresh()->status)->toBe($status)->and($e->fresh()->note)->toBe('catatan bersama')
            ->and($e->fresh()->schedule_id)->toBe($this->hariIni->id)->and($e->histories()->count())->toBe(1);
    }
    expect(Activity::where('description', 'rekaman.ditandai')->count())->toBe(1);
    Notification::assertNothingSent();
})->with([[S::SudahRekaman], [S::TidakHadir], [S::BelumRekaman]]);

test('penandaan sebelum hari kegiatan ditolak', function () {
    $besok = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
    $e = makeScheduledEntry(S::Terjadwal, $besok);

    expect(fn () => $this->service->mark($besok, [$e->id], S::SudahRekaman, null, $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->status)->toBe(S::Terjadwal);
});

test('pukul 03.00 WIB sudah dihitung hari kegiatan walau tanggal UTC masih kemarin', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 20:00:00', 'UTC'));
    $jadwal = makeSchedule($this->school, '2026-10-09');
    $e = makeScheduledEntry(S::Terjadwal, $jadwal);

    $this->service->mark($jadwal, [$e->id], S::SudahRekaman, null, $this->admin);

    expect($e->fresh()->status)->toBe(S::SudahRekaman);
});

test('satu entri tidak sah menggagalkan seluruh penandaan', function (string $kasus) {
    $ok = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $buruk = match ($kasus) {
        'sudah berstatus akhir' => makeScheduledEntry(S::SudahRekaman, $this->hariIni),
        'milik jadwal lain' => makeScheduledEntry(S::Terjadwal, makeSchedule($this->school, now('Asia/Jakarta')->toDateString())),
    };

    expect(fn () => $this->service->mark($this->hariIni, [$ok->id, $buruk->id], S::SudahRekaman, null, $this->admin))
        ->toThrow(ValidationException::class);

    expect($ok->fresh()->status)->toBe(S::Terjadwal)->and($ok->histories()->count())->toBe(0);
})->with(['sudah berstatus akhir', 'milik jadwal lain']);

test('status penandaan harus salah satu dari tiga status akhir', function () {
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);

    expect(fn () => $this->service->mark($this->hariIni, [$e->id], S::Diajukan, null, $this->admin))->toThrow(AccessRuleException::class);
});

test('Tidak hadir dapat didaftarkan lagi, Sudah rekaman tidak; kunci data ikut terbuka', function () {
    makeUserWith(['verifikasi.putuskan'], roleName: 'petugas-rek');
    $op = makeUserWith(['pendaftaran.buat', 'pendaftaran.ajukan'], $this->school, true, 'op-rek');
    $absen = eligibleStudent($this->school);
    $hadir = eligibleStudent($this->school);
    $e1 = makeEntry(S::Terjadwal, $this->school, $absen);
    $e2 = makeEntry(S::Terjadwal, $this->school, $hadir);
    $e1->update(['schedule_id' => $this->hariIni->id]);
    $e2->update(['schedule_id' => $this->hariIni->id]);
    expect($absen->isLocked())->toBeTrue();

    $this->service->mark($this->hariIni, [$e1->id], S::TidakHadir, null, $this->admin);
    $this->service->mark($this->hariIni, [$e2->id], S::SudahRekaman, null, $this->admin);
    expect($absen->isLocked())->toBeFalse();

    $registrations = app(RegistrationService::class);
    $baru = $registrations->createDraft(['title' => 'Gelombang 2', 'student_ids' => [$absen->id]], $op);
    $registrations->submit($baru, $op);
    expect($baru->entries()->first()->status)->toBe(S::Diajukan);

    $ditolak = $registrations->createDraft(['title' => 'Gelombang 3', 'student_ids' => [$hadir->id]], $op);
    expect(fn () => $registrations->submit($ditolak, $op))->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Jalankan, gagal** — `php artisan test tests/Feature/Schedules/RecordingServiceTest.php` → FAIL (class tidak ada).

- [ ] **Step 3: Implementasi** — `RecordingService.php`:

```php
<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordingService
{
    /** Status kehadiran yang boleh ditandai; ketiganya final. */
    private const MARKS = [RegistrationStatus::SudahRekaman, RegistrationStatus::TidakHadir, RegistrationStatus::BelumRekaman];

    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly StatusTransitioner $transitions,
    ) {}

    /**
     * Menandai siswa Terjadwal pada jadwal ini; seluruhnya berhasil atau seluruhnya gagal.
     *
     * @param  list<int>  $entryIds
     */
    public function mark(Schedule $schedule, array $entryIds, RegistrationStatus $to, ?string $note, User $actor): void
    {
        if (! in_array($to, self::MARKS, true)) {
            throw new AccessRuleException('Status penandaan tidak valid.');
        }
        if (! $schedule->isOpenForMarking()) {
            throw new AccessRuleException('Penandaan baru dapat dilakukan pada atau setelah tanggal jadwal.');
        }

        DB::transaction(function () use ($schedule, $entryIds, $to, $note, $actor) {
            $entries = $this->schedules->lockEntries($entryIds);
            $invalid = $entries->count() !== count(array_unique($entryIds))
                || $entries->contains(fn (RegistrationStudent $e) => $e->schedule_id !== $schedule->id || $e->status !== RegistrationStatus::Terjadwal);
            if ($invalid) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian siswa tidak dapat ditandai; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, $to, $actor, $note);
            }
            activity('jadwal')->performedOn($schedule)->causedBy($actor)
                ->withProperties(['status' => $to->value, 'jumlah' => $entries->count()])->log('rekaman.ditandai');
        });
    }
}
```

- [ ] **Step 4: Jalankan, lulus** — `php artisan test tests/Feature/Schedules tests/Feature/Registrations` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app tests
git commit -m "feat: tambah penandaan rekaman massal" -m "RecordingService menandai siswa Terjadwal sebagai Sudah rekaman, Tidak hadir, atau Belum rekaman secara atomik: ditolak sebelum hari kegiatan (zona Asia/Jakarta), dan seluruhnya gagal bila ada entri yang sudah berstatus akhir atau milik jadwal lain. Tes mengunci bahwa siswa Tidak hadir dapat didaftarkan lagi dan terbuka kunci datanya, sedangkan Sudah rekaman tetap tidak dapat."
```

---

### Task 5: HTTP — request, controller, rute, menu, label log, Beranda, matriks izin

**Files:**
- Create: `app/Domains/Schedules/Http/Requests/{ScheduleRequest,AssignRequest,RecordingRequest}.php`, `app/Domains/Schedules/Http/Controllers/{ScheduleController,ScheduleStudentController,RecordingController}.php`, `app/Http/Controllers/DashboardController.php`, `routes/schedules.php`
- Modify: `routes/web.php`, `app/Domains/Access/Services/MenuService.php`, `lang/id/activity.php`, `tests/Feature/Access/{MenuTest,RoutePermissionMatrixTest}.php`
- Test: `tests/Feature/Schedules/{ScheduleHttpTest,RecordingHttpTest,DashboardTest}.php`

**Interfaces:**
- Consumes: semua service Task 2–4.
- Produces: rute bernama `jadwal.{index,create,store,show,edit,update}`, `jadwal.siswa.{store,destroy}`, `jadwal.rekaman.{show,store}`; prop halaman:
  - `Jadwal/Index`: `schedules` (paginator `{id,date,location,school,is_past,participants_count,counts}`), `filters`, `schools`.
  - `Jadwal/Form`: `schedule` (`{id,date,location,has_participants}` | null), `schools`.
  - `Jadwal/Show`: `schedule` (`{id,date,location,school,created_by,is_past}`), `participants` (`[{id,student:{id,name,nisn},status}]`), `candidates` (`[{id,name,nisn,hint}]`, hanya untuk pemegang `jadwal.ubah` dan jadwal belum lewat, selain itu `[]`).
  - `Jadwal/Rekaman`: `schedule` (seperti Show), `entries` (`[{id,name,nisn,hint}]` untuk entri `Terjadwal`), `marked` (`[{id,student,status}]` status akhir), `can_mark` (bool: hari kegiatan sudah tiba).
  - `Dashboard`: `schedules` (`[{date,school,location,participants}]`, maks 3) hanya untuk pemegang `jadwal.lihat`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Schedules/ScheduleHttpTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['jadwal.lihat', 'jadwal.buat', 'jadwal.ubah', 'rekaman.lihat'], roleName: 'admin-http');
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-http-jdw');
    $this->besok = now('Asia/Jakarta')->addDay()->toDateString();
});

test('admin membuat jadwal lalu melihat detailnya', function () {
    $this->actingAs($this->admin)
        ->post('/jadwal', ['school_id' => $this->school->id, 'date' => $this->besok, 'location' => 'Aula SMPN 1'])
        ->assertRedirect();

    $s = Schedule::firstOrFail();
    $this->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Jadwal/Show')
        ->where('schedule.location', 'Aula SMPN 1')->where('schedule.is_past', false)
        ->has('participants', 0)->has('candidates', 0));
});

test('validasi: lokasi wajib, sekolah wajib untuk staf, tanggal tidak boleh lewat', function () {
    $this->actingAs($this->admin);

    $this->post('/jadwal', ['date' => $this->besok, 'location' => ''])->assertSessionHasErrors(['location', 'school_id']);
    $this->post('/jadwal', ['school_id' => $this->school->id, 'date' => now('Asia/Jakarta')->subDay()->toDateString(), 'location' => 'X'])
        ->assertSessionHasErrors('date');
    expect(Schedule::count())->toBe(0);
});

test('ubah: tanggal lampau yang tidak berubah boleh, memindahkan ke masa lalu tidak', function () {
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDays(3)->toDateString());
    $this->actingAs($this->admin);

    $this->put("/jadwal/{$lewat->id}", ['date' => $lewat->date->toDateString(), 'location' => 'Lokasi baru'])
        ->assertRedirect("/jadwal/{$lewat->id}");
    expect($lewat->fresh()->location)->toBe('Lokasi baru');

    $depan = makeSchedule($this->school, $this->besok);
    $this->put("/jadwal/{$depan->id}", ['date' => now('Asia/Jakarta')->subDay()->toDateString(), 'location' => 'X'])->assertSessionHasErrors('date');
});

test('kandidat hanya untuk pemegang jadwal.ubah dan operator tidak melihatnya', function () {
    $s = makeSchedule($this->school, $this->besok);
    makeEntry(S::Terverifikasi, $this->school);
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->admin)->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p
        ->has('candidates', 1)->where('candidates.0.hint', fn ($h) => is_string($h)));
    $this->actingAs($this->op)->get("/jadwal/{$s->id}")->assertInertia(fn (Assert $p) => $p->has('candidates', 0));
});

test('masukkan dan keluarkan siswa lewat HTTP, entri milik jadwal lain 404', function () {
    $s = makeSchedule($this->school, $this->besok);
    $e = makeEntry(S::Terverifikasi, $this->school);
    $lain = makeScheduledEntry(S::Terjadwal, makeSchedule($this->school, $this->besok));
    $this->actingAs($this->admin);

    $this->post("/jadwal/{$s->id}/siswa", ['entry_ids' => []])->assertSessionHasErrors('entry_ids');
    $this->post("/jadwal/{$s->id}/siswa", ['entry_ids' => [$e->id]])->assertRedirect("/jadwal/{$s->id}")->assertSessionHas('success');
    expect($e->fresh()->status)->toBe(S::Terjadwal);

    $this->delete("/jadwal/{$s->id}/siswa/{$lain->id}")->assertNotFound();
    $this->delete("/jadwal/{$s->id}/siswa/{$e->id}")->assertSessionHas('success');
    expect($e->fresh()->status)->toBe(S::Terverifikasi);
});

test('penempatan gagal menghasilkan galat entry_ids', function () {
    $s = makeSchedule($this->school, $this->besok);
    $diajukan = makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->admin)->post("/jadwal/{$s->id}/siswa", ['entry_ids' => [$diajukan->id]])->assertSessionHasErrors('entry_ids');
});

test('operator hanya melihat jadwal sekolahnya; sekolah lain 404; tanpa izin ubah ditolak', function () {
    $milik = makeSchedule($this->school, $this->besok);
    $asing = makeSchedule(null, $this->besok);
    $this->actingAs($this->op);

    $this->get('/jadwal')->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.id', $milik->id));
    $this->get("/jadwal/{$asing->id}")->assertNotFound();
    $this->get("/jadwal/{$milik->id}/ubah")->assertForbidden();
    $this->post('/jadwal', ['date' => $this->besok, 'location' => 'X'])->assertForbidden();
});

test('daftar menyaring periode dan memuat ringkasan status', function () {
    $depan = makeSchedule($this->school, $this->besok);
    makeSchedule($this->school, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeScheduledEntry(S::Terjadwal, $depan);
    $this->actingAs($this->admin);

    $this->get('/jadwal?filter[period]=akan-datang')->assertInertia(fn (Assert $p) => $p
        ->has('schedules.data', 1)->where('schedules.data.0.participants_count', 1)->where('schedules.data.0.counts.terjadwal', 1));
    $this->get('/jadwal?filter[period]=lewat')->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.is_past', true));
});
```

`tests/Feature/Schedules/RecordingHttpTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = makeUserWith(['jadwal.lihat', 'rekaman.lihat', 'rekaman.tandai'], roleName: 'admin-rek-http');
    $this->hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
});

test('layar penandaan memuat peserta Terjadwal dan yang sudah ditandai', function () {
    makeScheduledEntry(S::Terjadwal, $this->hariIni);
    makeScheduledEntry(S::SudahRekaman, $this->hariIni);

    $this->actingAs($this->admin)->get("/jadwal/{$this->hariIni->id}/rekaman")->assertInertia(fn (Assert $p) => $p
        ->component('Jadwal/Rekaman')->where('can_mark', true)
        ->has('entries', 1)->has('marked', 1)->where('marked.0.status', 'sudah_rekaman'));
});

test('penandaan massal lewat HTTP dan validasi', function () {
    $a = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $this->actingAs($this->admin);
    $url = "/jadwal/{$this->hariIni->id}/rekaman";

    $this->post($url, ['entry_ids' => [$a->id]])->assertSessionHasErrors('status');
    $this->post($url, ['status' => 'diajukan', 'entry_ids' => [$a->id]])->assertSessionHasErrors('status');
    $this->post($url, ['status' => 'tidak_hadir', 'entry_ids' => []])->assertSessionHasErrors('entry_ids');
    expect($a->fresh()->status)->toBe(S::Terjadwal);

    $this->post($url, ['status' => 'tidak_hadir', 'entry_ids' => [$a->id], 'note' => 'Sakit'])->assertRedirect($url)->assertSessionHas('success');
    expect($a->fresh()->status)->toBe(S::TidakHadir)->and($a->fresh()->note)->toBe('Sakit');
});

test('sebelum hari kegiatan: halaman menandai can_mark salah dan POST menghasilkan galat', function () {
    $besok = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
    $e = makeScheduledEntry(S::Terjadwal, $besok);
    $this->actingAs($this->admin);

    $this->get("/jadwal/{$besok->id}/rekaman")->assertInertia(fn (Assert $p) => $p->where('can_mark', false));
    $this->post("/jadwal/{$besok->id}/rekaman", ['status' => 'sudah_rekaman', 'entry_ids' => [$e->id]])->assertSessionHas('error');
    expect($e->fresh()->status)->toBe(S::Terjadwal);
});

test('operator tanpa izin rekaman ditolak', function () {
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-rek-http');

    $this->actingAs($op)->get("/jadwal/{$this->hariIni->id}/rekaman")->assertForbidden();
});
```

`tests/Feature/Schedules/DashboardTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

test('Beranda memuat jadwal terdekat milik sekolah pengguna tanpa yang lewat', function () {
    $sekolah = School::factory()->create();
    $depan = makeSchedule($sekolah, now('Asia/Jakarta')->addDays(2)->toDateString());
    makeScheduledEntry(S::Terjadwal, $depan);
    makeSchedule($sekolah, now('Asia/Jakarta')->subDays(2)->toDateString());
    makeSchedule(null, now('Asia/Jakarta')->addDay()->toDateString());
    $op = makeUserWith(['jadwal.lihat'], $sekolah, true, 'op-beranda');

    $this->actingAs($op)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->component('Dashboard')->has('schedules', 1)
        ->where('schedules.0.participants', 1)->where('schedules.0.location', $depan->location));
});

test('Beranda tanpa izin jadwal tidak memuat jadwal; staf melihat semua sekolah', function () {
    makeSchedule(null, now('Asia/Jakarta')->addDay()->toDateString());
    makeSchedule(null, now('Asia/Jakarta')->addDays(2)->toDateString());

    $this->actingAs(makeUserWith([], roleName: 'tanpa-jdw'))->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('schedules', 0));
    $this->actingAs(makeUserWith(['jadwal.lihat'], roleName: 'staf-jdw'))->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('schedules', 2));
});
```

`RoutePermissionMatrixTest.php`: tambahkan baris dataset

```php
    ['GET', '/jadwal', 'jadwal.lihat'],
    ['GET', '/jadwal/buat', 'jadwal.buat'],
    ['POST', '/jadwal', 'jadwal.buat'],
    ['GET', '/jadwal/{schedule}', 'jadwal.lihat'],
    ['GET', '/jadwal/{schedule}/ubah', 'jadwal.ubah'],
    ['PUT', '/jadwal/{schedule}', 'jadwal.ubah'],
    ['POST', '/jadwal/{schedule}/siswa', 'jadwal.ubah'],
    ['DELETE', '/jadwal/{schedule}/siswa/{slot}', 'jadwal.ubah'],
    ['GET', '/jadwal/{schedule}/rekaman', 'rekaman.lihat'],
    ['POST', '/jadwal/{schedule}/rekaman', 'rekaman.tandai'],
```

dan ganti `resolveUri` agar membuat entri terjadwal yang konsisten dengan jadwalnya (binding bertingkat dievaluasi sebelum middleware `can`, jadi entri harus milik jadwal itu):

```php
function resolveUri(string $uri): string
{
    $target = User::factory()->create();
    $school = School::factory()->create();
    $role = Role::create(['name' => 'sasaran-'.uniqid()]);
    $student = Student::factory()->create();
    $entry = makeEntry();
    $slot = makeScheduledEntry();

    return str_replace(
        ['{user}', '{school}', '{role}', '{student}', '{registration}', '{entry}', '{schedule}', '{slot}'],
        [$target->id, $school->id, $role->id, $student->id, $entry->registration_id, $entry->id, $slot->schedule_id, $slot->id],
        $uri,
    );
}
```

`MenuTest.php`: ubah ekspektasi grup operator menjadi `['Utama', 'Data Siswa', 'Jadwal']` dan admin menjadi `['Utama', 'Manajemen Akses', 'Master Data', 'Data Siswa', 'Verifikasi', 'Jadwal', 'Sistem']`; tambah tes:

```php
test('menu Jadwal tampil untuk pemegang jadwal.lihat', function () {
    $this->seed(AccessSeeder::class);
    $operator = app(MenuService::class)->forUser(User::factory()->create()->assignRole('operator-sekolah'));

    expect(collect($operator)->firstWhere('group', 'Jadwal')['items'][0])->toBe(['label' => 'Jadwal', 'href' => '/jadwal', 'icon' => 'calendar-days']);
});
```

- [ ] **Step 2: Jalankan, gagal** — `php artisan test tests/Feature/Schedules tests/Feature/Access/MenuTest.php` → FAIL (rute 404/controller tidak ada).

- [ ] **Step 3: Implementasi — request**

`ScheduleRequest.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Requests;

use App\Domains\Schedules\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;

/** Satu kelas untuk buat dan ubah; `$this->route('schedule')` kosong saat buat. */
class ScheduleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schedule = $this->route('schedule');
        // Tanggal lampau yang tidak diubah boleh tetap (mis. hanya mengganti lokasi); tanggal baru tidak boleh di masa lalu.
        $dateChanged = ! $schedule instanceof Schedule || $this->input('date') !== $schedule->date->toDateString();

        return [
            // Operator sekolah dikunci ke sekolahnya; hanya staf memilih sekolah, dan hanya saat buat.
            'school_id' => $this->user()->school_id === null && $this->isMethod('post')
                ? ['required', 'integer', 'exists:schools,id']
                : ['nullable'],
            'date' => ['required', 'date_format:Y-m-d', ...($dateChanged ? ['after_or_equal:'.now('Asia/Jakarta')->toDateString()] : [])],
            'location' => ['required', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'school_id.required' => 'Pilih sekolah.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Format tanggal tidak valid.',
            'date.after_or_equal' => 'Tanggal tidak boleh di masa lalu.',
            'location.required' => 'Lokasi wajib diisi.',
        ];
    }
}
```

`AssignRequest.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['entry_ids.required' => 'Pilih minimal satu siswa.', 'entry_ids.min' => 'Pilih minimal satu siswa.'];
    }
}
```

`RecordingRequest.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['sudah_rekaman', 'tidak_hadir', 'belum_rekaman'])],
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer', 'distinct'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status penandaan.',
            'status.in' => 'Pilih status penandaan.',
            'entry_ids.required' => 'Pilih minimal satu siswa.',
            'entry_ids.min' => 'Pilih minimal satu siswa.',
        ];
    }
}
```

- [ ] **Step 4: Implementasi — controller**

`ScheduleController.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Http\Requests\ScheduleRequest;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Services\SchoolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $schedules,
        private readonly SchoolService $schools,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Jadwal/Index', [
            'schedules' => $this->schedules->paginate()->through(fn (Schedule $s) => [
                'id' => $s->id,
                'date' => $s->date->toDateString(),
                'location' => $s->location,
                'school' => $s->school?->name,
                'is_past' => $s->isPast(),
                'participants_count' => $s->entries->count(),
                'counts' => $s->entries->countBy(fn (RegistrationStudent $e) => $e->status->value)->all(),
            ]),
            'filters' => $request->query('filter', []),
            'schools' => $this->schools->optionsFor($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Jadwal/Form', ['schedule' => null, 'schools' => $this->schools->optionsFor($request->user())]);
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $schedule = $this->schedules->create($request->validated(), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Jadwal dibuat.');
    }

    public function show(Request $request, Schedule $schedule): Response
    {
        $schedule->load(['school', 'creator', 'entries.student']);
        $canAssign = $request->user()->can('jadwal.ubah') && ! $schedule->isPast();

        return Inertia::render('Jadwal/Show', [
            'schedule' => self::detail($schedule) + ['created_by' => $schedule->creator?->name],
            'participants' => $schedule->entries->map(fn (RegistrationStudent $e) => self::participant($e))->values(),
            'candidates' => $canAssign
                ? $this->schedules->candidates($schedule)->map(fn (RegistrationStudent $e) => self::checklistItem($e))->values()
                : [],
        ]);
    }

    public function edit(Schedule $schedule): Response
    {
        return Inertia::render('Jadwal/Form', [
            'schedule' => [
                'id' => $schedule->id,
                'date' => $schedule->date->toDateString(),
                'location' => $schedule->location,
                'has_participants' => $schedule->entries()->exists(),
            ],
            'schools' => null,
        ]);
    }

    public function update(ScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->schedules->update($schedule, $request->validated(), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Jadwal diperbarui.');
    }

    /** @return array<string, mixed> */
    public static function detail(Schedule $s): array
    {
        return [
            'id' => $s->id,
            'date' => $s->date->toDateString(),
            'location' => $s->location,
            'school' => $s->school?->name,
            'is_past' => $s->isPast(),
        ];
    }

    /** @return array<string, mixed> */
    public static function participant(RegistrationStudent $e): array
    {
        return [
            'id' => $e->id,
            'student' => ['id' => $e->student->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn],
            'status' => $e->status->value,
        ];
    }

    /** @return array{id: int, name: string, nisn: string, hint: ?string} */
    public static function checklistItem(RegistrationStudent $e): array
    {
        return ['id' => $e->id, 'name' => $e->student->name, 'nisn' => $e->student->nisn, 'hint' => $e->registration?->title];
    }
}
```

`ScheduleStudentController.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Http\Requests\AssignRequest;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleStudentController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function store(AssignRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->schedules->assign($schedule, $request->validated('entry_ids'), $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Siswa dijadwalkan.');
    }

    public function destroy(Request $request, Schedule $schedule, RegistrationStudent $entry): RedirectResponse
    {
        $this->schedules->unassign($entry, $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Siswa dikeluarkan dari jadwal.');
    }
}
```

`RecordingController.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Controllers;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Http\Requests\RecordingRequest;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\RecordingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordingController extends Controller
{
    public function __construct(private readonly RecordingService $recording) {}

    public function show(Schedule $schedule): Response
    {
        $schedule->load(['school', 'entries.student', 'entries.registration']);
        $entries = $schedule->entries;

        return Inertia::render('Jadwal/Rekaman', [
            'schedule' => ScheduleController::detail($schedule),
            'entries' => $entries->where('status', RegistrationStatus::Terjadwal)
                ->map(fn (RegistrationStudent $e) => ScheduleController::checklistItem($e))->values(),
            'marked' => $entries->filter(fn (RegistrationStudent $e) => $e->status !== RegistrationStatus::Terjadwal && $e->status !== RegistrationStatus::Terverifikasi)
                ->map(fn (RegistrationStudent $e) => ScheduleController::participant($e))->values(),
            'can_mark' => $schedule->isOpenForMarking(),
        ]);
    }

    public function store(RecordingRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->recording->mark(
            $schedule,
            $request->validated('entry_ids'),
            RegistrationStatus::from($request->validated('status')),
            $request->validated('note'),
            $request->user(),
        );

        return redirect()->route('jadwal.rekaman.show', $schedule)->with('success', 'Penandaan disimpan.');
    }
}
```

`app/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ScheduleService $schedules): Response
    {
        return Inertia::render('Dashboard', [
            'schedules' => $request->user()->can('jadwal.lihat')
                ? $schedules->upcoming(3)->map(fn (Schedule $s) => [
                    'date' => $s->date->toDateString(),
                    'school' => $s->school?->name,
                    'location' => $s->location,
                    'participants' => $s->entries_count,
                ])->values()
                : [],
        ]);
    }
}
```

- [ ] **Step 5: Implementasi — rute, menu, label log**

`routes/schedules.php`:

```php
<?php

use App\Domains\Schedules\Http\Controllers\RecordingController;
use App\Domains\Schedules\Http\Controllers\ScheduleController;
use App\Domains\Schedules\Http\Controllers\ScheduleStudentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('jadwal', [ScheduleController::class, 'index'])->middleware('can:jadwal.lihat')->name('jadwal.index');
    Route::get('jadwal/buat', [ScheduleController::class, 'create'])->middleware('can:jadwal.buat')->name('jadwal.create');
    Route::post('jadwal', [ScheduleController::class, 'store'])->middleware(['can:jadwal.buat', 'throttle:60,1'])->name('jadwal.store');
    Route::get('jadwal/{schedule}', [ScheduleController::class, 'show'])->middleware('can:jadwal.lihat')->name('jadwal.show');
    Route::get('jadwal/{schedule}/ubah', [ScheduleController::class, 'edit'])->middleware('can:jadwal.ubah')->name('jadwal.edit');
    Route::put('jadwal/{schedule}', [ScheduleController::class, 'update'])->middleware('can:jadwal.ubah')->name('jadwal.update');

    Route::post('jadwal/{schedule}/siswa', [ScheduleStudentController::class, 'store'])->middleware(['can:jadwal.ubah', 'throttle:60,1'])->name('jadwal.siswa.store');
    // scopeBindings: entri harus milik jadwal pada URL, selain itu 404.
    Route::delete('jadwal/{schedule}/siswa/{entry}', [ScheduleStudentController::class, 'destroy'])
        ->middleware(['can:jadwal.ubah', 'throttle:60,1'])->scopeBindings()->name('jadwal.siswa.destroy');

    Route::get('jadwal/{schedule}/rekaman', [RecordingController::class, 'show'])->middleware('can:rekaman.lihat')->name('jadwal.rekaman.show');
    Route::post('jadwal/{schedule}/rekaman', [RecordingController::class, 'store'])->middleware(['can:rekaman.tandai', 'throttle:60,1'])->name('jadwal.rekaman.store');
});
```

`routes/web.php`: tambah `require __DIR__.'/schedules.php';` setelah `registrations.php`, dan ganti rute dashboard menjadi `Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)->middleware(['auth'])->name('dashboard');` (impor `DashboardController`; hapus impor `Inertia` bila tak terpakai).

`MenuService::ITEMS` — sebelum item `Sistem`: `['group' => 'Jadwal', 'label' => 'Jadwal', 'href' => '/jadwal', 'icon' => 'calendar-days', 'permission' => 'jadwal.lihat'],`.

`lang/id/activity.php`: `types` tambah `'jadwal' => 'Jadwal'`; `subjects` tambah `'Schedule' => 'Jadwal'`; `actions` tambah `'jadwal.dibuat' => 'Jadwal dibuat'`, `'jadwal.diubah' => 'Jadwal diubah'`, `'jadwal.penempatan' => 'Penempatan siswa di jadwal'`, `'rekaman.ditandai' => 'Rekaman ditandai'`.

- [ ] **Step 6: Jalankan seluruh suite backend (background)**

Run (background): `php artisan test` lalu baca ekor outputnya. Expected: PASS semua. Perbarui ekspektasi lama hanya bila perubahan memang disengaja (menu, daftar tipe log).

- [ ] **Step 7: Pint dan Larastan**

Run: `vendor/bin/pint` lalu `composer analyse` → bersih. Perbaiki temuan dengan docblock/closure (jangan `@phpstan-ignore`).

- [ ] **Step 8: Commit**

```bash
git add app routes lang tests
git commit -m "feat: tambah halaman jadwal, penempatan, penandaan rekaman, dan jadwal di Beranda" -m "ScheduleController, ScheduleStudentController, dan RecordingController dengan FormRequest, rute berizin jadwal.* dan rekaman.* (scoped binding agar entri milik jadwal lain 404), menu Jadwal, label log jadwal, dan DashboardController yang menyuplai jadwal terdekat sesuai sekolah pengguna. Validasi tanggal memakai zona Asia/Jakarta dan membolehkan tanggal lampau yang tidak berubah saat hanya mengganti lokasi. Matriks izin diperluas untuk semua rute baru."
```

---

## FRONTEND

### Task 6: Panduan UI, tipe, ikon menu, EntryChecklist

**Files:**
- Create: `resources/js/types/schedule.ts`, `resources/js/Components/shared/EntryChecklist.vue`, `resources/js/Components/shared/EntryChecklist.test.ts`
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`, `resources/js/config/menuIcons.ts`, `resources/js/config/menuIcons.test.ts`

**Interfaces:**
- Produces: tipe `ScheduleRow, ScheduleDetail, ParticipantRow, ChecklistItem`; `<EntryChecklist v-model="number[]" :items="ChecklistItem[]" :empty-text="string" :disabled="boolean" />`; ikon menu `calendar-days`.

- [ ] **Step 1: Tulis tes yang gagal**

`EntryChecklist.test.ts`:

```ts
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { ChecklistItem } from '@/types/schedule';
import EntryChecklist from './EntryChecklist.vue';

const items: ChecklistItem[] = [
    { id: 1, name: 'Ani', nisn: '0000000001', hint: 'Gelombang 1' },
    { id: 2, name: 'Budi', nisn: '0000000002', hint: null },
    { id: 3, name: 'Citra', nisn: '0000000003' },
];

function mountList(modelValue: number[] = [], extra: Record<string, unknown> = {}) {
    return mount(EntryChecklist, {
        props: { items, modelValue, 'onUpdate:modelValue': () => {}, ...extra },
    });
}

describe('EntryChecklist', () => {
    it('menampilkan nama, NISN, dan petunjuk', () => {
        const text = mountList().text();
        expect(text).toContain('Ani');
        expect(text).toContain('0000000002');
        expect(text).toContain('Gelombang 1');
    });

    it('"Pilih semua" memilih semua item', async () => {
        const wrapper = mountList();
        const button = wrapper.findAll('button').find((b) => b.text().includes('Pilih semua'))!;
        await button.trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[1, 2, 3]]);
    });

    it('penghitung mengikuti jumlah terpilih', () => {
        expect(mountList([1, 2]).find('[role="status"]').text()).toContain('2 dipilih');
    });

    it('pencarian menyaring menurut nama atau NISN', async () => {
        const wrapper = mountList();
        await wrapper.find('input').setValue('budi');
        expect(wrapper.text()).toContain('Budi');
        expect(wrapper.text()).not.toContain('Ani');
    });

    it('menampilkan teks kosong bila tidak ada item', () => {
        const wrapper = mountList([], { items: [], emptyText: 'Tidak ada siswa.' });
        expect(wrapper.text()).toContain('Tidak ada siswa.');
    });
});
```

Tambahkan di `menuIcons.test.ts` ekspektasi bahwa `menuIcon('calendar-days')` bukan ikon fallback (ikuti pola tes yang ada di berkas itu).

Run: `npm test -- EntryChecklist menuIcons` → FAIL.

- [ ] **Step 2: Implementasi**

`types/schedule.ts`:

```ts
import type { RegistrationStatus } from './registration';

export type ScheduleRow = {
    id: number;
    date: string;
    location: string;
    school: string | null;
    is_past: boolean;
    participants_count: number;
    counts: Partial<Record<RegistrationStatus, number>>;
};

export type ScheduleDetail = {
    id: number;
    date: string;
    location: string;
    school: string | null;
    is_past: boolean;
    created_by?: string | null;
};

export type ParticipantRow = {
    id: number;
    student: { id: number; name: string; nisn: string };
    status: RegistrationStatus;
};

export type ChecklistItem = {
    id: number;
    name: string;
    nisn: string;
    hint?: string | null;
};
```

`EntryChecklist.vue`:

```vue
<script setup lang="ts">
import SearchInput from '@/Components/shared/SearchInput.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import type { ChecklistItem } from '@/types/schedule';
import { computed, ref } from 'vue';

const selected = defineModel<number[]>({ required: true });
const props = withDefaults(
    defineProps<{
        items: ChecklistItem[];
        emptyText?: string;
        disabled?: boolean;
    }>(),
    { emptyText: 'Tidak ada siswa.', disabled: false },
);

const search = ref('');
const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? props.items.filter(
              (i) => i.name.toLowerCase().includes(q) || i.nisn.includes(q),
          )
        : props.items;
});

function toggle(id: number, on: boolean) {
    selected.value = on
        ? [...selected.value, id]
        : selected.value.filter((x) => x !== id);
}

function selectAll() {
    selected.value = props.items.map((i) => i.id);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="items.length" class="flex flex-col gap-3 sm:flex-row sm:items-center">
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
                :disabled="disabled"
                @click="selectAll"
            >
                Pilih semua
            </Button>
        </div>

        <p v-if="items.length" role="status" class="text-muted-foreground text-sm">
            {{ selected.length }} dipilih
        </p>

        <ul v-if="visible.length" class="divide-y rounded-lg border">
            <li
                v-for="item in visible"
                :key="item.id"
                class="flex items-start gap-3 px-4 py-3"
            >
                <Checkbox
                    :id="`entri-${item.id}`"
                    class="mt-0.5"
                    :model-value="selected.includes(item.id)"
                    :disabled="disabled"
                    @update:model-value="toggle(item.id, $event === true)"
                />
                <label
                    :for="`entri-${item.id}`"
                    class="min-w-0 flex-1 text-sm leading-5"
                >
                    <span class="block font-medium break-words">{{ item.name }}</span>
                    <span class="text-muted-foreground block text-xs leading-4">
                        NISN {{ item.nisn }}<template v-if="item.hint"> · {{ item.hint }}</template>
                    </span>
                </label>
            </li>
        </ul>
        <p v-else class="text-muted-foreground text-sm">
            {{ items.length ? 'Tidak ada siswa yang sesuai pencarian.' : emptyText }}
        </p>
    </div>
</template>
```

`menuIcons.ts`: impor `CalendarDays` dari `@lucide/vue` dan tambah `'calendar-days': CalendarDays`.

Panduan UI — sisipkan sebelum `## 6. Pola lintas halaman` (gunakan Edit; teks Indonesia):

```
### 5.25 Jadwal: daftar dan form `/jadwal` (`jadwal.lihat`)
- **Daftar:** judul "Jadwal", deskripsi "Jadwal perekaman per sekolah." Tombol **+ Buat jadwal** (`jadwal.buat`). Filter (pola 5.7): cari lokasi, Periode (Semua / Akan datang / Sudah lewat), Sekolah (hanya staf). Tabel: **Jadwal** (tanggal dengan hari, lokasi), **Sekolah** (hanya staf), **Peserta** (jumlah), **Ringkasan status** (teks, mis. "Terjadwal 12 · Sudah rekaman 3"), **Aksi** (Lihat; Ubah bila `jadwal.ubah`; Tandai rekaman bila `rekaman.lihat`). Jadwal lewat diberi teks "Sudah lewat". State: loading, kosong ("Belum ada jadwal"), hasil filter kosong, petunjuk geser di mobile.
- **Form** (`/jadwal/buat`, `/jadwal/{id}/ubah`, pola 5.8): Sekolah (hanya staf saat buat), Tanggal (input tanggal), Lokasi. Pada ubah dengan peserta tampil catatan "Operator sekolah akan diberi tahu bila tanggal atau lokasi berubah." Bar aksi bawah: Batal dan Simpan.

### 5.26 Jadwal: detail dan penempatan siswa `/jadwal/{id}`
- Header: lokasi, tanggal dengan hari, sekolah, dibuat oleh; tombol **Ubah** (`jadwal.ubah`) dan **Penandaan rekaman** (`rekaman.lihat`). Tabel **Peserta**: Siswa (nama, NISN), Status (badge berteks), Aksi **Keluarkan** untuk status Terjadwal bila `jadwal.ubah` (konfirmasi "Siswa kembali menjadi Terverifikasi dan dapat dijadwalkan ulang.").
- Tombol **Tambah siswa** (`jadwal.ubah`, jadwal belum lewat) membuka dialog: daftar siswa Terverifikasi sekolah itu yang belum dijadwalkan (cari, kotak centang, "Pilih semua", penghitung `role="status"`), tombol Batal dan **Jadwalkan N siswa** (dialog ini sekaligus konfirmasi; operator sekolah diberi tahu). Galat penempatan tampil di dalam dialog. Operator sekolah melihat detail tanpa tombol ubah, tambah, atau keluarkan.

### 5.27 Penandaan rekaman `/jadwal/{id}/rekaman` (`rekaman.lihat`)
- Header jadwal. Sebelum hari kegiatan tampil banner "Penandaan dibuka pada {tanggal}." dan kontrol dinonaktifkan. Daftar peserta Terjadwal memakai ceklis massal (5.26), lalu pilihan status radio berteks **Sudah rekaman / Tidak hadir / Belum rekaman** dengan keterangan singkat, kolom Catatan opsional, dan tombol **Simpan penandaan** (konfirmasi menyebut jumlah dan status; hanya `rekaman.tandai`). Bagian **Sudah ditandai** menampilkan status akhir (hanya baca). Status akhir tidak dapat diubah.
```

Dan pada **5.6 Beranda**, ubah butir 3 menjadi: `3. **Jadwal terdekat**: kartu dengan tanggal besar di kiri, lalu sekolah dan lokasi; berisi maksimal 3 jadwal hari ini atau setelahnya, dibatasi sekolah pengguna (data nyata sejak Fase 5; hanya untuk pemegang jadwal.lihat).`

- [ ] **Step 3: Jalankan** — `npm test`, `npm run typecheck`, `npm run lint` → bersih.

- [ ] **Step 4: Commit**

```bash
git add docs resources/js
git commit -m "feat: tambah panduan UI jadwal, tipe, ikon menu, dan komponen ceklis siswa" -m "Panduan UI 5.25-5.27 dan pembaruan Beranda 5.6. EntryChecklist: pencarian, pilih semua, penghitung role=status, dan teks kosong, dipakai dialog penempatan serta layar penandaan. Tipe TypeScript jadwal dan ikon menu calendar-days."
```

---

### Task 7: Halaman Jadwal — daftar dan form

**Files:**
- Create: `resources/js/Pages/Jadwal/Index.vue`, `resources/js/Pages/Jadwal/Form.vue`

**Interfaces:**
- Consumes: kontrak prop Task 5, `StatusBadge`/`registrationStatus`, pola `Pendaftaran/Index.vue` dan `Siswa/Form.vue`.

- [ ] **Step 1: `Jadwal/Index.vue`** — ikuti `Pendaftaran/Index.vue` (impor yang sama kecuali ikon: `CalendarDays`, `Eye`, `Pencil`, `Plus`, `Search`, `ClipboardCheck`):

```vue
<script setup lang="ts">
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
import { formatDateWithDay } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type { Paginator } from '@/types/access';
import type { ScheduleRow } from '@/types/schedule';
import { Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    CalendarDays,
    ClipboardCheck,
    Eye,
    Pencil,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, watch } from 'vue';

const props = defineProps<{
    schedules: Paginator<ScheduleRow>;
    filters: Record<string, string | undefined>;
    schools: { id: number; name: string }[] | null;
}>();

const { can } = usePermission();
const { filters, loading, apply, reset } = useDataTable('/jadwal', {
    search: props.filters.search ?? '',
    period: props.filters.period ?? '',
    school_id: props.filters.school_id ?? '',
});

watch(() => [filters.period, filters.school_id], apply);
watch(() => filters.search, useDebounceFn(apply, 300));

const hasFilters = computed(() =>
    Object.values(filters).some((v) => v !== '' && v !== null),
);
const periodOptions = [
    { value: 'akan-datang', label: 'Akan datang' },
    { value: 'lewat', label: 'Sudah lewat' },
];
const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);

const columns = computed(() => [
    { key: 'date', label: 'Jadwal', class: 'w-[280px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'participants_count', label: 'Peserta', class: 'w-[96px]' },
    { key: 'counts', label: 'Ringkasan status' },
    { key: 'actions', label: 'Aksi', class: 'w-[72px] text-right' },
]);
</script>

<template>
    <AppLayout title="Jadwal">
        <PageHeader
            title="Jadwal"
            description="Jadwal perekaman per sekolah."
        >
            <template #actions>
                <Button
                    v-if="can(PERMISSIONS.JadwalBuat)"
                    as-child
                    class="h-10 px-4"
                >
                    <Link :href="route('jadwal.create')">
                        <Plus class="size-4" aria-hidden="true" />
                        Buat jadwal
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 lg:flex lg:items-start">
                <FormField
                    v-slot="{ id }"
                    label="Cari jadwal"
                    class="col-span-2 lg:flex-1"
                >
                    <SearchInput
                        :id="id"
                        :model-value="String(filters.search ?? '')"
                        placeholder="Cari lokasi"
                        @update:model-value="filters.search = $event"
                    />
                </FormField>
                <FormField v-slot="{ id }" label="Periode" class="lg:w-[200px]">
                    <SelectField
                        :id="id"
                        v-model="filters.period"
                        :options="periodOptions"
                        all-label="Semua periode"
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
                    :rows="schedules.data"
                    :loading="loading"
                    row-class="h-16"
                    table-class="min-w-[800px]"
                >
                    <template #cell-date="{ row }">
                        <div class="min-w-0">
                            <Link
                                :href="route('jadwal.show', row.id)"
                                class="block truncate text-sm leading-5 font-semibold hover:underline"
                            >
                                {{ formatDateWithDay(row.date) }}
                            </Link>
                            <p
                                class="text-muted-foreground truncate text-xs leading-4"
                            >
                                {{ row.location
                                }}<template v-if="row.is_past">
                                    · Sudah lewat</template
                                >
                            </p>
                        </div>
                    </template>
                    <template #cell-school="{ row }">{{ row.school }}</template>
                    <template #cell-counts="{ row }">
                        <p
                            v-if="!row.participants_count"
                            class="text-muted-foreground text-xs"
                        >
                            Belum ada peserta
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
                            <RowActions :label="`Aksi untuk jadwal ${row.location}`">
                                <DropdownMenuItem
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link :href="route('jadwal.show', row.id)">
                                        <Eye class="size-4" aria-hidden="true" />
                                        Lihat detail
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="can(PERMISSIONS.JadwalUbah)"
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link :href="route('jadwal.edit', row.id)">
                                        <Pencil
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Ubah
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="can(PERMISSIONS.RekamanLihat)"
                                    as-child
                                    class="h-10 gap-3 px-3 text-sm font-medium"
                                >
                                    <Link
                                        :href="route('jadwal.rekaman.show', row.id)"
                                    >
                                        <ClipboardCheck
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Penandaan rekaman
                                    </Link>
                                </DropdownMenuItem>
                            </RowActions>
                        </div>
                    </template>
                    <template #empty>
                        <EmptyState
                            v-if="hasFilters"
                            :icon="Search"
                            title="Tidak ada hasil"
                            description="Tidak ada jadwal yang sesuai dengan pencarian atau filter Anda."
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
                            :icon="CalendarDays"
                            title="Belum ada jadwal"
                            description="Jadwal perekaman akan muncul di sini setelah dibuat oleh admin kecamatan."
                        >
                            <template
                                v-if="can(PERMISSIONS.JadwalBuat)"
                                #action
                            >
                                <Button as-child class="h-9">
                                    <Link :href="route('jadwal.create')"
                                        >Buat jadwal</Link
                                    >
                                </Button>
                            </template>
                        </EmptyState>
                    </template>
                </DataTable>
                <Pagination :paginator="schedules" />
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 2: `Jadwal/Form.vue`**:

```vue
<script setup lang="ts">
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import SelectField from '@/Components/shared/SelectField.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    schedule: {
        id: number;
        date: string;
        location: string;
        has_participants: boolean;
    } | null;
    schools: { id: number; name: string }[] | null;
}>();

const isEdit = computed(() => props.schedule !== null);
const title = computed(() => (isEdit.value ? 'Ubah jadwal' : 'Buat jadwal'));

const form = useForm({
    school_id: '' as number | '',
    date: props.schedule?.date ?? '',
    location: props.schedule?.location ?? '',
});

const schoolOptions = computed(() =>
    (props.schools ?? []).map((s) => ({ value: s.id, label: s.name })),
);
// Zona Asia/Jakarta, sama dengan aturan server.
const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
}).format(new Date());

const clientErrors = ref<Record<string, string>>({});
const error = (key: keyof typeof form.errors) =>
    clientErrors.value[key] ?? form.errors[key];
const hasErrors = computed(
    () =>
        Object.keys(clientErrors.value).length > 0 ||
        Object.keys(form.errors).length > 0,
);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (props.schools && !isEdit.value && form.school_id === '')
        errors.school_id = 'Pilih sekolah.';
    if (!form.date) errors.date = 'Tanggal wajib diisi.';
    else if (form.date !== props.schedule?.date && form.date < today)
        errors.date = 'Tanggal tidak boleh di masa lalu.';
    if (!form.location.trim()) errors.location = 'Lokasi wajib diisi.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();

    const options = { onError: focusFirstInvalid };
    if (props.schedule) form.put(route('jadwal.update', props.schedule.id), options);
    else form.post(route('jadwal.store'), options);
}

const crumbs = computed(() => [
    { label: 'Jadwal', href: route('jadwal.index') },
    { label: title.value },
]);
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">{{ title }}</h1>
            <p class="text-muted-foreground text-sm leading-5">
                {{
                    isEdit
                        ? 'Perbarui tanggal atau lokasi kegiatan.'
                        : 'Tentukan sekolah, tanggal, dan lokasi kegiatan perekaman.'
                }}
            </p>
        </div>

        <form class="max-w-[720px]" novalidate @submit.prevent="submit">
            <div class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Detail jadwal
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Tanda * menunjukkan kolom wajib diisi.
                    </p>
                </div>

                <InlineAlert v-if="hasErrors"
                    >Periksa kembali data yang Anda isi.</InlineAlert
                >
                <InlineAlert v-if="schedule?.has_participants" variant="warning">
                    Operator sekolah akan diberi tahu bila tanggal atau lokasi
                    berubah.
                </InlineAlert>

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

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Tanggal"
                    required
                    :error="error('date')"
                >
                    <Input
                        :id="id"
                        v-model="form.date"
                        type="date"
                        class="h-10"
                        :min="schedule ? undefined : today"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>

                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Lokasi"
                    required
                    :error="error('location')"
                >
                    <Input
                        :id="id"
                        v-model="form.location"
                        class="h-10"
                        placeholder="Contoh: Aula SMP Negeri 1"
                        autocomplete="off"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <Button as-child variant="outline" class="h-10 px-4">
                    <Link
                        :href="
                            schedule
                                ? route('jadwal.show', schedule.id)
                                : route('jadwal.index')
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
                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
```

- [ ] **Step 3: Jalankan** — `npm run typecheck`, `npm run lint`, `npm test` → bersih (perbaiki galat tipe pada `DataTable` generic bila ada).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Jadwal
git commit -m "feat: tambah halaman daftar dan form jadwal" -m "Daftar jadwal dengan filter lokasi/periode/sekolah, ringkasan status per jadwal, petunjuk geser di mobile, dan aksi sesuai izin. Form buat/ubah dengan validasi klien yang sama dengan server (tanggal tidak boleh di masa lalu bila berubah, zona Asia/Jakarta) dan catatan bahwa operator akan diberi tahu saat jadwal berpeserta diubah."
```

---

### Task 8: Detail jadwal dan dialog tambah siswa

**Files:**
- Create: `resources/js/Pages/Jadwal/Show.vue`

- [ ] **Step 1: `Jadwal/Show.vue`**:

```vue
<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import EntryChecklist from '@/Components/shared/EntryChecklist.vue';
import InfoList from '@/Components/shared/InfoList.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateWithDay } from '@/lib/date';
import { PERMISSIONS } from '@/lib/permissions';
import type {
    ChecklistItem,
    ParticipantRow,
    ScheduleDetail,
} from '@/types/schedule';
import { Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Pencil, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    schedule: ScheduleDetail;
    participants: ParticipantRow[];
    candidates: ChecklistItem[];
}>();

const { can } = usePermission();
const canEdit = computed(() => can(PERMISSIONS.JadwalUbah));
const canAssign = computed(() => canEdit.value && !props.schedule.is_past);

const info = computed(() => [
    { label: 'Tanggal', value: formatDateWithDay(props.schedule.date) },
    { label: 'Lokasi', value: props.schedule.location },
    { label: 'Sekolah', value: props.schedule.school },
    { label: 'Dibuat oleh', value: props.schedule.created_by ?? null },
]);

const columns = computed(() => [
    { key: 'student', label: 'Siswa' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
    ...(canEdit.value
        ? [{ key: 'actions', label: 'Aksi', class: 'w-[140px] text-right' }]
        : []),
]);

const addOpen = ref(false);
const picked = ref<number[]>([]);
const assigning = ref(false);
const assignError = ref<string | null>(null);

function openAdd() {
    picked.value = [];
    assignError.value = null;
    addOpen.value = true;
}

function assign() {
    router.post(
        route('jadwal.siswa.store', props.schedule.id),
        { entry_ids: picked.value },
        {
            preserveScroll: true,
            onStart: () => (assigning.value = true),
            onFinish: () => (assigning.value = false),
            onSuccess: () => (addOpen.value = false),
            onError: (errors) =>
                (assignError.value = errors.entry_ids ?? 'Gagal menjadwalkan.'),
        },
    );
}

const removing = ref<ParticipantRow | null>(null);
const removeOpen = computed({
    get: () => removing.value !== null,
    set: (open: boolean) => {
        if (!open) removing.value = null;
    },
});

function remove() {
    if (removing.value)
        router.delete(
            route('jadwal.siswa.destroy', [props.schedule.id, removing.value.id]),
            { preserveScroll: true },
        );
}

const crumbs = computed(() => [
    { label: 'Jadwal', href: route('jadwal.index') },
    { label: formatDateWithDay(props.schedule.date) },
]);
</script>

<template>
    <AppLayout title="Detail jadwal" :breadcrumbs="crumbs">
        <div
            class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-2xl leading-8 font-semibold break-words">
                    {{ schedule.location }}
                </h1>
                <p class="text-muted-foreground text-sm leading-5">
                    {{ formatDateWithDay(schedule.date)
                    }}<template v-if="schedule.is_past"> · Sudah lewat</template>
                </p>
            </div>
            <div
                class="flex w-full items-center gap-2 sm:w-auto [&>*]:flex-1 sm:[&>*]:flex-none"
            >
                <Button
                    v-if="canEdit"
                    as-child
                    variant="outline"
                    class="h-10 px-4"
                >
                    <Link :href="route('jadwal.edit', schedule.id)">
                        <Pencil class="size-4" aria-hidden="true" />
                        Ubah
                    </Link>
                </Button>
                <Button
                    v-if="can(PERMISSIONS.RekamanLihat)"
                    as-child
                    variant="outline"
                    class="h-10 px-4"
                >
                    <Link :href="route('jadwal.rekaman.show', schedule.id)">
                        <ClipboardCheck class="size-4" aria-hidden="true" />
                        Penandaan rekaman
                    </Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <h2 class="text-base leading-6 font-semibold">Informasi</h2>
                <InfoList :items="info" />
            </section>

            <section class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base leading-6 font-semibold">Peserta</h2>
                    <Button
                        v-if="canAssign"
                        type="button"
                        class="h-10 px-4"
                        @click="openAdd"
                    >
                        <UserPlus class="size-4" aria-hidden="true" />
                        Tambah siswa
                    </Button>
                </div>
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="columns"
                    :rows="participants"
                    row-class="h-16"
                    table-class="min-w-[560px]"
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
                        </div>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" kind="registration" />
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Button
                                v-if="row.status === 'terjadwal'"
                                type="button"
                                variant="ghost"
                                class="h-9 px-3"
                                @click="removing = row"
                            >
                                Keluarkan
                                <span class="sr-only"
                                    >{{ row.student.name }} dari jadwal</span
                                >
                            </Button>
                        </div>
                    </template>
                    <template #empty>
                        <p class="text-muted-foreground p-6 text-sm">
                            Belum ada siswa di jadwal ini.
                        </p>
                    </template>
                </DataTable>
            </section>
        </div>

        <Dialog v-model:open="addOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto rounded-xl">
                <DialogHeader>
                    <DialogTitle>Tambah siswa ke jadwal</DialogTitle>
                    <DialogDescription>
                        Siswa Terverifikasi dari sekolah ini yang belum
                        dijadwalkan. Operator sekolah akan diberi tahu.
                    </DialogDescription>
                </DialogHeader>
                <InlineAlert v-if="assignError">{{ assignError }}</InlineAlert>
                <EntryChecklist
                    v-model="picked"
                    :items="candidates"
                    empty-text="Belum ada siswa terverifikasi yang belum dijadwalkan."
                />
                <DialogFooter>
                    <Button variant="outline" @click="addOpen = false"
                        >Batal</Button
                    >
                    <Button
                        :disabled="!picked.length || assigning"
                        @click="assign"
                    >
                        Jadwalkan {{ picked.length }} siswa
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="removeOpen"
            :title="`Keluarkan ${removing?.student.name ?? ''}?`"
            description="Siswa kembali menjadi Terverifikasi dan dapat dijadwalkan ulang."
            confirm-label="Keluarkan"
            destructive
            @confirm="remove"
        />
    </AppLayout>
</template>
```

- [ ] **Step 2: Jalankan** — `npm run typecheck`, `npm run lint`, `npm test` → bersih.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Jadwal/Show.vue
git commit -m "feat: tambah detail jadwal dengan penempatan dan pengeluaran siswa" -m "Halaman detail menampilkan informasi jadwal dan peserta dengan status berteks. Dialog Tambah siswa memakai ceklis massal dan sekaligus menjadi konfirmasi penjadwalan; Keluarkan meminta konfirmasi. Operator sekolah melihat detail tanpa tombol ubah, tambah, atau keluarkan."
```

---

### Task 9: Layar penandaan rekaman

**Files:**
- Create: `resources/js/Pages/Jadwal/Rekaman.vue`

- [ ] **Step 1: `Jadwal/Rekaman.vue`**:

```vue
<script setup lang="ts">
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue';
import DataTable from '@/Components/shared/DataTable.vue';
import EntryChecklist from '@/Components/shared/EntryChecklist.vue';
import FormField from '@/Components/shared/FormField.vue';
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import StatusBadge from '@/Components/shared/StatusBadge.vue';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { usePermission } from '@/composables/usePermission';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatDateWithDay } from '@/lib/date';
import { focusFirstInvalid } from '@/lib/focusFirstInvalid';
import { PERMISSIONS } from '@/lib/permissions';
import { registrationStatus } from '@/lib/status';
import type {
    ChecklistItem,
    ParticipantRow,
    ScheduleDetail,
} from '@/types/schedule';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    schedule: ScheduleDetail;
    entries: ChecklistItem[];
    marked: ParticipantRow[];
    can_mark: boolean;
}>();

const { can } = usePermission();
const editable = computed(
    () => props.can_mark && can(PERMISSIONS.RekamanTandai),
);

const statuses = [
    {
        value: 'sudah_rekaman',
        label: 'Sudah rekaman',
        hint: 'Siswa sudah direkam pada kegiatan ini.',
    },
    { value: 'tidak_hadir', label: 'Tidak hadir', hint: 'Siswa tidak datang.' },
    {
        value: 'belum_rekaman',
        label: 'Belum rekaman',
        hint: 'Siswa hadir tetapi belum sempat direkam.',
    },
];

const form = useForm({
    status: '',
    entry_ids: [] as number[],
    note: '',
});
const clientErrors = ref<Record<string, string>>({});
const error = (key: 'status' | 'entry_ids' | 'note') =>
    clientErrors.value[key] ?? form.errors[key];
const confirmOpen = ref(false);

const statusLabel = computed(() => registrationStatus(form.status).label);

function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!form.entry_ids.length) errors.entry_ids = 'Pilih minimal satu siswa.';
    if (!form.status) errors.status = 'Pilih status penandaan.';
    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function submit() {
    form.clearErrors();
    if (!validate()) return focusFirstInvalid();
    confirmOpen.value = true;
}

function save() {
    form.post(route('jadwal.rekaman.store', props.schedule.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: focusFirstInvalid,
    });
}

const markedColumns = [
    { key: 'student', label: 'Siswa' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
];

const crumbs = computed(() => [
    { label: 'Jadwal', href: route('jadwal.index') },
    {
        label: formatDateWithDay(props.schedule.date),
        href: route('jadwal.show', props.schedule.id),
    },
    { label: 'Penandaan rekaman' },
]);
</script>

<template>
    <AppLayout title="Penandaan rekaman" :breadcrumbs="crumbs">
        <div class="mb-6 flex flex-col gap-1">
            <h1 class="text-2xl leading-8 font-semibold">Penandaan rekaman</h1>
            <p class="text-muted-foreground text-sm leading-5">
                {{ schedule.location }} · {{ formatDateWithDay(schedule.date)
                }}<template v-if="schedule.school"> · {{ schedule.school }}</template>
            </p>
        </div>

        <div class="flex max-w-[720px] flex-col gap-6">
            <InlineAlert v-if="!can_mark" variant="warning">
                Penandaan dibuka pada {{ formatDate(schedule.date) }}.
            </InlineAlert>

            <section class="bg-card flex flex-col gap-4 rounded-lg border p-6">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base leading-6 font-semibold">
                        Siswa terjadwal
                    </h2>
                    <p class="text-muted-foreground text-xs leading-4">
                        Pilih siswa, tentukan status, lalu simpan. Status akhir
                        tidak dapat diubah.
                    </p>
                </div>

                <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                    <EntryChecklist
                        v-model="form.entry_ids"
                        :items="entries"
                        :disabled="!editable"
                        empty-text="Tidak ada siswa yang menunggu penandaan."
                    />
                    <p v-if="error('entry_ids')" class="text-danger text-xs" role="alert">
                        {{ error('entry_ids') }}
                    </p>

                    <template v-if="entries.length && can(PERMISSIONS.RekamanTandai)">
                        <fieldset class="flex flex-col gap-3" :disabled="!editable">
                            <legend class="sr-only">Status penandaan</legend>
                            <label
                                v-for="s in statuses"
                                :key="s.value"
                                class="flex cursor-pointer items-start gap-3 text-sm leading-5"
                            >
                                <input
                                    v-model="form.status"
                                    type="radio"
                                    name="status"
                                    :value="s.value"
                                    class="accent-primary mt-1 size-4"
                                    :aria-invalid="!!error('status')"
                                />
                                <span>
                                    <span class="block font-medium">{{ s.label }}</span>
                                    <span class="text-muted-foreground text-xs">{{
                                        s.hint
                                    }}</span>
                                </span>
                            </label>
                            <p v-if="error('status')" class="text-danger text-xs" role="alert">
                                {{ error('status') }}
                            </p>
                        </fieldset>

                        <FormField
                            v-slot="{ id, describedBy, invalid }"
                            label="Catatan"
                            :error="error('note')"
                            hint="Opsional; berlaku untuk semua siswa yang dipilih."
                        >
                            <Textarea
                                :id="id"
                                v-model="form.note"
                                rows="3"
                                maxlength="500"
                                :disabled="!editable"
                                :aria-invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </FormField>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                class="h-10 px-4"
                                :disabled="!editable || form.processing"
                            >
                                <LoaderCircle
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                                {{ form.processing ? 'Menyimpan…' : 'Simpan penandaan' }}
                            </Button>
                        </div>
                    </template>
                </form>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-base leading-6 font-semibold">Sudah ditandai</h2>
                <DataTable
                    :columns="markedColumns"
                    :rows="marked"
                    row-class="h-14"
                    table-class="min-w-[480px]"
                >
                    <template #cell-student="{ row }">
                        <div class="min-w-0">
                            <p class="truncate text-sm leading-5 font-semibold">
                                {{ row.student.name }}
                            </p>
                            <p class="text-muted-foreground truncate text-xs leading-4">
                                NISN {{ row.student.nisn }}
                            </p>
                        </div>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :status="row.status" kind="registration" />
                    </template>
                    <template #empty>
                        <p class="text-muted-foreground p-6 text-sm">
                            Belum ada siswa yang ditandai.
                        </p>
                    </template>
                </DataTable>
            </section>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="`Tandai ${form.entry_ids.length} siswa sebagai ${statusLabel}?`"
            description="Status akhir tidak dapat diubah setelah disimpan."
            confirm-label="Simpan penandaan"
            @confirm="save"
        />
    </AppLayout>
</template>
```

- [ ] **Step 2: Jalankan** — `npm run typecheck`, `npm run lint`, `npm test`, `npm run build` → bersih; build sukses.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Jadwal/Rekaman.vue
git commit -m "feat: tambah layar penandaan rekaman massal" -m "Ceklis siswa Terjadwal, pilihan status berteks (Sudah rekaman, Tidak hadir, Belum rekaman) dengan catatan opsional dan konfirmasi menyebut jumlah dan status. Sebelum hari kegiatan kontrol dinonaktifkan dengan banner tanggal pembukaan; siswa yang sudah ditandai tampil hanya baca."
```

---

### Task 10: Verifikasi, review, dan penutupan fase

**Files:**
- Modify: `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (catatan Status Fase 5), memory `deferred-items.md`
- Bila review menemukan temuan valid: perbaikan dengan TDD (BE) atau tes Vitest.

- [ ] **Step 1: Verifikasi suite penuh** — `php artisan test` (background, tunggu hasil), `vendor/bin/pint --test`, `composer analyse`, `npm run typecheck`, `npm run lint`, `npm test`, `npm run build`. Expected: semua hijau; catat jumlah tes. Jalankan `php artisan migrate` pada DB lokal bila belum.

- [ ] **Step 2: Periksa blok Beranda** — buka `resources/js/Pages/Dashboard.vue` dan pastikan blok "Jadwal terdekat" merender `schedules` (tanggal besar, sekolah, lokasi) dan state kosong bila `schedules` kosong. Bila blok belum ada atau tidak sesuai panduan 5.6, lengkapi sesuai panduan (komponen `SectionCard`, `dateParts`) dan tambahkan commit `feat: tampilkan jadwal terdekat di Beranda`.

- [ ] **Step 3: Verifikasi browser (Playwright, desktop 1440 dan mobile 390)**

Jalankan `php artisan serve --port=8099` di latar belakang. Login lewat `browser_run_code_unsafe` (`page.getByLabel('Email').fill(...)`, `getByLabel('Password', {exact: true})`, klik "Masuk") dengan `sari@example.com` (admin kecamatan) dan `op.siswa@example.com` (operator), sandi `password`. Pastikan ada siswa `Terverifikasi` (setujui lewat antrean verifikasi bila perlu). Alur admin: buat jadwal (hari ini) → detail → Tambah siswa (dialog, pilih semua, Jadwalkan) → ubah lokasi (notifikasi muncul bagi operator) → Keluarkan satu siswa lalu tambahkan lagi → layar penandaan: tandai satu Tidak hadir dan satu Sudah rekaman (konfirmasi) → siswa Tidak hadir dapat didaftarkan lagi di pendaftaran baru. Alur operator: Beranda memuat jadwal terdekat sekolahnya, `/jadwal` hanya jadwal sekolahnya tanpa tombol ubah/tambah/tandai, jadwal sekolah lain 404. Periksa: tidak ada overflow horizontal di 390 px (selain tabel dalam kartu dengan petunjuk geser), konsol tanpa galat, state kosong dan loading, jadwal besok menonaktifkan penandaan dengan banner. Ambil tangkapan layar (hapus setelah dipakai), hentikan server.

- [ ] **Step 4: Review ponytail dan impeccable**

Panggil skill `ponytail:ponytail-review` untuk seluruh diff Fase 5 (`git diff <commit spec>..HEAD -- app routes resources/js lang database tests`) dan skill `impeccable` (critique + audit) untuk halaman Jadwal, `EntryChecklist`, dan blok Beranda dengan panduan `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`. Nilai temuan menurut efek bagi pengguna; perbaiki yang valid (Critical/Important) dengan TDD satu putaran (tes merah → hijau → suite hijau), catat yang ditolak sebagai ruling dan minor sebagai "Deferred minors". Commit perbaikan: `refactor: terapkan hasil review Fase 5` dengan isi yang menjelaskan temuan yang diterapkan.

- [ ] **Step 5: Perbarui rencana sprint dan memory**

Sisipkan catatan setelah heading `## Fase 5 — Jadwal dan penandaan rekaman` di rencana sprint (gunakan Edit):

```
**Status Fase 5 (2026-10-08): SELESAI** (spec: `specs/2026-10-08-fase-5-jadwal-rekaman-design.md`, rencana: `plans/2026-10-08-fase-5-jadwal-rekaman.md`). BE: domain `Schedules` (jadwal per sekolah, penempatan siswa Terverifikasi dengan kunci baris dan notifikasi, keluarkan dari jadwal, penandaan massal Sudah rekaman/Tidak hadir/Belum rekaman yang atomik dan hanya pada atau setelah tanggal jadwal), transisi baru di `RegistrationStatus` lewat `StatusTransitioner` (atribut `schedule_id` dalam transaksi yang sama), notifikasi jadwal, label log, jadwal terdekat di Beranda. FE: daftar/form/detail jadwal, dialog tambah siswa, layar penandaan, `EntryChecklist`, panduan UI 5.25–5.27. Penyimpangan dari spec: lihat Rulings R1–R6 di rencana dan ledger akhir.
```

Perbarui memory `deferred-items.md`: hapus bagian "Fase 5" (selesai), perbarui kalimat pembuka ("per 2026-10-08, setelah Fase 5 selesai"), tambahkan ke Fase 6: koreksi status akhir (tidak ada; tambah transisi koreksi terbatas bila sering salah tandai), hapus/pindah jadwal, kapasitas dan jam kegiatan, notifikasi penandaan; tambahkan ke catatan teknis: `StatusTransitioner::move` menerima `attributes` untuk kolom tambahan, dan binding bertingkat dievaluasi sebelum middleware `can` (entri harus konsisten dengan induknya di matriks izin). Perbarui `MEMORY.md` bila deskripsi berubah.

- [ ] **Step 6: Commit dan penutup**

```bash
git add docs
git commit -m "docs: tandai Fase 5 selesai pada rencana sprint" -m "Catatan status Fase 5 beserta ringkasan cakupan dan penyimpangan dari spec."
```

Susun pesan akhir untuk pengguna: ringkasan hasil (jumlah tes, build), daftar **Rulings I made** (R1–R6 plus keputusan saat eksekusi, masing-masing dengan biaya bila salah) dan **Deferred minors**, lalu hapus workspace ledger bila dibuat (`.superpowers/sdd/<nama-rencana>/`).
