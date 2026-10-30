# Fase 7b — Sesi Rekaman Berkuota dan Pendaftaran Siswa ke Sesi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin kecamatan membuat sesi rekaman per sekolah dengan kuota; siswa yang datanya lengkap mendaftar sendiri ke sesi sekolahnya (kursi ditahan di bawah kunci baris), admin memutuskan di antrean verifikasi, dan pendaftaran batch lama hilang.

**Architecture:** `schedules` menjadi sesi (jam mulai/selesai, kuota, `cancelled_at`); `registration_students` menjadi pendaftaran sesi per siswa (`schedule_id` wajib, tanpa `registration_id`, enum status baru, indeks unik satu pendaftaran aktif per siswa dibangun ulang). Nama kelas dan tabel `Schedule`/`RegistrationStudent` dipertahankan (hanya label UI menjadi "Sesi rekaman"); model `Registration` (batch) dihapus. Semua perubahan status tetap lewat `StatusTransitioner`. Kepemilikan lewat scope pemilik pada `RegistrationStudent`, sama polanya dengan `Student`.

**Tech Stack:** Laravel 12 + Inertia + Vue 3 + TypeScript, spatie/laravel-permission, Pest (butuh MySQL `sijebol_test`), Vitest, Larastan (`--memory-limit=1G`), Pint.

**Spec:** [`docs/superpowers/specs/2026-10-19-alur-mandiri-siswa-design.md`](../specs/2026-10-19-alur-mandiri-siswa-design.md) (bagian 4.3, 4.4 sebagian, 4.7 sebagian, 6, 7, 8 dan 9 yang relevan). Panduan UI: [`2026-10-07-sijebol-ui-guide.md`](../specs/2026-10-07-sijebol-ui-guide.md). Fase sebelumnya: [`2026-10-19-fase-7a-akun-siswa-dan-kepemilikan-data.md`](2026-10-19-fase-7a-akun-siswa-dan-kepemilikan-data.md) (selesai, sudah di-commit).

**Cakupan fase ini (spec §10, tahap 2):** sesi dengan kuota (buat, ubah, batalkan sesi, hapus), siswa mendaftar/membatalkan/mengajukan ulang, keputusan admin di antrean verifikasi disesuaikan ke model baru (Setujui → Terdaftar, Minta perbaikan, Tolak), ceklis manual disesuaikan ke status baru, beranda dan rekap dipetakan minimal ke status baru, penghapusan pendaftaran batch.

**Di luar fase ini (7c/7d, jangan dikerjakan di sini):**
- 7c: batas waktu perbaikan dan Kedaluwarsa, tiket + kode QR, **semua notifikasi ke siswa** (disetujui, perbaikan, ditolak, sesi diubah/dibatalkan, pengingat), pembatalan pendaftaran oleh admin, "Minta perbaikan hanya bila sesi paling cepat besok", pembatalan kursi saat sekolah siswa diganti (spec §4.9; di 7a masih berupa penolakan), scheduled command, "buka kunci" data.
- 7d: scan QR, penandaan Tidak hadir otomatis setelah sesi, label KTP-el langsung, beranda dan laporan baru.
- **Gerbang rilis:** 7b tidak boleh dirilis ke siswa sungguhan sebelum 7c (notifikasi, tiket, batas waktu perbaikan).
- Konsekuensi sementara: sampai 7c, siswa tidak menerima email/lonceng atas keputusan admin (status hanya terlihat di halaman Sesi rekaman), dan operator sekolah tidak lagi menerima notifikasi pendaftaran/penempatan.

## Mode eksekusi

Ditanyakan ke user setelah rencana disetujui (di 7a: awalnya kolaboratif, lalu "Saya kerjakan semua"). Ledger dan alur ikut skill yang dipilih.

## Penyimpangan dan keputusan perancang (perlu persetujuan user)

1. **Nama `Schedule` dan `RegistrationStudent` dipertahankan** (spec §7 mengizinkan bentuk lain). Mengganti nama kelas/tabel hanya menambah churn; label UI saja yang menjadi "Sesi rekaman".
2. **Sesi penuh tetap tampil di halaman siswa, dinonaktifkan dengan teks "Penuh"** (spec §4.3 "hanya melihat yang belum penuh", §2 "sesi tidak bisa dipilih"). Menyembunyikan membuat siswa bingung "kenapa tidak ada sesi". Sesi yang pendaftarannya sudah ditutup atau dibatalkan tidak ditampilkan.
3. **Hari terakhir mendaftar = H-2 (inklusif)**: sesi tanggal 20 dapat didaftar sampai tanggal 18. Pembatalan dan ajukan ulang oleh siswa dapat dilakukan sampai hari sebelum sesi (tanggal 19). Nilai ada di `config/sijebol.php`.
4. **`Terdaftar → Kedaluwarsa` tidak ada di peta transisi** (catatan kaki spec §6: Terdaftar yang lewat menjadi Tidak hadir, di 7d).
5. **Status "Tidak hadir" tidak menahan kursi dan tidak aktif** (spec §4.3 tidak menyebutnya; sesinya sudah lewat). "Belum rekaman" menahan kursi tetapi tidak aktif (spec §4.3 + aturan v1).
6. **Sesi dapat dibatalkan admin di 7b** (spec §4.3) tanpa notifikasi (notifikasi di 7c). Hanya pendaftaran Menunggu verifikasi/Perlu perbaikan/Terdaftar yang menjadi Dibatalkan; yang sudah ditandai kehadirannya tidak diubah.
7. **Ajukan ulang oleh siswa masuk 7b** (tanpa batas waktu perbaikan) supaya status Perlu perbaikan bukan jalan buntu; batas waktu dan kedaluwarsa di 7c.
8. **Permission:** `pendaftaran.ajukan` dihapus dari enum; `pendaftaran.lihat` = melihat halaman Sesi rekaman siswa, `pendaftaran.buat` = mendaftar/membatalkan/mengajukan ulang. Role `siswa` memegang keduanya; admin memegang semuanya (anti eskalasi, seperti `profil.*`), jadi rutenya `/saya/*` tetap 404 bagi non-siswa. Menu "Pendaftaran" staf dihapus; "Jadwal" berlabel "Sesi rekaman".
9. **Migrasi satu arah dan menolak jalan bila masih ada data** di `schedules`/`registration_students` (pesan: jalankan `php artisan sijebol:bersihkan-data-siswa` dulu). `down()` melempar `RuntimeException`.
10. **Rekap laporan menyaring menurut tanggal pendaftaran sesi** (`registration_students.created_at`), karena "tanggal pengajuan batch" tidak ada lagi.

## Global Constraints

