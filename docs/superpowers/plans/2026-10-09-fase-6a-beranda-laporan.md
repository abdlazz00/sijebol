# Fase 6a — Beranda riil dan laporan rekap: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Beranda menampilkan kartu ringkasan dan "Perlu tindakan" nyata sesuai permission, dan admin kecamatan mendapat halaman Laporan rekap sekolah × status dengan filter tanggal pengajuan dan ekspor Excel.

**Architecture:** Domain baru `app/Domains/Reports` (Controller → FormRequest → Service → Repository interface + Eloquent). Satu `ReportRepository` menyuplai dua pemakai: `ReportService::recap` (halaman dan ekspor memakai method yang sama) dan `DashboardService` (kartu dan aksi Beranda). `DashboardController` hanya menyebar hasil service ke props Inertia. Frontend: halaman `Laporan/Index` memakai `DataTable` dengan baris yang diratakan oleh `lib/recap.ts`; `Dashboard.vue` tidak berubah.

**Tech Stack:** Laravel 13 / PHP 8.5 / MySQL 8, maatwebsite/excel v4 (sudah terpasang), Pest, Larastan level 5, Pint, Vue 3 + TS + Inertia v2, Vitest, ESLint, Playwright (verifikasi).

**Spec:** `docs/superpowers/specs/2026-10-09-fase-6a-beranda-laporan-design.md`

## Global Constraints

- Controller → FormRequest → Service → Repository; repository = interface di `Repositories/Contracts` + `Eloquent*`, dibind di `app/Providers/RepositoryServiceProvider.php`.
- Laporan hanya menghitung entri bukan `draf`; filter periode memakai `registrations.submitted_at`, `dari` dan `sampai` inklusif, `sampai >= dari`.
- Semua sekolah tampil di rekap (termasuk yang 0), urut nama, ada baris total; kolom: Diajukan, Perlu perbaikan, Terverifikasi, Terjadwal, Sudah rekaman, Tidak hadir, Belum rekaman, Ditolak, Total.
- Kartu dan aksi Beranda hanya muncul bila pengguna punya permission modulnya; aksi dengan hitungan 0 disembunyikan.
- Ekspor di-throttle dan dicatat di log aktivitas (`laporan.diekspor`); isi Excel sama dengan tabel halaman.
- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`.
- Tabel lebar di mobile wajib punya petunjuk "Geser tabel untuk melihat kolom lainnya" (`md:hidden`).
- Pest dataset tidak boleh berisi closure; tes yang memicu notifikasi harus membuat permission (`Permission::findOrCreate`); jangan edit file PHP dengan Python yang memuat backslash namespace (pakai Edit/Write).
- Suite penuh backend >120 detik: jalankan di background dan pantau. File FE baru dijalankan `npx prettier --write`.
- Commit: pesan detail, **tanpa** `Co-Authored-By` atau atribusi tool/AI apa pun. Persetujuan rencana ini mencakup langkah commit di dalamnya.

## Rulings (keputusan saya yang menyimpang atau melengkapi spec)

- **R1:** Varian kartu Beranda ditentukan oleh `user->school_id` (terikat sekolah atau tidak), bukan nama role; permission menentukan kartu mana yang boleh. Alasan: admin memegang semua permission, jadi permission saja tidak membedakan staf dan operator.
- **R2:** Union ikon `SummaryCard`/`ActionItem` di `types/dashboard.ts` dan `StatCard` tidak diubah karena ikon yang ada (`clipboard-list`, `clipboard-check`, `calendar-days`, `calendar-plus`) sudah cukup; hanya komentar usang di `types/dashboard.ts` diperbarui.
- **R3:** Batas tanggal dibandingkan sebagai string `Y-m-d 00:00:00` dan `Y-m-d 23:59:59`. Ini setara konversi Asia/Jakarta di spec karena `config('app.timezone')` sudah `Asia/Jakarta` dan kolom `timestamp` disimpan dalam zona itu; query tetap memakai indeks.
- **R4:** Role terikat sekolah dilarang memegang `laporan.lihat` (aturan yang sama dengan `log.lihat` di `RoleService`), supaya rekap lintas sekolah tidak bocor. Tambahan di luar spec.
- **R5:** Ekspor menetralkan nama sekolah yang diawali `=`, `+`, `-`, `@` dengan awalan `'` (injeksi formula Excel). Tambahan di luar spec.
- **R6:** Laporan memakai `DataTable` dengan baris yang diratakan di `resources/js/lib/recap.ts` (fungsi murni, diuji Vitest) daripada tabel buatan sendiri.

## Review Focus

1. `dari` kosong dengan `sampai` terisi (dan sebaliknya) harus tetap valid, bukan error "after_or_equal" — tes di Task 2.
2. Entri tepat pada `23:59:59` hari `sampai` ikut dihitung, entri `00:00:00` hari berikutnya tidak — tes di Task 1.
3. Tidak ada sekolah sama sekali: rekap kosong dengan total 0, dan halaman menampilkan empty state tanpa baris total — tes di Task 1 dan Task 4.
4. Role terikat sekolah diberi `laporan.lihat`: ditolak, juga saat update — tes di Task 1.
5. Nama sekolah berawalan `=` pada file Excel tidak boleh menjadi formula — tes di Task 2.

---

## File Structure

**Dibuat**
- `app/Domains/Reports/Repositories/Contracts/ReportRepositoryInterface.php` — kontrak hitung status dan matriks.
- `app/Domains/Reports/Repositories/EloquentReportRepository.php` — query hitung.
- `app/Domains/Reports/Services/ReportService.php` — `recap()` dan `recordExport()`.
- `app/Domains/Reports/Services/DashboardService.php` — `forUser()` untuk kartu dan aksi Beranda.
- `app/Domains/Reports/Http/Requests/ReportRequest.php` — validasi `dari`/`sampai`.
- `app/Domains/Reports/Http/Controllers/ReportController.php` — `index`, `export`.
- `app/Domains/Reports/Exports/RecapExport.php` — Excel.
- `routes/reports.php`
- `tests/Feature/Reports/{ReportServiceTest,ReportHttpTest,DashboardSummaryTest}.php`
- `resources/js/types/report.ts`, `resources/js/lib/recap.ts`, `resources/js/lib/recap.test.ts`, `resources/js/Pages/Laporan/Index.vue`

**Diubah**
- `app/Providers/RepositoryServiceProvider.php` (binding), `routes/web.php` (require), `app/Domains/Access/Services/MenuService.php` (item Laporan), `app/Domains/Access/Services/RoleService.php` (aturan R4), `app/Http/Controllers/DashboardController.php`, `lang/id/activity.php`.
- `tests/Feature/Access/{MenuTest,RoutePermissionMatrixTest,RoleServiceTest}.php`.
- `resources/js/config/menuIcons.ts` (+ `.test.ts`), `resources/js/types/dashboard.ts` (komentar).
- `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (5.6 dan 5.28), `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (status).

