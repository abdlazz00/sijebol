# Fase 6b — Alur lapangan: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup celah operasional lapangan: batalkan penandaan rekaman, hapus jadwal kosong, pindah siswa antar-jadwal, notifikasi penandaan, notifikasi massal verifikasi, urutan jadwal terdekat-dulu, dan persetujuan massal.

**Architecture:** Semua perubahan status tetap lewat `StatusTransitioner::move`. `RecordingService` mendapat `undo` dan notifikasi; `ScheduleService` mendapat `delete` dan `move`; `VerificationService` mendapat `approveMany` (atomik, memeriksa ulang kelayakan lewat `EligibilityService`). Controller dan route baru mengikuti pola yang ada; frontend menambah dialog koreksi di halaman Jadwal dan ceklis massal di antrean Verifikasi.

**Tech Stack:** Laravel 13 / PHP 8.5 / MySQL 8, spatie permission + activitylog, Pest, Larastan level 5, Pint, Vue 3 + TS + Inertia v2, Vitest, ESLint, Playwright (verifikasi).

**Spec:** `docs/superpowers/specs/2026-10-09-fase-6b-alur-lapangan-design.md`

## Global Constraints

- Controller → FormRequest → Service → Repository; repository = interface di `Repositories/Contracts` + `Eloquent*`; semua perubahan status entri hanya lewat `StatusTransitioner::move`.
- Pembatalan penandaan: satu entri per aksi, catatan wajib (maks 500), `schedule_id` dipertahankan, ditolak bila siswa punya entri aktif lain; izin `rekaman.tandai`.
- Hapus jadwal: hanya jadwal tanpa satu pun entri (termasuk yang sudah ditandai); izin `jadwal.ubah`, tanpa permission baru.
- Pindah antar-jadwal: jadwal tujuan milik sekolah yang sama, tanggal hari ini atau setelahnya, entri `Terjadwal`; dari jadwal yang sudah lewat tetapi belum ditandai tetap boleh; atomik dengan dua baris riwayat dan satu notifikasi.
- Setujui massal: maksimal 50 entri, atomik, kelayakan diperiksa ulang tiap entri (umur pada tanggal pengajuan, dokumen KK dan akta, tidak ada entri aktif lain); satu `EntryDecidedNotification` per pendaftaran.
- Urutan daftar jadwal: yang akan datang dulu (terdekat → terjauh), lalu yang lewat (terbaru → terlama); filter Periode tidak berubah; memakai `Schedule::today()`.
- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; tabel lebar di mobile wajib punya petunjuk "Geser tabel untuk melihat kolom lainnya" (`md:hidden`).
- Pest dataset tidak boleh berisi closure (pakai nama kasus + `match`); tes yang memicu `toSchool/toPermission` harus membuat permission terkait (`Permission::findOrCreate`); helper tes global diberi nama unik; jangan edit PHP dengan Python heredoc yang memuat backslash namespace (pakai Edit/Write).
- Suite penuh backend >120 detik: jalankan di background. File FE baru dijalankan `npx prettier --write`.
- Commit: pesan detail, **tanpa** `Co-Authored-By` atau atribusi tool/AI apa pun. Persetujuan rencana ini mencakup langkah commit di dalamnya.

## Rulings (keputusan saya yang melengkapi spec)

- **R1:** `RegistrationRepositoryInterface` mendapat `lockEntries(array $ids)` (kunci baris + memuat siswa, dokumen, entri siswa, pendaftaran) agar `VerificationService` tetap lewat repository.
- **R2:** `ScheduleRepositoryInterface` mendapat `delete(Schedule)` dan `targets(Schedule $current)` (jadwal tujuan pindah).
- **R3:** Controller memindahkan siswa dengan `Schedule::query()->findOrFail($id)` (terkena scope sekolah, sama seperti route binding) lalu menyerahkan objeknya ke `ScheduleService::move`.
- **R4:** Kunci untuk pemeriksaan entri aktif lain di `undo` memakai `lockForUpdate()->pluck('id')`, bukan `exists()`, supaya klausa kunci valid di MySQL.
- **R5:** `PermissionRegistrar`/seeder tidak berubah (tanpa permission baru), sehingga matriks role dan tes izin tidak terdampak.

## Review Focus

1. Siswa yang sudah didaftarkan ulang (entri aktif lain) tidak boleh kembali ke `Terjadwal` lewat pembatalan; status akhir tetap — tes di Task 1.
2. Jadwal yang hanya memuat entri yang sudah ditandai tidak boleh terhapus (riwayat yatim) — tes di Task 2.
3. Satu entri tidak layak (umur di luar 16-17 atau dokumen kurang) membatalkan seluruh persetujuan massal dan tidak mengirim notifikasi — tes di Task 5.
4. Persetujuan massal dengan id ganda, id tidak ada, entri sekolah lain, entri yang sudah diputuskan, atau lebih dari 50 id ditolak seluruhnya — tes di Task 5.
5. Pindah ke jadwal hari ini diperbolehkan, dari jadwal lewat yang belum ditandai diperbolehkan, ke jadwal sendiri, sekolah lain, atau yang sudah lewat ditolak tanpa mengubah entri — tes di Task 2.

---

## File Structure

**Dibuat**
- `app/Domains/Schedules/Http/Requests/{UndoMarkingRequest,MoveRequest}.php`
- `app/Domains/Registrations/Http/Requests/ApproveManyRequest.php`
- `tests/Feature/Schedules/{ScheduleMoveDeleteTest,ScheduleCorrectionsHttpTest}.php`

**Diubah**
- `app/Domains/Registrations/Enums/RegistrationStatus.php`, `app/Domains/Notifications/Notifications/ScheduleNotification.php`
- `app/Domains/Schedules/Services/{RecordingService,ScheduleService}.php`, `app/Domains/Schedules/Repositories/{Contracts/ScheduleRepositoryInterface,EloquentScheduleRepository}.php`
- `app/Domains/Schedules/Http/Controllers/{RecordingController,ScheduleController,ScheduleStudentController}.php`, `routes/schedules.php`
- `app/Domains/Registrations/Services/VerificationService.php`, `app/Domains/Registrations/Repositories/{Contracts/RegistrationRepositoryInterface,EloquentRegistrationRepository}.php`, `app/Domains/Registrations/Http/Controllers/VerificationController.php`, `routes/registrations.php`
- `lang/id/activity.php`
- Tes: `tests/Feature/Schedules/{ScheduleModelTest,ScheduleServiceTest,RecordingServiceTest,ScheduleHttpTest}.php`, `tests/Feature/Registrations/{VerificationServiceTest,VerificationHttpTest}.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`
- FE: `resources/js/Pages/Jadwal/{Rekaman,Show}.vue`, `resources/js/Pages/Verifikasi/Index.vue`, `resources/js/types/schedule.ts`
- Dokumen: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`, `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`; memory `deferred-items.md`.

---

### Task 1: Transisi pembatalan, notifikasi penandaan, dan `RecordingService::undo`

**Files:**
- Modify: `app/Domains/Registrations/Enums/RegistrationStatus.php:37-48`, `app/Domains/Notifications/Notifications/ScheduleNotification.php`, `app/Domains/Schedules/Services/RecordingService.php`, `lang/id/activity.php`
- Test: `tests/Feature/Schedules/{ScheduleModelTest,ScheduleServiceTest,RecordingServiceTest}.php`

**Interfaces:**
- Produces: `RegistrationStatus::{SudahRekaman,TidakHadir,BelumRekaman}->next() === [Terjadwal]`; `ScheduleNotification::marked(int $scheduleId, int $count, string $statusLabel, string $date): self`, `::undone(int $scheduleId, string $statusLabel): self`; `RecordingService::undo(RegistrationStudent $entry, string $note, User $actor): void`; `RecordingService::mark` kini mengirim satu notifikasi `marked` ke operator sekolah.

- [ ] **Step 1: Perbarui dan tambah tes yang gagal**

`tests/Feature/Schedules/ScheduleModelTest.php` — ganti tiga baris status akhir (baris 14-16) menjadi:

```php
        ->and(S::SudahRekaman->next())->toBe([S::Terjadwal])
        ->and(S::TidakHadir->next())->toBe([S::Terjadwal])
        ->and(S::BelumRekaman->next())->toBe([S::Terjadwal]);
```
(titik koma hanya di akhir baris terakhir; pertahankan struktur `expect(...)->and(...)` di sekelilingnya.)

`tests/Feature/Schedules/ScheduleServiceTest.php` — di akhir tes `isi notifikasi memuat tanggal dan lokasi`, setelah pernyataan `expect(...)` yang ada dan sebelum `});`, tambahkan blok `expect` kedua:

```php
    $marked = ScheduleNotification::marked(7, 2, 'Tidak hadir', '2026-12-01');
    $undone = ScheduleNotification::undone(7, 'Sudah rekaman');

    expect($marked->title())->toBe('Rekaman ditandai')
        ->and($marked->body())->toBe('2 siswa ditandai Tidak hadir pada 1 Desember 2026.')
        ->and($marked->url())->toBe('/jadwal/7')
        ->and($undone->title())->toBe('Penandaan dibatalkan')
        ->and($undone->body())->toBe('Penandaan Sudah rekaman dibatalkan; siswa kembali Terjadwal.');