- Bahasa UI, pesan validasi, dan isi notifikasi: Bahasa Indonesia baku.
- Hak akses lewat enum `Permission` + seeder + UI RBAC; tidak ada `if` peran selain `isStudent()` (scope pemilik dan rute `/saya/*`).
- Kuota tidak pernah terlampaui: pendaftaran dilakukan dalam `DB::transaction` dengan `lockForUpdate()` pada baris sesi, dan perhitungan kursi memakai `RegistrationStatus::holdingSeat()`.
- Satu pendaftaran aktif per siswa (indeks unik pada kolom generated `active_student_id`; daftar status di migrasi **harus sama** dengan `RegistrationStatus::active()`).
- Umur minimal 16 tahun dihitung pada **tanggal sesi**, hanya di `EligibilityService`.
- Semua aksi tulis baru memakai `throttle`; semua perubahan status lewat `StatusTransitioner`; semua perubahan data tercatat lewat `activity()`.
- Siswa hanya mengakses pendaftarannya sendiri; milik siswa lain = 404.
- Tidak ada `Co-Authored-By` atau atribusi AI pada commit message (aturan CLAUDE.md mengalahkan atribusi bawaan); commit hanya setelah user memintanya.
- Verifikasi akhir fase: `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vue-tsc --noEmit`, `npx eslint` pada berkas yang disentuh, `npm test`, `npm run build`.

**Jendela merah yang disengaja:** Task 1 mengganti enum status sehingga tes lama untuk verifikasi, rekaman, beranda, dan rekap gagal sampai dipindahkan di Task 5–6. Tiap task hanya menjalankan perintah tes yang disebutnya; **seluruh suite Pest wajib hijau di akhir Task 6**, seluruh Vitest hijau di akhir Task 8. Tes yang hanya menguji fitur yang dihapus (batch, pemilih kandidat, penempatan/pindah/keluarkan) dihapus di task yang menghapus kodenya.

**Peta status lama → baru untuk porting tes:** Draf → (tes dihapus); Diajukan → MenungguVerifikasi; Terverifikasi → (tes dihapus, tidak ada padanan); Terjadwal → Terdaftar; PerluPerbaikan, SudahRekaman, TidakHadir, BelumRekaman, Ditolak tidak berubah. Nilai string: `menunggu_verifikasi`, `terdaftar`, `dibatalkan`, `kedaluwarsa` baru.

## Review Focus

1. Dua siswa berebut kursi terakhir (kuota 1): satu berhasil, satu ditolak "Kuota sesi ini sudah penuh."; kursi yang ditolak/dibatalkan dilepas dan dapat diisi siswa lain; `lockForUpdate` pada baris sesi terbukti dieksekusi. (Task 3)
2. Siswa B (sekolah sama) tidak dapat membatalkan, mengajukan ulang, atau melihat pendaftaran siswa A: 404; sesi sekolah lain 404; admin/operator mengakses `/saya/sesi` mendapat 404/403. (Task 1, 4)
3. Mengubah sesi berpendaftar: tanggal terkunci, kuota tidak boleh di bawah kursi tertahan, jam/lokasi boleh; membatalkan sesi melepas semua kursi tanpa menyentuh yang sudah ditandai. (Task 2)
4. Batas hari: tepat H-2 masih boleh mendaftar, H-1 ditutup; batal sampai H-1 boleh, hari-H ditolak; siswa yang genap 16 tahun tepat pada tanggal sesi boleh, sehari kurang ditolak. (Task 1, 3)
5. Data/dokumen belum lengkap, sudah punya pendaftaran aktif, atau baris siswa belum ada: ditolak dengan pesan jelas tanpa mengubah data; mendaftar ulang setelah Ditolak/Dibatalkan diperbolehkan. (Task 3)

---

### Task 1: Skema, enum status, model, dan pembersihan batch (fondasi)

**Files:**
- Create: `config/sijebol.php`, `database/migrations/2026_10_20_100001_convert_registrations_to_session_registrations.php`
- Modify: `app/Domains/Registrations/Enums/RegistrationStatus.php`, `app/Domains/Registrations/Models/RegistrationStudent.php`, `app/Domains/Registrations/Models/RegistrationStatusHistory.php`, `app/Domains/Schedules/Models/Schedule.php`
- Modify: `database/factories/ScheduleFactory.php`, `database/factories/RegistrationStudentFactory.php`, `tests/Pest.php`, `app/Console/Commands/ResetStudentData.php`, `routes/registrations.php`, `lang/id/activity.php`
- Delete: `app/Domains/Registrations/Models/Registration.php`, `database/factories/RegistrationFactory.php`, `app/Domains/Registrations/Http/Controllers/RegistrationController.php`, `app/Domains/Registrations/Http/Requests/RegistrationRequest.php`, `app/Domains/Registrations/Services/RegistrationService.php` (ditulis ulang di Task 3), `app/Domains/Notifications/Notifications/RegistrationSubmittedNotification.php`
- Delete tes: `tests/Feature/Registrations/{RegistrationHttpTest,RegistrationModelTest,RegistrationServiceTest,CandidatePickerTest}.php`
- Test: `tests/Feature/Registrations/RegistrationStatusTest.php`, `tests/Feature/Registrations/SessionSchemaTest.php`, `tests/Feature/Schedules/ScheduleModelTest.php` (tulis ulang), `tests/Feature/Students/StudentOwnershipTest.php` (tambah), `tests/Feature/Console/ResetStudentDataTest.php` (sesuaikan)

**Interfaces:**
- Produces: `config('sijebol.registration_close_days')` = `2`.
- Produces: `enum RegistrationStatus` kasus `MenungguVerifikasi='menunggu_verifikasi'`, `PerluPerbaikan`, `Terdaftar='terdaftar'`, `SudahRekaman`, `TidakHadir`, `BelumRekaman`, `Ditolak`, `Dibatalkan='dibatalkan'`, `Kedaluwarsa='kedaluwarsa'` (label Indonesia: "Menunggu verifikasi", "Perlu perbaikan", "Terdaftar", …); `next(): list<self>`, `active(): list<self>` (MenungguVerifikasi, PerluPerbaikan, Terdaftar, SudahRekaman), `holdingSeat(): list<self>` (active + BelumRekaman), `locking(): list<self>` (MenungguVerifikasi, Terdaftar, SudahRekaman), `isActive()`, `locksStudent()`, `values(array): list<string>`.
- Produces: kolom `schedules.start_time`/`end_time` (`time`), `quota` (`unsignedSmallInteger`), `cancelled_at` (nullable timestamp); `registration_students` tanpa `registration_id`, `schedule_id` NOT NULL (restrict), default status `menunggu_verifikasi`; `registration_status_histories.from_status` nullable.
- Produces: `Schedule::isCancelled(): bool`; `Schedule::lastRegistrationDay(): string` (tanggal sesi dikurangi `registration_close_days`, `Y-m-d`); `Schedule::isOpenForRegistration(): bool` (tidak dibatalkan dan `today() <= lastRegistrationDay()`); `Schedule::allowsStudentChanges(): bool` (tidak dibatalkan dan `today() < tanggal sesi`); `Schedule::seatsTaken(): int` (hitung entri berstatus `holdingSeat()`).
- Produces: `RegistrationStudent` — `$fillable` = `school_id, student_id, schedule_id, status, note, decided_by, decided_at`; relasi `student()`, `schedule()`, `histories()`; scope global `owner` (akun siswa hanya melihat entri milik `students.user_id`-nya; `Auth::hasUser()` mencegah rekursi).
- Produces helper Pest: `makeEntry(RegistrationStatus $status = MenungguVerifikasi, ?School $school = null, ?Student $student = null, ?Schedule $schedule = null): RegistrationStudent` (sekolah/siswa/sesi dibuat bila kosong, satu sekolah) dan `makeSchedule(?School, ?string $date = null)` dengan kuota 30, 08:00–12:00.

