# Batas Waktu Pendaftaran per Sesi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, inline). Langkah memakai checkbox (`- [ ]`).

**Goal:** Admin menentukan batas waktu pendaftaran (tanggal dan jam) pada tiap sesi rekaman. Sesi yang batasnya lewat tetap tampil di daftar siswa dengan ribbon "Pendaftaran ditutup" dan tombol daftar nonaktif; server tetap menolak pendaftarannya.

**Architecture:** Kolom baru `schedules.registration_closes_at` (datetime, wajib) menggantikan aturan global "tanggal sesi − `registration_close_days`". `Schedule::isOpenForRegistration()` membandingkan `now()` dengan kolom itu (dipakai `RegistrationService::register`). `config('sijebol.registration_close_days')` tinggal menjadi isian awal form admin. Form admin memakai `<input type="datetime-local">` bawaan browser.

**Tech Stack:** Laravel, Pest, Vue 3 + Inertia, Vitest.

**Spec:** Disepakati di chat 2026-11-01 (gambaran "sesuai gambaran itu"); panduan UI `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`.

## Global Constraints

- Batas wajib diisi, format `Y-m-d\TH:i`, zona Asia/Jakarta (zona aplikasi), tidak boleh melewati jam mulai sesi.
- Isian awal form = tanggal sesi − `registration_close_days` hari, pukul 23.59; berhenti mengikuti tanggal setelah admin mengubah batasnya sendiri; mode ubah memakai nilai tersimpan.
- Sesi lama diisi batasnya dengan aturan lama: tanggal sesi − 2 hari pukul 23.59.59 (nilai ditulis tetap di migrasi, bukan dibaca dari config).
- Daftar siswa menampilkan sesi tidak dibatalkan dengan tanggal ≥ hari ini (termasuk yang pendaftarannya sudah ditutup); sesi lewat dan dibatalkan tidak tampil.
- Aturan siswa membatalkan atau mengajukan ulang sampai H-1 (`allowsStudentChanges`) tidak berubah.
- Bahasa UI Indonesia; status tidak hanya dibedakan lewat warna (ribbon bertuliskan teks).
- Tanpa `Co-Authored-By`; tanpa commit sampai diminta.

## Review Focus

- Sesi yang ditutup admin lalu dibuka lagi lewat Ubah: siswa langsung bisa mendaftar (tes di Task 1).
- Batas persis di detik batas: `now() == closes_at` masih terbuka; satu detik setelahnya tertutup.
- Mengubah sesi lama yang punya pendaftar tanpa menyentuh batas tetap lolos validasi (batas lama sudah ≤ jam mulai).
- Tes lama yang membuat sesi lewat factory tidak berubah perilakunya (factory mengisi batas dengan aturan lama).

---

### Task 1: Backend

**Files:**
- Create: `database/migrations/2026_11_01_100001_add_registration_closes_at_to_schedules_table.php`
- Modify: `app/Domains/Schedules/Models/Schedule.php`, `database/factories/ScheduleFactory.php`, `app/Domains/Schedules/Http/Requests/ScheduleRequest.php`, `app/Domains/Schedules/Services/ScheduleService.php` (`attributes()` dan daftar `wasChanged`), `app/Domains/Schedules/Http/Controllers/ScheduleController.php` (`create`, `edit`, `detail`), `app/Domains/Registrations/Services/RegistrationService.php` (`openSchedules`), `app/Domains/Registrations/Http/Controllers/SelfRegistrationController.php` (hapus prop `close_days`)
- Test: `tests/Feature/Schedules/ScheduleModelTest.php`, `tests/Feature/Schedules/ScheduleHttpTest.php`, `tests/Feature/Registrations/SessionRegistrationHttpTest.php`

**Interfaces:**
- Produces: kolom `registration_closes_at` (cast `datetime`, fillable); `Schedule::isOpenForRegistration(): bool` = tidak dibatalkan dan `Carbon::now(ZONE) <= registration_closes_at`; `lastRegistrationDay()` dihapus.
- Produces (props Inertia): `Jadwal/Form` `create` → `schedule: null`, `close_days: int`; `edit` → `schedule.registration_closes_at: 'Y-m-d\TH:i'` dan `close_days`; `Jadwal/Show` → `schedule.registration_closes_at: ISO string` menggantikan `last_registration_day`; `Saya/Sesi` → tanpa `close_days`, tiap sesi di `schedules` bertambah `closed: bool` dan `closes_at: ISO string`.