---

### Task 1: Repository dan service laporan, aturan role

**Files:**
- Create: `app/Domains/Reports/Repositories/Contracts/ReportRepositoryInterface.php`
- Create: `app/Domains/Reports/Repositories/EloquentReportRepository.php`
- Create: `app/Domains/Reports/Services/ReportService.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Modify: `app/Domains/Access/Services/RoleService.php:112-117`
- Test: `tests/Feature/Reports/ReportServiceTest.php`, `tests/Feature/Access/RoleServiceTest.php`

**Interfaces:**
- Produces: `ReportRepositoryInterface::statusCounts(): array<string,int>` (status → jumlah, terkena scope sekolah), `::schoolNames(): array<int,string>` (id → nama, urut nama), `::schoolStatusCounts(?string $from, ?string $to): array<int, array<string,int>>` (school_id → status → jumlah, tanpa draf).
- Produces: `ReportService::recap(?string $from, ?string $to): array{columns: list<array{key,label}>, rows: list<array{school: string, counts: array<string,int>, total: int}>, totals: array{counts: array<string,int>, total: int}}` dan `::recordExport(User $actor, ?string $from, ?string $to): void`.

- [ ] **Step 1: Tulis tes yang gagal (service)**

`tests/Feature/Reports/ReportServiceTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Domains\Reports\Services\ReportService;
use App\Domains\Schools\Models\School;
use App\Domains\Registrations\Models\RegistrationStudent;

/** Entri berstatus tertentu yang pendaftarannya diajukan pada waktu tertentu. */
function makeEntryAt(S $status, School $school, string $submittedAt): RegistrationStudent
{
    $entry = makeEntry($status, $school);
    $entry->registration->update(['submitted_at' => $submittedAt]);

    return $entry;
}

beforeEach(function () {
    $this->service = app(ReportService::class);
});

test('rekap menghitung entri per sekolah dan status, draf tidak ikut, sekolah tanpa entri tampil 0', function () {
    $a = School::factory()->create(['name' => 'SMPN A']);
    $b = School::factory()->create(['name' => 'SMPN B']);
    School::factory()->create(['name' => 'SMPN C']);
    makeEntry(S::Diajukan, $a);
    makeEntry(S::Diajukan, $a);
    makeEntry(S::SudahRekaman, $a);
    makeEntry(S::Terjadwal, $b);
    makeEntry(S::Draf, $b);

    $recap = $this->service->recap(null, null);

    expect(collect($recap['columns'])->pluck('key')->all())
        ->toBe(['diajukan', 'perlu_perbaikan', 'terverifikasi', 'terjadwal', 'sudah_rekaman', 'tidak_hadir', 'belum_rekaman', 'ditolak'])
        ->and(collect($recap['columns'])->pluck('label')->first())->toBe('Diajukan')
        ->and(collect($recap['rows'])->pluck('school')->all())->toBe(['SMPN A', 'SMPN B', 'SMPN C'])
        ->and($recap['rows'][0]['counts']['diajukan'])->toBe(2)
        ->and($recap['rows'][0]['total'])->toBe(3)
        ->and($recap['rows'][1]['total'])->toBe(1)
        ->and($recap['rows'][2]['total'])->toBe(0)
        ->and($recap['totals']['total'])->toBe(4)
        ->and($recap['totals']['counts']['diajukan'])->toBe(2)
        ->and($recap['totals']['counts']['sudah_rekaman'])->toBe(1);
});

test('tanpa sekolah, rekap kosong dengan total 0', function () {
    $recap = $this->service->recap(null, null);

    expect($recap['rows'])->toBe([])
        ->and($recap['totals']['total'])->toBe(0)
        ->and($recap['totals']['counts']['diajukan'])->toBe(0);
});

test('filter tanggal pengajuan inklusif sampai 23:59:59 hari sampai', function () {
    $s = School::factory()->create();
    makeEntryAt(S::Diajukan, $s, '2026-10-04 23:59:59');
    makeEntryAt(S::Diajukan, $s, '2026-10-05 00:00:00');
    makeEntryAt(S::Diajukan, $s, '2026-10-06 23:59:59');
    makeEntryAt(S::Diajukan, $s, '2026-10-07 00:00:00');

    expect($this->service->recap('2026-10-05', '2026-10-06')['totals']['total'])->toBe(2)
        ->and($this->service->recap('2026-10-06', null)['totals']['total'])->toBe(2)
        ->and($this->service->recap(null, '2026-10-05')['totals']['total'])->toBe(2)
        ->and($this->service->recap(null, null)['totals']['total'])->toBe(4);
});

test('hitungan status operator hanya milik sekolahnya', function () {
    $a = School::factory()->create();
    $b = School::factory()->create();
    makeEntry(S::Draf, $a);
    makeEntry(S::Draf, $a);
    makeEntry(S::Draf, $b);
    makeEntry(S::Diajukan, $b);

    $this->actingAs(makeUserWith([], $a, true, 'op-lap'));

    expect(app(ReportRepositoryInterface::class)->statusCounts())->toBe(['draf' => 2]);
});

