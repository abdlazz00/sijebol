# Fase 7c — Perbaikan Berbatas Waktu, Tiket QR, Notifikasi Siswa, dan Scheduler Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Siswa diberi tahu atas setiap keputusan dan perubahan yang menyangkut pendaftarannya, mendapat tiket QR saat disetujui, dan kursi yang tidak jadi dipakai dilepas otomatis (batas waktu perbaikan, sesi berakhir) — sehingga fase 7b layak dirilis.

**Architecture:** Memakai ulang `StatusTransitioner` (aktor boleh `null` = sistem), sistem `Notification` yang ada (email + lonceng, antrean), dan scheduler Laravel. Satu kelas notifikasi siswa `RegistrationNotification` dengan pabrik statis (pola `ScheduleNotification`). Kolom baru di `registration_students`: `ticket_code`, `fix_deadline_at`, `fix_reminded_at`, `session_reminded_at`. Dua perintah terjadwal: kedaluwarsa dan pengingat. QR digambar di peramban dari `ticket_code` (tanpa data pribadi).

**Tech Stack:** Laravel 12 + Inertia + Vue 3 + TypeScript, Pest (butuh MySQL `sijebol_test`, jangan dijalankan dua proses sekaligus), Vitest, Larastan (`--memory-limit=1G`), Pint, paket npm `qrcode` (baru).

**Spec:** [`docs/superpowers/specs/2026-10-19-alur-mandiri-siswa-design.md`](../specs/2026-10-19-alur-mandiri-siswa-design.md) (bagian 4.4, 4.5, 4.7, 4.9, 5, 6, 8). Panduan UI: [`2026-10-07-sijebol-ui-guide.md`](../specs/2026-10-07-sijebol-ui-guide.md). Fase sebelumnya: [`2026-10-19-fase-7b-sesi-rekaman-dan-pendaftaran-siswa.md`](2026-10-19-fase-7b-sesi-rekaman-dan-pendaftaran-siswa.md) (selesai, di-commit `464c55c`).

**Cakupan fase ini (spec §10, tahap 3):** batas waktu perbaikan + Kedaluwarsa, aturan "Minta perbaikan hanya bila sesi paling cepat besok", tiket + QR, seluruh notifikasi ke siswa (disetujui, perlu perbaikan, ditolak, kursi dilepas, sesi diubah/dibatalkan, dibatalkan petugas, pengingat), pembatalan pendaftaran oleh admin, ganti sekolah siswa = batalkan kursi lama, aktor "sistem" di riwayat, dua perintah terjadwal, dan sisa minor 7b yang menyentuh berkas yang sama.

**Di luar fase ini (7d, jangan dikerjakan di sini):** halaman scan QR dan penandaan hadir lewat scan, `scanned_at`, penandaan Tidak hadir otomatis setelah sesi, label "KTP-el langsung", beranda dan laporan baru, WhatsApp. Ticket di 7c hanya diterbitkan dan ditampilkan; belum ada yang membacanya selain halaman tiket.

## Mode eksekusi

Ditanyakan ke user setelah rencana disetujui (di 7a awalnya kolaboratif lalu "Saya kerjakan semua"; di 7b "Saya kerjakan semua").

## Penyimpangan dan keputusan perancang (perlu persetujuan user)