```

`tests/Feature/Schedules/RecordingServiceTest.php` — tambahkan `use App\Domains\Notifications\Notifications\ScheduleNotification;` dan `use Spatie\Permission\Models\Permission;`; pada `beforeEach` tambahkan baris pertama `Permission::findOrCreate('jadwal.lihat', 'web');` (penerima notifikasi dicari lewat permission ini). Lalu tambahkan di akhir file:

```php
test('penandaan massal memberi tahu operator sekolah itu satu kali', function () {
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-mark');
    $lain = makeUserWith(['jadwal.lihat'], School::factory()->create(), true, 'op-mark');
    $a = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $b = makeScheduledEntry(S::Terjadwal, $this->hariIni);

    $this->service->mark($this->hariIni, [$a->id, $b->id], S::TidakHadir, null, $this->admin);

    Notification::assertSentToTimes($op, ScheduleNotification::class, 1);
    Notification::assertSentTo($op, ScheduleNotification::class, fn (ScheduleNotification $n) => $n->title() === 'Rekaman ditandai' && str_starts_with($n->body(), '2 siswa ditandai Tidak hadir'));
    Notification::assertNotSentTo($lain, ScheduleNotification::class);
});

test('batalkan penandaan mengembalikan ke Terjadwal dengan jadwal utuh, riwayat, log, dan notifikasi', function (S $status) {
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-undo');
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $this->service->mark($this->hariIni, [$e->id], $status, null, $this->admin);

    $this->service->undo($e->fresh(), 'Salah tandai', $this->admin);

    expect($e->fresh()->status)->toBe(S::Terjadwal)
        ->and($e->fresh()->schedule_id)->toBe($this->hariIni->id)
        ->and($e->fresh()->note)->toBe('Salah tandai')
        ->and($e->histories()->count())->toBe(2)
        ->and(Activity::where('description', 'rekaman.dibatalkan')->count())->toBe(1);
    Notification::assertSentTo($op, ScheduleNotification::class, fn (ScheduleNotification $n) => $n->title() === 'Penandaan dibatalkan');
})->with([[S::SudahRekaman], [S::TidakHadir], [S::BelumRekaman]]);

test('batalkan hanya untuk entri yang sudah ditandai', function () {
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);

    expect(fn () => $this->service->undo($e, 'x', $this->admin))->toThrow(AccessRuleException::class);
    expect($e->fresh()->status)->toBe(S::Terjadwal)->and($e->histories()->count())->toBe(0);
});