test('ekspor dicatat di log aktivitas dengan filternya', function () {
    $admin = makeUserWith(['laporan.lihat'], roleName: 'pelapor');

    $this->service->recordExport($admin, '2026-10-01', null);

    $log = Spatie\Activitylog\Models\Activity::where('description', 'laporan.diekspor')->sole();
    expect($log->causer_id)->toBe($admin->id)
        ->and($log->properties['dari'])->toBe('2026-10-01')
        ->and($log->properties['sampai'])->toBeNull();
});
```

Tambahkan di `tests/Feature/Access/RoleServiceTest.php` setelah tes `log.lihat` (baris ~143):

```php
test('role terikat sekolah tidak boleh memegang laporan.lihat (rekap mencakup seluruh kecamatan)', function () {
    expect(fn () => $this->service->create([
        'name' => 'operator-laporan', 'is_school_scoped' => true, 'permissions' => ['laporan.lihat'],
    ], $this->super))->toThrow(AccessRuleException::class);

    $role = $this->service->create([
        'name' => 'operator-biasa-2', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat'],
    ], $this->super);

    expect(fn () => $this->service->update($role, [
        'name' => 'operator-biasa-2', 'is_school_scoped' => true, 'permissions' => ['siswa.lihat', 'laporan.lihat'],
    ], $this->super))->toThrow(AccessRuleException::class);
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Reports/ReportServiceTest.php tests/Feature/Access/RoleServiceTest.php`
Expected: FAIL (class `ReportService` / `ReportRepositoryInterface` tidak ada; tes role `laporan.lihat` tidak melempar).

- [ ] **Step 3: Implementasi minimal**

`app/Domains/Reports/Repositories/Contracts/ReportRepositoryInterface.php`:

```php
<?php

namespace App\Domains\Reports\Repositories\Contracts;

interface ReportRepositoryInterface
{
    /** Jumlah entri per status; terkena scope sekolah pengguna. @return array<string, int> */
    public function statusCounts(): array;

    /** Nama sekolah urut nama. @return array<int, string> */
    public function schoolNames(): array;

    /**
     * Jumlah entri bukan-draf per sekolah dan status; `$from`/`$to` (Y-m-d) inklusif pada tanggal pengajuan pendaftaran.
     *
     * @return array<int, array<string, int>>
     */
    public function schoolStatusCounts(?string $from, ?string $to): array;
}
```

`app/Domains/Reports/Repositories/EloquentReportRepository.php`:

```php
<?php

namespace App\Domains\Reports\Repositories;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Domains\Schools\Models\School;
use Illuminate\Database\Eloquent\Builder;

class EloquentReportRepository implements ReportRepositoryInterface
{
    public function statusCounts(): array
    {
        return RegistrationStudent::query()
            ->selectRaw('status, count(*) as total')->groupBy('status')
            ->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
    }

    public function schoolNames(): array
    {
        return School::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function schoolStatusCounts(?string $from, ?string $to): array
    {
        // app.timezone = Asia/Jakarta dan kolom timestamp disimpan dalam zona itu, jadi batas hari cukup string.
        return RegistrationStudent::query()
            ->join('registrations', 'registrations.id', '=', 'registration_students.registration_id')
            ->where('registration_students.status', '!=', RegistrationStatus::Draf->value)
            ->when($from !== null, fn (Builder $q) => $q->where('registrations.submitted_at', '>=', "{$from} 00:00:00"))
            ->when($to !== null, fn (Builder $q) => $q->where('registrations.submitted_at', '<=', "{$to} 23:59:59"))
            ->selectRaw('registration_students.school_id, registration_students.status, count(*) as total')
            ->groupBy('registration_students.school_id', 'registration_students.status')
            ->toBase()->get()
            ->groupBy('school_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n)->all())
            ->all();
    }
}
```

`app/Domains/Reports/Services/ReportService.php`:

```php
<?php

namespace App\Domains\Reports\Services;

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Models\User;

class ReportService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * Rekap sekolah × status (tanpa draf), lengkap dengan baris total.
     *
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array{school: string, counts: array<string, int>, total: int}>, totals: array{counts: array<string, int>, total: int}}
     */
    public function recap(?string $from, ?string $to): array
    {
        $statuses = array_values(array_filter(RegistrationStatus::cases(), fn (RegistrationStatus $s) => $s !== RegistrationStatus::Draf));
        $counts = $this->reports->schoolStatusCounts($from, $to);
        $totals = array_fill_keys(array_map(fn (RegistrationStatus $s) => $s->value, $statuses), 0);
        $rows = [];

        foreach ($this->reports->schoolNames() as $id => $name) {
            $row = [];
            foreach ($statuses as $status) {
                $row[$status->value] = $counts[$id][$status->value] ?? 0;
                $totals[$status->value] += $row[$status->value];
            }
            $rows[] = ['school' => $name, 'counts' => $row, 'total' => array_sum($row)];
        }

        return [
            'columns' => array_map(fn (RegistrationStatus $s) => ['key' => $s->value, 'label' => $s->label()], $statuses),
            'rows' => $rows,
            'totals' => ['counts' => $totals, 'total' => array_sum($totals)],
        ];
    }

    public function recordExport(User $actor, ?string $from, ?string $to): void
    {
        activity('laporan')->causedBy($actor)->withProperties(['dari' => $from, 'sampai' => $to])->log('laporan.diekspor');
    }
}
```

`app/Providers/RepositoryServiceProvider.php`: tambahkan dua `use` (`ReportRepositoryInterface`, `EloquentReportRepository`) dan baris binding

```php
        ReportRepositoryInterface::class => EloquentReportRepository::class,
```

`app/Domains/Access/Services/RoleService.php` — ganti `assertScopeAllows`:

```php
    private function assertScopeAllows(bool $schoolScoped, array $permissions): void
    {
        $districtWide = [Permission::LogLihat->value => 'Log Aktivitas', Permission::LaporanLihat->value => 'Laporan'];

        foreach ($districtWide as $permission => $label) {
            if ($schoolScoped && in_array($permission, $permissions, true)) {
                throw new AccessRuleException("Role terikat sekolah tidak boleh memiliki izin {$label} karena datanya mencakup seluruh kecamatan.");
            }
        }
    }
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Reports/ReportServiceTest.php tests/Feature/Access/RoleServiceTest.php`
Expected: PASS. Bila tes log aktivitas gagal karena tipe `laporan` belum ada di `lang/id/activity.php`, lanjutkan ke Task 2 (label hanya dipakai tampilan, bukan penyimpanan).

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app/Domains/Reports app/Domains/Access/Services/RoleService.php app/Providers tests/Feature/Reports tests/Feature/Access/RoleServiceTest.php && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Reports app/Providers/RepositoryServiceProvider.php app/Domains/Access/Services/RoleService.php tests/Feature/Reports/ReportServiceTest.php tests/Feature/Access/RoleServiceTest.php
git commit -m "feat: tambah repository dan service rekap laporan" -m "ReportRepository menyuplai hitungan status (terkena scope sekolah) dan matriks sekolah x status dengan filter tanggal pengajuan inklusif; draf tidak dihitung dan semua sekolah ikut walau 0. ReportService menyusun kolom, baris, dan total serta mencatat ekspor di log aktivitas. Role terikat sekolah kini dilarang memegang laporan.lihat seperti log.lihat agar rekap lintas sekolah tidak bocor."
```

---

### Task 2: Halaman, ekspor Excel, rute, dan menu laporan

**Files:**
- Create: `app/Domains/Reports/Http/Requests/ReportRequest.php`, `app/Domains/Reports/Http/Controllers/ReportController.php`, `app/Domains/Reports/Exports/RecapExport.php`, `routes/reports.php`
- Modify: `routes/web.php:22`, `app/Domains/Access/Services/MenuService.php`, `lang/id/activity.php`
- Test: `tests/Feature/Reports/ReportHttpTest.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`, `tests/Feature/Access/MenuTest.php`

**Interfaces:**
- Consumes: `ReportService::recap`, `::recordExport` (Task 1).
- Produces: rute `laporan.index` (`GET /laporan`) merender `Laporan/Index` dengan props `recap` (bentuk `recap()`) dan `filters` (`{dari: ?string, sampai: ?string}`); rute `laporan.ekspor` (`GET /laporan/ekspor`) mengunduh `rekap-pendaftaran.xlsx`. `RecapExport::headings(): list<string>` dan `::array(): list<list<string|int>>`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Reports/ReportHttpTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Exports\RecapExport;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'SMPN A']);
    $this->viewer = makeUserWith(['laporan.lihat'], roleName: 'pelapor');
});