- [ ] **Step 1: Tulis tes yang gagal**
  - `RegistrationStatusTest`: `next()` persis spec §6 (MenungguVerifikasi → Terdaftar, PerluPerbaikan, Ditolak, Dibatalkan, Kedaluwarsa; PerluPerbaikan → MenungguVerifikasi, Dibatalkan, Kedaluwarsa; Terdaftar → SudahRekaman, TidakHadir, BelumRekaman, Dibatalkan; SudahRekaman/TidakHadir/BelumRekaman → Terdaftar; Ditolak/Dibatalkan/Kedaluwarsa → kosong); `active()`, `holdingSeat()`, `locking()` berisi tepat kasus di atas.
  - `SessionSchemaTest`: kolom baru ada dan tabel `registrations` tidak ada; dua entri aktif untuk siswa yang sama melempar `UniqueConstraintViolationException`; entri Dibatalkan/Ditolak/Kedaluwarsa/TidakHadir tidak menghalangi entri aktif baru; entri `SudahRekaman` menghalangi; riwayat dengan `from_status` null dapat disimpan.
  - `ScheduleModelTest` (tulis ulang): `seatsTaken()` menghitung tepat status `holdingSeat()` dan mengabaikan Ditolak/Dibatalkan/Kedaluwarsa/TidakHadir; dengan `Carbon::setTestNow`, sesi H+2 `isOpenForRegistration()` true, H+1 false, sesi dibatalkan false; `allowsStudentChanges()` true pada H+1, false pada hari-H dan sesi dibatalkan; `lastRegistrationDay()` = tanggal − 2 hari; `isPast()`/`isOpenForMarking()` perilaku lama tetap.
  - `StudentOwnershipTest` (tambah): akun siswa A hanya melihat `RegistrationStudent` miliknya (`RegistrationStudent::count()` = 1 walau B satu sekolah punya entri); staf tanpa sekolah melihat semua.
  - `ResetStudentDataTest`: daftar tabel tidak lagi memuat `registrations`; tes tetap lulus.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="RegistrationStatusTest|SessionSchemaTest|ScheduleModelTest|StudentOwnershipTest|ResetStudentDataTest"`; Expected: FAIL (enum/kolom belum ada).
- [ ] **Step 3: Implementasi**
  - Migrasi `up()`, urutannya: (a) bila `registration_students` atau `schedules` berisi baris, lempar `RuntimeException('Data lama masih ada. Jalankan "php artisan sijebol:bersihkan-data-siswa" lebih dulu, lalu ulangi migrasi.')`; (b) `schedules`: tambah `start_time`, `end_time`, `quota`, `cancelled_at`; (c) `registration_students`: lepas FK dan unik `registration_id`, buang kolomnya; lepas unik `active_student_id` lalu buang kolom, tambah ulang sebagai `storedAs("CASE WHEN status IN ('menunggu_verifikasi','perlu_perbaikan','terdaftar','sudah_rekaman') THEN student_id END")` dengan indeks unik, `schedule_id` menjadi non-null, default status `menunggu_verifikasi`; (d) `registration_status_histories.from_status` nullable; (e) `dropIfExists('registrations')`. `down()` melempar `RuntimeException`. Beri komentar di migrasi bahwa daftar status harus sama dengan `RegistrationStatus::active()`.
  - Enum, `Schedule`, `RegistrationStudent`, `RegistrationStatusHistory` (cast `from_status` nullable) sesuai Interfaces. `Schedule::today()` yang ada dipakai ulang; `Schedule` mendapat `fillable`/cast baru (`start_time`/`end_time` string `H:i:s`, `cancelled_at` datetime).
  - `config/sijebol.php`: `['registration_close_days' => 2]`.
  - Hapus berkas batch di daftar Files. `routes/registrations.php`: hapus rute `pendaftaran.*`, rute verifikasi tetap. Buang `pendaftaran.diajukan` dari `lang/id/activity.php`; tambahkan kunci `pendaftaran.dibuat` ("Mendaftar ke sesi"), `pendaftaran.dibatalkan` ("Pendaftaran sesi dibatalkan"), `jadwal.dibatalkan` ("Sesi dibatalkan"), dan ganti subject `Registration` dengan `RegistrationStudent` => "Pendaftaran sesi" di `subjects`.
  - Factory dan helper Pest sesuai Interfaces. `ResetStudentData::TABLES` tanpa `registrations`.
  - Biarkan sisa kode lama (VerificationService, RecordingService, dsb.) rusak sementara; diperbaiki di Task 2–6.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2 ditambah `php artisan migrate:fresh --env=testing` bila perlu; Expected: PASS.
- [ ] **Step 5: Commit** — `refactor: ubah skema jadwal dan pendaftaran menjadi sesi rekaman berkuota`

---

### Task 2: Sesi rekaman — buat, ubah, batalkan, hapus

**Files:**
- Modify: `app/Domains/Schedules/Services/ScheduleService.php`, `app/Domains/Schedules/Http/Controllers/ScheduleController.php`, `app/Domains/Schedules/Http/Requests/ScheduleRequest.php`, `app/Domains/Schedules/Repositories/Contracts/ScheduleRepositoryInterface.php`, `app/Domains/Schedules/Repositories/EloquentScheduleRepository.php`, `routes/schedules.php`, `app/Domains/Notifications/Notifications/ScheduleNotification.php`
- Create: `app/Domains/Schedules/Http/Requests/CancelScheduleRequest.php`
- Delete: `app/Domains/Schedules/Http/Controllers/ScheduleStudentController.php`, `app/Domains/Schedules/Http/Requests/{AssignRequest,MoveRequest}.php`
- Delete tes: `tests/Feature/Schedules/{ScheduleAssignmentTest,ScheduleMoveDeleteTest}.php`
- Test: `tests/Feature/Schedules/ScheduleServiceTest.php`, `ScheduleHttpTest.php`, `ScheduleCorrectionsHttpTest.php` (tulis ulang)

**Interfaces:**
- Consumes (Task 1): `Schedule::seatsTaken()`, `isCancelled()`, enum `holdingSeat()`.
- Produces: `ScheduleService::create(array{school_id?: ?int, date: string, start_time: string, end_time: string, location: string, quota: int} $data, User $actor): Schedule`; `update(Schedule, array{date, start_time, end_time, location, quota} $data, User $actor): Schedule`; `cancel(Schedule $schedule, string $note, User $actor): int` (jumlah pendaftaran yang dibatalkan); `delete(Schedule, User): void`.
- Produces: rute `POST /jadwal/{schedule}/batalkan` (`jadwal.batalkan`, `can:jadwal.ubah`, `throttle:30,1`); rute `jadwal.siswa.*` dihapus.
- Produces: payload Inertia `Jadwal/*`: `ScheduleController::detail()` memuat `date`, `start_time` (`H:i`), `end_time` (`H:i`), `location`, `school`, `quota`, `taken`, `cancelled` (bool), `is_past`, `last_registration_day`; `index` baris memuat `quota`, `taken`, `cancelled`, `counts`; `show` memuat `participants` (semua status, urut id) dan `created_by`; `edit` memuat `has_participants`.

- [ ] **Step 1: Tulis tes yang gagal**
  - Buat: sesi tersimpan dengan jam/kuota; `end_time` harus setelah `start_time` (error `end_time`), `quota` bilangan bulat 1–1000, tanggal tidak boleh di masa lalu (aturan lama), format jam `H:i`.
  - Ubah: jam, lokasi, dan kuota (naik) boleh pada sesi berpendaftar; mengganti tanggal sesi berpendaftar → `ValidationException` pada `date` ("Tanggal tidak dapat diubah karena sesi sudah memiliki pendaftar. Batalkan sesi lalu buat sesi baru."); kuota di bawah `seatsTaken()` → error `quota` menyebut jumlah kursi tertahan; kuota sama dengan kursi tertahan boleh; entri Ditolak/Dibatalkan tidak dihitung; sesi dibatalkan tidak dapat diubah (`AccessRuleException`); log `jadwal.diubah` hanya bila ada perubahan.
  - Batalkan: pendaftaran MenungguVerifikasi/PerluPerbaikan/Terdaftar menjadi Dibatalkan dengan catatan, riwayat tercatat, `cancelled_at` terisi, log `jadwal.dibatalkan`; entri SudahRekaman/TidakHadir/BelumRekaman/Ditolak tidak berubah; sesi yang sudah lewat atau sudah dibatalkan ditolak; catatan wajib 5–500 karakter; mengembalikan jumlah yang dibatalkan.
  - Hapus: sesi tanpa entri apa pun terhapus dan tercatat; sesi dengan entri (status apa pun) ditolak ("Sesi masih memiliki pendaftar; batalkan sesi bila tidak jadi dilaksanakan.").
  - HTTP: izin tiap rute (`jadwal.buat/ubah/lihat`; operator sekolah `jadwal.lihat` hanya melihat sesi sekolahnya dan tidak melihat tombol/aksi ubah); `show` memuat peserta semua status; rute `jadwal.siswa.*` berstatus 404 (fallback).
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="ScheduleServiceTest|ScheduleHttpTest|ScheduleCorrectionsHttpTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi**
  - `ScheduleRequest` satu kelas untuk buat dan ubah (pola yang ada): tambah aturan jam dan kuota; pesan Indonesia (`end_time.after` = "Jam selesai harus setelah jam mulai.", `quota` = "Kuota harus berupa angka 1–1000.").
  - `ScheduleService`: `update` mengunci baris sesi (`lockForUpdate`) di dalam transaksi sebelum memeriksa kursi tertahan; `cancel` mengunci sesi lalu memindahkan entri lewat `StatusTransitioner::move($entry, Dibatalkan, $actor, $note)`; **tanpa notifikasi** (7c). Hapus `assign`, `unassign`, `move`, `targets`, `candidates`, dan notifikasi operator pada `update`.
  - Repository: buang `targets`/`candidates`; `upcoming($limit)` memberi `taken_count` (`withCount` entri berstatus `holdingSeat()`); `paginate` memuat ringkasan status dan kursi. `ScheduleService::upcomingTable`/`calendarEvents` memakai `taken_count` sebagai `participants` (angka yang sama dipakai beranda di Task 6).
  - `ScheduleNotification`: hapus `assigned`/`changed` (tidak ada pemanggil lagi; `marked`/`undone` tetap).
  - Label: judul/deskripsi halaman diperbarui di Task 7.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: kelola sesi rekaman dengan kuota, ubah terbatas, dan pembatalan sesi`

---

### Task 3: Layanan pendaftaran siswa ke sesi

**Files:**
- Create: `app/Domains/Registrations/Services/RegistrationService.php` (ditulis ulang), `tests/Feature/Registrations/SessionRegistrationServiceTest.php`
- Modify: `app/Domains/Registrations/Services/StatusTransitioner.php`, `app/Domains/Students/Services/EligibilityService.php`, `app/Domains/Registrations/Repositories/Contracts/RegistrationRepositoryInterface.php`, `app/Domains/Registrations/Repositories/EloquentRegistrationRepository.php`
- Test: `tests/Feature/Students/EligibilityServiceTest.php`, `tests/Feature/Registrations/StatusTransitionerTest.php` (sesuaikan)

**Interfaces:**
- Consumes (Task 1–2): enum, `Schedule::{isOpenForRegistration,allowsStudentChanges,seatsTaken}`, `RegistrationStudent` ber-scope pemilik.
- Produces: `EligibilityService::check(Student $student, ?CarbonInterface $on = null, ?int $exceptEntryId = null): array{eligible: bool, reasons: list<string>}` — parameter ke-3 kini id **entri** yang dikecualikan dari pemeriksaan "sudah punya pendaftaran aktif" (pesan: "Sudah memiliki pendaftaran sesi yang aktif."). Pemanggil lama (`SelfStudentController`, `VerificationController`) disesuaikan di Task 4–5.
- Produces: `RegistrationService::register(User $actor, Schedule $schedule): RegistrationStudent`; `cancel(RegistrationStudent $entry, User $actor): void`; `resubmit(RegistrationStudent $entry, User $actor): void`.
- Produces: `RegistrationRepositoryInterface` dipangkas menjadi `queue(int $perPage = 20)`, `lockEntries(array $ids)`, ditambah `create(array $attributes): RegistrationStudent`. Entri terbaru siswa dibaca di service lewat `$student->registrationEntries()->latest('id')->first()`.
- Produces: `StatusTransitioner::move(...)` mengisi `decided_by`/`decided_at` **hanya** bila status tujuan Terdaftar, PerluPerbaikan, atau Ditolak (bukan saat Dibatalkan/Kedaluwarsa).

- [ ] **Step 1: Tulis tes yang gagal** (`SessionRegistrationServiceTest`, akun siswa dibuat dengan `makeUserWith([...], $school, true, 'siswa')` + `eligibleStudent`)
  - Daftar sukses: entri MenungguVerifikasi, `schedule_id` benar, riwayat `null → menunggu_verifikasi` oleh siswa, log `pendaftaran.dibuat`, data siswa menjadi terkunci (`isLocked()`).
  - **Kuota:** kuota 1 → siswa kedua ditolak "Kuota sesi ini sudah penuh."; setelah entri pertama Ditolak/Dibatalkan, siswa kedua berhasil; Terdaftar/SudahRekaman/BelumRekaman/PerluPerbaikan menahan kursi; kuota 2 dengan 2 siswa sukses. Pendaftaran menjalankan query `select … from schedules … for update` (tangkap lewat `DB::listen`) — bukti kunci baris.
  - **Penolakan tanpa perubahan data:** baris siswa belum ada → "Lengkapi data diri dan dokumen terlebih dahulu."; dokumen KK/akta kurang → alasan dari eligibility; sudah punya entri aktif (sesi lain) → ditolak; sesi sekolah lain → `ModelNotFoundException`; sesi dibatalkan; sesi H-1 (ditutup) ditolak, H-2 diterima (`Carbon::setTestNow`); umur: lahir tepat 16 tahun pada tanggal sesi diterima, sehari kurang ditolak (walau sekarang sudah/belum 16).
  - Mendaftar lagi setelah Ditolak/Dibatalkan diterima (sesi sama maupun lain). Pelanggaran indeks unik karena balapan dipetakan menjadi `AccessRuleException` ("Anda sudah memiliki pendaftaran aktif.").
  - `cancel`: dari MenungguVerifikasi/PerluPerbaikan/Terdaftar → Dibatalkan (catatan "Dibatalkan oleh siswa", kursi lepas, `decided_by` tidak terisi); pada hari-H atau sesi dibatalkan → `AccessRuleException` "Pembatalan hanya dapat dilakukan sampai sehari sebelum sesi."; status lain ditolak.
  - `resubmit`: hanya dari PerluPerbaikan, sampai H-1; memeriksa ulang eligibility pada tanggal sesi dengan mengecualikan entri itu sendiri; hasil MenungguVerifikasi dan data terkunci lagi; kursi tidak berubah.
  - `EligibilityServiceTest`: pemeriksaan "sibuk" mengecualikan `exceptEntryId`; umur dihitung pada `$on`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="SessionRegistrationServiceTest|EligibilityServiceTest|StatusTransitionerTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi**
  - `register`: ambil baris siswa lewat `$actor->student()->first()` (null → pesan di atas); dalam `DB::transaction`: kunci sesi (`Schedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail()` — scope sekolah membuat sesi sekolah lain gagal), kunci baris siswa, periksa `isOpenForRegistration()`, eligibility pada `$schedule->date`, `seatsTaken() < quota`, lalu buat entri dan riwayat awal; tangkap `UniqueConstraintViolationException` di luar transaksi. Pesan kesalahan berupa `AccessRuleException` (alasan eligibility digabung dengan spasi).
  - `cancel`/`resubmit` memakai `StatusTransitioner`; `resubmit` memakai pola kunci siswa yang dipakai `RegistrationService::resubmit` lama (dihapus di Task 1; ambil polanya dari riwayat git).
  - `StatusTransitioner`: kondisi `decided_*` seperti Interfaces. `EligibilityService`: ubah parameter dan pesan.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: siswa mendaftar, membatalkan, dan mengajukan ulang pendaftaran sesi dengan kuota`

---

### Task 4: Rute dan halaman data siswa — Sesi rekaman, permission, menu

**Files:**
- Create: `app/Domains/Registrations/Http/Controllers/SelfRegistrationController.php`, `tests/Feature/Registrations/SessionRegistrationHttpTest.php`
- Modify: `routes/registrations.php`, `app/Domains/Access/Enums/Permission.php`, `resources/js/lib/permissions.ts`, `database/seeders/AccessSeeder.php`, `app/Domains/Access/Services/MenuService.php`, `app/Domains/Students/Http/Controllers/SelfStudentController.php`
- Test: `tests/Feature/Access/{AccessSeederTest,MenuTest,RoutePermissionMatrixTest}.php`, `tests/Unit/PermissionEnumTest.php`, `tests/Feature/Students/SelfStudentTest.php`

**Interfaces:**
- Consumes (Task 3): `RegistrationService::{register,cancel,resubmit}`, `EligibilityService::check`.
- Produces: rute `GET /saya/sesi` (`saya.sesi`, `can:pendaftaran.lihat`); `POST /saya/sesi/{schedule}/daftar` (`saya.sesi.daftar`, `can:pendaftaran.buat`, `throttle:10,1`); `POST /saya/pendaftaran/{entry}/batal` (`saya.pendaftaran.batal`, `can:pendaftaran.buat`, `throttle:30,1`); `POST /saya/pendaftaran/{entry}/ajukan-ulang` (`saya.pendaftaran.ajukan-ulang`, sama). Semua di dalam grup `auth`.
- Produces: props Inertia `Saya/Sesi`: `entry: null | {id, status, note, can_cancel, can_resubmit, schedule: {id, date, start_time, end_time, location}}` (entri terbaru siswa, status apa pun); `schedules: list<{id, date, start_time, end_time, location, quota, taken, full, eligible, reasons}>` — kosong bila siswa punya entri aktif, selain itu sesi sekolahnya yang tidak dibatalkan dan `lastRegistrationDay >= today`, urut tanggal, eligibility dihitung pada tanggal sesi; `ready: bool` (baris siswa ada); `close_days: int`.
- Produces: `Permission::PendaftaranAjukan` dihapus (enum dan `permissions.ts`); seeder: `siswa` + `pendaftaran.lihat`, `pendaftaran.buat`.
- Produces: menu — "Pendaftaran" dihapus; "Jadwal" berlabel "Sesi rekaman"; item "Sesi rekaman" `/saya/sesi` (icon `calendar-days`, izin `pendaftaran.lihat`) hanya untuk `isStudent()` (aturan `/saya` di `MenuService` diperluas menjadi semua href berawalan `/saya`).

- [ ] **Step 1: Tulis tes yang gagal**
  - `SessionRegistrationHttpTest`: siswa membuka `/saya/sesi` (props sesuai Interfaces: sesi penuh `full=true`; sesi sekolah lain, dibatalkan, atau lewat batas daftar tidak muncul; `schedules` kosong bila ada entri aktif; `entry.can_cancel` dan `can_resubmit` sesuai status/tanggal); `POST daftar` sukses → redirect `saya.sesi` + flash `success`, gagal (penuh/belum lengkap) → flash `error` dan tidak ada entri; throttle `daftar` 10/menit terpasang.
  - **Kepemilikan:** siswa B membatalkan atau mengajukan ulang entri siswa A (satu sekolah) → 404 dan entri A tidak berubah; sesi sekolah lain → 404.
  - Akses: admin dan operator (punya/tidak punya izin) yang membuka `/saya/sesi` atau memposting → 404/403 (admin 404 karena `isStudent()`, operator 403 karena izin); pengguna tanpa login → redirect login.
  - `SelfStudentTest`: kartu eligibility di `/saya` tidak menampilkan alasan "sudah terdaftar" untuk siswa yang punya entri aktif (controller memanggil `check($student, null, $activeEntryId)`).
  - Seeder/menu/enum/matrix: `siswa` memegang tepat `profil.lihat`, `profil.ubah`, `dokumen.*`, `pendaftaran.lihat`, `pendaftaran.buat`; menu siswa memuat "Data saya" dan "Sesi rekaman"; menu admin tidak memuat `/saya/*` dan tidak lagi memuat "Pendaftaran"; baris `/pendaftaran*` dan `pendaftaran.ajukan` hilang dari `RoutePermissionMatrixTest`, baris `saya.sesi*` dan `saya.pendaftaran.*` ditambahkan.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="SessionRegistrationHttpTest|AccessSeederTest|MenuTest|RoutePermissionMatrixTest|PermissionEnumTest|PermissionTypescriptSyncTest|SelfStudentTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi**
  - `SelfRegistrationController` tipis; setiap aksi diawali `abort_unless($request->user()->isStudent(), 404)`: `index` menyusun props lewat `RegistrationService`/repository (bukan query di controller); aksi memanggil service lalu `back()`/redirect dengan flash. Galat `AccessRuleException` sudah dirender sebagai `back()->with('error')` oleh handler yang ada.
  - Binding `{entry}` ke `RegistrationStudent` (scope pemilik menghasilkan 404); `{schedule}` ke `Schedule` (scope sekolah).
  - Hapus `PendaftaranAjukan` dari enum dan cerminan TS; `MenuService`, seeder, dan `SelfStudentController` sesuai Interfaces. Jalankan `AccessSeeder` ulang di DB dev dicatat di Task 9.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: halaman sesi rekaman siswa dan izin pendaftaran sesi`

---

### Task 5: Verifikasi, ceklis manual, dan kartu siswa memakai model baru

**Files:**
- Modify: `app/Domains/Registrations/Services/VerificationService.php`, `app/Domains/Registrations/Http/Controllers/VerificationController.php`, `app/Domains/Registrations/Support/EntryPresenter.php`, `app/Domains/Registrations/Repositories/EloquentRegistrationRepository.php`, `app/Domains/Schedules/Services/RecordingService.php`, `app/Domains/Schedules/Http/Controllers/RecordingController.php`, `app/Domains/Schedules/Repositories/EloquentScheduleRepository.php`, `app/Domains/Students/Http/Controllers/StudentController.php`, `app/Domains/Students/Services/StudentService.php`, `app/Domains/Students/Repositories/{Contracts/StudentRepositoryInterface,EloquentStudentRepository}.php`
- Delete: `app/Domains/Notifications/Notifications/EntryDecidedNotification.php`
- Test (porting, pakai peta status di Global Constraints): `tests/Feature/Registrations/{VerificationServiceTest,VerificationHttpTest,StatusTransitionerTest,StudentRegistrationCardTest}.php`, `tests/Feature/Schedules/{RecordingServiceTest,RecordingHttpTest}.php`, `tests/Feature/Notifications/RegistrationNotificationTest.php`, `tests/Feature/Performance/ListQueryCountTest.php`

**Interfaces:**
- Consumes: `RegistrationStatus`, `StatusTransitioner`, `EligibilityService::check(..., $exceptEntryId)`.
- Produces: `VerificationService::decide(RegistrationStudent $entry, 'approve'|'fix'|'reject' $decision, ?string $note, User $actor): void` — approve → Terdaftar, fix → PerluPerbaikan, reject → Ditolak; hanya dari MenungguVerifikasi; log `pendaftaran.diputuskan` dengan subject entri; **tanpa notifikasi** (7c). `approveMany(list<int> $ids, User): int` — semua-atau-tidak-sama-sekali, kelayakan diperiksa ulang pada tanggal sesi tiap entri.
- Produces: repository `queue()` menyaring `status = menunggu_verifikasi` dengan filter `school_id`, `schedule_id`, `search` (nama/NISN); baris antrean dan halaman keputusan memuat `session: {id, date, start_time, end_time, location}` menggantikan `registration`.
- Produces: `EntryPresenter::row` menangani riwayat `from` null (`'from' => null`).
- Produces: `RecordingService::mark` bekerja pada status Terdaftar (menggantikan Terjadwal) dan `undo` mengembalikan ke Terdaftar; notifikasi `marked`/`undone` ke operator tetap; `checklistItem` tanpa `hint`.
- Produces: `StudentController::show` — `registrations` berisi `{id (id entri), status, schedule: {id, date, start_time, end_time, location}}`; `StudentService::candidatesPage` dan `StudentRepositoryInterface::searchForSchool` dihapus.

- [ ] **Step 1: Porting dan tes baru (merah dulu)**
  - Verifikasi: approve → Terdaftar dengan `decided_by`; fix/reject butuh catatan (aturan `DecisionRequest` yang ada); keputusan pada entri yang bukan MenungguVerifikasi ditolak; `approveMany` atomik, menolak seluruhnya bila satu entri tidak layak pada tanggal sesinya, dan tidak lagi mengirim notifikasi; antrean menyaring per sesi dan per sekolah serta tidak memuat status lain; operator sekolah tidak punya akses antrean (izin).
  - Rekaman: ceklis hanya untuk Terdaftar pada sesi itu, menolak sebelum hari-H, batalkan penandaan kembali ke Terdaftar, menolak bila siswa sudah punya entri aktif lain.
  - Kartu siswa: `registrations.0.id` = id entri, `status` `menunggu_verifikasi`, memuat jadwal.
  - `ListQueryCountTest`: jumlah query halaman jadwal/antrean tidak tumbuh per baris (sesuaikan data uji, anggaran query tidak dilonggarkan).
  - Tes notifikasi lama untuk pengajuan/keputusan batch dihapus; tambahkan tes bahwa keputusan **tidak** mengirim notifikasi ke operator maupun admin.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="VerificationServiceTest|VerificationHttpTest|StatusTransitionerTest|StudentRegistrationCardTest|RecordingServiceTest|RecordingHttpTest|ListQueryCountTest|RegistrationNotificationTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces; hapus `candidatesPage`/`searchForSchool`; `VerificationController::show` memanggil `check($student, $entry->schedule->date, $entry->id)`; tidak ada lagi referensi ke `Registration` (cek dengan `grep -rn "Registration\\b" app`).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `refactor: verifikasi dan ceklis rekaman memakai status pendaftaran sesi`

---

### Task 6: Beranda dan rekap dipetakan ke status baru

**Files:**
- Modify: `app/Domains/Reports/Services/DashboardService.php`, `app/Domains/Reports/Services/ReportService.php`, `app/Domains/Reports/Repositories/EloquentReportRepository.php`, `app/Domains/Reports/Repositories/Contracts/ReportRepositoryInterface.php`, `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/Reports/{DashboardSummaryTest,ReportServiceTest,ReportHttpTest}.php`, `tests/Feature/Schedules/DashboardTest.php`

**Interfaces:**
- Produces: `ReportService::recap(?string $from, ?string $to)` — kolom = seluruh `RegistrationStatus::cases()` (urutan enum), tanpa pengecualian Draf; periode menyaring `registration_students.created_at` (inklusif, batas hari seperti sekarang) tanpa join `registrations`.
- Produces: `DashboardService::forUser` — pemetaan: kartu/aksi "Menunggu verifikasi" memakai `MenungguVerifikasi`; "Terjadwal" menjadi "Terdaftar" (`Terdaftar`); "Draf"/"Terverifikasi"/"belum dijadwalkan" dibuang. KPI staf: *Jumlah Pelajar Terdaftar* = semua entri; *Sudah Terdaftar Sesi* = Terdaftar; *Sudah Melakukan Perekaman* = SudahRekaman; *Menunggu Verifikasi* = MenungguVerifikasi + PerluPerbaikan; kunci KPI lama (`total_students`, `scheduled`, `recorded`, `in_process`, `unscheduled`) dipertahankan agar komponen frontend tidak berubah, label diperbarui. Donut: Sudah Rekam / Terdaftar / Menunggu & Perbaikan. Operator (hanya memantau): tanpa kartu/aksi pendaftaran dan tanpa tautan ke rute yang sudah dihapus; aksi cepat staf "Tambah Pendaftaran" dibuang, "Buat Jadwal" menjadi "Buat Sesi Rekaman".
- Produces: `DashboardController` mengisi `participants` dari `taken_count`.

- [ ] **Step 1: Porting tes (merah dulu)** — `DashboardSummaryTest`/`DashboardTest`: kartu, aksi, dan KPI per peran dengan status baru; tidak ada tautan ke rute yang tidak ada (tes menelusuri semua `href` payload dan memastikan dapat di-resolve); operator tidak melihat angka sekolah lain. `ReportServiceTest`/`ReportHttpTest`: kolom sembilan status, filter periode menurut tanggal pendaftaran, baris total, ekspor mengikuti rekap, izin `laporan.lihat`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="DashboardSummaryTest|DashboardTest|ReportServiceTest|ReportHttpTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces; tidak membuat metrik baru (itu 7d).
- [ ] **Step 4: Jalankan seluruh backend** — `php artisan test` (jalankan di latar belakang, ±3 menit) lalu `vendor/bin/pint --test` dan `vendor/bin/phpstan analyse --memory-limit=1G`; Expected: **seluruh suite hijau**, Pint dan PHPStan bersih. Perbaiki sisa tes lama yang gagal di sini bila ada (mis. `RouteGuardAuditTest`, `AccessGuardTest`).
- [ ] **Step 5: Commit** — `refactor: beranda dan rekap memakai status pendaftaran sesi`

---

### Task 7: Frontend — tipe, status, dan halaman Sesi rekaman (staf)

**Files:**
- Modify: `resources/js/types/registration.ts`, `resources/js/types/schedule.ts`, `resources/js/lib/status.ts`, `resources/js/lib/status.test.ts`, `resources/js/Components/shared/StatusBadge.test.ts`, `resources/js/Pages/Jadwal/{Form,Index,Show,Rekaman}.vue`, `resources/js/Components/shared/EntryChecklist.vue` (+ test)
- Delete: `resources/js/Pages/Pendaftaran/{Form,Index,Show}.vue`, `resources/js/Components/shared/StudentMultiSelect.vue` (+ test)
- Create: `resources/js/Pages/Jadwal/Jadwal.test.ts`

**Interfaces:**
- Consumes (Task 2, 5): payload `Jadwal/*` dan `Verifikasi/*` seperti di Interfaces task tersebut.
- Produces: `RegistrationStatus` TS = sembilan nilai baru; `REGISTRATION_STATUS` di `lib/status.ts` (varian: menunggu_verifikasi `info`, perlu_perbaikan `warning`, terdaftar `success`, sudah_rekaman `success`, tidak_hadir `danger`, belum_rekaman `warning`, ditolak `danger`, dibatalkan `neutral`, kedaluwarsa `neutral`); tipe `ScheduleRow`/`ScheduleDetail` memuat `start_time`, `end_time`, `quota`, `taken`, `cancelled`, `last_registration_day`; tipe pendaftaran batch (`RegistrationRow`, `RegistrationDetail`, `CandidateRow`) dihapus.

- [ ] **Step 1: Tulis tes Vitest yang gagal** — `status.test.ts` (sembilan status berlabel + varian di atas, status tak dikenal tetap aman); `Jadwal.test.ts`: *Form* — kolom Jam mulai/Jam selesai/Kuota tampil dan divalidasi di klien ("Jam selesai harus setelah jam mulai.", kuota 1–1000), pada ubah dengan peserta kolom Tanggal tampil sebagai teks baca-saja (bukan input `disabled`, agar terbaca dan terjangkau pembaca layar) dengan keterangan; *Index* — kolom Kuota menampilkan "12 dari 30 kursi", sesi dibatalkan diberi teks "Dibatalkan", teks "Sudah lewat" tetap; *Show* — tabel peserta menampilkan semua status dengan badge berteks, tombol **Batalkan sesi** hanya untuk pemegang `jadwal.ubah` dan sesi yang belum lewat/dibatalkan, dialog pembatalan menyebut jumlah pendaftar yang terdampak dan mewajibkan catatan, tombol **Hapus** hanya untuk sesi tanpa peserta dan berada di menu aksi (`RowActions`), terpisah dari tombol **Batalkan sesi** supaya dua aksi merusak tidak bersebelahan; ringkasan kursi memisahkan "Terdaftar" dari "Menunggu verifikasi" (teks), tidak ada lagi tombol Tambah siswa/Pindahkan/Keluarkan; *Rekaman* — daftar ceklis memakai status Terdaftar.
- [ ] **Step 2: Jalankan, pastikan gagal** — `npx vitest run resources/js/lib/status.test.ts resources/js/Pages/Jadwal`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — ikuti panduan UI (§5.25–5.27, diperbarui di Task 9) dan komponen bersama yang sudah ada (`PageHeader`, `DataTable`, `FormField`, `ConfirmDialog`, `StatusBadge`, `InlineAlert`). Semua judul/menu/breadcrumb "Jadwal" → "Sesi rekaman". Tanggal/jam lewat `lib/date.ts` (tambah `formatTime` bila belum ada, format `14.30`). Dialog pembatalan sesi: kolom Catatan dengan label, minimal 5 karakter, tombol destruktif menyebut nama sesi. Teks status selalu berupa kata, bukan warna saja.
- [ ] **Step 4: Jalankan, pastikan lulus** — `npx vitest run resources/js/lib resources/js/Pages/Jadwal resources/js/Components/shared`; lalu `npx vue-tsc --noEmit`; Expected: PASS, tipe bersih (halaman Verifikasi/Saya/Siswa yang belum diperbarui bisa memicu galat tipe: itu dibereskan di Task 8, jangan ditambal di sini).
- [ ] **Step 5: Commit** — `feat: halaman sesi rekaman staf dengan kuota dan pembatalan sesi`

---

### Task 8: Frontend — halaman Sesi rekaman siswa, verifikasi, kartu siswa

**Files:**
- Create: `resources/js/Pages/Saya/Sesi.vue`, `resources/js/Pages/Saya/Sesi.test.ts`
- Modify: `resources/js/Pages/Saya/Index.vue` (+ `Saya.test.ts`), `resources/js/Pages/Verifikasi/{Index,Show}.vue` (+ tes bila ada), `resources/js/Pages/Siswa/Show.vue` (+ `Siswa.test.ts`), `resources/js/Pages/Laporan/Index.vue` (hanya sub-judul), `resources/js/Components/shared/StatusTimeline.vue`, `resources/js/types/student.ts`
- Test: `resources/js/Pages/Saya/Sesi.test.ts`, `Saya.test.ts`, `Siswa.test.ts`, `resources/js/Pages/Verifikasi/Verifikasi.test.ts` (baru bila belum ada)

**Interfaces:**
- Consumes (Task 4–5): props `Saya/Sesi` (`entry`, `schedules`, `ready`, `close_days`), antrean/keputusan `session`, `registrations[].schedule` pada detail siswa, kolom rekap sembilan status.
- Produces: halaman `Saya/Sesi` (rute `saya.sesi`) dengan keadaan (teks selalu menyertai status):
  0. Semua keadaan memakai satu kerangka: judul halaman "Sesi rekaman", satu kartu utama, dan satu baris **Langkah berikutnya** (teks, bukan hanya badge) yang menyebut apa yang harus dilakukan siswa atau bahwa ia menunggu petugas.
  1. `ready=false`: banner "Lengkapi data diri dan dokumen dulu" + tombol ke `/saya`; tanpa daftar sesi.
  2. Ada `entry` aktif: kartu **Pendaftaran Anda** — judul kartu = hari, tanggal, dan jam sesi (info yang dicari siswa di hari-H), lalu badge status dan penunjuk tahap tertulis "Menunggu verifikasi → Terdaftar → Rekaman" (tahap sekarang ditandai teks dan `aria-current`), lalu (`14.30–17.00`), lokasi, catatan petugas (Perlu perbaikan/Ditolak) sebagai teks; MenungguVerifikasi: "Menunggu pemeriksaan petugas; data Anda terkunci sementara."; PerluPerbaikan: tombol **Perbaiki data** (ke `/saya`) dan **Ajukan ulang** (dialog konfirmasi); tombol **Batalkan pendaftaran** (ConfirmDialog; hanya bila `can_cancel`; bila tidak, keterangan "Pembatalan hanya sampai sehari sebelum sesi."); Terdaftar: keterangan "Anda terdaftar. Datang sesuai jadwal." (tiket di 7c; jangan menjanjikan tiket di teks).
  3. Tanpa entri aktif: daftar sesi (satu daftar kartu responsif, bukan dua render) dengan tanggal+hari, jam, lokasi, "sisa N dari M kursi", tombol **Daftar** (dialog konfirmasi menyebut tanggal, jam, lokasi, bahwa data dan dokumen terkunci selama diperiksa, dan caranya bila ada yang salah: "Batalkan pendaftaran untuk mengubah data, atau tunggu petugas meminta perbaikan."); sesi penuh tampil "Penuh" dan tombol nonaktif; sesi `eligible=false` menampilkan alasan sebagai teks dan tombol nonaktif — alasan yang sama di **semua** sesi (mis. dokumen belum diunggah) diangkat sekali ke banner di atas daftar (irisan `reasons` antar sesi, dihitung di klien), per sesi hanya alasan khas sesi itu (umur pada tanggal tersebut); bila ada lebih dari 4 sesi, kelompokkan menurut bulan; keterangan "Pendaftaran ditutup {close_days} hari sebelum sesi." Empty state "Belum ada sesi yang dibuka untuk sekolah Anda" bila kosong. Bila entri terakhir Ditolak/Dibatalkan, tampil ringkasan kecilnya di atas daftar.
  - Galat server (`flash.error`) tampil sebagai banner; tombol submit menampilkan spinner dan nonaktif saat proses; setelah sukses fokus pindah ke judul kartu hasil.
- Produces: `Saya/Index.vue` — kartu "Sesi rekaman" dengan tautan ke `saya.sesi` setelah data+dokumen lengkap; terkunci (MenungguVerifikasi/Terdaftar/SudahRekaman) tetap read-only seperti di 7a.

- [ ] **Step 1: Tulis tes Vitest yang gagal** — `Sesi.test.ts` untuk tiga keadaan di atas (termasuk penuh, tidak eligible, can_cancel false, ajukan ulang, dialog konfirmasi memanggil `router.post` ke rute yang benar dengan `preserveScroll`); `Saya.test.ts` (tautan sesi hanya saat lengkap); `Siswa.test.ts` (kartu pendaftaran menampilkan tanggal sesi, bukan judul batch); Verifikasi — baris antrean menampilkan sesi, filter Sesi, dialog keputusan tetap mewajibkan catatan untuk perbaikan/tolak.
- [ ] **Step 2: Jalankan, pastikan gagal** — `npx vitest run resources/js/Pages/Saya resources/js/Pages/Siswa resources/js/Pages/Verifikasi resources/js/Pages/Laporan`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — ikuti panduan UI dan pola 7a (`Saya/Index.vue`, `Sekolah/Tautan.vue`): kartu `SectionCard`, `PageHeader`, `ConfirmDialog`, `InlineAlert`, `StatusBadge`; area sentuh ≥ 40 px; fokus terkelola; bahasa baku tanpa jargon ("sesi rekaman", "kursi", bukan "slot/kuota" di UI siswa). Tidak ada nomor kontak bantuan yang dikarang.
- [ ] **Step 4: Jalankan, pastikan lulus** — `npm test` (seluruh Vitest), `npx vue-tsc --noEmit`, `npx eslint` pada berkas yang disentuh, `npm run build`; Expected: semua hijau. (Jangan menjalankan `prettier --write` pada seluruh folder; ia mengubah berkas di luar cakupan.)
- [ ] **Step 5: Commit** — `feat: halaman sesi rekaman siswa dan penyesuaian verifikasi serta kartu siswa`

---

### Task 9: Dokumentasi, panduan UI, dan verifikasi akhir fase

**Files:**
- Modify: `docs/deployment-guide.md` (bagian 9), `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (§5.21–5.27 dan menu), memori proyek `alur-mandiri-siswa.md`
- Test: tidak ada tes baru; jalankan seluruh verifikasi.

- [ ] **Step 1: Dokumentasi** — `deployment-guide.md`: urutan wajib `php artisan sijebol:bersihkan-data-siswa` → `php artisan migrate --force` → `php artisan db:seed --class=AccessSeeder --force` (migrasi menolak jalan bila data lama masih ada; migrasi satu arah, ambil cadangan basis data dulu); catatan `config/sijebol.php`. Panduan UI: ganti §5.21–5.23 (pendaftaran batch, pemilih siswa) dengan satu paragraf "Sesi rekaman siswa `/saya/sesi`" yang menunjuk keadaan di Task 8, dan beri catatan beda singkat pada §5.24–5.28 (antrean per sesi, jadwal→sesi rekaman, kuota, batalkan sesi, tanpa penempatan, rekap menurut tanggal pendaftaran).
- [ ] **Step 2: Verifikasi penuh** — `php artisan test` (latar belakang), `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vue-tsc --noEmit`, `npm test`, `npm run build`, `npx eslint` pada berkas yang disentuh; Expected: semua hijau. `grep -rn "pendaftaran.ajukan\|Registration::\|Terjadwal\|Terverifikasi\|Diajukan" app resources/js routes lang` tidak menyisakan referensi ke status/rute yang dihapus (kecuali teks label sah).
- [ ] **Step 3: Penelusuran manual (daftar untuk user, belum dijalankan Claude)** — di DB dev setelah urutan perintah Step 1: admin membuat sesi (kuota 2) → dua akun siswa dari link sekolah melengkapi data+dokumen → keduanya mendaftar → siswa ketiga ditolak "Kuota penuh" → admin menyetujui satu dan meminta perbaikan yang lain → siswa memperbaiki dan mengajukan ulang → admin membatalkan sesi dan kursi lepas → ceklis manual pada sesi uji di hari-H. Uji juga migrasi pada DB berisi data lama untuk melihat penolakan dengan pesan.
- [ ] **Step 4: Memori** — perbarui `alur-mandiri-siswa.md`: status 7b, keputusan perancang di atas, daftar 7c/7d yang tersisa.
- [ ] **Step 5: Commit** — `docs: perbarui panduan deploy dan panduan UI untuk sesi rekaman`