test('batalkan ditolak bila siswa sudah punya pendaftaran aktif lain', function () {
    $student = eligibleStudent($this->school);
    $lama = makeEntry(S::Terjadwal, $this->school, $student);
    $lama->update(['schedule_id' => $this->hariIni->id]);
    $this->service->mark($this->hariIni, [$lama->id], S::TidakHadir, null, $this->admin);
    makeEntry(S::Diajukan, $this->school, $student);

    expect(fn () => $this->service->undo($lama->fresh(), 'x', $this->admin))->toThrow(AccessRuleException::class);

    expect($lama->fresh()->status)->toBe(S::TidakHadir)->and(Activity::where('description', 'rekaman.dibatalkan')->count())->toBe(0);
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Schedules/ScheduleModelTest.php tests/Feature/Schedules/ScheduleServiceTest.php tests/Feature/Schedules/RecordingServiceTest.php`
Expected: FAIL (peta transisi lama, `ScheduleNotification::marked` tidak ada, `undo` tidak ada).

- [ ] **Step 3: Implementasi minimal**

`RegistrationStatus::next()` — ganti baris `default => [],` dan tambahkan arm sebelum `default`:

```php
            self::SudahRekaman, self::TidakHadir, self::BelumRekaman => [self::Terjadwal],
            default => [],
```
dan ubah docblock menjadi "Transisi yang sah dari status ini (state machine spec v1 §3.7, ditambah keluarkan dari jadwal dan batalkan penandaan)."

`ScheduleNotification` — tambahkan setelah `changed`:

```php
    public static function marked(int $scheduleId, int $count, string $statusLabel, string $date): self
    {
        return new self($scheduleId, 'Rekaman ditandai', "{$count} siswa ditandai {$statusLabel} pada ".self::format($date).'.');
    }

    public static function undone(int $scheduleId, string $statusLabel): self
    {
        return new self($scheduleId, 'Penandaan dibatalkan', "Penandaan {$statusLabel} dibatalkan; siswa kembali Terjadwal.");
    }
```

`RecordingService` (ganti isi kelas sesuai di bawah; impor tambahan `ScheduleNotification`, `NotificationService`, `UniqueConstraintViolationException`):

```php
<?php

namespace App\Domains\Schedules\Services;

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Registrations\Services\StatusTransitioner;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordingService
{
    /** Status kehadiran yang boleh ditandai; ketiganya dapat dibatalkan kembali ke Terjadwal. */
    private const MARKS = [RegistrationStatus::SudahRekaman, RegistrationStatus::TidakHadir, RegistrationStatus::BelumRekaman];

    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly StatusTransitioner $transitions,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Menandai siswa Terjadwal pada jadwal ini; seluruhnya berhasil atau seluruhnya gagal.
     *
     * @param  list<int>  $entryIds
     */
    public function mark(Schedule $schedule, array $entryIds, RegistrationStatus $to, ?string $note, User $actor): void
    {
        // Tanpa guard ini, Terverifikasi (transisi sah dari Terjadwal) menjadi "keluarkan" tanpa mengosongkan schedule_id.
        if (! in_array($to, self::MARKS, true)) {
            throw new AccessRuleException('Status penandaan tidak valid.');
        }
        if (! $schedule->isOpenForMarking()) {
            throw new AccessRuleException('Penandaan baru dapat dilakukan pada atau setelah tanggal jadwal.');
        }

        $count = DB::transaction(function () use ($schedule, $entryIds, $to, $note, $actor) {
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

            return $entries->count();
        });

        $this->notifications->toSchool(
            'jadwal.lihat',
            ScheduleNotification::marked($schedule->id, $count, $to->label(), $schedule->date->toDateString()),
            $schedule->school_id,
        );
    }

    /** Membatalkan penandaan satu siswa: kembali ke Terjadwal pada jadwal yang sama, dengan catatan. */
    public function undo(RegistrationStudent $entry, string $note, User $actor): void
    {
        if (! in_array($entry->status, self::MARKS, true) || $entry->schedule_id === null) {
            throw new AccessRuleException('Hanya siswa yang sudah ditandai yang dapat dibatalkan penandaannya.');
        }
        $previous = $entry->status;
        $schedule = $entry->schedule;
        $conflict = 'Siswa sudah memiliki pendaftaran aktif lain; penandaan tidak dapat dibatalkan.';

        try {
            DB::transaction(function () use ($entry, $note, $actor, $previous, $schedule, $conflict) {
                // Tidak hadir dan Belum rekaman melepas slot entri aktif; siswa mungkin sudah didaftarkan ulang.
                $busy = RegistrationStudent::query()
                    ->where('student_id', $entry->student_id)->where('id', '!=', $entry->id)
                    ->whereIn('status', RegistrationStatus::values(RegistrationStatus::active()))
                    ->lockForUpdate()->pluck('id')->isNotEmpty();
                if ($busy) {
                    throw new AccessRuleException($conflict);
                }

                $this->transitions->move($entry, RegistrationStatus::Terjadwal, $actor, $note);
                activity('jadwal')->performedOn($schedule)->causedBy($actor)
                    ->withProperties(['status_lama' => $previous->value, 'siswa_id' => $entry->student_id])->log('rekaman.dibatalkan');
            });
        } catch (UniqueConstraintViolationException) {
            // Balapan: pendaftaran aktif baru masuk setelah pemeriksaan di atas; indeks unik active_student_id menolaknya.
            throw new AccessRuleException($conflict);
        }

        $this->notifications->toSchool(
            'jadwal.lihat',
            ScheduleNotification::undone($schedule->id, $previous->label()),
            $schedule->school_id,
        );
    }
}
```

`lang/id/activity.php` — tambahkan di `actions`: `'rekaman.dibatalkan' => 'Penandaan rekaman dibatalkan',`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Schedules tests/Feature/Registrations`
Expected: PASS. Bila tes lama gagal karena `PermissionDoesNotExist` pada `jadwal.lihat`, tambahkan `Permission::findOrCreate('jadwal.lihat', 'web');` pada `beforeEach` tes itu (alasan sama dengan Step 1).

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app tests lang && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Registrations/Enums/RegistrationStatus.php app/Domains/Notifications/Notifications/ScheduleNotification.php app/Domains/Schedules/Services/RecordingService.php lang/id/activity.php tests/Feature/Schedules
git commit -m "feat: tambah pembatalan penandaan rekaman dan notifikasi penandaan" -m "Status akhir (Sudah rekaman, Tidak hadir, Belum rekaman) kini dapat kembali ke Terjadwal lewat StatusTransitioner dengan catatan wajib; jadwal dipertahankan dan pembatalan ditolak bila siswa sudah punya entri aktif lain (termasuk balapan lewat indeks unik). Penandaan massal dan pembatalannya memberi tahu operator sekolah lewat ScheduleNotification, dan keduanya tercatat di log aktivitas."
```

---

### Task 2: Hapus jadwal dan pindah antar-jadwal (service dan repository)

**Files:**
- Modify: `app/Domains/Schedules/Repositories/Contracts/ScheduleRepositoryInterface.php`, `app/Domains/Schedules/Repositories/EloquentScheduleRepository.php`, `app/Domains/Schedules/Services/ScheduleService.php`, `lang/id/activity.php`
- Create/Test: `tests/Feature/Schedules/ScheduleMoveDeleteTest.php`

**Interfaces:**
- Consumes: `ScheduleNotification::assigned` (Fase 5), `StatusTransitioner::move`.
- Produces: `ScheduleRepositoryInterface::delete(Schedule): void`, `::targets(Schedule $current): Collection<Schedule>` (sekolah sama, `date >= today`, id berbeda, urut tanggal naik); `ScheduleService::delete(Schedule, User): void`, `::move(RegistrationStudent $entry, Schedule $target, User $actor): void`, `::targets(Schedule $current): Collection`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Schedules/ScheduleMoveDeleteTest.php`:

```php
<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Audit\Services\ActivityLogService;
use App\Domains\Notifications\Notifications\ScheduleNotification;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Schools\Models\School;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-mv');
    $this->admin = makeUserWith(['jadwal.ubah'], roleName: 'admin-mv');
    $this->service = app(ScheduleService::class);
    $this->besok = now('Asia/Jakarta')->addDay()->toDateString();
    $this->lusa = now('Asia/Jakarta')->addDays(2)->toDateString();
});

test('jadwal kosong dihapus dan tercatat di log', function () {
    $jadwal = makeSchedule($this->school, $this->besok);

    $this->service->delete($jadwal, $this->admin);

    expect(Schedule::count())->toBe(0)
        ->and(Activity::where('description', 'jadwal.dihapus')->count())->toBe(1);
});

test('baris log jadwal yang sudah dihapus tetap tampil dengan label #id', function () {
    $jadwal = makeSchedule($this->school, $this->besok);
    $this->service->delete($jadwal, $this->admin);

    $baris = collect(app(ActivityLogService::class)->list()->items())->firstWhere('action', 'Jadwal dihapus');

    expect($baris['subject']['label'])->toBe('#'.$jadwal->id);
});

test('jadwal yang masih memiliki entri, termasuk yang sudah ditandai, tidak dapat dihapus', function (string $kasus) {
    $jadwal = makeSchedule($this->school, $this->besok);
    match ($kasus) {
        'terjadwal' => makeScheduledEntry(S::Terjadwal, $jadwal),
        'sudah ditandai' => makeScheduledEntry(S::SudahRekaman, $jadwal),
    };

    expect(fn () => $this->service->delete($jadwal, $this->admin))->toThrow(AccessRuleException::class);

    expect(Schedule::count())->toBe(1)->and(Activity::where('description', 'jadwal.dihapus')->count())->toBe(0);
})->with(['terjadwal', 'sudah ditandai']);

test('pindah memindahkan entri atomik dengan dua riwayat, log, dan satu notifikasi ke tujuan', function () {
    $asal = makeSchedule($this->school, $this->besok);
    $tujuan = makeSchedule($this->school, $this->lusa);
    $e = makeScheduledEntry(S::Terjadwal, $asal);

    $this->service->move($e, $tujuan, $this->admin);

    expect($e->fresh()->status)->toBe(S::Terjadwal)->and($e->fresh()->schedule_id)->toBe($tujuan->id)
        ->and($e->histories()->count())->toBe(2)
        ->and(Activity::where('description', 'jadwal.dipindahkan')->count())->toBe(1);
    Notification::assertSentToTimes($this->op, ScheduleNotification::class, 1);
    Notification::assertSentTo($this->op, ScheduleNotification::class, fn (ScheduleNotification $n) => $n->url() === "/jadwal/{$tujuan->id}");
});

test('pindah ke jadwal hari ini dan dari jadwal lewat yang belum ditandai diperbolehkan', function () {
    $hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
    $lewat = makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());
    $e = makeScheduledEntry(S::Terjadwal, $lewat);

    $this->service->move($e, $hariIni, $this->admin);

    expect($e->fresh()->schedule_id)->toBe($hariIni->id);
});

test('pindah yang tidak sah ditolak tanpa mengubah entri', function (string $kasus) {
    $asal = makeSchedule($this->school, $this->besok);
    $e = makeScheduledEntry(S::Terjadwal, $asal);
    [$entri, $tujuan] = match ($kasus) {
        'jadwal sama' => [$e, $asal],
        'sekolah lain' => [$e, makeSchedule(null, $this->lusa)],
        'jadwal lewat' => [$e, makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString())],
        'bukan Terjadwal' => [makeEntry(S::Terverifikasi, $this->school), makeSchedule($this->school, $this->lusa)],
    };

    expect(fn () => $this->service->move($entri, $tujuan, $this->admin))->toThrow(AccessRuleException::class);

    expect($e->fresh()->schedule_id)->toBe($asal->id)->and($e->fresh()->status)->toBe(S::Terjadwal)
        ->and($e->histories()->count())->toBe(0);
    Notification::assertNothingSent();
})->with(['jadwal sama', 'sekolah lain', 'jadwal lewat', 'bukan Terjadwal']);

test('jadwal tujuan: sekolah sama, hari ini atau setelahnya, tanpa jadwal sendiri, tanggal naik', function () {
    $sendiri = makeSchedule($this->school, $this->besok);
    $lusa = makeSchedule($this->school, $this->lusa);
    $hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
    makeSchedule($this->school, now('Asia/Jakarta')->subDay()->toDateString());
    makeSchedule(null, $this->besok);

    $ids = $this->service->targets($sendiri)->pluck('id')->all();

    expect($ids)->toBe([$hariIni->id, $lusa->id]);
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Schedules/ScheduleMoveDeleteTest.php`
Expected: FAIL (`delete`, `move`, `targets` belum ada).

- [ ] **Step 3: Implementasi**

`ScheduleRepositoryInterface` — tambahkan sebelum `candidates`:

```php
    public function delete(Schedule $schedule): void;

    /**
     * Jadwal tujuan pindah: sekolah sama, hari ini atau setelahnya, selain `$current`, tanggal naik.
     *
     * @return Collection<int, Schedule>
     */
    public function targets(Schedule $current): Collection;
```

`EloquentScheduleRepository` — tambahkan:

```php
    public function delete(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function targets(Schedule $current): Collection
    {
        return Schedule::query()
            ->where('school_id', $current->school_id)->where('id', '!=', $current->id)
            ->where('date', '>=', Schedule::today())->orderBy('date')->orderBy('id')->get();
    }
```

`ScheduleService` — tambahkan di akhir kelas:

```php
    /** @return Collection<int, Schedule> */
    public function targets(Schedule $current): Collection
    {
        return $this->schedules->targets($current);
    }

    public function delete(Schedule $schedule, User $actor): void
    {
        // Entri yang sudah ditandai tetap memegang schedule_id sebagai jejak, jadi jadwal berriwayat tidak dapat dihapus.
        if ($schedule->entries()->exists()) {
            throw new AccessRuleException('Jadwal masih memiliki siswa; keluarkan atau batalkan penandaan mereka lebih dulu.');
        }

        DB::transaction(function () use ($schedule, $actor) {
            activity('jadwal')->performedOn($schedule)->causedBy($actor)->log('jadwal.dihapus');
            $this->schedules->delete($schedule);
        });
    }

    public function move(RegistrationStudent $entry, Schedule $target, User $actor): void
    {
        if ($entry->status !== RegistrationStatus::Terjadwal) {
            throw new AccessRuleException('Hanya siswa Terjadwal yang dapat dipindahkan.');
        }
        if ($target->id === $entry->schedule_id) {
            throw new AccessRuleException('Siswa sudah berada di jadwal ini.');
        }
        if ($target->school_id !== $entry->school_id) {
            throw new AccessRuleException('Jadwal tujuan harus milik sekolah yang sama.');
        }
        if ($target->isPast()) {
            throw new AccessRuleException('Jadwal tujuan sudah lewat.');
        }

        DB::transaction(function () use ($entry, $target, $actor) {
            $from = $entry->schedule;
            // Dua langkah lewat StatusTransitioner (keluarkan lalu tempatkan) agar peta transisi dan riwayat tetap utuh.
            $this->transitions->move($entry, RegistrationStatus::Terverifikasi, $actor, 'Dipindahkan dari jadwal '.$from->date->toDateString(), ['schedule_id' => null]);
            $this->transitions->move($entry, RegistrationStatus::Terjadwal, $actor, 'Dipindahkan ke jadwal '.$target->date->toDateString(), ['schedule_id' => $target->id]);
            activity('jadwal')->performedOn($target)->causedBy($actor)
                ->withProperties(['dari' => $from->id, 'siswa_id' => $entry->student_id])->log('jadwal.dipindahkan');
        });

        $this->notifications->toSchool(
            'jadwal.lihat',
            ScheduleNotification::assigned($target->id, 1, $target->date->toDateString(), $target->location),
            $target->school_id,
        );
    }
```

`lang/id/activity.php` — tambahkan di `actions`: `'jadwal.dihapus' => 'Jadwal dihapus',` dan `'jadwal.dipindahkan' => 'Siswa dipindahkan antar-jadwal',`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Schedules`
Expected: PASS.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app tests lang && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Schedules lang/id/activity.php tests/Feature/Schedules/ScheduleMoveDeleteTest.php
git commit -m "feat: tambah hapus jadwal kosong dan pindah siswa antar-jadwal" -m "ScheduleService::delete menolak jadwal yang masih memiliki entri (termasuk yang sudah ditandai) dan mencatat log. ScheduleService::move memindahkan siswa Terjadwal ke jadwal sekolah yang sama yang belum lewat dalam satu transaksi lewat StatusTransitioner (dua baris riwayat) dan memberi tahu operator. Repository mendapat delete dan daftar jadwal tujuan."
```

---

### Task 3: Rute, request, dan controller untuk batalkan, hapus, dan pindah

**Files:**
- Create: `app/Domains/Schedules/Http/Requests/{UndoMarkingRequest,MoveRequest}.php`
- Modify: `app/Domains/Schedules/Http/Controllers/{RecordingController,ScheduleController,ScheduleStudentController}.php`, `routes/schedules.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`
- Test: `tests/Feature/Schedules/ScheduleCorrectionsHttpTest.php`

**Interfaces:**
- Consumes: `RecordingService::undo`, `ScheduleService::{delete,move,targets}` (Task 1-2).
- Produces: rute `jadwal.destroy` (`DELETE /jadwal/{schedule}`), `jadwal.siswa.pindah` (`POST /jadwal/{schedule}/siswa/{entry}/pindah`, body `target_schedule_id`), `jadwal.rekaman.batalkan` (`POST /jadwal/{schedule}/rekaman/{entry}/batalkan`, body `note`); prop `targets: {id: int, date: string, location: string}[]` pada `Jadwal/Show` (kosong bila tanpa `jadwal.ubah`).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Schedules/ScheduleCorrectionsHttpTest.php`:

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
    $this->admin = makeUserWith(['jadwal.lihat', 'jadwal.ubah', 'rekaman.lihat', 'rekaman.tandai'], roleName: 'admin-koreksi');
    $this->hariIni = makeSchedule($this->school, now('Asia/Jakarta')->toDateString());
    $this->besok = makeSchedule($this->school, now('Asia/Jakarta')->addDay()->toDateString());
});

test('hapus jadwal kosong lewat HTTP; yang berisi memberi galat dan tetap ada', function () {
    $isi = makeSchedule($this->school, now('Asia/Jakarta')->addDays(3)->toDateString());
    makeScheduledEntry(S::Terjadwal, $isi);
    $this->actingAs($this->admin);

    $this->delete("/jadwal/{$this->besok->id}")->assertRedirect('/jadwal')->assertSessionHas('success');
    expect(Schedule::whereKey($this->besok->id)->exists())->toBeFalse();

    $this->delete("/jadwal/{$isi->id}")->assertSessionHas('error');
    expect(Schedule::whereKey($isi->id)->exists())->toBeTrue();
});

test('pindah lewat HTTP: sukses, target wajib, entri jadwal lain 404', function () {
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $lain = makeScheduledEntry(S::Terjadwal, $this->besok);
    $this->actingAs($this->admin);
    $url = "/jadwal/{$this->hariIni->id}/siswa/{$e->id}/pindah";

    $this->post($url, [])->assertSessionHasErrors('target_schedule_id');
    $this->post($url, ['target_schedule_id' => 999999])->assertSessionHasErrors('target_schedule_id');
    $this->post("/jadwal/{$this->hariIni->id}/siswa/{$lain->id}/pindah", ['target_schedule_id' => $this->besok->id])->assertNotFound();

    $this->post($url, ['target_schedule_id' => $this->besok->id])->assertRedirect("/jadwal/{$this->hariIni->id}")->assertSessionHas('success');
    expect($e->fresh()->schedule_id)->toBe($this->besok->id);
});

test('pindah ke jadwal sekolah lain menghasilkan galat tanpa mengubah entri', function () {
    $e = makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $asing = makeSchedule(null, now('Asia/Jakarta')->addDay()->toDateString());
    $this->actingAs($this->admin);

    $this->post("/jadwal/{$this->hariIni->id}/siswa/{$e->id}/pindah", ['target_schedule_id' => $asing->id])->assertSessionHas('error');
    expect($e->fresh()->schedule_id)->toBe($this->hariIni->id);
});

test('detail jadwal memuat jadwal tujuan hanya bagi pemegang jadwal.ubah', function () {
    makeScheduledEntry(S::Terjadwal, $this->hariIni);
    $op = makeUserWith(['jadwal.lihat'], $this->school, true, 'op-koreksi');

    $this->actingAs($this->admin)->get("/jadwal/{$this->hariIni->id}")->assertInertia(fn (Assert $p) => $p
        ->has('targets', 1)->where('targets.0.id', $this->besok->id)->where('targets.0.location', $this->besok->location));
    $this->actingAs($op)->get("/jadwal/{$this->hariIni->id}")->assertInertia(fn (Assert $p) => $p->has('targets', 0));
});

test('batalkan penandaan lewat HTTP: catatan wajib, sukses, entri jadwal lain 404, tanpa izin 403', function () {
    $e = makeScheduledEntry(S::SudahRekaman, $this->hariIni);
    $lain = makeScheduledEntry(S::SudahRekaman, $this->besok);
    $url = "/jadwal/{$this->hariIni->id}/rekaman/{$e->id}/batalkan";
    $this->actingAs($this->admin);

    $this->post($url, [])->assertSessionHasErrors('note');
    $this->post($url, ['note' => ''])->assertSessionHasErrors('note');
    $this->post("/jadwal/{$this->hariIni->id}/rekaman/{$lain->id}/batalkan", ['note' => 'x'])->assertNotFound();
    expect($e->fresh()->status)->toBe(S::SudahRekaman);

    $this->post($url, ['note' => 'Salah tandai'])->assertRedirect("/jadwal/{$this->hariIni->id}/rekaman")->assertSessionHas('success');
    expect($e->fresh()->status)->toBe(S::Terjadwal);

    $op = makeUserWith(['jadwal.lihat', 'rekaman.lihat'], $this->school, true, 'op-batal');
    $this->actingAs($op)->post($url, ['note' => 'x'])->assertForbidden();
});
```

`tests/Feature/Access/RoutePermissionMatrixTest.php` — tambahkan di dataset `rute` setelah baris `/laporan/ekspor`:

```php
    ['DELETE', '/jadwal/{schedule}', 'jadwal.ubah'],
    ['POST', '/jadwal/{schedule}/siswa/{slot}/pindah', 'jadwal.ubah'],
    ['POST', '/jadwal/{schedule}/rekaman/{slot}/batalkan', 'rekaman.tandai'],
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Schedules/ScheduleCorrectionsHttpTest.php`
Expected: FAIL (rute 404/405).

- [ ] **Step 3: Implementasi**

`app/Domains/Schedules/Http/Requests/UndoMarkingRequest.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UndoMarkingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Catatan wajib diisi untuk membatalkan penandaan.'];
    }
}
```

`app/Domains/Schedules/Http/Requests/MoveRequest.php`:

```php
<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['target_schedule_id' => ['required', 'integer', 'exists:schedules,id']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['target_schedule_id.required' => 'Pilih jadwal tujuan.', 'target_schedule_id.exists' => 'Jadwal tujuan tidak ditemukan.'];
    }
}
```

`RecordingController` — impor `UndoMarkingRequest`, tambahkan:

```php
    public function undo(UndoMarkingRequest $request, Schedule $schedule, RegistrationStudent $entry): RedirectResponse
    {
        $this->recording->undo($entry, $request->validated('note'), $request->user());

        return redirect()->route('jadwal.rekaman.show', $schedule)->with('success', 'Penandaan dibatalkan.');
    }
```

`ScheduleController` — tambahkan `destroy` dan prop `targets` pada `show`:

```php
    public function destroy(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->schedules->delete($schedule, $request->user());

        return redirect()->route('jadwal.index')->with('success', 'Jadwal dihapus.');
    }
```
Pada `show()` tambahkan ke array props (setelah `candidates`):

```php
            'targets' => $request->user()->can('jadwal.ubah')
                ? $this->schedules->targets($schedule)->map(fn (Schedule $s) => ['id' => $s->id, 'date' => $s->date->toDateString(), 'location' => $s->location])->values()
                : [],
```

`ScheduleStudentController` — impor `MoveRequest`, tambahkan:

```php
    public function move(MoveRequest $request, Schedule $schedule, RegistrationStudent $entry): RedirectResponse
    {
        $target = Schedule::query()->findOrFail($request->validated('target_schedule_id'));
        $this->schedules->move($entry, $target, $request->user());

        return redirect()->route('jadwal.show', $schedule)->with('success', 'Siswa dipindahkan.');
    }
```

`routes/schedules.php` — tambahkan di dalam grup `auth` (setelah rute `jadwal.update`, `siswa.destroy`, dan `rekaman.store` masing-masing sesuai konteks):

```php
    Route::delete('jadwal/{schedule}', [ScheduleController::class, 'destroy'])->middleware(['can:jadwal.ubah', 'throttle:60,1'])->name('jadwal.destroy');
    Route::post('jadwal/{schedule}/siswa/{entry}/pindah', [ScheduleStudentController::class, 'move'])
        ->middleware(['can:jadwal.ubah', 'throttle:60,1'])->scopeBindings()->name('jadwal.siswa.pindah');
    Route::post('jadwal/{schedule}/rekaman/{entry}/batalkan', [RecordingController::class, 'undo'])
        ->middleware(['can:rekaman.tandai', 'throttle:60,1'])->scopeBindings()->name('jadwal.rekaman.batalkan');
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Schedules tests/Feature/Access/RoutePermissionMatrixTest.php`
Expected: PASS. Entri `{slot}` pada matriks berstatus Terjadwal sehingga aksi batalkan/pindah menghasilkan galat atau validasi, bukan 403; itu yang diperiksa matriks.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app tests routes && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Schedules routes/schedules.php tests/Feature/Schedules/ScheduleCorrectionsHttpTest.php tests/Feature/Access/RoutePermissionMatrixTest.php
git commit -m "feat: tambah rute batalkan penandaan, hapus jadwal, dan pindah siswa" -m "Tiga rute baru memakai izin yang sudah ada (rekaman.tandai dan jadwal.ubah), throttle, dan scopeBindings agar entri harus milik jadwal pada URL. Detail jadwal memuat daftar jadwal tujuan bagi pemegang jadwal.ubah. Galat aturan bisnis kembali sebagai pesan error, validasi memakai pesan Indonesia."
```

---

### Task 4: Urutan daftar jadwal terdekat-dulu

**Files:**
- Modify: `app/Domains/Schedules/Repositories/EloquentScheduleRepository.php:19`
- Test: `tests/Feature/Schedules/ScheduleHttpTest.php`

**Interfaces:** Produces: `ScheduleRepositoryInterface::paginate` mengurutkan yang akan datang (naik) lalu yang lewat (menurun).

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan di `tests/Feature/Schedules/ScheduleHttpTest.php`:

```php
test('daftar jadwal: akan datang terdekat dulu, lalu yang lewat terbaru dulu', function () {
    $tanggal = fn (int $hari) => now('Asia/Jakarta')->addDays($hari)->toDateString();
    foreach ([5, -3, 1, -1, 0] as $hari) {
        makeSchedule($this->school, $tanggal($hari));
    }

    $this->actingAs($this->admin)->get('/jadwal')->assertInertia(fn (Assert $p) => $p
        ->where('schedules.data.0.date', $tanggal(0))->where('schedules.data.1.date', $tanggal(1))
        ->where('schedules.data.2.date', $tanggal(5))->where('schedules.data.3.date', $tanggal(-1))
        ->where('schedules.data.4.date', $tanggal(-3)));

    $this->get('/jadwal?filter[period]=lewat')->assertInertia(fn (Assert $p) => $p
        ->where('schedules.data.0.date', $tanggal(-1))->where('schedules.data.1.date', $tanggal(-3)));
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Schedules/ScheduleHttpTest.php --filter="terdekat dulu"`
Expected: FAIL (urutan masih menurun).

- [ ] **Step 3: Implementasi**

Di `EloquentScheduleRepository::paginate`, ganti inisialisasi `$base`:

```php
        $today = Schedule::today();
        // Akan datang dulu (terdekat), lalu yang lewat (terbaru): bendera lewat, tanggal naik untuk yang akan datang, lalu menurun.
        $base = Schedule::query()->with(['school', 'entries:id,schedule_id,status'])
            ->orderByRaw('date < ?', [$today])
            ->orderByRaw('case when date >= ? then date end asc', [$today])
            ->orderByDesc('date')->orderBy('id');
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Schedules`
Expected: PASS. Bila tes lama (`daftar menyaring periode ...`) mengasumsikan urutan menurun, sesuaikan asersinya ke urutan baru.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Schedules/Repositories/EloquentScheduleRepository.php tests/Feature/Schedules/ScheduleHttpTest.php
git commit -m "feat: urutkan daftar jadwal dari yang terdekat" -m "Jadwal yang akan datang tampil lebih dulu dengan tanggal naik, diikuti jadwal yang sudah lewat dengan tanggal menurun; filter Periode tidak berubah dan batas hari memakai Schedule::today()."
```

---

### Task 5: Setujui massal dan notifikasi massal verifikasi

**Files:**
- Create: `app/Domains/Registrations/Http/Requests/ApproveManyRequest.php`
- Modify: `app/Domains/Registrations/Repositories/{Contracts/RegistrationRepositoryInterface,EloquentRegistrationRepository}.php`, `app/Domains/Registrations/Services/VerificationService.php`, `app/Domains/Registrations/Http/Controllers/VerificationController.php`, `routes/registrations.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`
- Test: `tests/Feature/Registrations/{VerificationServiceTest,VerificationHttpTest}.php`

**Interfaces:**
- Consumes: `EligibilityService::check(Student, ?CarbonInterface $on, ?int $exceptRegistrationId): array{eligible: bool, reasons: list<string>}`, `StatusTransitioner::move`.
- Produces: `RegistrationRepositoryInterface::lockEntries(array $ids): Collection<RegistrationStudent>` (urut id, terkunci, memuat `student.documents`, `student.registrationEntries`, `registration`); `VerificationService::approveMany(array $entryIds, User $actor): int`; rute `verifikasi.setujui` (`POST /verifikasi/setujui`, body `entry_ids`).

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan di `tests/Feature/Registrations/VerificationServiceTest.php` (impor tambahan: `App\Domains\Registrations\Models\RegistrationStudent`, `App\Domains\Students\Models\Student`, `Illuminate\Validation\ValidationException`):

```php
/** Entri Diajukan baru di pendaftaran yang sama dengan `$entry`, untuk siswa yang layak. */
function makeSiblingEntryDiajukan(RegistrationStudent $entry, string $name): RegistrationStudent
{
    $student = eligibleStudent($entry->registration->school);
    $student->update(['name' => $name]);

    return RegistrationStudent::factory()->create([
        'registration_id' => $entry->registration_id, 'school_id' => $entry->school_id, 'student_id' => $student->id, 'status' => S::Diajukan,
    ]);
}

test('setujui massal: semua Terverifikasi, riwayat per entri, satu notifikasi per pendaftaran dengan ringkasan', function () {
    $a1 = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $a1->student->update(['name' => 'Ani']);
    $a2 = makeSiblingEntryDiajukan($a1, 'Budi');
    $b1 = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $b1->student->update(['name' => 'Cici']);

    $jumlah = $this->service->approveMany([$a1->id, $a2->id, $b1->id], $this->petugas);

    expect($jumlah)->toBe(3);
    foreach ([$a1, $a2, $b1] as $e) {
        expect($e->fresh()->status)->toBe(S::Terverifikasi)->and($e->fresh()->decided_by)->toBe($this->petugas->id)->and($e->histories()->count())->toBe(1);
    }
    expect(Activity::where('description', 'pendaftaran.diputuskan')->count())->toBe(3);
    Notification::assertSentToTimes($this->op, EntryDecidedNotification::class, 2);
    $bodies = Notification::sent($this->op, EntryDecidedNotification::class)->map(fn ($n) => $n->body())->all();
    expect($bodies)->toContain('2 siswa: Ani, Budi.')->and($bodies)->toContain('Cici');
});

test('satu entri tidak layak menggagalkan seluruh persetujuan massal tanpa notifikasi', function (string $kasus) {
    $baik = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $siswa = match ($kasus) {
        'tanpa dokumen' => Student::factory()->for($this->school)->create(['name' => 'Tanpa Dokumen']),
        'umur di luar 16-17' => tap(eligibleStudent($this->school), fn (Student $s) => $s->update(['name' => 'Terlalu Tua', 'birth_date' => now()->subYears(25)->toDateString()])),
    };
    $buruk = makeEntry(S::Diajukan, $this->school, $siswa);

    try {
        $this->service->approveMany([$baik->id, $buruk->id], $this->petugas);
        $this->fail('Seharusnya ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['entry_ids'][0])->toContain($siswa->name);
    }

    expect($baik->fresh()->status)->toBe(S::Diajukan)->and($buruk->fresh()->status)->toBe(S::Diajukan)->and($baik->histories()->count())->toBe(0);
    Notification::assertNothingSent();
})->with(['tanpa dokumen', 'umur di luar 16-17']);

test('persetujuan massal ditolak seluruhnya untuk id tidak ada, entri yang sudah diputuskan, dan bukan Diajukan', function (string $kasus) {
    $baik = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $buruk = match ($kasus) {
        'id tidak ada' => 999999,
        'sudah diputuskan' => makeEntry(S::Terverifikasi, $this->school)->id,
        'bukan Diajukan' => makeEntry(S::Draf, $this->school)->id,
    };

    expect(fn () => $this->service->approveMany([$baik->id, $buruk], $this->petugas))->toThrow(ValidationException::class);

    expect($baik->fresh()->status)->toBe(S::Diajukan);
    Notification::assertNothingSent();
})->with(['id tidak ada', 'sudah diputuskan', 'bukan Diajukan']);

test('operator terikat sekolah lain tidak dapat menyetujui entri sekolah ini (entri tak terlihat)', function () {
    $entri = makeEntry(S::Diajukan, $this->school, eligibleStudent($this->school));
    $this->actingAs(makeUserWith(['verifikasi.putuskan'], School::factory()->create(), true, 'terikat-lain'));

    expect(fn () => $this->service->approveMany([$entri->id], auth()->user()))->toThrow(ValidationException::class);
    expect($entri->fresh()->status)->toBe(S::Diajukan);
});
```

Tambahkan di `tests/Feature/Registrations/VerificationHttpTest.php`:

```php
test('setujui massal lewat HTTP: sukses, validasi jumlah dan id ganda, galat entri', function () {
    $a = makeEntry(S::Diajukan, student: eligibleStudent());
    $b = makeEntry(S::Diajukan, student: eligibleStudent());
    $this->actingAs($this->petugas);

    $this->post('/verifikasi/setujui', [])->assertSessionHasErrors('entry_ids');
    $this->post('/verifikasi/setujui', ['entry_ids' => []])->assertSessionHasErrors('entry_ids');
    $this->post('/verifikasi/setujui', ['entry_ids' => [$a->id, $a->id]])->assertSessionHasErrors('entry_ids.1');
    $this->post('/verifikasi/setujui', ['entry_ids' => range(1, 51)])->assertSessionHasErrors('entry_ids');
    expect($a->fresh()->status)->toBe(S::Diajukan);

    $this->post('/verifikasi/setujui', ['entry_ids' => [$a->id, $b->id]])->assertRedirect('/verifikasi')->assertSessionHas('success', '2 siswa disetujui.');
    expect($a->fresh()->status)->toBe(S::Terverifikasi)->and($b->fresh()->status)->toBe(S::Terverifikasi);

    $this->post('/verifikasi/setujui', ['entry_ids' => [$a->id]])->assertSessionHasErrors('entry_ids');
});
```

`tests/Feature/Access/RoutePermissionMatrixTest.php` — tambahkan di dataset `rute`: `['POST', '/verifikasi/setujui', 'verifikasi.putuskan'],`.

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Registrations/VerificationServiceTest.php tests/Feature/Registrations/VerificationHttpTest.php`
Expected: FAIL (`approveMany`, rute belum ada).

- [ ] **Step 3: Implementasi**

`RegistrationRepositoryInterface` — tambahkan (impor `Illuminate\Database\Eloquent\Collection` dan `RegistrationStudent`):

```php
    /**
     * Mengunci baris entri (urut id) beserta siswa, dokumen, entri siswa, dan pendaftarannya untuk keputusan massal.
     *
     * @param  list<int>  $ids
     * @return Collection<int, RegistrationStudent>
     */
    public function lockEntries(array $ids): Collection;
```

`EloquentRegistrationRepository` — tambahkan (impor `Illuminate\Database\Eloquent\Collection`):

```php
    public function lockEntries(array $ids): Collection
    {
        return RegistrationStudent::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()
            ->with(['student.documents', 'student.registrationEntries', 'registration'])->get();
    }
```

`ApproveManyRequest`:

```php
<?php

namespace App\Domains\Registrations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveManyRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entry_ids' => ['required', 'array', 'min:1', 'max:50'],
            'entry_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'entry_ids.required' => 'Pilih minimal satu siswa.',
            'entry_ids.min' => 'Pilih minimal satu siswa.',
            'entry_ids.max' => 'Maksimal 50 siswa sekali setujui.',
        ];
    }
}
```

`VerificationService` — tambahkan `EligibilityService` ke konstruktor (impor `App\Domains\Students\Services\EligibilityService`, `Illuminate\Validation\ValidationException`) dan method:

```php
    /**
     * Menyetujui banyak entri sekaligus: seluruhnya berhasil atau seluruhnya gagal. Kelayakan diperiksa ulang
     * per entri (umur pada tanggal pengajuan, dokumen, entri aktif lain).
     *
     * @param  list<int>  $entryIds
     */
    public function approveMany(array $entryIds, User $actor): int
    {
        $approved = DB::transaction(function () use ($entryIds, $actor) {
            $entries = $this->registrations->lockEntries($entryIds);
            if ($entries->count() !== count(array_unique($entryIds)) || $entries->contains(fn (RegistrationStudent $e) => $e->status !== RegistrationStatus::Diajukan)) {
                throw ValidationException::withMessages(['entry_ids' => 'Sebagian entri sudah diputuskan; muat ulang halaman lalu coba lagi.']);
            }

            foreach ($entries as $entry) {
                $check = $this->eligibility->check($entry->student, $entry->registration->submitted_at, $entry->registration_id);
                if (! $check['eligible']) {
                    throw ValidationException::withMessages(['entry_ids' => "{$entry->student->name} tidak dapat disetujui: {$check['reasons'][0]}"]);
                }
            }

            foreach ($entries as $entry) {
                $this->transitions->move($entry, RegistrationStatus::Terverifikasi, $actor);
                activity('pendaftaran')->performedOn($entry->registration)->causedBy($actor)
                    ->withProperties(['siswa_id' => $entry->student_id, 'keputusan' => RegistrationStatus::Terverifikasi->value, 'massal' => true])->log('pendaftaran.diputuskan');
            }

            return $entries;
        });

        // Satu notifikasi per pendaftaran, bukan per siswa.
        foreach ($approved->groupBy('registration_id') as $registrationId => $group) {
            $this->notifications->toSchool(
                'pendaftaran.lihat',
                new EntryDecidedNotification(RegistrationStatus::Terverifikasi, self::summary($group->map(fn (RegistrationStudent $e) => $e->student->name)->all()), (int) $registrationId, null),
                $group->first()->school_id,
            );
        }

        return $approved->count();
    }

    /** @param list<string> $names */
    private static function summary(array $names): string
    {
        $n = count($names);

        return match (true) {
            $n === 1 => $names[0],
            $n <= 3 => "{$n} siswa: ".implode(', ', $names).'.',
            default => "{$n} siswa: {$names[0]}, {$names[1]}, dan ".($n - 2).' lainnya.',
        };
    }
```

`VerificationController` — impor `ApproveManyRequest`, tambahkan:

```php
    public function approveMany(ApproveManyRequest $request): RedirectResponse
    {
        $count = $this->verification->approveMany($request->validated('entry_ids'), $request->user());

        return redirect()->route('verifikasi.index')->with('success', "{$count} siswa disetujui.");
    }
```

`routes/registrations.php` — tambahkan setelah rute `verifikasi.decide`:

```php
    Route::post('verifikasi/setujui', [VerificationController::class, 'approveMany'])->middleware(['can:verifikasi.putuskan', 'throttle:30,1'])->name('verifikasi.setujui');
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Registrations tests/Feature/Access/RoutePermissionMatrixTest.php`
Expected: PASS. Bila tes "operator terikat sekolah lain" berbeda hasil (mis. `auth()->user()` null), gunakan user yang dikembalikan `makeUserWith` yang disimpan ke variabel dan `actingAs` pada variabel itu.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app tests routes && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Registrations routes/registrations.php tests/Feature/Registrations tests/Feature/Access/RoutePermissionMatrixTest.php
git commit -m "feat: tambah persetujuan massal verifikasi dengan notifikasi per pendaftaran" -m "VerificationService::approveMany menyetujui hingga 50 entri secara atomik: entri dikunci, harus berstatus Diajukan, dan kelayakan (umur pada tanggal pengajuan, dokumen, entri aktif lain) diperiksa ulang untuk tiap entri; satu entri bermasalah membatalkan seluruhnya dengan pesan yang menyebut siswanya. Notifikasi ke operator digabung menjadi satu per pendaftaran dengan ringkasan siswa, dan setiap entri tetap tercatat di riwayat dan log."
```

---

### Task 6: Frontend halaman Jadwal (batalkan penandaan, hapus, pindah)

**Files:**
- Modify: `resources/js/types/schedule.ts`, `resources/js/Pages/Jadwal/Rekaman.vue`, `resources/js/Pages/Jadwal/Show.vue`

**Interfaces:**
- Consumes: rute `jadwal.rekaman.batalkan`, `jadwal.destroy`, `jadwal.siswa.pindah`; prop `targets` pada Show (Task 3); `ConfirmDialog` (slot default + `confirm-disabled`), `SelectField` (`placeholder`).
- Produces: tipe `ScheduleTarget = { id: number; date: string; location: string }`.

- [ ] **Step 1: Tipe**

`resources/js/types/schedule.ts` — tambahkan:

```ts
export type ScheduleTarget = {
    id: number;
    date: string;
    location: string;
};
```

- [ ] **Step 2: `Rekaman.vue` — aksi Batalkan**

Impor tambahan: `Textarea` sudah ada; tambahkan `router` ke impor `@inertiajs/vue3` (`import { router, useForm } from '@inertiajs/vue3';`). Pada `<script setup>`:

1. Ganti `markedColumns` menjadi computed:

```ts
const canUndo = computed(() => can(PERMISSIONS.RekamanTandai));
const markedColumns = computed(() => [
    { key: 'student', label: 'Siswa' },
    { key: 'status', label: 'Status', class: 'w-[176px]' },
    ...(canUndo.value
        ? [{ key: 'actions', label: 'Aksi', class: 'w-[120px] text-right' }]
        : []),
]);
```

2. Tambahkan state dan fungsi (di bawah `save`):

```ts
const undoing = ref<ParticipantRow | null>(null);
const undoNote = ref('');
const undoOpen = computed({
    get: () => undoing.value !== null,
    set: (open: boolean) => {
        if (!open) undoing.value = null;
    },
});

function startUndo(row: ParticipantRow) {
    undoNote.value = '';
    undoing.value = row;
}

function undo() {
    if (!undoing.value) return;
    router.post(
        route('jadwal.rekaman.batalkan', [props.schedule.id, undoing.value.id]),
        { note: undoNote.value.trim() },
        { preserveScroll: true },
    );
}
```

3. Ubah teks: paragraf "Pilih siswa, tentukan status, lalu simpan. Status akhir tidak dapat diubah." menjadi "Pilih siswa, tentukan status, lalu simpan. Status dapat dibatalkan kemudian oleh petugas dengan catatan."; `description` pada `ConfirmDialog` simpan penandaan menjadi "Penandaan dapat dibatalkan kemudian dengan catatan."

4. Pada `DataTable` "Sudah ditandai" tambahkan slot aksi (setelah slot `cell-status`):

```vue
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                class="h-9 px-3"
                                @click="startUndo(row)"
                            >
                                Batalkan
                                <span class="sr-only"
                                    >penandaan {{ row.student.name }}</span
                                >
                            </Button>
                        </div>
                    </template>
```
dan ubah `table-class="min-w-[480px]"` menjadi `table-class="min-w-[560px]"`.

5. Tambahkan dialog di akhir template (sebelum `</AppLayout>`):

```vue
        <ConfirmDialog
            v-model:open="undoOpen"
            :title="`Batalkan penandaan ${undoing?.student.name ?? ''}?`"
            description="Siswa kembali menjadi Terjadwal pada jadwal ini dan operator sekolah diberi tahu."
            confirm-label="Batalkan penandaan"
            destructive
            :confirm-disabled="!undoNote.trim()"
            @confirm="undo"
        >
            <FormField
                v-slot="{ id, describedBy }"
                label="Catatan"
                hint="Wajib; jelaskan alasan pembatalan."
            >
                <Textarea
                    :id="id"
                    v-model="undoNote"
                    rows="3"
                    maxlength="500"
                    :aria-describedby="describedBy"
                />
            </FormField>
        </ConfirmDialog>
```

- [ ] **Step 3: `Show.vue` — hapus jadwal dan pindah**

Impor tambahan: `SelectField` dari `@/Components/shared/SelectField.vue`, `FormField` dari `@/Components/shared/FormField.vue`, ikon `Trash2` dan `MoveRight` dari `@lucide/vue`, tipe `ScheduleTarget`, `formatDate` dari `@/lib/date` (bersama `formatDateWithDay`). Tambahkan prop `targets: ScheduleTarget[]` ke `defineProps`. Tambahkan di `<script setup>`:

```ts
const canDelete = computed(() => canEdit.value && props.participants.length === 0);
const deleteOpen = ref(false);

function destroy() {
    router.delete(route('jadwal.destroy', props.schedule.id));
}

const moving = ref<ParticipantRow | null>(null);
const targetId = ref<number | null>(null);
const moveOpen = computed({
    get: () => moving.value !== null,
    set: (open: boolean) => {
        if (!open) moving.value = null;
    },
});
const targetOptions = computed(() =>
    props.targets.map((t) => ({
        value: t.id,
        label: `${formatDate(t.date)} · ${t.location}`,
    })),
);

function startMove(row: ParticipantRow) {
    targetId.value = null;
    moving.value = row;
}

function move() {
    if (!moving.value || targetId.value === null) return;
    router.post(
        route('jadwal.siswa.pindah', [props.schedule.id, moving.value.id]),
        { target_schedule_id: targetId.value },
        { preserveScroll: true },
    );
}
```

Template: tambahkan tombol di header (setelah tombol "Ubah"):

```vue
                <Button
                    v-if="canDelete"
                    type="button"
                    variant="outline"
                    class="h-10 px-4"
                    @click="deleteOpen = true"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Hapus
                </Button>
```
Lebarkan kolom aksi peserta (`w-[140px]` → `w-[220px]`) dan `table-class` ke `min-w-[640px]`; di slot `cell-actions` bungkus kedua tombol dalam `<div class="flex justify-end gap-1">` dan tambahkan sebelum tombol "Keluarkan":

```vue
                            <Button
                                v-if="row.status === 'terjadwal' && targets.length"
                                type="button"
                                variant="ghost"
                                class="h-9 px-3"
                                @click="startMove(row)"
                            >
                                <MoveRight class="size-4" aria-hidden="true" />
                                Pindahkan
                                <span class="sr-only">{{ row.student.name }}</span>
                            </Button>
```
Tambahkan dua dialog di akhir template:

```vue
        <ConfirmDialog
            v-model:open="deleteOpen"
            title="Hapus jadwal ini?"
            description="Jadwal tanpa peserta dihapus permanen dan tercatat di log aktivitas."
            confirm-label="Hapus jadwal"
            destructive
            @confirm="destroy"
        />

        <ConfirmDialog
            v-model:open="moveOpen"
            :title="`Pindahkan ${moving?.student.name ?? ''}?`"
            description="Siswa dipindahkan ke jadwal lain milik sekolah ini dan operator sekolah diberi tahu."
            confirm-label="Pindahkan"
            :confirm-disabled="targetId === null"
            @confirm="move"
        >
            <FormField v-slot="{ id }" label="Jadwal tujuan">
                <SelectField
                    :id="id"
                    v-model="targetId"
                    :options="targetOptions"
                    placeholder="Pilih jadwal tujuan"
                />
            </FormField>
        </ConfirmDialog>
```

- [ ] **Step 4: Format, uji, typecheck, lint, build**

Run: `npx prettier --write resources/js/types/schedule.ts resources/js/Pages/Jadwal/Rekaman.vue resources/js/Pages/Jadwal/Show.vue && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau. Bila `typecheck` menolak `v-model="targetId"` (tipe `number | null` vs `SelectField`), samakan tipe prop lewat `ref<number | string | null>(null)` dan konversi dengan `Number(...)` pada `move()`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/types/schedule.ts resources/js/Pages/Jadwal
git commit -m "feat: tambah aksi batalkan penandaan, hapus jadwal, dan pindah siswa di halaman jadwal" -m "Halaman Penandaan rekaman mendapat tombol Batalkan per siswa dengan catatan wajib dan teks yang tidak lagi menyebut status akhir final. Detail jadwal mendapat tombol Hapus untuk jadwal tanpa peserta dan aksi Pindahkan ke jadwal lain sekolah yang sama, masing-masing dengan dialog konfirmasi."
```

---

### Task 7: Frontend antrean verifikasi (setujui terpilih)

**Files:**
- Modify: `resources/js/Pages/Verifikasi/Index.vue`

**Interfaces:**
- Consumes: rute `verifikasi.setujui` (Task 5); `usePermission`, `PERMISSIONS.VerifikasiPutuskan`, `Checkbox`, `ConfirmDialog`, `InlineAlert`.

- [ ] **Step 1: Skrip**

Tambahkan impor: `ConfirmDialog` (`@/Components/shared/ConfirmDialog.vue`), `InlineAlert` (`@/Components/shared/InlineAlert.vue`), `Checkbox` (`@/Components/ui/checkbox`), `usePermission` (`@/composables/usePermission`), `PERMISSIONS` (`@/lib/permissions`), `router` dari `@inertiajs/vue3` (`import { Link, router } from '@inertiajs/vue3';`), dan `ref` dari `vue`. Tambahkan setelah `watch(...)` yang ada:

```ts
const { can } = usePermission();
const canDecide = computed(() => can(PERMISSIONS.VerifikasiPutuskan));

const selected = ref<number[]>([]);
const approveOpen = ref(false);
const approving = ref(false);
const approveError = ref<string | null>(null);

// Pilihan hanya berlaku untuk halaman yang sedang dilihat.
watch(
    () => props.queue.data,
    () => (selected.value = []),
);

const allSelected = computed(
    () =>
        props.queue.data.length > 0 &&
        selected.value.length === props.queue.data.length,
);

function toggle(id: number, on: boolean) {
    selected.value = on
        ? [...selected.value, id]
        : selected.value.filter((x) => x !== id);
}

function toggleAll(on: boolean) {
    selected.value = on ? props.queue.data.map((r) => r.id) : [];
}

function approve() {
    router.post(
        route('verifikasi.setujui'),
        { entry_ids: selected.value },
        {
            preserveScroll: true,
            onStart: () => {
                approving.value = true;
                approveError.value = null;
            },
            onFinish: () => (approving.value = false),
            onSuccess: () => (selected.value = []),
            onError: (errors) =>
                (approveError.value = errors.entry_ids ?? 'Gagal menyetujui.'),
        },
    );
}
```

Ubah `columns` agar menambahkan kolom pilih di awal bila `canDecide`:

```ts
const columns = computed(() => [
    ...(canDecide.value
        ? [{ key: 'select', label: 'Pilih', class: 'w-[64px]' }]
        : []),
    { key: 'student', label: 'Siswa', class: 'w-[240px]' },
    ...(props.schools ? [{ key: 'school', label: 'Sekolah' }] : []),
    { key: 'registration', label: 'Pendaftaran' },
    { key: 'since', label: 'Diajukan', class: 'w-[160px]' },
    { key: 'actions', label: 'Aksi', class: 'w-[120px] text-right' },
]);
```

- [ ] **Step 2: Template**

Ubah `description` `PageHeader` menjadi "Periksa siswa yang diajukan sekolah; putuskan satu per satu atau setujui beberapa sekaligus." Di dalam `<div class="flex flex-col gap-4">` sebelum petunjuk geser, tambahkan:

```vue
                <InlineAlert v-if="approveError">{{ approveError }}</InlineAlert>
                <div
                    v-if="canDecide && queue.data.length"
                    class="flex flex-wrap items-center gap-3"
                >
                    <Checkbox
                        id="pilih-semua"
                        :model-value="allSelected"
                        @update:model-value="toggleAll($event === true)"
                    />
                    <label for="pilih-semua" class="text-sm leading-5"
                        >Pilih semua di halaman ini</label
                    >
                    <p
                        role="status"
                        class="text-muted-foreground text-sm leading-5"
                    >
                        {{ selected.length }} dipilih
                    </p>
                    <Button
                        type="button"
                        class="ml-auto h-10 px-4"
                        :disabled="!selected.length || approving"
                        @click="approveOpen = true"
                    >
                        <BadgeCheck class="size-4" aria-hidden="true" />
                        Setujui terpilih
                    </Button>
                </div>
```
Tambahkan slot sel pilih pada `DataTable` (sebelum `#cell-student`):

```vue
                    <template #cell-select="{ row }">
                        <Checkbox
                            :id="`pilih-${row.id}`"
                            :model-value="selected.includes(row.id)"
                            :aria-label="`Pilih ${row.student.name}`"
                            @update:model-value="toggle(row.id, $event === true)"
                        />
                    </template>
```
Dan dialog sebelum `</AppLayout>`:

```vue
        <ConfirmDialog
            v-model:open="approveOpen"
            :title="`Setujui ${selected.length} siswa?`"
            description="Kelayakan setiap siswa diperiksa ulang. Bila ada yang tidak layak, tidak ada yang disetujui."
            confirm-label="Setujui"
            @confirm="approve"
        />
```

- [ ] **Step 3: Format, uji, typecheck, lint, build**

Run: `npx prettier --write resources/js/Pages/Verifikasi/Index.vue && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Verifikasi/Index.vue
git commit -m "feat: tambah ceklis dan setujui terpilih di antrean verifikasi" -m "Petugas pemegang verifikasi.putuskan dapat memilih beberapa siswa di halaman antrean (atau semua di halaman itu) lalu menyetujuinya sekaligus lewat dialog konfirmasi; pilihan dikosongkan saat halaman atau filter berubah dan galat kelayakan dari server tampil di atas tabel."
```

---

### Task 8: Verifikasi, review, dokumentasi, dan penutupan

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (5.24, 5.25-5.27), `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`; memory `deferred-items.md`.

- [ ] **Step 1: Suite penuh backend di background**

Run (background): `php artisan test > /tmp/f6b-full.txt 2>&1` lalu pantau. Expected: semua lulus (375 sebelumnya + tes baru). Juga `vendor/bin/pint --test` dan `vendor/bin/phpstan analyse --memory-limit=1G` bersih, serta `npx vitest run`, `npm run typecheck`, `npm run lint`, `npm run build`.

- [ ] **Step 2: Verifikasi browser (Playwright)**

Siapkan data demo sementara lewat tinker (hapus setelahnya): dua siswa layak berstatus `Diajukan` pada satu pendaftaran sekolah demo, satu entri `Terjadwal` pada jadwal hari ini, dan satu jadwal kosong. Jalankan `php artisan serve --port=8099` (background); jangan matikan Vite dev server milik user di port 5173. Login lewat `browser_run_code_unsafe` (`page.getByLabel('Email').fill(...)`; bila sudah login `/login` mengalihkan, langsung buka halaman tujuan). Periksa pada 1440 dan 390 px sebagai `sari@example.com`:
- Antrean verifikasi: ceklis, "Pilih semua di halaman ini", "Setujui terpilih" membuka dialog, persetujuan berhasil, operator menerima satu notifikasi per pendaftaran (cek `op.siswa@example.com`).
- Jadwal: tombol Hapus hanya pada jadwal kosong dan berfungsi; Pindahkan membuka dialog dengan pilihan jadwal tujuan dan memindahkan; urutan daftar terdekat-dulu.
- Penandaan rekaman: tandai lalu **Batalkan** dengan catatan; dialog menonaktifkan tombol tanpa catatan.
- Operator: tidak melihat Hapus, Pindahkan, Batalkan, maupun ceklis verifikasi.
- Tanpa overflow horizontal halaman pada 390 px, konsol hanya berisi galat yang diharapkan. Jangan klik tautan unduhan; bila klik tersangkut, `browser_close`. Bersihkan data demo, matikan server 8099, hapus tangkapan layar.

- [ ] **Step 3: Review**

`ponytail:ponytail-review` pada `git diff 86cfe46..HEAD -- app routes resources/js lang tests` dan `impeccable` (critique) pada `Pages/Jadwal/{Rekaman,Show}.vue` dan `Pages/Verifikasi/Index.vue` dengan panduan UI. Perbaiki temuan valid dengan TDD, jalankan ulang tes terkait; commit terpisah "refactor: terapkan hasil review Fase 6b".

- [ ] **Step 4: Dokumentasi**

Panduan UI: perbarui 5.24 (ceklis dan Setujui terpilih, batas 50, kelayakan diperiksa ulang), 5.25 (urutan terdekat-dulu), 5.26 (tombol Hapus untuk jadwal kosong, aksi Pindahkan, daftar peserta), 5.27 (Batalkan penandaan dengan catatan wajib; teks status tidak lagi final). Sprint plan: tambahkan "Status Fase 6b (2026-10-09)" yang merangkum hasil, 6c belum dimulai, rilis ditahan. Memory `deferred-items.md`: hapus butir 6b (koreksi status akhir, hapus jadwal, pindah jadwal, notifikasi penandaan, penggabungan notifikasi, jadwal terdekat-dulu, verifikasi massal) dan catat "6b SELESAI"; sisakan 6c dan kelompok D.

- [ ] **Step 5: Verifikasi akhir dan commit**

Jalankan ulang semua pemeriksaan Step 1; semuanya harus hijau sebelum klaim selesai.

```bash
git add docs/superpowers
git commit -m "docs: tandai Fase 6b selesai dan perbarui panduan UI" -m "Panduan UI memuat pola koreksi (batalkan penandaan, hapus, pindah), urutan jadwal baru, dan ceklis verifikasi massal; rencana sprint mencatat status Fase 6b, 6c belum dimulai, dan rilis masih ditahan."
```