test('halaman laporan memuat rekap dan filter kosong', function () {
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->viewer)->get('/laporan')->assertInertia(fn (Assert $p) => $p
        ->component('Laporan/Index')
        ->where('recap.rows.0.school', 'SMPN A')
        ->where('recap.rows.0.counts.diajukan', 1)
        ->where('recap.totals.total', 1)
        ->where('filters.dari', null)
        ->where('filters.sampai', null));
});

test('filter dipakai halaman; salah satu batas boleh kosong; urutan terbalik dan format salah ditolak', function () {
    $this->actingAs($this->viewer)->get('/laporan?dari=2026-10-05&sampai=2026-10-06')
        ->assertInertia(fn (Assert $p) => $p->where('filters.dari', '2026-10-05')->where('filters.sampai', '2026-10-06'));

    $this->actingAs($this->viewer)->get('/laporan?sampai=2026-10-05')->assertOk()->assertSessionHasNoErrors();
    $this->actingAs($this->viewer)->get('/laporan?dari=2026-10-05')->assertOk()->assertSessionHasNoErrors();

    $this->actingAs($this->viewer)->from('/laporan')->get('/laporan?dari=2026-10-06&sampai=2026-10-05')->assertSessionHasErrors('sampai');
    $this->actingAs($this->viewer)->from('/laporan')->get('/laporan?dari=bukan-tanggal')->assertSessionHasErrors('dari');
});

test('ekspor berisi data yang sama dengan halaman', function () {
    Excel::fake();
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->viewer)->get('/laporan/ekspor');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', function (RecapExport $export) {
        $rows = $export->array();

        return $export->headings() === ['Sekolah', 'Diajukan', 'Perlu perbaikan', 'Terverifikasi', 'Terjadwal', 'Sudah rekaman', 'Tidak hadir', 'Belum rekaman', 'Ditolak', 'Total']
            && $rows[0] === ['SMPN A', 1, 0, 0, 0, 0, 0, 0, 0, 1]
            && $rows[1] === ['Total', 1, 0, 0, 0, 0, 0, 0, 0, 1];
    });
});

test('ekspor mengikuti filter tanggal', function () {
    Excel::fake();
    makeEntry(S::Diajukan, $this->school);

    $this->actingAs($this->viewer)->get('/laporan/ekspor?dari=2099-01-01');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', fn (RecapExport $e) => $e->array()[0] === ['SMPN A', 0, 0, 0, 0, 0, 0, 0, 0, 0]);
});

test('ekspor menghasilkan berkas xlsx sungguhan dan tercatat di log', function () {
    $this->actingAs($this->viewer)->get('/laporan/ekspor?dari=2026-10-01')
        ->assertOk()->assertDownload('rekap-pendaftaran.xlsx');

    $log = Activity::where('description', 'laporan.diekspor')->sole();
    expect($log->causer_id)->toBe($this->viewer->id)->and($log->properties['dari'])->toBe('2026-10-01');
});

test('nama sekolah berawalan simbol formula dinetralkan di berkas ekspor', function () {
    Excel::fake();
    School::factory()->create(['name' => '=HYPERLINK("http://x")']);
    School::factory()->create(['name' => '@cmd']);

    $this->actingAs($this->viewer)->get('/laporan/ekspor');

    Excel::assertDownloaded('rekap-pendaftaran.xlsx', function (RecapExport $e) {
        $names = array_column($e->array(), 0);

        return in_array("'=HYPERLINK(\"http://x\")", $names, true) && in_array("'@cmd", $names, true) && in_array('SMPN A', $names, true);
    });
});
```

`tests/Feature/Access/RoutePermissionMatrixTest.php` — tambahkan di dataset `rute` (setelah baris `/jadwal/{schedule}/rekaman` POST):

```php
    ['GET', '/laporan', 'laporan.lihat'],
    ['GET', '/laporan/ekspor', 'laporan.lihat'],