1. **"Buka kunci" tidak dibuat sebagai fitur terpisah** (spec §4.9). Kebutuhannya sudah tertutup oleh tiga jalur: *Minta perbaikan* (membuka data selama Perlu perbaikan), *batalkan pendaftaran oleh admin* (siswa bebas mengubah data lalu mendaftar lagi), dan *koreksi data oleh admin* yang mengabaikan kunci (7a). Menambah transisi baru dari Terdaftar akan melanggar peta status spec §6.
2. **Pembatalan pendaftaran oleh admin memakai izin `verifikasi.putuskan`** (tidak ada izin baru), hanya untuk Menunggu verifikasi / Perlu perbaikan / Terdaftar, wajib catatan ≥ 5 karakter, hanya sampai sehari sebelum sesi (spec §4.7); pada hari sesi dipakai penandaan manual. Tombolnya di tabel peserta halaman sesi.
3. **QR digambar di peramban memakai paket npm `qrcode`** (belum ada pustaka QR di proyek, Composer maupun npm). Isi QR = `ticket_code` acak 32 karakter, tanpa NIK/nama. Tiket sah selama status Terdaftar; halaman tiket 404 untuk status lain. Kode tidak dihapus saat tiket ditarik (keabsahan ditentukan status, dibaca scan di 7d).
4. **Aktor sistem = `by_user_id` NULL di riwayat**, tampil "Sistem" (kolom dibuat nullable; `StatusTransitioner::move` menerima `?User`). `decided_by` tidak diisi untuk aksi sistem.
5. **Scheduler tiap jam** (`withoutOverlapping`): perintah *kedaluwarsa* dan *pengingat*. Pengingat sesi H-1 dikirim sejak pukul 08.00 WIB (`config('sijebol.reminder_hour')`); pengingat batas perbaikan dikirim saat batas tinggal ≤ 24 jam. Idempotensi lewat kolom `*_reminded_at`.
6. **Pendaftaran yang sesinya sudah lewat** dan masih Menunggu verifikasi / Perlu perbaikan menjadi Kedaluwarsa oleh scheduler (spec §4.4), dan keputusan admin pada sesi yang sudah lewat ditolak server (menutup minor 7b #8).
7. **Batas perbaikan** = yang lebih awal antara 48 jam sejak diminta (`config('sijebol.fix_window_hours')`) dan akhir hari sebelum sesi (23.59.59 H-1). Setelah batas, siswa tidak dapat mengajukan ulang walau scheduler belum jalan.
8. **Ganti sekolah siswa oleh admin membatalkan** pendaftaran Menunggu verifikasi / Perlu perbaikan / Terdaftar (kursi dilepas, siswa diberi tahu) menggantikan penolakan 7a; siswa berstatus Sudah rekaman tetap menahan penggantian sekolah.
9. **Notifikasi dikirim ke akun siswa saja** (`students.user_id`); siswa tanpa akun atau akun nonaktif dilewati tanpa galat. Admin dan operator tidak menerima notifikasi per pendaftar (spec §5). Satu metode baru `NotificationService::toStudent`.
10. **Sisa minor 7b ikut dikerjakan** karena menyentuh berkas yang sama: setujui tunggal memeriksa ulang kelayakan, hapus sesi di bawah kunci baris, sesi yang sudah lewat tidak dapat diubah, spinner tombol Daftar.

## Global Constraints

- Bahasa UI, pesan, dan isi notifikasi: Bahasa Indonesia baku. Isi notifikasi tidak memuat NIK; tiket dan notifikasi tidak memuat data pribadi selain nama siswa di halaman tiket.
- Hak akses lewat enum `Permission` + seeder + UI RBAC; tidak ada izin baru di fase ini. Rute `/saya/*` tetap 404 bagi non-siswa dan milik siswa lain.
- Semua perubahan status lewat `StatusTransitioner` dengan riwayat; aksi tulis baru memakai `throttle`; semua perubahan data tercatat lewat `activity()`.
- Notifikasi dikirim **setelah** transaksi berhasil (di luar `DB::transaction`), lewat antrean (`BaseNotification` sudah `ShouldQueue`); tes memakai `Notification::fake()`.
- Zona waktu `Asia/Jakarta` (`Schedule::today()`); semua batas hari dihitung di satu tempat (`Schedule`).
- Tidak ada `Co-Authored-By` atau atribusi AI pada commit message (aturan CLAUDE.md mengalahkan atribusi bawaan); commit hanya setelah user memintanya.
- Verifikasi akhir fase: `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vue-tsc --noEmit`, `npx vitest run` (bila time-out di mesin berbeban, ulangi dengan `--testTimeout=30000`), `npx eslint` pada berkas yang disentuh, `npm run build`.

## Review Focus

1. Kedaluwarsa: Perlu perbaikan lewat batas dan sesi yang sudah lewat menjadi Kedaluwarsa, kursi dilepas dan siswa diberi tahu **sekali**; menjalankan perintah dua kali tidak menggandakan notifikasi; siswa tidak dapat mengajukan ulang setelah batas walau perintah belum jalan. (Task 3, 5)
2. Batas hari: pada hari sesi hanya Setujui/Tolak (Minta perbaikan ditolak server); batas perbaikan = min(+48 jam, akhir H-1); pembatalan admin ditolak pada hari sesi; keputusan pada sesi lewat ditolak. (Task 3, 4)
3. Tiket: kode hanya ada setelah disetujui, unik, tidak bocor di payload lain (antrean, detail siswa, daftar peserta, notifikasi); halaman tiket 404 untuk status lain dan untuk siswa lain; kode tetap sama setelah penandaan dibatalkan. (Task 3, 6)
4. Ganti sekolah: pendaftaran pra-kehadiran dibatalkan atomik bersama perubahan sekolah (gagal sebagian = tidak ada yang berubah), Sudah rekaman menahan. (Task 4)
5. Penerima notifikasi: hanya siswa pemilik pendaftaran (bukan siswa lain, operator, atau admin); siswa tanpa akun/nonaktif dilewati; perubahan sesi hanya memberi tahu pendaftar yang masih relevan; pengingat tidak terkirim dua kali. (Task 2, 4, 5)

---

### Task 1: Skema tiket/tenggat, aktor sistem, dan konfigurasi

**Files:**
- Create: `database/migrations/2026_10_21_100001_add_ticket_and_deadlines_to_registration_students_table.php`
- Modify: `config/sijebol.php`, `app/Domains/Registrations/Models/RegistrationStudent.php`, `app/Domains/Registrations/Models/RegistrationStatusHistory.php`, `app/Domains/Registrations/Services/StatusTransitioner.php`, `app/Domains/Registrations/Support/EntryPresenter.php`, `app/Domains/Schedules/Models/Schedule.php`
- Test: `tests/Feature/Registrations/StatusTransitionerTest.php`, `tests/Feature/Registrations/TicketSchemaTest.php` (baru), `tests/Feature/Schedules/ScheduleModelTest.php`, `tests/Feature/Registrations/VerificationHttpTest.php` (riwayat sistem)

**Interfaces:**
- Produces: kolom `registration_students.ticket_code` (string 40, nullable, **unik**), `fix_deadline_at`, `fix_reminded_at`, `session_reminded_at` (timestamp nullable); `registration_status_histories.by_user_id` nullable (FK tetap).
- Produces: `config('sijebol.fix_window_hours')` = `48`, `config('sijebol.reminder_hour')` = `8`.
- Produces: `StatusTransitioner::move(RegistrationStudent $entry, RegistrationStatus $to, ?User $actor, ?string $note = null, array $attributes = []): RegistrationStudent` — `$actor` null = sistem: `decided_by`/`decided_at` tidak diisi, riwayat `by_user_id` null.
- Produces: `RegistrationStudent::$fillable` memuat empat kolom baru, cast datetime untuk tiga timestamp; `RegistrationStatusHistory::user()` tetap, nilainya null untuk sistem.
- Produces: `Schedule::fixDeadlineFrom(CarbonInterface $now): Carbon` = yang lebih awal antara `$now` + `fix_window_hours` jam dan `tanggal sesi − 1 hari` pukul 23.59.59 (zona Jakarta).
- Produces: `EntryPresenter::row` — riwayat `by` bernilai `'Sistem'` bila `by_user_id` null.

- [ ] **Step 1: Tulis tes yang gagal**
  - `TicketSchemaTest`: kolom baru ada; dua entri tidak boleh memakai `ticket_code` sama (`UniqueConstraintViolationException`), banyak entri boleh `ticket_code` null; riwayat dengan `by_user_id` null tersimpan.
  - `StatusTransitionerTest`: `move(..., actor: null)` menulis riwayat `by_user_id` null, tidak mengisi `decided_by`/`decided_at` walau tujuan Terdaftar; `move` dengan aktor tetap mengisinya (perilaku lama); `$attributes` (mis. `ticket_code`) ikut tersimpan.
  - `ScheduleModelTest`: `fixDeadlineFrom` — sesi 10 hari lagi → `now + 48 jam`; sesi besok pukul `now = 10.00` → hari ini 23.59.59; sesi lusa dengan `now` 22.00 → `now + 48 jam` jatuh hari sesi, jadi dipotong ke akhir H-1 (besok 23.59.59).
  - `VerificationHttpTest`: halaman keputusan menampilkan riwayat berpelaku null sebagai `history.N.by = 'Sistem'`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="TicketSchemaTest|StatusTransitionerTest|ScheduleModelTest|VerificationHttpTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi**
  - Migrasi `up()`: tambah empat kolom (`ticket_code` unik); `by_user_id` menjadi nullable lewat `->nullable()->change()` pada kolom berkunci asing (MySQL 8). `down()` boleh melempar `RuntimeException` seperti migrasi 7b atau membalikkan; ikuti gaya migrasi 7b bila membalik tidak aman.
  - `StatusTransitioner`: parameter `?User $actor`; `decided_*` hanya bila `$actor !== null` dan tujuan termasuk `DECISIONS`; `by_user_id => $actor?->id`.
  - `EntryPresenter`: `'by' => $h->user?->name ?? 'Sistem'`.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tambah kode tiket, tenggat perbaikan, dan aktor sistem pada pendaftaran sesi`

---

### Task 2: Notifikasi ke siswa

**Files:**
- Create: `app/Domains/Notifications/Notifications/RegistrationNotification.php`
- Modify: `app/Domains/Notifications/Services/NotificationService.php`
- Test: `tests/Feature/Notifications/RegistrationNotificationTest.php`

**Interfaces:**
- Consumes: `BaseNotification` (judul, isi, url; kanal `database` + `mail`).
- Produces: `RegistrationNotification` (konstruktor privat, pabrik statis; semua menerima data primitif, bukan model): `approved(int $entryId, string $date, string $startTime, string $endTime, string $location)`; `needsFix(string $note, CarbonInterface $deadline)`; `rejected(string $note)`; `seatReleased(string $reason)` (kedaluwarsa); `cancelledByStaff(string $note)` (dipakai juga saat sekolah diganti, dengan catatan "Sekolah Anda diganti oleh admin. …"); `sessionChanged(string $date, string $startTime, string $endTime, string $location)`; `sessionCancelled(string $date, string $note)`; `fixReminder(CarbonInterface $deadline)`; `sessionReminder(string $date, string $startTime, string $endTime, string $location)`. `url()`: `approved` → `/saya/tiket/{entryId}`; `needsFix`, `fixReminder` → `/saya`; semua lainnya → `/saya/sesi`.
- Produces: `NotificationService::toStudent(Student $student, BaseNotification $notification): bool` — mengirim ke `$student->user` bila ada dan `is_active`; mengembalikan true bila terkirim, false bila dilewati.

- [ ] **Step 1: Tulis tes yang gagal**
  - Isi pesan (judul dan inti isi) tiap pabrik, dengan tanggal berformat Indonesia ("Selasa, 20 Okt 2026" tidak wajib; cukup memuat hari-tanggal-bulan-tahun Indonesia dan jam `08.00–12.00`, lokasi, catatan). `approved` menyebut "tiket", saran "Simpan tiket sebagai gambar agar tetap bisa dibuka tanpa sinyal", dan url `/saya/tiket/{id}`; `needsFix` memuat catatan dan batas ("sebelum …"); `rejected` menyebut kursi dilepas; `sessionCancelled` menyebut pendaftaran ikut dibatalkan; tidak ada isi yang memuat NIK (uji dengan siswa ber-NIK dikenal: isi tidak mengandung NIK).
  - `toStudent`: terkirim ke akun siswa aktif (`Notification::assertSentTo`); dilewati (false, tidak ada notifikasi) untuk siswa tanpa `user_id` dan untuk akun `is_active = false`; tidak pernah mengirim ke siswa lain, operator sekolah, atau admin.
  - Kanal: `via()` memuat `database` dan `mail`; `toMail` memuat tautan absolut ke url.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="RegistrationNotificationTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — mengikuti pola `ScheduleNotification` (konstruktor privat + pabrik statis, `title()/body()/url()` dari properti, format tanggal `Carbon::parse($date)->locale('id')->translatedFormat('l, j F Y')`, jam `08.00–12.00`). `toStudent` memakai `Notification::send` atau `$user->notify`.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tambah notifikasi pendaftaran sesi untuk siswa`

---

### Task 3: Keputusan verifikasi — tiket, batas perbaikan, dan notifikasi

**Files:**
- Modify: `app/Domains/Registrations/Services/VerificationService.php`, `app/Domains/Registrations/Services/RegistrationService.php`, `app/Domains/Registrations/Http/Controllers/VerificationController.php`
- Test: `tests/Feature/Registrations/VerificationServiceTest.php`, `VerificationHttpTest.php`, `SessionRegistrationServiceTest.php`

**Interfaces:**
- Consumes (Task 1–2): `StatusTransitioner::move(..., ?User)`, `Schedule::fixDeadlineFrom`, `RegistrationNotification::{approved,needsFix,rejected}`, `NotificationService::toStudent`.
- Produces: `VerificationService::decide(RegistrationStudent $entry, 'approve'|'fix'|'reject' $decision, ?string $note, User $actor): void` — perilaku baru: (a) sesi yang sudah lewat (`Schedule::isPast()`) ditolak dengan `AccessRuleException("Sesi sudah lewat; pendaftaran ini akan kedaluwarsa.")` untuk ketiga keputusan; (b) `approve` memeriksa ulang kelayakan pada tanggal sesi (`AccessRuleException` berisi alasan pertama) lalu menerbitkan `ticket_code` baru (`Str::random(32)` lewat satu metode privat) dan mengosongkan `fix_deadline_at`; (c) `fix` hanya bila `! $schedule->isOpenForMarking()` (sesi paling cepat besok), selain itu `AccessRuleException("Permintaan perbaikan hanya dapat dibuat sampai sehari sebelum sesi; pada hari sesi pilih Setujui atau Tolak.")`, dan mengisi `fix_deadline_at = $schedule->fixDeadlineFrom(now())`, `fix_reminded_at = null`; (d) setelah transaksi, kirim `toStudent` (approved / needsFix / rejected).
- Produces: `VerificationService::approveMany` menerbitkan tiket per pendaftaran dan mengirim satu notifikasi `approved` per siswa setelah transaksi.
- Produces: `RegistrationService::resubmit` — menolak dengan `AccessRuleException("Batas waktu perbaikan sudah lewat.")` bila `fix_deadline_at` ada dan sudah lewat; saat sukses mengosongkan `fix_deadline_at` dan `fix_reminded_at`.
- Produces: payload `Verifikasi/Show` — `entry.can_request_fix: bool` (= sesi belum hari-H), `entry.fix_deadline: ?string` (ISO) bila ada, dan `entry.fix_deadline_preview: ?string` (ISO, `Schedule::fixDeadlineFrom(now())`, hanya bila `can_request_fix`).

- [ ] **Step 1: Tulis tes yang gagal**
  - Setujui: status Terdaftar, `ticket_code` terisi 32 karakter dan unik antar pendaftaran, `Notification::assertSentTo` akun siswa (`approved`, url memuat id pendaftaran), admin/operator/siswa lain tidak menerima; setujui siswa yang dokumennya sudah dihapus ditolak (kelayakan) tanpa mengubah status; `approveMany` — semua mendapat tiket berbeda, satu notifikasi per siswa, gagal-semua tetap tanpa notifikasi dan tanpa tiket.
  - Minta perbaikan: pada sesi H+3 → `fix_deadline_at` ≈ `now + 48 jam`; pada sesi besok → 23.59.59 hari ini; pada sesi hari-H → ditolak dan status tidak berubah; notifikasi `needsFix` memuat catatan dan batas; HTTP: `entry.can_request_fix` false pada hari-H, true sebelumnya; `fix_deadline` tampil untuk Perlu perbaikan; `fix_deadline_preview` terisi hanya bila perbaikan boleh diminta dan sama dengan tenggat yang benar-benar dipakai.
  - Tolak: notifikasi `rejected`; kursi lepas (sudah ada).
  - Sesi lewat: ketiga keputusan ditolak (status tetap), pesan "Sesi sudah lewat…".
  - `resubmit`: ditolak setelah `fix_deadline_at` (pakai `Carbon::setTestNow`), diterima sebelum batas dan mengosongkan `fix_deadline_at`/`fix_reminded_at`; siswa tanpa akun tidak membuat galat saat keputusan.
  - Tiket tidak bocor: antrean `/verifikasi` dan detail keputusan tidak memuat `ticket_code`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="VerificationServiceTest|VerificationHttpTest|SessionRegistrationServiceTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `VerificationService` menerima `NotificationService` dan `EligibilityService` (sudah ada); notifikasi dikirim setelah `DB::transaction` selesai. Kata keputusan → pabrik notifikasi dipetakan di satu tempat. `VerificationController::show` menambah dua kunci payload.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: terbitkan tiket saat disetujui, batasi permintaan perbaikan, dan beri tahu siswa`

---

### Task 4: Pembatalan oleh admin, perubahan/pembatalan sesi, dan ganti sekolah

**Files:**
- Create: `app/Domains/Schedules/Http/Requests/CancelEntryRequest.php`
- Modify: `app/Domains/Registrations/Services/RegistrationService.php`, `app/Domains/Schedules/Services/ScheduleService.php`, `app/Domains/Students/Services/StudentService.php`, `app/Domains/Schedules/Http/Controllers/ScheduleController.php`, `routes/schedules.php`, `lang/id/activity.php`
- Test: `tests/Feature/Registrations/SessionRegistrationServiceTest.php`, `tests/Feature/Schedules/ScheduleServiceTest.php`, `ScheduleCorrectionsHttpTest.php`, `tests/Feature/Students/StudentCorrectionTest.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`

**Interfaces:**
- Consumes (Task 1–2): `StatusTransitioner`, `RegistrationNotification::{cancelledByStaff,sessionChanged,sessionCancelled}`, `NotificationService::toStudent`.
- Produces: `RegistrationService::cancelByStaff(RegistrationStudent $entry, string $note, User $actor): void` — hanya status Menunggu verifikasi / Perlu perbaikan / Terdaftar (selain itu `AccessRuleException`), hanya bila `$entry->schedule->allowsStudentChanges()` (tanggal sesi di masa depan; pesan "Pada hari sesi, gunakan penandaan manual."), memakai `StatusTransitioner` dengan catatan, log `pendaftaran.dibatalkan` (properti `oleh_staf = true`), lalu notifikasi `cancelledByStaff`.
- Produces: `RegistrationService::releaseSeats(Student $student, string $note, User $actor): int` — membatalkan semua pendaftaran pra-kehadiran siswa itu (tiga status di atas), mengembalikan jumlahnya; melempar `AccessRuleException("Sekolah tidak dapat diganti: siswa sudah direkam.")` bila ada pendaftaran Sudah rekaman. Dipanggil dari `StudentService::correct` **di dalam transaksi yang sama** dengan perubahan sekolah (gagal sebagian = tidak ada yang berubah); notifikasi `cancelledByStaff` per pendaftaran dibatalkan setelah transaksi.
- Produces: rute `POST /jadwal/{schedule}/peserta/{entry}/batalkan` (`jadwal.peserta.batalkan`, `can:verifikasi.putuskan`, `throttle:30,1`, `scopeBindings`); `CancelEntryRequest` — `note` wajib 5–500 karakter.
- Produces: `ScheduleService::update` — setelah perubahan nyata pada `start_time`/`end_time`/`location`, kirim `sessionChanged` ke siswa dengan pendaftaran Menunggu verifikasi / Perlu perbaikan / Terdaftar di sesi itu (setelah transaksi); `ScheduleService::cancel` mengirim `sessionCancelled` ke siswa yang pendaftarannya dibatalkan; `ScheduleService::update` menolak sesi yang sudah lewat (`isPast()`) dengan `AccessRuleException("Sesi yang sudah lewat tidak dapat diubah.")`; `ScheduleService::delete` memeriksa entri di bawah `lockForUpdate` pada baris sesi.
- Produces: payload `Jadwal/Show` — tiap peserta memuat `can_cancel: bool` (status tiga di atas, sesi belum hari-H, sesi tidak dibatalkan) agar frontend tidak menghitung sendiri.

- [ ] **Step 1: Tulis tes yang gagal**
  - `cancelByStaff`: tiga status berhasil → Dibatalkan, catatan tersimpan, kursi lepas, log, notifikasi hanya ke siswa itu; status lain, hari-H, dan sesi dibatalkan ditolak (status tetap); HTTP: catatan wajib/≥5, 403 tanpa `verifikasi.putuskan`, 404 untuk entri sesi lain (scope bindings) dan sekolah lain, operator terikat sekolah dengan izin itu tetap hanya melihat sekolahnya.
  - Sesi: ubah jam/lokasi → notifikasi `sessionChanged` hanya ke pendaftar relevan (bukan Ditolak/Dibatalkan/Sudah rekaman, bukan sekolah lain), perubahan kuota saja **tidak** memicu notifikasi; ubah sesi lewat ditolak; batalkan sesi → `sessionCancelled` ke tiap siswa yang dibatalkan; hapus sesi membaca entri di bawah kunci (pola tes `for update` di `ScheduleServiceTest`).
  - Ganti sekolah (`StudentCorrectionTest`, menggantikan tes "tidak dapat diganti bila masih punya pendaftaran aktif" 7a): pendaftaran pra-kehadiran menjadi Dibatalkan, `school_id` siswa dan akun berpindah, notifikasi `cancelledByStaff` terkirim; Sudah rekaman → ditolak dan **tidak ada** yang berubah (sekolah, status, notifikasi); kegagalan di tengah (mis. entri kedua menolak lewat tiruan) membatalkan seluruh perubahan.
  - `RoutePermissionMatrixTest`: baris `POST /jadwal/{schedule}/peserta/{slot}/batalkan` → `verifikasi.putuskan`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="SessionRegistrationServiceTest|ScheduleServiceTest|ScheduleCorrectionsHttpTest|StudentCorrectionTest|RoutePermissionMatrixTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `StudentService` mendapat `RegistrationService` lewat konstruktor; notifikasi dikumpulkan di dalam transaksi dan dikirim sesudahnya. Kunci urutan: sesi sebelum siswa (konsisten dengan `register`). Kunci bahasa log `pendaftaran.dibatalkan` sudah ada.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2 ditambah `php artisan test` penuh di akhir task (hari-H dan minor 7b menyentuh banyak tes); Expected: PASS.
- [ ] **Step 5: Commit** — `feat: pembatalan pendaftaran oleh admin, pemberitahuan perubahan sesi, dan pembatalan kursi saat sekolah diganti`

---

### Task 5: Perintah terjadwal — kedaluwarsa dan pengingat

**Files:**
- Create: `app/Console/Commands/ExpireRegistrations.php`, `app/Console/Commands/SendRegistrationReminders.php`
- Modify: `routes/console.php`, `docs/operasional-queue.md`
- Test: `tests/Feature/Console/ExpireRegistrationsTest.php`, `tests/Feature/Console/SendRegistrationRemindersTest.php`

**Interfaces:**
- Consumes (Task 1–2): `StatusTransitioner::move(..., null)`, `RegistrationNotification::{seatReleased,fixReminder,sessionReminder}`, `NotificationService::toStudent`.
- Produces: `php artisan sijebol:kedaluwarsakan` — (a) Perlu perbaikan dengan `fix_deadline_at < now()` → Kedaluwarsa, catatan "Batas waktu perbaikan terlewati."; (b) Menunggu verifikasi / Perlu perbaikan pada sesi dengan `date < today()` (Asia/Jakarta) → Kedaluwarsa, catatan "Sesi berakhir sebelum pendaftaran diputuskan."; aktor sistem (null); setelah tiap perubahan kirim `seatReleased`; mencetak jumlah; aman dijalankan berulang (entri yang sudah Kedaluwarsa tidak disentuh lagi); bekerja tanpa pengguna login (tanpa scope global).
- Produces: `php artisan sijebol:kirim-pengingat` — (a) Perlu perbaikan dengan `fix_deadline_at` dalam (now, now + 24 jam] dan `fix_reminded_at` null → `fixReminder`, isi `fix_reminded_at`; (b) Terdaftar pada sesi `date = besok`, `session_reminded_at` null, jam sekarang ≥ `config('sijebol.reminder_hour')` → `sessionReminder`, isi `session_reminded_at`; sesi dibatalkan dilewati; idempoten.
- Produces: jadwal di `routes/console.php`: kedua perintah `->hourly()->withoutOverlapping()`.

- [ ] **Step 1: Tulis tes yang gagal** (`Carbon::setTestNow`, `Notification::fake`, `Artisan::call`)
  - Kedaluwarsa: Perlu perbaikan lewat batas → Kedaluwarsa, riwayat `by_user_id` null, kursi lepas (`seatsTaken` turun), satu notifikasi `seatReleased` ke siswa itu; sebelum batas tidak disentuh; Menunggu verifikasi pada sesi kemarin → Kedaluwarsa, pada sesi hari ini/besok tidak; status lain (Terdaftar, Ditolak, Sudah rekaman) tidak disentuh; menjalankan dua kali → tepat satu perubahan dan satu notifikasi; siswa tanpa akun tidak membuat galat; siswa yang kedaluwarsa dapat mendaftar lagi.
  - Pengingat: perbaikan — batas 20 jam lagi → terkirim sekali dan `fix_reminded_at` terisi, batas 30 jam lagi → belum, sudah diingatkan → tidak lagi, sudah lewat batas → tidak; sesi — besok & pukul 07.59 → belum, 08.00 → terkirim sekali, lusa → belum, sesi dibatalkan → tidak, status selain Terdaftar → tidak; dua kali jalan → satu notifikasi.
  - Jadwal: `app(Illuminate\Console\Scheduling\Schedule::class)->events()` memuat kedua perintah dengan ekspresi cron tiap jam.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="ExpireRegistrationsTest|SendRegistrationRemindersTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — kueri memakai `RegistrationStudent::withoutGlobalScopes()` dengan `with(['student.user','schedule'])`, diproses per entri (`chunkById`) agar satu kegagalan tidak membatalkan yang lain; tiap perubahan dalam transaksi sendiri, notifikasi setelahnya. `docs/operasional-queue.md`: tambah bagian "Perintah terjadwal" yang menjelaskan kedua perintah, bahwa cron `schedule:run` setiap menit wajib hidup, dan cara menjalankan manual untuk uji.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: kedaluwarsakan pendaftaran dan kirim pengingat lewat scheduler`

---

### Task 6: Tiket (backend) dan payload halaman Sesi

**Files:**
- Modify: `app/Domains/Registrations/Http/Controllers/SelfRegistrationController.php`, `routes/registrations.php`
- Test: `tests/Feature/Registrations/SessionRegistrationHttpTest.php` (tambah), `tests/Feature/Access/RoutePermissionMatrixTest.php`

**Interfaces:**
- Consumes (Task 1, 3): `ticket_code`, `fix_deadline_at`.
- Produces: rute `GET /saya/tiket/{entry}` (`saya.tiket`, `can:pendaftaran.lihat`) → Inertia `Saya/Tiket` dengan props `ticket: {entry_id: int, status: 'terdaftar'|'sudah_rekaman', code: string|null, student_name: string, school: string, schedule: {id, date, start_time, end_time, location}}`; `code` hanya terisi bila status Terdaftar. Akun non-siswa 404; pendaftaran siswa lain 404 (scope pemilik); status selain Terdaftar/Sudah rekaman → 404.
- Produces: payload `Saya/Sesi` — `entry` mendapat `fix_deadline: ?string` (ISO); `can_resubmit` kini juga mensyaratkan `fix_deadline_at` null atau belum lewat.

- [ ] **Step 1: Tulis tes yang gagal**
  - Tiket Terdaftar: props sesuai, `code` 32 karakter sama dengan kolom; Sudah rekaman: `code` null; Menunggu verifikasi / Perlu perbaikan / Ditolak / Dibatalkan / Kedaluwarsa → 404; siswa B membuka tiket siswa A (satu sekolah) → 404; admin → 404; tanpa login → login; kode tidak muncul di `/saya/sesi`, `/saya`, atau `/verifikasi`.
  - Tiket tetap memakai kode yang sama setelah penandaan Sudah rekaman dibatalkan (kembali Terdaftar).
  - `entry.fix_deadline` terisi untuk Perlu perbaikan; `can_resubmit` false setelah batas (`Carbon::setTestNow`).
  - Matriks rute: baris `GET /saya/tiket/{entry}` → `pendaftaran.lihat`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="SessionRegistrationHttpTest|RoutePermissionMatrixTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `SelfRegistrationController::ticket(Request, RegistrationStudent $entry)` memakai `abort_unless(isStudent, 404)` seperti aksi lain; `abort_unless(in_array(status, [Terdaftar, SudahRekaman]), 404)`; nama siswa dari `$entry->student->name`, sekolah dari `$entry->schedule->school->name`.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: halaman tiket siswa dan tenggat perbaikan pada halaman sesi`

---

### Task 7: Frontend — tiket QR, tenggat, dan pembatalan oleh admin

**Files:**
- Create: `resources/js/Pages/Saya/Tiket.vue`, `resources/js/Pages/Saya/Tiket.test.ts`
- Modify: `package.json` (+ lockfile; dependensi `qrcode` dan `@types/qrcode`), `resources/js/Pages/Saya/Sesi.vue`, `resources/js/Pages/Saya/Sesi.test.ts`, `resources/js/Pages/Verifikasi/Show.vue`, `resources/js/Pages/Verifikasi/Verifikasi.test.ts`, `resources/js/Pages/Jadwal/Show.vue`, `resources/js/Pages/Jadwal/Jadwal.test.ts`, `resources/js/types/registration.ts`, `resources/js/types/schedule.ts`, `resources/js/lib/date.ts` (+ test, bila perlu `formatDateTime` sudah ada → pakai ulang)

**Interfaces:**
- Consumes (Task 3, 4, 6): payload `Saya/Tiket`, `entry.fix_deadline`/`can_resubmit` pada `Saya/Sesi`, `entry.can_request_fix`/`fix_deadline` pada `Verifikasi/Show`, `participants[].can_cancel` pada `Jadwal/Show`, rute `saya.tiket`, `jadwal.peserta.batalkan`.
- Produces: `Saya/Tiket.vue` — tampilan: judul "Tiket rekaman", QR (dari `code`, dibuat di klien dengan `QRCode.toDataURL`, `alt` bermakna: "Kode QR tiket rekaman {nama}"), nama siswa, sekolah, hari-tanggal, jam, lokasi, instruksi "Tunjukkan QR ini kepada petugas saat sesi", tombol **Simpan sebagai gambar** yang menyimpan **kartu tiket** PNG (QR + nama siswa + hari-tanggal, jam, lokasi, dibuat lewat `canvas`; nama berkas `tiket-rekaman-{tanggal}.png`) agar tetap berguna dari galeri tanpa sinyal, dan **Kembali ke sesi saya**; kontainer QR **selalu putih** dengan modul gelap di tema apa pun (jangan memakai token tema), lebar `min(80vw, 320px)`, dan petunjuk "Naikkan kecerahan layar saat dipindai."; status Sudah rekaman: tanpa QR, teks "Perekaman Anda sudah selesai." Tidak ada janji yang tidak ada (belum ada label KTP-el).

- [ ] **Step 1: Tulis tes Vitest yang gagal**
  - `Tiket.test.ts` (modul `qrcode` ditiru, `toDataURL` mengembalikan `data:image/png;base64,AAA`): menampilkan QR `img` dengan `alt` bermakna, detail sesi, instruksi kecerahan, kontainer QR berlatar putih (kelas tetap, bukan token tema), tombol simpan yang memanggil canvas (`getContext` ditiru; assert teks nama dan sesi digambar) lalu memicu unduhan dengan nama berkas benar; QR dibuat dari `code` (assert argumen); status Sudah rekaman tidak merender `img` QR; gagal membuat QR → pesan "QR gagal dibuat. Muat ulang halaman." (bukan halaman kosong); kode tidak dirender sebagai teks.
  - `Sesi.test.ts`: status Terdaftar atau Sudah rekaman → tombol/tautan **Lihat tiket** ke `saya.tiket`; Perlu perbaikan → teks "Batas perbaikan: {tanggal · jam}" dari `fix_deadline` disertai sisa waktu relatif (`relativeTime`); setelah batas (`can_resubmit` false karena tenggat) tampil teks "Batas perbaikan sudah lewat; kursi akan dilepas otomatis." (bukan sekadar tombol yang hilang); `NEXT` perlu perbaikan menyebut batas; spinner tombol Daftar tampil selama proses untuk sesi yang sedang dikirim (mengganti kondisi `picked?.id` yang mati — simpan id yang dikirim di ref terpisah); `can_resubmit` false → tombol Ajukan ulang hilang.
  - `Verifikasi.test.ts`: opsi **Minta perbaikan** nonaktif dengan keterangan tertulis "Hanya sampai sehari sebelum sesi" bila `can_request_fix` false, memilih Setujui/Tolak tetap bisa; memilih Minta perbaikan menampilkan hint batas dengan tenggat sebenarnya dari `fix_deadline_preview` ("Siswa diberi batas sampai {tanggal · jam}").
  - `Jadwal.test.ts`: kolom aksi peserta menampilkan **Batalkan pendaftaran** di dalam menu aksi baris (`RowActions`, label menyebut nama siswa) hanya untuk `can_cancel` dan pemegang `verifikasi.putuskan` — bukan tombol terbuka di tiap baris; dialog menyebut nama siswa dan kursi dilepas, mewajibkan catatan ≥ 5 karakter, lalu `router.post(route('jadwal.peserta.batalkan', [sesi, entri]), {note}, {preserveScroll: true})`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `npx vitest run resources/js/Pages/Saya resources/js/Pages/Verifikasi resources/js/Pages/Jadwal`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `npm install qrcode` dan `npm install -D @types/qrcode` (versi stabil terbaru di npm saat eksekusi; catat versi di ledger). Ikuti pola kartu dan komponen yang ada (`PageHeader`, `SectionCard`, `InlineAlert`, `ConfirmDialog`, `Button`). QR dibuat di `onMounted`/`watch(code)`, ukuran ≥ 280 px dengan margin tenang 2 modul, kontras tinggi; area sentuh ≥ 40 px; fokus pindah ke judul tiket saat halaman dibuka. Halaman Sesi: tautan tiket tampil menonjol untuk Terdaftar (tombol utama). Jangan memakai kata "tiket" pada teks status selain Terdaftar/Sudah rekaman.
- [ ] **Step 4: Jalankan, pastikan lulus** — `npx vitest run`, `npx vue-tsc --noEmit`, `npx eslint` pada berkas yang disentuh, `npm run build`; Expected: hijau (jangan `prettier --write` pada seluruh folder).
- [ ] **Step 5: Commit** — `feat: halaman tiket QR, tenggat perbaikan, dan pembatalan pendaftaran oleh admin di antarmuka`

---

### Task 8: Dokumentasi dan verifikasi akhir fase

**Files:**
- Modify: `docs/deployment-guide.md` (bagian 11), `docs/operasional-queue.md` (bila belum), `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`, memori proyek `alur-mandiri-siswa.md`

- [ ] **Step 1: Dokumentasi** — `deployment-guide.md` bagian 11 "Catatan Fase 7c": urutan `php artisan migrate --force` (migrasi 7c aman pada data yang ada: hanya menambah kolom dan melonggarkan `by_user_id`), wajib `schedule:run` via cron tiap menit dan worker antrean hidup (email siswa lewat antrean), daftar perintah terjadwal dan konfigurasi `fix_window_hours`/`reminder_hour`, serta bahwa SMTP produksi harus siap sebelum rilis (gerbang rilis 7b terbuka bila 7c lulus UAT). Panduan UI: tambah halaman tiket (§5.21 lanjutan), keterangan tenggat dan aturan "Minta perbaikan", tombol Batalkan pendaftaran di §5.26.
- [ ] **Step 2: Verifikasi penuh** — urutan: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `php artisan test` (latar belakang, satu proses), `npx vue-tsc --noEmit`, `npx vitest run` (tanpa menjalankan Pest bersamaan), `npm run build`; Expected: semua hijau. `grep -rn "ticket_code" app resources/js` hanya muncul di model/service/controller tiket, bukan di payload daftar.
- [ ] **Step 3: Penelusuran manual (daftar untuk user, belum dijalankan Claude)** — di DB dev setelah `php artisan migrate` + `php artisan queue:work` + `MAIL_MAILER=log`: (1) siswa daftar sesi besok → admin Minta perbaikan (cek email di log, batas = akhir hari ini) → `php artisan sijebol:kirim-pengingat` → ubah jam server/`fix_deadline_at` ke masa lalu lalu `php artisan sijebol:kedaluwarsakan` → kursi lepas dan siswa dapat daftar lagi; (2) sesi H+5: Setujui → email + lonceng + tiket tampil dan QR terbaca pemindai QR HP (isi = kode 32 karakter); (3) admin ubah jam sesi → siswa terdaftar diberi tahu; admin batalkan satu pendaftaran lalu batalkan sesi; (4) admin ganti sekolah siswa terdaftar → kursi lama dilepas.
- [ ] **Step 4: Memori** — perbarui `alur-mandiri-siswa.md`: status 7c, keputusan di atas, sisa untuk 7d.
- [ ] **Step 5: Commit** — `docs: perbarui panduan deploy, operasional, dan panduan UI untuk fase 7c`