- [ ] **Step 1: Tes (RED)**, semuanya di berkas yang disebut di atas:
  - `ScheduleModelTest`: ganti tes "H-2 inklusif" dengan `isOpenForRegistration` memakai `registration_closes_at` eksplisit: terbuka tepat di detik batas, tertutup satu detik sesudahnya, tertutup bila dibatalkan.
  - `ScheduleHttpTest`: `formSesi()` default memuat `registration_closes_at` = hari ini `T23:59` (≤ jam mulai besok); tes buat memeriksa `schedule.registration_closes_at` di Show dan menghapus assert `last_registration_day`; validasi: kosong ditolak, format salah ditolak, batas setelah jam mulai ditolak (`registration_closes_at` error); tes ubah: batas tersimpan dan dapat diubah (sesi ditutup lalu dibuka lagi); `Jadwal/Form` create memuat `close_days` = 2.
  - `SessionRegistrationHttpTest`: hapus `where('close_days', 2)`; sesi dengan batas lewat tetap ada di `schedules` dengan `closed: true` dan `closes_at`; sesi tanggal lewat dan yang dibatalkan tidak ada; mendaftar ke sesi tertutup ditolak "Pendaftaran untuk sesi ini sudah ditutup."; sesi hari ini yang batasnya sengaja dibuka (batas masih di depan) dapat didaftar.
- [ ] **Step 2: Jalankan** `php artisan test --compact tests/Feature/Schedules tests/Feature/Registrations/SessionRegistrationHttpTest.php`. Harapan: GAGAL.
- [ ] **Step 3: Migrasi**: tambah kolom `registration_closes_at` `dateTime` nullable setelah `end_time`; isi baris lama dengan `DATE_ADD(DATE_SUB(date, INTERVAL 2 DAY), INTERVAL '23:59:59' HOUR_SECOND)`; ubah menjadi non-null. `down()` menghapus kolom.
- [ ] **Step 4: Model dan factory**: `fillable` dan `casts` menambah `registration_closes_at` (docblock `@property Carbon $registration_closes_at`); hapus `lastRegistrationDay()`; `isOpenForRegistration()` memakai `Carbon::now(self::ZONE)->lessThanOrEqualTo($this->registration_closes_at)`. Factory: `registration_closes_at` berupa closure `fn (array $a) => Carbon::parse($a['date'])->subDays(2)->setTime(23, 59, 59)`.
- [ ] **Step 5: Request dan service**: `ScheduleRequest::rules()` menambah `registration_closes_at` (`bail`, `required`, `date_format:Y-m-d\TH:i`, closure yang gagal bila nilai > `date.'T'.start_time`; pesan "Batas pendaftaran tidak boleh melewati jam mulai sesi."), plus pesan wajib dan format; `ScheduleService::attributes()` menambah `registration_closes_at`; tambahkan ke daftar `wasChanged([...])` log `jadwal.diubah`.
- [ ] **Step 6: Controller**: `create` dan `edit` mengirim `close_days` (config) dan, saat ubah, `registration_closes_at` berformat `Y-m-d\TH:i`; `detail()` mengirim `registration_closes_at` (`->toIso8601String()`) menggantikan `last_registration_day`. `SelfRegistrationController::index` tidak lagi mengirim `close_days`.
- [ ] **Step 7: `openSchedules`**: filter `date >= Schedule::today()` (bukan `firstOpenDate`), tambah `closed` (`! $s->isOpenForRegistration()`) dan `closes_at` (`->toIso8601String()`) pada tiap baris; perbarui docblock tipe kembali.
- [ ] **Step 8: Jalankan ulang** perintah Step 2, lalu `php artisan test --compact` penuh dan `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`. Harapan: lulus semua.

### Task 2: Form admin dan Show

**Files:**
- Modify: `resources/js/Pages/Jadwal/Form.vue`, `resources/js/Pages/Jadwal/Show.vue`, `resources/js/types/schedule.ts`
- Test: `resources/js/Pages/Jadwal/Jadwal.test.ts` (fixture dan kasus form), `resources/js/Pages/Jadwal/Pindai.test.ts` (fixture)

**Interfaces:**
- Consumes: props `close_days`, `schedule.registration_closes_at` dari Task 1.