```

`tests/Feature/Access/MenuTest.php` — ubah daftar grup admin menjadi `['Utama', 'Manajemen Akses', 'Master Data', 'Data Siswa', 'Verifikasi', 'Jadwal', 'Laporan', 'Sistem']` dan tambahkan:

```php
test('menu Laporan tampil untuk pemegang laporan.lihat', function () {
    $staf = makeUserWith(['laporan.lihat'], roleName: 'pelapor-menu');

    expect(collect(app(MenuService::class)->forUser($staf))->firstWhere('group', 'Laporan')['items'][0])
        ->toBe(['label' => 'Rekap pendaftaran', 'href' => '/laporan', 'icon' => 'file-spreadsheet']);
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Reports/ReportHttpTest.php tests/Feature/Access/MenuTest.php`
Expected: FAIL (rute 404, kelas `RecapExport` tidak ada, grup Laporan tidak ada).

- [ ] **Step 3: Implementasi minimal**

`app/Domains/Reports/Http/Requests/ReportRequest.php`:

```php
<?php

namespace App\Domains\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            // after_or_equal hanya bila `dari` terisi; tanpa itu Laravel membandingkan dengan teks "dari".
            'sampai' => ['nullable', 'date_format:Y-m-d', ...($this->filled('dari') ? ['after_or_equal:dari'] : [])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dari.date_format' => 'Format tanggal tidak valid.',
            'sampai.date_format' => 'Format tanggal tidak valid.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }
}
```

`app/Domains/Reports/Exports/RecapExport.php`:

```php
<?php

namespace App\Domains\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Berkas rekap; isinya diambil dari `ReportService::recap` sehingga identik dengan halaman.
 *
 * @phpstan-type Recap array{columns: list<array{key: string, label: string}>, rows: list<array{school: string, counts: array<string, int>, total: int}>, totals: array{counts: array<string, int>, total: int}}
 */
class RecapExport implements FromArray, WithHeadings
{
    /** @param  Recap  $recap */
    public function __construct(private readonly array $recap) {}

    /** @return list<string> */
    public function headings(): array
    {
        return ['Sekolah', ...array_column($this->recap['columns'], 'label'), 'Total'];
    }

    /** @return list<list<string|int>> */
    public function array(): array
    {
        $rows = array_map(
            fn (array $r) => [self::safe($r['school']), ...array_values($r['counts']), $r['total']],
            $this->recap['rows'],
        );
        $rows[] = ['Total', ...array_values($this->recap['totals']['counts']), $this->recap['totals']['total']];

        return $rows;
    }

    /** Teks berawalan = + - @ dibaca Excel sebagai formula; awalan ' menjadikannya teks biasa. */
    private static function safe(string $text): string
    {
        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }
}
```

`app/Domains/Reports/Http/Controllers/ReportController.php`:

```php
<?php

namespace App\Domains\Reports\Http\Controllers;

use App\Domains\Reports\Exports\RecapExport;
use App\Domains\Reports\Http\Requests\ReportRequest;
use App\Domains\Reports\Services\ReportService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(ReportRequest $request): Response
    {
        [$from, $to] = [$request->validated('dari'), $request->validated('sampai')];

        return Inertia::render('Laporan/Index', [
            'recap' => $this->reports->recap($from, $to),
            'filters' => ['dari' => $from, 'sampai' => $to],
        ]);
    }

    public function export(ReportRequest $request): BinaryFileResponse
    {
        [$from, $to] = [$request->validated('dari'), $request->validated('sampai')];
        $this->reports->recordExport($request->user(), $from, $to);

        return Excel::download(new RecapExport($this->reports->recap($from, $to)), 'rekap-pendaftaran.xlsx');
    }
}
```

`routes/reports.php`:

```php
<?php

use App\Domains\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('laporan', [ReportController::class, 'index'])->middleware('can:laporan.lihat')->name('laporan.index');
    Route::get('laporan/ekspor', [ReportController::class, 'export'])->middleware(['can:laporan.lihat', 'throttle:20,1'])->name('laporan.ekspor');
});
```

`routes/web.php`: tambahkan `require __DIR__.'/reports.php';` setelah `schedules.php`.

`MenuService::ITEMS`: sisipkan sebelum item "Log Aktivitas":

```php
        ['group' => 'Laporan', 'label' => 'Rekap pendaftaran', 'href' => '/laporan', 'icon' => 'file-spreadsheet', 'permission' => 'laporan.lihat'],
```

`lang/id/activity.php`: tambahkan di `types` → `'laporan' => 'Laporan'` (sebelum `'default'`), dan di `actions` → `'laporan.diekspor' => 'Laporan diekspor',`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Reports tests/Feature/Access/MenuTest.php tests/Feature/Access/RoutePermissionMatrixTest.php tests/Feature/Audit`
Expected: PASS. Bila `assertDownloaded` dengan `Excel::fake()` tidak cocok dengan versi paket (nama method), cek `vendor/maatwebsite/excel/src/Fakes/ExcelFake.php` dan sesuaikan nama method, bukan isi asersinya.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app/Domains/Reports app/Domains/Access routes tests/Feature lang && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Reports app/Domains/Access/Services/MenuService.php routes/reports.php routes/web.php lang/id/activity.php tests/Feature/Reports/ReportHttpTest.php tests/Feature/Access/MenuTest.php tests/Feature/Access/RoutePermissionMatrixTest.php
git commit -m "feat: tambah halaman dan ekspor Excel rekap laporan" -m "Rute /laporan dan /laporan/ekspor (laporan.lihat, ekspor di-throttle) memakai ReportService yang sama sehingga isi berkas identik dengan tabel halaman. Filter dari/sampai divalidasi (salah satu boleh kosong). Ekspor dicatat di log aktivitas, nama sekolah berawalan simbol formula dinetralkan, dan menu Laporan ditambahkan untuk pemegang izin."
```

---

### Task 3: Kartu ringkasan dan Perlu tindakan di Beranda

**Files:**
- Create: `app/Domains/Reports/Services/DashboardService.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/Reports/DashboardSummaryTest.php`

**Interfaces:**
- Consumes: `ReportRepositoryInterface::statusCounts` (Task 1).
- Produces: `DashboardService::forUser(User): array{summary: list<array{label,value,caption,icon}>, actions: list<array{title,description,label,href,icon}>}`; props Inertia `summary` dan `actions` pada halaman `Dashboard` (bentuk sesuai `types/dashboard.ts`).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Reports/DashboardSummaryTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use Inertia\Testing\AssertableInertia as Assert;

/** @param list<S> $statuses */
function makeEntries(array $statuses, ?School $school = null): void
{
    foreach ($statuses as $status) {
        makeEntry($status, $school ?? School::factory()->create());
    }
}

test('staf melihat kartu dan aksi se-kecamatan', function () {
    makeEntries([S::Diajukan, S::Diajukan, S::Terverifikasi, S::Terjadwal, S::SudahRekaman, S::PerluPerbaikan, S::Draf]);
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.lihat', 'jadwal.buat', 'pendaftaran.lihat'], roleName: 'staf-beranda');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('summary', 3)
        ->where('summary.0.label', 'Menunggu verifikasi')->where('summary.0.value', 2)
        ->where('summary.1.label', 'Terjadwal')->where('summary.1.value', 1)
        ->where('summary.2.label', 'Sudah rekaman')->where('summary.2.value', 1)
        ->has('actions', 2)
        ->where('actions.0.title', '2 entri menunggu verifikasi')->where('actions.0.href', '/verifikasi')
        ->where('actions.1.title', '1 siswa terverifikasi belum dijadwalkan')->where('actions.1.href', '/jadwal'));
});

test('operator hanya melihat angka dan tugas sekolahnya', function () {
    $sekolah = School::factory()->create();
    makeEntries([S::Draf, S::PerluPerbaikan, S::PerluPerbaikan, S::Terjadwal, S::SudahRekaman], $sekolah);
    makeEntries([S::Diajukan, S::Diajukan, S::PerluPerbaikan, S::Terjadwal]);
    $op = makeUserWith(['pendaftaran.lihat', 'jadwal.lihat'], $sekolah, true, 'op-beranda-2');

    $this->actingAs($op)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('summary', 3)
        ->where('summary.0.label', 'Draf')->where('summary.0.value', 1)
        ->where('summary.1.label', 'Perlu perbaikan')->where('summary.1.value', 2)
        ->where('summary.2.label', 'Sudah rekaman')->where('summary.2.value', 1)
        ->has('actions', 2)
        ->where('actions.0.title', '2 entri perlu diperbaiki')->where('actions.0.href', '/pendaftaran')
        ->where('actions.1.title', '1 siswa terjadwal')->where('actions.1.href', '/jadwal'));
});

test('kartu dan aksi hilang bila izin modulnya tidak ada', function () {
    makeEntries([S::Diajukan, S::Terverifikasi, S::Terjadwal]);

    $this->actingAs(makeUserWith([], roleName: 'tanpa-izin'))->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p->has('summary', 0)->has('actions', 0));

    $this->actingAs(makeUserWith(['jadwal.lihat'], roleName: 'hanya-jadwal'))->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p
            ->has('summary', 2)->where('summary.0.label', 'Terjadwal')->where('summary.1.label', 'Sudah rekaman')
            ->has('actions', 1)->where('actions.0.title', '1 siswa terjadwal'));
});

test('aksi dengan hitungan 0 disembunyikan tetapi kartu tetap tampil dengan angka 0', function () {
    $staf = makeUserWith(['verifikasi.lihat', 'jadwal.buat', 'jadwal.lihat'], roleName: 'staf-kosong');

    $this->actingAs($staf)->get('/dashboard')->assertInertia(fn (Assert $p) => $p
        ->has('actions', 0)->where('summary.0.value', 0)->has('summary', 3));
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Reports/DashboardSummaryTest.php`
Expected: FAIL (`summary`/`actions` tidak dikirim controller).

- [ ] **Step 3: Implementasi minimal**

`app/Domains/Reports/Services/DashboardService.php`:

```php
<?php

namespace App\Domains\Reports\Services;

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Reports\Repositories\Contracts\ReportRepositoryInterface;
use App\Models\User;

class DashboardService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * Kartu dan aksi Beranda. Angka sudah dibatasi sekolah oleh scope model; operator (terikat sekolah)
     * dan staf kecamatan mendapat kartu berbeda, dan tiap kartu/aksi butuh izin modulnya.
     *
     * @return array{summary: list<array{label: string, value: int, caption: string, icon: string}>, actions: list<array{title: string, description: string, label: string, href: string, icon: string}>}
     */
    public function forUser(User $user): array
    {
        $counts = $this->reports->statusCounts();
        $n = fn (S $status): int => $counts[$status->value] ?? 0;
        $bound = $user->school_id !== null;
        $summary = [];
        $actions = [];

        if (! $bound && $user->can('verifikasi.lihat')) {
            $summary[] = $this->card('Menunggu verifikasi', $n(S::Diajukan), 'Entri berstatus Diajukan', 'clipboard-list');
        }
        if ($bound && $user->can('pendaftaran.lihat')) {
            $summary[] = $this->card('Draf', $n(S::Draf), 'Belum diajukan', 'clipboard-list');
            $summary[] = $this->card('Perlu perbaikan', $n(S::PerluPerbaikan), 'Menunggu perbaikan sekolah', 'clipboard-list');
        }
        if ($user->can('jadwal.lihat')) {
            if (! $bound) {
                $summary[] = $this->card('Terjadwal', $n(S::Terjadwal), 'Menunggu penandaan rekaman', 'calendar-days');
            }
            $summary[] = $this->card('Sudah rekaman', $n(S::SudahRekaman), 'Sudah direkam', 'clipboard-check');
        }

        if ($user->can('verifikasi.lihat') && $n(S::Diajukan) > 0) {
            $actions[] = $this->action($n(S::Diajukan).' entri menunggu verifikasi', 'Periksa data dan dokumen siswa yang diajukan sekolah.', 'Buka antrean', route('verifikasi.index', absolute: false), 'clipboard-list');
        }
        if ($user->can('jadwal.buat') && $n(S::Terverifikasi) > 0) {
            $actions[] = $this->action($n(S::Terverifikasi).' siswa terverifikasi belum dijadwalkan', 'Tetapkan jadwal perekaman untuk mereka.', 'Atur jadwal', route('jadwal.index', absolute: false), 'calendar-plus');
        }
        if ($bound && $user->can('pendaftaran.lihat') && $n(S::PerluPerbaikan) > 0) {
            $actions[] = $this->action($n(S::PerluPerbaikan).' entri perlu diperbaiki', 'Perbaiki data atau dokumen, lalu ajukan ulang.', 'Buka pendaftaran', route('pendaftaran.index', absolute: false), 'clipboard-list');
        }
        if ($user->can('jadwal.lihat') && ! $user->can('jadwal.buat') && $n(S::Terjadwal) > 0) {
            $actions[] = $this->action($n(S::Terjadwal).' siswa terjadwal', 'Lihat jadwal perekaman sekolah Anda.', 'Lihat jadwal', route('jadwal.index', absolute: false), 'calendar-plus');
        }

        return ['summary' => $summary, 'actions' => $actions];
    }

    /** @return array{label: string, value: int, caption: string, icon: string} */
    private function card(string $label, int $value, string $caption, string $icon): array
    {
        return compact('label', 'value', 'caption', 'icon');
    }

    /** @return array{title: string, description: string, label: string, href: string, icon: string} */
    private function action(string $title, string $description, string $label, string $href, string $icon): array
    {
        return compact('title', 'description', 'label', 'href', 'icon');
    }
}
```

`app/Http/Controllers/DashboardController.php` (ganti seluruh isi):

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Reports\Services\DashboardService;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ScheduleService $schedules, DashboardService $dashboard): Response
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
            ...$dashboard->forUser($request->user()),
        ]);
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Reports tests/Feature/Schedules/DashboardTest.php`
Expected: PASS (tes Beranda Fase 5 tetap hijau).

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Reports/Services/DashboardService.php app/Http/Controllers/DashboardController.php tests/Feature/Reports/DashboardSummaryTest.php
git commit -m "feat: isi kartu ringkasan dan perlu tindakan Beranda dengan data nyata" -m "DashboardService menghitung kartu dan aksi dari hitungan status yang sudah dibatasi sekolah oleh scope model. Varian staf kecamatan dan operator ditentukan oleh keterikatan sekolah pengguna, sedangkan setiap kartu dan aksi hanya muncul bila izin modulnya ada; aksi dengan hitungan 0 disembunyikan."
```

---

### Task 4: Halaman Laporan (frontend)

**Files:**
- Create: `resources/js/types/report.ts`, `resources/js/lib/recap.ts`, `resources/js/lib/recap.test.ts`, `resources/js/Pages/Laporan/Index.vue`
- Modify: `resources/js/config/menuIcons.ts`, `resources/js/config/menuIcons.test.ts`, `resources/js/types/dashboard.ts:1`