- [ ] **Step 1: Tes (RED)**: fixture `last_registration_day` menjadi `registration_closes_at: '2026-10-18T23:59:59+07:00'`; Show menampilkan "Dibuka sampai 18 Okt 2026 · 23.59"; Form buat: isian awal batas terisi `tanggal − close_days` pukul 23.59 saat tanggal dipilih, mengikuti perubahan tanggal selama belum disentuh dan berhenti mengikuti setelah diubah manual; Form ubah memakai nilai tersimpan dan tidak ikut berubah saat tanggal diubah; submit ditolak di klien bila batas kosong atau melewati jam mulai (pesan sama dengan server).
- [ ] **Step 2: Jalankan** `npx vitest run resources/js/Pages/Jadwal`. Harapan: GAGAL.
- [ ] **Step 3: Form.vue**: prop `close_days: number`, `schedule.registration_closes_at: string`; field `registration_closes_at` (`FormField` label "Batas pendaftaran", wajib, hint "Setelah batas ini siswa tidak dapat mendaftar; sesi tetap tampil dengan label ditutup.", `Input type="datetime-local"`, `class="h-10 sm:w-64"`) ditaruh setelah baris jam dan sebelum Lokasi; fungsi kecil menghitung isian awal (tanggal − `close_days` hari, `T23:59`); `watch` pada `form.date` mengisi ulang sampai `registration_closes_at` disentuh (`@input`); `validate()` menambah dua aturan klien.
- [ ] **Step 4: Show.vue dan types**: baris "Pendaftaran" memakai `formatDateTime(props.schedule.registration_closes_at)`; tipe `ScheduleDetail` mengganti `last_registration_day` dengan `registration_closes_at: string`.
- [ ] **Step 5: Jalankan ulang** perintah Step 2. Harapan: LULUS.

### Task 3: Ribbon di halaman siswa

**Files:**
- Modify: `resources/js/Pages/Saya/Sesi.vue`, `resources/js/types/registration.ts`
- Test: `resources/js/Pages/Saya/Sesi.test.ts`

**Interfaces:**
- Consumes: `OpenSchedule.closed: boolean`, `OpenSchedule.closes_at: string`; prop `close_days` dihapus.

- [ ] **Step 1: Tes (RED)**: sesi `closed: true` menampilkan teks "Pendaftaran ditutup" (ribbon), tombol Daftar nonaktif walau `eligible: true`, dan alasan kelayakan khusus sesi itu tidak ditampilkan; sesi terbuka menampilkan "Pendaftaran dibuka sampai {tanggal jam}" dan tombolnya aktif; prop `close_days` tidak lagi dibutuhkan.
- [ ] **Step 2: Jalankan** `npx vitest run resources/js/Pages/Saya/Sesi.test.ts`. Harapan: GAGAL.
- [ ] **Step 3: types**: `OpenSchedule` menambah `closed: boolean; closes_at: string`.
- [ ] **Step 4: Sesi.vue**: hapus prop `close_days` dan kalimat "Pendaftaran ditutup {{ close_days }} hari sebelum sesi." (diganti keterangan per sesi); kartu sesi tertutup: ribbon teks "Pendaftaran ditutup" di pojok kartu (`absolute`, kartu `relative overflow-hidden`, gaya `bg-danger-soft text-danger`, ikon `Lock`), baris kursi diganti keterangan "Ditutup {formatDateTime(closes_at)}", tombol `:disabled="s.closed || s.full || !s.eligible || busy"`; kartu terbuka menampilkan "Dibuka sampai {formatDateTime(closes_at)}" di bawah baris kursi; alasan kelayakan khusus disembunyikan untuk sesi tertutup.
- [ ] **Step 5: Jalankan ulang** perintah Step 2. Harapan: LULUS.

### Task 4: Dokumen dan verifikasi

**Files:**
- Modify: `docs/superpowers/specs/2026-10-19-alur-mandiri-siswa-design.md` dan panduan UI hanya pada kalimat yang menyebut batas H-2 (periksa lewat grep `H-2`, `close_days`, `ditutup`), `docs/deployment-guide.md` (catatan: migrasi mengisi sesi lama)

- [ ] **Step 1:** grep dokumen, perbarui kalimat yang kini keliru ("ditutup H-2" menjadi "ditutup pada batas waktu per sesi; isian awal H-2"); tambahkan catatan pemasangan: `php artisan migrate --force` (tanpa seeder).
- [ ] **Step 2:** `npx vitest run`, `npm run typecheck`, `npm run build`, `php artisan test --compact` (penuh), Pint, PHPStan.
- [ ] **Step 3:** review `ponytail:ponytail-review` atas seluruh diff dan `impeccable` atas perubahan Vue (detector: `node <skill>/scripts/detect.mjs --json <berkas Vue yang berubah>`); perbaiki temuan valid.