**Interfaces:**
- Consumes: props `recap` dan `filters` dari `laporan.index`; rute `laporan.index`, `laporan.ekspor` (Ziggy `route()`); ikon menu `file-spreadsheet` (Task 2).
- Produces: `recapTable(recap: Recap): { columns: TableColumn[]; rows: RecapTableRow[] }`.

- [ ] **Step 1: Tulis tes Vitest yang gagal**

`resources/js/lib/recap.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { recapTable } from './recap';

const recap = {
    columns: [
        { key: 'diajukan', label: 'Diajukan' },
        { key: 'ditolak', label: 'Ditolak' },
    ],
    rows: [
        {
            school: 'SMPN A',
            counts: { diajukan: 2, ditolak: 0 },
            total: 2,
        },
    ],
    totals: { counts: { diajukan: 2, ditolak: 0 }, total: 2 },
};

describe('recapTable', () => {
    it('menyusun kolom Sekolah, status, dan Total', () => {
        const { columns } = recapTable(recap);

        expect(columns.map((c) => c.key)).toEqual([
            'school',
            'diajukan',
            'ditolak',
            'total',
        ]);
        expect(columns.map((c) => c.label)).toEqual([
            'Sekolah',
            'Diajukan',
            'Ditolak',
            'Total',
        ]);
    });

    it('meratakan baris dan menambah baris total', () => {
        const { rows } = recapTable(recap);

        expect(rows).toEqual([
            {
                school: 'SMPN A',
                diajukan: 2,
                ditolak: 0,
                total: 2,
                isTotal: false,
            },
            {
                school: 'Total',
                diajukan: 2,
                ditolak: 0,
                total: 2,
                isTotal: true,
            },
        ]);
    });

    it('tanpa sekolah tidak menambah baris total', () => {
        const empty = {
            ...recap,
            rows: [],
            totals: { counts: { diajukan: 0, ditolak: 0 }, total: 0 },
        };

        expect(recapTable(empty).rows).toEqual([]);
    });
});
```

Tambahkan ke `resources/js/config/menuIcons.test.ts`: impor `FileSpreadsheet` dari `@lucide/vue` dan baris `expect(menuIcon('file-spreadsheet')).toBe(FileSpreadsheet);` pada tes pemetaan.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `npx vitest run resources/js/lib/recap.test.ts resources/js/config/menuIcons.test.ts`
Expected: FAIL (`./recap` tidak ada; ikon belum terdaftar).

- [ ] **Step 3: Implementasi**

`resources/js/types/report.ts`:

```ts
export interface RecapColumn {
    key: string;
    label: string;
}

export interface RecapRow {
    school: string;
    counts: Record<string, number>;
    total: number;
}

export interface Recap {
    columns: RecapColumn[];
    rows: RecapRow[];
    totals: { counts: Record<string, number>; total: number };
}

export interface ReportFilters {
    dari: string | null;
    sampai: string | null;
}
```

`resources/js/lib/recap.ts`:

```ts
import type { Recap } from '@/types/report';

export interface TableColumn {
    key: string;
    label: string;
    class?: string;
}

export type RecapTableRow = Record<string, string | number | boolean>;

/** Meratakan rekap server menjadi kolom dan baris `DataTable`; baris total hanya ada bila ada sekolah. */
export function recapTable(recap: Recap): {
    columns: TableColumn[];
    rows: RecapTableRow[];
} {
    const numeric = 'w-[96px] text-right';
    const columns: TableColumn[] = [
        { key: 'school', label: 'Sekolah', class: 'min-w-[220px]' },
        ...recap.columns.map((c) => ({
            key: c.key,
            label: c.label,
            class: numeric,
        })),
        { key: 'total', label: 'Total', class: numeric },
    ];
    const flat = (
        school: string,
        counts: Record<string, number>,
        total: number,
        isTotal: boolean,
    ): RecapTableRow => ({ school, ...counts, total, isTotal });

    const rows = recap.rows.map((r) => flat(r.school, r.counts, r.total, false));
    if (rows.length) {
        rows.push(
            flat('Total', recap.totals.counts, recap.totals.total, true),
        );
    }

    return { columns, rows };
}
```

`resources/js/config/menuIcons.ts`: impor `FileSpreadsheet` dan tambahkan `'file-spreadsheet': FileSpreadsheet,` ke peta.

`resources/js/types/dashboard.ts` baris 1: ganti komentar menjadi `/** Props Beranda; disuplai `DashboardService` dan `DashboardController`. */`.

`resources/js/Pages/Laporan/Index.vue`:

```vue
<script setup lang="ts">
import DataTable from '@/Components/shared/DataTable.vue';
import EmptyState from '@/Components/shared/EmptyState.vue';
import FormField from '@/Components/shared/FormField.vue';
import PageHeader from '@/Components/shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import { recapTable, type RecapTableRow } from '@/lib/recap';
import type { Recap, ReportFilters } from '@/types/report';
import { router, usePage } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, reactive } from 'vue';

const props = defineProps<{ recap: Recap; filters: ReportFilters }>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const form = reactive({
    dari: props.filters.dari ?? '',
    sampai: props.filters.sampai ?? '',
});
const hasFilters = computed(() => form.dari !== '' || form.sampai !== '');
const table = computed(() => recapTable(props.recap));

/** Hanya kirim batas yang terisi. */
const query = (v: { dari: string; sampai: string }) =>
    Object.fromEntries(Object.entries(v).filter(([, x]) => x !== ''));

// Ekspor mengikuti filter yang sedang diterapkan di server, bukan isian yang belum disimpan.
const exportHref = computed(() =>
    route(
        'laporan.ekspor',
        query({
            dari: props.filters.dari ?? '',
            sampai: props.filters.sampai ?? '',
        }),
    ),
);

function apply() {
    router.get(route('laporan.index'), query(form), { preserveScroll: true });
}

function reset() {
    form.dari = '';
    form.sampai = '';
    apply();
}

function cell(row: RecapTableRow, key: string): string {
    const value = row[key];

    return typeof value === 'number' ? formatNumber(value) : String(value);
}
</script>

<template>
    <AppLayout title="Laporan">
        <PageHeader
            title="Rekap pendaftaran"
            description="Jumlah entri per sekolah dan status, menurut tanggal pengajuan pendaftaran."
        >
            <template #actions>
                <Button as-child class="h-10 px-4">
                    <a :href="exportHref">
                        <Download class="size-4" aria-hidden="true" />
                        Ekspor Excel
                    </a>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <form
                class="grid grid-cols-2 items-start gap-4 lg:flex"
                novalidate
                @submit.prevent="apply"
            >
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Dari tanggal"
                    :error="errors.dari"
                    class="lg:w-[200px]"
                >
                    <Input
                        :id="id"
                        v-model="form.dari"
                        type="date"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <FormField
                    v-slot="{ id, describedBy, invalid }"
                    label="Sampai tanggal"
                    :error="errors.sampai"
                    class="lg:w-[200px]"
                >
                    <Input
                        :id="id"
                        v-model="form.sampai"
                        type="date"
                        :aria-invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </FormField>
                <div class="col-span-2 flex items-center gap-2 lg:pt-7">
                    <Button type="submit" class="h-10 px-4">Terapkan</Button>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="outline"
                        class="h-10 px-4"
                        @click="reset"
                    >
                        Reset
                    </Button>
                </div>
            </form>

            <div class="flex flex-col gap-4">
                <p
                    class="text-muted-foreground flex items-center gap-2 text-xs leading-4 md:hidden"
                >
                    <span aria-hidden="true">↔</span> Geser tabel untuk melihat
                    kolom lainnya
                </p>
                <DataTable
                    :columns="table.columns"
                    :rows="table.rows"
                    row-class="h-12"
                    table-class="min-w-[960px]"
                >
                    <template
                        v-for="column in table.columns"
                        :key="column.key"
                        #[`cell-${column.key}`]="{ row }"
                    >
                        <span :class="{ 'font-semibold': row.isTotal }">{{
                            cell(row, column.key)
                        }}</span>
                    </template>
                    <template #empty>
                        <EmptyState
                            title="Belum ada sekolah"
                            description="Tambahkan sekolah di Master Data untuk melihat rekap."
                        />
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 4: Format, uji, typecheck, lint**

Run: `npx prettier --write resources/js/lib/recap.ts resources/js/lib/recap.test.ts resources/js/types/report.ts resources/js/Pages/Laporan/Index.vue resources/js/config/menuIcons.ts resources/js/config/menuIcons.test.ts resources/js/types/dashboard.ts && npx vitest run && npm run typecheck && npm run lint`
Expected: semua hijau. Bila `Button` tidak punya `variant="outline"`, cek varian di `resources/js/Components/ui/button` dan pakai varian sekunder yang ada (mis. dipakai tombol "Reset" di halaman lain). Bila typecheck menolak `v-for` pada `<template #[...]>`, ganti dengan satu slot per kolom lewat komponen kecil, tanpa mengubah perilaku.

- [ ] **Step 5: Build**

Run: `npm run build`
Expected: sukses tanpa error.

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/report.ts resources/js/lib/recap.ts resources/js/lib/recap.test.ts resources/js/Pages/Laporan resources/js/config/menuIcons.ts resources/js/config/menuIcons.test.ts resources/js/types/dashboard.ts
git commit -m "feat: tambah halaman rekap laporan" -m "Halaman Laporan memakai DataTable dengan baris yang diratakan oleh lib/recap (diuji), filter tanggal awal dan akhir dengan pesan galat dari server, tombol Ekspor Excel yang membawa filter aktif, petunjuk geser tabel di mobile, dan empty state. Ikon menu file-spreadsheet didaftarkan."
```

---

### Task 5: Verifikasi, review, dokumentasi, dan penutupan

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (bagian 5.6, tambah 5.28), `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (status Fase 6a)
- Memory (di luar repo): `C:\Users\abdulaziz\.claude\projects\C--Users-abdulaziz-Documents-pribadi-SIJEBOL\memory\deferred-items.md`

- [ ] **Step 1: Suite penuh backend di background**

Run (background): `php artisan test` lalu pantau sampai selesai. Expected: semua lulus (356 sebelumnya + tes baru). Juga `vendor/bin/pint --test` dan `vendor/bin/phpstan analyse --memory-limit=1G` bersih.

- [ ] **Step 2: Verifikasi browser (Playwright)**

Jalankan `php artisan serve --port=8099` (background) dan `npm run build`. Login lewat `browser_run_code_unsafe` dengan `page.getByLabel('Email').fill(...)`. Periksa pada 1440 dan 390 px:
- `sari@example.com` (password `password`): Beranda memuat 3 kartu angka dan aksi nyata; `/laporan` menampilkan tabel, baris total, filter, dan tombol Ekspor; isi filter tanggal terbalik menampilkan pesan galat; unduh `rekap-pendaftaran.xlsx` berhasil; menu "Laporan" ada.
- `op.siswa@example.com`: Beranda memuat kartu Draf/Perlu perbaikan/Sudah rekaman milik sekolahnya saja; `/laporan` dan `/laporan/ekspor` menjawab 403; tidak ada menu Laporan.
- Tidak ada overflow horizontal halaman pada 390 px (tabel boleh bergeser di dalam wadahnya, petunjuk geser tampil), konsol tanpa galat nyata.
Matikan server, hapus tangkapan layar.

- [ ] **Step 3: Review**

Jalankan `ponytail:ponytail-review` pada diff Fase 6a (`git diff 670d60d..HEAD -- app routes resources/js lang tests`) dan `impeccable` (critique) pada `Pages/Laporan/Index.vue` dan Beranda dengan panduan UI. Perbaiki temuan valid dengan TDD, jalankan ulang tes terkait.

- [ ] **Step 4: Dokumentasi**

Panduan UI: perbarui 5.6 (kartu per peran dan aksi sekarang berasal dari data nyata) dan tambahkan 5.28 "Laporan `/laporan`" (header, filter Dari/Sampai, tombol Ekspor Excel, tabel dengan baris total, petunjuk geser, empty state, state galat validasi). Sprint plan: tambah catatan "Status Fase 6a (2026-10-09)" yang merangkum hasil dan menyatakan 6b dan 6c belum dimulai, rilis ditahan. Memory `deferred-items.md`: hapus butir Beranda placeholder; pindahkan butir kelompok A, B, C ke daftar "Fase 6b/6c (disetujui masuk)"; kelompok D tetap tertunda.

- [ ] **Step 5: Verifikasi akhir dan commit**

Jalankan ulang: suite BE penuh, Pint, Larastan, `npx vitest run`, `npm run typecheck`, `npm run lint`, `npm run build`. Semua harus hijau sebelum klaim selesai.

```bash
git add docs/superpowers
git commit -m "docs: tandai Fase 6a selesai dan perbarui panduan UI" -m "Panduan UI memuat bagian Laporan (5.28) dan Beranda berdata nyata (5.6); rencana sprint mencatat status Fase 6a, menyatakan 6b dan 6c belum dimulai, dan rilis masih ditahan."
```

(Perbaikan hasil review, bila ada, di-commit terpisah dengan pesan "refactor: terapkan hasil review Fase 6a" sebelum commit dokumentasi ini.)
