# Fase 7d — Scan QR, Kehadiran Otomatis, Label KTP-el Langsung, Beranda, dan Laporan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin kecamatan mencatat kehadiran dengan memindai QR tiket di lapangan, yang tidak terpindai otomatis menjadi Tidak hadir, petugas melihat penanda KTP-el langsung, dan beranda serta laporan menampilkan kuota, kehadiran per sesi, dan per sekolah — sehingga alur mandiri siswa (spec alur mandiri) lengkap dari tautan sekolah sampai kehadiran.

**Architecture:** Satu service `ScanService` (di bawah kunci baris, memakai `StatusTransitioner`) dan satu endpoint JSON; halaman kamera Vue memakai pustaka `qr-scanner`. Kolom baru `registration_students.scanned_at`. Perintah terjadwal `sijebol:tandai-tidak-hadir` meniru pola `ExpireRegistrations` (per entri, kunci baris, cek ulang). Label KTP-el langsung dihitung di `EligibilityService` (tidak disimpan). Beranda dan laporan memakai ulang `DashboardService`, `ReportService`, dan tabel yang ada.

**Tech Stack:** Laravel 12 + Inertia + Vue 3 + TypeScript, Pest (butuh MySQL `sijebol_test`, jangan dua proses sekaligus), Vitest, Larastan (`--memory-limit=1G`), Pint, paket npm `qr-scanner@1.4.2` (baru).

**Spec:** [`docs/superpowers/specs/2026-10-19-alur-mandiri-siswa-design.md`](../specs/2026-10-19-alur-mandiri-siswa-design.md) (bagian 3, 4.6, 4.8, 6, 7, 8, 9). Panduan UI: [`2026-10-07-sijebol-ui-guide.md`](../specs/2026-10-07-sijebol-ui-guide.md). Fase sebelumnya: [`2026-10-19-fase-7c-verifikasi-lanjutan-tiket-dan-notifikasi.md`](2026-10-19-fase-7c-verifikasi-lanjutan-tiket-dan-notifikasi.md) (selesai dan terverifikasi otomatis, belum di-commit saat rencana ini ditulis).

**Cakupan fase ini (spec §10, tahap 4):** halaman dan endpoint scan QR, `scanned_at`, penolakan scan dengan pesan jelas, ceklis manual tetap sebagai cadangan, Tidak hadir otomatis setelah sesi, label KTP-el langsung, beranda (kuota, kehadiran, menunggu verifikasi, perbaikan metrik), dan laporan kehadiran per sesi.

**Di luar fase ini:** WhatsApp, pemindaian offline, notifikasi ke siswa untuk Tidak hadir, notifikasi per scan ke operator sekolah, riwayat scan sebagai halaman tersendiri, evaluasi/pelacakan penerbitan KTP-el. Sisa minor 7b/7c yang ditunda tidak disentuh kecuali berkas yang sama diubah.

## Mode eksekusi

Ditanyakan ke user setelah rencana disetujui (7a–7c: "Saya kerjakan semua").

## Penyimpangan dan keputusan perancang (perlu persetujuan user)

1. **Izin baru `rekaman.pindai`** (spec §3 meminta izin memindai tiket), terpisah dari `rekaman.tandai`: petugas lapangan dapat diberi hak memindai tanpa hak menandai/membatalkan penandaan. Diberikan ke super-admin dan admin-kecamatan (seeder memberi semua izin); tidak ke operator sekolah maupun siswa. Seeder `AccessSeeder` harus dijalankan ulang di basis data dev/produksi.
2. **Pustaka `qr-scanner@1.4.2`** untuk membaca QR dari kamera: `BarcodeDetector` bawaan peramban tidak ada di Safari/Firefox, dan menulis pembaca QR sendiri jauh lebih besar. Pustaka memakai `BarcodeDetector` bila ada.
3. **Endpoint scan berupa JSON (`fetch`), bukan Inertia**, agar halaman kamera tidak dimuat ulang tiap pindaian. Satu helper `postJson` kecil (token XSRF dari cookie); belum ada helper HTTP lain di proyek (axios tidak terpasang).
4. **Jendela scan** = tanggal sesi saja, dari `jam mulai − 60 menit` sampai `jam selesai + 60 menit` (`config('sijebol.scan_tolerance_minutes')` = 60, zona Jakarta). Angka 60 adalah tebakan; satu tempat konfigurasi.
5. **Hanya `scanned_at`** (tanpa `scanned_by`): pelaku sudah ada di riwayat status. Penandaan manual tidak mengisi `scanned_at`; membatalkan penandaan (`RecordingService::undo`) mengosongkannya agar tiket dapat dipindai lagi.
6. **Scan tidak mengirim notifikasi** ke operator sekolah (penandaan manual per batch tetap mengirim ringkasan seperti sekarang) agar tidak membanjiri (spec §2 anti-tujuan). Setiap scan sukses dan setiap scan ditolak untuk tiket yang dikenal tercatat di audit log (`rekaman.dipindai`, `rekaman.pindai-ditolak`); kode yang tidak dikenal tidak disimpan.
7. **Tidak hadir otomatis berjalan sejak hari setelah sesi** (`tanggal sesi < hari ini`), bukan begitu jam selesai lewat: seluruh hari-H tetap tersedia untuk ceklis manual cadangan. Spec §4.6 hanya berkata "setelah sesi berakhir". Dampak bila salah: siswa yang tidak hadir baru dapat mendaftar sesi lain keesokan harinya. Tanpa notifikasi.
8. **Label KTP-el langsung** (spec §4.8): tanggal acuan > tanggal lahir + 17 tahun, dengan `addYearsNoOverflow` (lahir 29 Februari → ulang tahun ke-17 dihitung 28 Februari pada tahun biasa). Tanggal acuan = tanggal sesi pendaftaran aktif siswa, bila tidak ada = hari ini. Tampil di data siswa (daftar + detail), antrean verifikasi (daftar + detail), hasil pindai, dan tiket; tidak di halaman data milik siswa sendiri.
9. **Beranda:** tabel jadwal terdekat mengabaikan sesi yang dibatalkan (sekarang tampil sebagai "Dijadwalkan"), menampilkan kuota terisi/kuota, jumlah menunggu verifikasi, dan jumlah sudah rekaman per sesi, serta tautan Pindai pada sesi hari ini. Tren bulanan "Sudah Rekam" memakai bulan tanggal sesi (bukan `updated_at` yang bergeser tiap pembaruan). Aksi cepat "Input Hasil Perekaman" (memeriksa izin `rekaman.tambah` yang tidak ada) diganti "Pindai Tiket". Kartu dan KPI yang sudah dipetakan ke status baru dibiarkan.
10. **Laporan kehadiran per sesi** ditambahkan di halaman Laporan (tabel kedua + ekspor Excel kedua), difilter menurut **tanggal sesi** (tabel rekap per sekolah yang ada tetap menurut tanggal pendaftaran; keterangan pada tiap tabel menjelaskannya). Kehadiran = (Sudah rekaman + Belum rekaman) ÷ (Sudah rekaman + Belum rekaman + Tidak hadir); Belum rekaman berarti siswa hadir tetapi belum sempat direkam. Sesi dibatalkan tidak dimuat.

## Global Constraints

- Bahasa UI, pesan, dan galat: Bahasa Indonesia baku. Pesan penolakan scan memakai kalimat jelas yang bisa dibaca petugas di lapangan; hasil pindai hanya menampilkan nama dan NISN (tanpa NIK, tanpa kode tiket).
- Hak akses lewat enum `Permission` + cerminan `resources/js/lib/permissions.ts` + UI RBAC; tidak ada `if` peran di kode. Rute scan 404/403 mengikuti pola rute `jadwal.*`.
- Semua perubahan status lewat `StatusTransitioner` dengan riwayat (aksi terjadwal `$actor = null` = "Sistem"); aksi tulis baru memakai `throttle`; perubahan tercatat lewat `activity()`.
- Kamera hanya bekerja di HTTPS (atau `localhost`); halaman pindai wajib menjelaskan hal ini saat kamera tidak tersedia dan menyediakan jalur cadangan (penandaan manual).
- Zona waktu `Asia/Jakarta` (`Schedule::today()`); batas jendela scan dihitung di satu tempat (`Schedule`).
- Tidak ada `Co-Authored-By` atau atribusi AI pada commit message (aturan CLAUDE.md mengalahkan atribusi bawaan); commit hanya setelah user memintanya.
- Verifikasi akhir fase: `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vue-tsc --noEmit`, `npx vitest run` (bila time-out di mesin berbeban, ulangi dengan `--testTimeout=30000`), `npx eslint` pada berkas yang disentuh, `npm run build`.

## Review Focus

1. Scan ganda dan balapan: dua permintaan dengan kode yang sama hanya menghasilkan satu perubahan status dan satu baris riwayat; permintaan kedua dijawab "sudah dipindai pukul HH.MM". (Task 3)
2. Batas jendela dan jenis penolakan: tepat di batas diterima, semenit di luar ditolak; kode tak dikenal, tiket sesi lain, sesi dibatalkan, status selain Terdaftar, dan sudah ditandai manual masing-masing punya pesan sendiri dan tidak mengubah apa pun; kode tak dikenal tidak membocorkan apa pun. (Task 3)
3. Penandaan dari scan lalu dibatalkan: tiket dapat dipindai lagi dan `scanned_at` kosong; kode tiket tidak muncul di respons scan, daftar peserta, atau halaman staf mana pun. (Task 3)
4. Tidak hadir otomatis: hanya Terdaftar pada sesi yang sudah lewat; sesi hari ini, Sudah rekaman, dan status lain tidak disentuh; scan yang masuk di antara pemilihan dan pemrosesan perintah tidak ditimpa. (Task 5)
5. Label KTP-el: sehari sebelum, tepat pada, dan sehari sesudah ulang tahun ke-17; tanggal acuan = tanggal sesi vs hari ini; lahir 29 Februari; siswa 16 tahun tanpa label. (Task 2)
6. Kamera tidak tersedia/ditolak/tidak aman dan kamera berpindah antar halaman: pesan jelas, tautan cadangan manual, kamera dimatikan saat halaman ditinggalkan. (Task 4)

---

### Task 1: Skema scan, konfigurasi, jendela scan, dan izin

**Files:**
- Create: `database/migrations/2026_10_22_100001_add_scanned_at_to_registration_students_table.php`
- Modify: `config/sijebol.php`, `app/Domains/Registrations/Models/RegistrationStudent.php`, `app/Domains/Schedules/Models/Schedule.php`, `app/Domains/Access/Enums/Permission.php`, `resources/js/lib/permissions.ts`
- Test: `tests/Feature/Registrations/TicketSchemaTest.php`, `tests/Feature/Schedules/ScheduleModelTest.php`, tes izin yang mendaftar semua izin (cari dengan `grep -rn "Permission::values\|rekaman.tandai" tests`)

**Interfaces:**
- Produces: kolom `registration_students.scanned_at` (timestamp nullable); `RegistrationStudent::$scanned_at` (`Carbon|null`, fillable, cast datetime).
- Produces: `config('sijebol.scan_tolerance_minutes')` = `60`.
- Produces: `Schedule::scanWindow(): array{0: Carbon, 1: Carbon}` = `[jam mulai − toleransi, jam selesai + toleransi]` pada tanggal sesi, zona Jakarta.
- Produces: `Permission::RekamanPindai = 'rekaman.pindai'`, `PERMISSIONS.RekamanPindai = 'rekaman.pindai'`.

- [ ] **Step 1: Tulis tes yang gagal**
  - `TicketSchemaTest`: kolom `scanned_at` ada, bernilai null secara bawaan, dan tersimpan sebagai `Carbon`.
  - `ScheduleModelTest`: sesi 08.00–12.00 pada 2026-10-20 → `scanWindow()` = `[2026-10-20 07:00:00, 2026-10-20 13:00:00]` Asia/Jakarta; toleransi mengikuti konfigurasi (`config(['sijebol.scan_tolerance_minutes' => 15])` → 07.45–12.15).
  - Tes izin: `rekaman.pindai` ada di `Permission::values()`, dimiliki `admin-kecamatan` dan `super-admin` setelah `AccessSeeder`, tidak dimiliki `operator-sekolah` maupun `siswa`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="TicketSchemaTest|ScheduleModelTest"` ditambah berkas tes izin; Expected: FAIL.
- [ ] **Step 3: Implementasi** — migrasi `up()`/`down()` mengikuti gaya migrasi 7c; konfigurasi dengan komentar satu baris; `scanWindow()` memakai `Carbon::parse("{$date} {$time}", 'Asia/Jakarta')`; izin ditambahkan di enum dan cerminan TypeScript (cek `resources/js/lib/permissionMatrix.test.ts` dan tes lain yang menghitung izin).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2 + `npx vitest run resources/js/lib`; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tambah kolom scanned_at, jendela scan, dan izin rekaman.pindai`

---

### Task 2: Label KTP-el langsung

**Files:**
- Create: `resources/js/Components/shared/KtpLangsungBadge.vue`, `resources/js/Components/shared/KtpLangsungBadge.test.ts`
- Modify: `app/Domains/Students/Services/EligibilityService.php`, `app/Domains/Students/Repositories/EloquentStudentRepository.php`, `app/Domains/Students/Http/Controllers/StudentController.php`, `app/Domains/Registrations/Http/Controllers/VerificationController.php`, `app/Domains/Registrations/Http/Controllers/SelfRegistrationController.php` (payload tiket), `resources/js/Pages/Siswa/Index.vue`, `Siswa/Show.vue`, `Verifikasi/Index.vue`, `Verifikasi/Show.vue`, `Saya/Tiket.vue`, `resources/js/types/student.ts`, `types/registration.ts`
- Test: `tests/Feature/Students/EligibilityServiceTest.php`, `StudentHttpTest.php`, `tests/Feature/Registrations/VerificationHttpTest.php`, `TicketHttpTest.php`, `tests/Feature/Performance/ListQueryCountTest.php`, tes Vitest halaman terkait

**Interfaces:**
- Produces: `EligibilityService::directKtp(Student $student, ?CarbonInterface $on = null): bool` — `true` bila tanggal acuan (zona Jakarta, awal hari) **lebih besar** dari `birth_date + 17 tahun` (`addYearsNoOverflow`).
- Produces: `EligibilityService::activeSessionDate(Student $student): ?Carbon` — tanggal sesi pendaftaran aktif dari relasi `registrationEntries.schedule` yang sudah dimuat; `null` bila tidak ada. Pemanggil: `directKtp($s, $this->eligibility->activeSessionDate($s))`.
- Produces: kunci payload `ktp_langsung: bool` pada baris dan detail siswa (`Siswa/Index`, `Siswa/Show`), baris antrean dan detail siswa (`Verifikasi/Index`, `Verifikasi/Show`, acuan = tanggal sesi entri itu), dan `ticket.ktp_langsung` (acuan = tanggal sesi tiket).
- Produces: `<KtpLangsungBadge />` (tanpa prop): lencana informasi bertuliskan "KTP-el langsung" dengan ikon, `title`/teks sr-only "Berumur lebih dari 17 tahun; KTP-el dapat langsung terbit."

- [ ] **Step 1: Tulis tes yang gagal**
  - `EligibilityServiceTest` (`Carbon::setTestNow`/tanggal acuan eksplisit; siswa lahir 2009-10-20): acuan 2026-10-19 → false (umur 16); 2026-10-20 (tepat 17 tahun) → false; 2026-10-21 → true; lahir 2008-02-29: acuan 2025-02-28 → false, 2025-03-01 → true; `activeSessionDate` mengembalikan tanggal sesi untuk entri Menunggu/Terdaftar dan `null` untuk entri Ditolak saja; tanpa acuan eksplisit memakai hari ini.
  - HTTP: siswa 17 tahun lebih sehari yang mendaftar sesi besok → `ktp_langsung` true di `Siswa/Index`, `Siswa/Show`, `Verifikasi/Index`, `Verifikasi/Show`, dan `Saya/Tiket`; siswa yang baru genap 17 tahun pada tanggal sesi → false; siswa 16 tahun → false; label bergantung tanggal sesi (siswa genap 17 tahun lusa, sesi hari ini → false, sesi 3 hari lagi → true).
  - `ListQueryCountTest`: jumlah query daftar siswa dan antrean tidak tumbuh per baris (pemuatan `registrationEntries.schedule` dan `schedule` memakai eager loading).
  - Vitest: `KtpLangsungBadge` merender teks "KTP-el langsung" dan teks sr-only; tiap halaman merender lencana hanya bila `ktp_langsung` true.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="EligibilityServiceTest|StudentHttpTest|VerificationHttpTest|TicketHttpTest|ListQueryCountTest"` dan `npx vitest run resources/js/Components/shared resources/js/Pages/Siswa resources/js/Pages/Verifikasi resources/js/Pages/Saya`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — logika hanya di `EligibilityService` (satu tempat, tidak disimpan); controller menambah satu kunci; repository daftar siswa memuat `registrationEntries.schedule`; lencana memakai pola `StatusBadge` (variant `info`) dengan ikon `IdCard`; pada halaman detail tampilkan juga satu kalimat penjelas (bukan hanya `title`, agar terbaca di layar sentuh).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tampilkan penanda KTP-el langsung untuk siswa 17 tahun lebih sehari`

---

### Task 3: Layanan dan endpoint scan

**Files:**
- Create: `app/Domains/Schedules/Services/ScanService.php`, `app/Domains/Schedules/Http/Controllers/ScanController.php`, `app/Domains/Schedules/Http/Requests/ScanRequest.php`
- Modify: `routes/schedules.php`, `app/Domains/Schedules/Services/RecordingService.php` (undo), `lang/id/activity.php`
- Test: `tests/Feature/Schedules/ScanServiceTest.php`, `tests/Feature/Schedules/ScanHttpTest.php`, `tests/Feature/Schedules/RecordingServiceTest.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`

**Interfaces:**
- Consumes (Task 1–2): `Schedule::scanWindow()`, `RegistrationStudent::$scanned_at`, `Permission::RekamanPindai`, `EligibilityService::directKtp`.
- Produces: `ScanService::scan(Schedule $schedule, string $code, User $actor): RegistrationStudent` (entri dengan `student` termuat). Dalam satu transaksi: cari `ticket_code` dengan `withoutGlobalScopes()->lockForUpdate()`, lalu periksa berurutan dan lempar `AccessRuleException` dengan pesan:
  1. tidak ada → `Kode tiket tidak dikenal.`
  2. `schedule_id` berbeda → `Tiket ini untuk sesi lain ({sekolah}, {hari, tanggal}).`
  3. sesi dibatalkan → `Sesi ini dibatalkan.`
  4. status Sudah rekaman dan `scanned_at` ada → `Tiket sudah dipindai pukul {HH.MM}.`; Sudah rekaman tanpa `scanned_at` → `Siswa sudah ditandai sudah rekaman.`
  5. status bukan Terdaftar → `Tiket tidak berlaku (status: {label}).`
  6. di luar `scanWindow()` → `Di luar jam sesi. Pemindaian dibuka {HH.MM}–{HH.MM}.`
  Sukses: `StatusTransitioner::move($entry, SudahRekaman, $actor, 'Dipindai petugas.', ['scanned_at' => now()])` dan `activity('jadwal')->performedOn($schedule)->causedBy($actor)->withProperties(['siswa_id' => …])->log('rekaman.dipindai')`. Penolakan atas tiket yang dikenal (butir 2–6) dicatat `rekaman.pindai-ditolak` dengan properti `alasan` dan `siswa_id`; kode tak dikenal tidak dicatat.
- Produces: rute `GET jadwal/{schedule}/pindai` (`jadwal.pindai.show`, `can:rekaman.pindai`) → Inertia `Jadwal/Pindai` dengan `schedule` (`ScheduleController::detail`), `counts` `{waiting: int, done: int}` (jumlah Terdaftar dan Sudah rekaman pada sesi), `window` `{from, to}` ISO; dan `POST jadwal/{schedule}/pindai` (`jadwal.pindai.store`, `can:rekaman.pindai`, `throttle:120,1`) dengan body `code` (wajib, string, maks 64) → JSON `200 {status: 'ok', message: 'Tercatat sudah rekaman.', student: {name, nisn, ktp_langsung}, scanned_at, counts}` atau `422 {status: 'rejected', message}`.
- Produces: `RecordingService::undo` mengosongkan `scanned_at` (atribut pada `move`).
- Produces: `lang/id/activity.php` memuat `rekaman.dipindai` ("Tiket dipindai") dan `rekaman.pindai-ditolak` ("Pindai tiket ditolak").

- [ ] **Step 1: Tulis tes yang gagal**
  - `ScanServiceTest` (`Carbon::setTestNow` di dalam jendela): sukses → status Sudah rekaman, `scanned_at` = sekarang, satu baris riwayat dengan pelaku petugas dan catatan "Dipindai petugas.", log `rekaman.dipindai`; pindai kedua kode yang sama → `AccessRuleException` "Tiket sudah dipindai pukul …" dan tetap satu baris riwayat; setiap penolakan 1–6 menghasilkan pesan tepat dan tidak mengubah status/riwayat (data uji: kode acak, tiket sesi lain, sesi dibatalkan, entri Menunggu/Ditolak/Dibatalkan/Tidak hadir, ditandai manual Sudah rekaman tanpa `scanned_at`); batas jendela: tepat `mulai − 60 menit` dan tepat `selesai + 60 menit` diterima, satu menit di luar ditolak, hari lain ditolak; penolakan tiket yang dikenal tercatat `rekaman.pindai-ditolak`, kode acak tidak.
  - `RecordingServiceTest`: `undo` atas penandaan hasil scan → Terdaftar, `scanned_at` null, lalu `scan` lagi berhasil; `mark` manual tidak mengisi `scanned_at`.
  - `ScanHttpTest`: halaman pindai memuat `counts`/`window`; POST sukses → JSON `ok` dengan nama, NISN, `ktp_langsung`, `counts` ikut berubah, **tanpa** `ticket_code`/NIK di seluruh respons; POST ditolak → 422 dengan `status: 'rejected'` dan `message`; `code` kosong → 422; pengguna tanpa `rekaman.pindai` (termasuk yang punya `rekaman.tandai`), operator sekolah, dan siswa → 403/404 sesuai pola rute `jadwal.*`; operator terikat sekolah tidak dapat memindai sesi sekolah lain (route binding); daftar peserta (`jadwal.show`) dan halaman penandaan tidak memuat `ticket_code`; limit laju terpasang (`throttle:120,1` pada rute).
  - `RoutePermissionMatrixTest`: dua baris `GET /jadwal/{schedule}/pindai` dan `POST /jadwal/{schedule}/pindai` → `rekaman.pindai`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="ScanServiceTest|ScanHttpTest|RecordingServiceTest|RoutePermissionMatrixTest|RouteGuardAuditTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `ScanService` mengikuti pola `RecordingService` (konstruktor `StatusTransitioner`); controller menangkap `AccessRuleException` dan membalas 422 JSON (renderer global bootstrap mengubahnya menjadi redirect, tidak cocok untuk JSON); `counts` dihitung dari `entries` sesi; format jam `HH.MM` konsisten dengan tampilan jam lain.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: layanan dan endpoint pindai tiket QR untuk mencatat kehadiran`

---

### Task 4: Halaman pindai di peramban

**Files:**
- Create: `resources/js/Pages/Jadwal/Pindai.vue`, `resources/js/Pages/Jadwal/Pindai.test.ts`, `resources/js/lib/postJson.ts`, `resources/js/lib/postJson.test.ts`
- Modify: `package.json`, `package-lock.json` (`npm install qr-scanner@1.4.2`), `resources/js/Pages/Jadwal/Show.vue` (tautan "Pindai tiket" bila `can(PERMISSIONS.RekamanPindai)`, sebelum "Penandaan rekaman"), `resources/js/Pages/Jadwal/Rekaman.vue` (tautan ke halaman pindai), `resources/js/types/schedule.ts`, `resources/js/Pages/Jadwal/Jadwal.test.ts`

**Interfaces:**
- Consumes (Task 3): props `schedule`, `counts`, `window`; endpoint `jadwal.pindai.store` (JSON seperti di Task 3).
- Produces: `postJson<T>(url: string, body: unknown): Promise<{ status: number; data: T }>` — `fetch` `POST` dengan `Accept: application/json`, `Content-Type: application/json`, `X-Requested-With: XMLHttpRequest`, dan `X-XSRF-TOKEN` dari cookie `XSRF-TOKEN` (di-`decodeURIComponent`); tidak melempar untuk status 4xx (pemanggil membaca `status`), melempar hanya pada galat jaringan.
- Produces: `Jadwal/Pindai` — tombol eksplisit "Mulai memindai" (izin kamera diminta dari gestur pengguna; mobile-first), area kamera persegi, penghitung "Sudah {done} · Belum {waiting}", panel hasil terakhir, dan daftar 5 hasil terakhir.

- [ ] **Step 1: Tulis tes yang gagal** (`vi.mock('qr-scanner')`, `vi.stubGlobal('route', …)`, `postJson` dimock di tes halaman)
  - `postJson.test.ts`: header XSRF terisi dari cookie, status 422 dikembalikan tanpa melempar, galat jaringan melempar.
  - `Pindai.test.ts`: sebelum mulai kamera tidak dinyalakan; klik "Mulai memindai" membuat `QrScanner` dan memanggil `start()`; hasil dekode memanggil `postJson` ke `jadwal.pindai.store` dengan `code`; sukses menampilkan nama, NISN, teks "Tercatat sudah rekaman", lencana KTP-el bila `ktp_langsung`, dan menambah hitungan "Sudah"/mengurangi "Belum" dari `counts` respons; respons 422 menampilkan `message` sebagai penolakan (teks + ikon, bukan hanya warna); kode sama dalam 5 detik tidak mengirim permintaan kedua; selama permintaan berjalan pemindaian berikutnya diabaikan; 429 menampilkan "Terlalu banyak pindaian; tunggu sebentar."; galat jaringan menampilkan pesan dan pemindaian tetap lanjut; izin kamera ditolak (`start()` melempar `NotAllowedError`) dan `QrScanner.hasCamera()` false/`!isSecureContext` masing-masing menampilkan penjelasan (termasuk "butuh HTTPS") dan tautan ke penandaan manual (`jadwal.rekaman.show`); sesi dibatalkan → peringatan tanpa tombol kamera; di luar `window` → peringatan "Pemindaian dibuka …" (tombol tetap ada, server yang memutuskan); saat komponen dilepas `stop()` dan `destroy()` dipanggil; panel hasil berada di region `aria-live` dan tombol memenuhi target sentuh ≥ 44px.
  - `Jadwal.test.ts`: tautan "Pindai tiket" muncul hanya untuk pemegang `rekaman.pindai`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `npx vitest run resources/js/lib/postJson.test.ts resources/js/Pages/Jadwal`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `new QrScanner(video, onDecode, { preferredCamera: 'environment', maxScansPerSecond: 5, highlightScanRegion: false, returnDetailedScanResult: true })`; gagal-aman: bila kamera tidak ada atau konteks tidak aman, tampilkan pesan dan jalur cadangan; ikuti panduan UI (§ halaman kerja lapangan: teks ≥ 16px, kontras, tidak hanya warna, `prefers-reduced-motion`); tanpa kamera di jsdom, semua perilaku diuji lewat mock.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2 + `npx vue-tsc --noEmit` + `npx eslint` berkas yang disentuh; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: halaman pindai QR tiket untuk admin kecamatan`

---

### Task 5: Tidak hadir otomatis setelah sesi

**Files:**
- Create: `app/Console/Commands/MarkAbsentRegistrations.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Console/MarkAbsentRegistrationsTest.php`

**Interfaces:**
- Consumes: `StatusTransitioner::move(..., null, $note)`, pola `ExpireRegistrations` (per entri `DB::transaction` + `lockForUpdate` + cek ulang, `AccessRuleException` → lewati, `Throwable` → `report()` dan kode keluar 1).
- Produces: perintah `sijebol:tandai-tidak-hadir` — setiap pendaftaran berstatus Terdaftar pada sesi dengan `date < Schedule::today()` menjadi Tidak hadir oleh Sistem (`by_user_id` null) dengan catatan "Tidak terpindai sampai sesi berakhir."; tanpa notifikasi; keluaran "{n} pendaftaran ditandai tidak hadir." Terjadwal tiap jam, `withoutOverlapping`.

- [ ] **Step 1: Tulis tes yang gagal** (`Carbon::setTestNow('2026-10-19 10:00', 'Asia/Jakarta')`)
  - Terdaftar pada sesi kemarin → Tidak hadir, riwayat satu baris berpelaku null dan catatan di atas, kursi tidak lagi ditahan (`seatsTaken()` turun), dan siswa dapat mendaftar sesi lain; Terdaftar pada sesi hari ini dan besok tidak berubah; Sudah rekaman, Belum rekaman, Tidak hadir, Menunggu, Perlu perbaikan, Ditolak, Dibatalkan, dan Kedaluwarsa pada sesi lampau tidak disentuh; dijalankan dua kali → satu perubahan; tidak ada notifikasi terkirim (`Notification::assertNothingSent()`); kegagalan satu entri (mock `StatusTransitioner`) tidak menghentikan yang lain dan kode keluar 1; entri yang dipindai setelah dipilih perintah (disimulasikan seperti tes balapan `ExpireRegistrationsTest` via `Event::listen('eloquent.retrieved: …')` yang mengubah status ke Sudah rekaman) tidak ditimpa; terdaftar di jadwal (`schedule:list`) tiap jam tanpa tumpang tindih (`0 * * * *`).
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter=MarkAbsentRegistrationsTest`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — salin bentuk `ExpireRegistrations` (tanpa scope global, `chunkById(100)`), tanpa kelas dasar bersama (dua pemakai belum cukup alasan untuk abstraksi).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tandai tidak hadir otomatis setelah sesi berakhir`

---

### Task 6: Beranda — kuota, kehadiran, dan perbaikan metrik

**Files:**
- Modify: `app/Domains/Schedules/Repositories/EloquentScheduleRepository.php` (+ kontrak), `app/Domains/Schedules/Services/ScheduleService.php`, `app/Domains/Reports/Services/DashboardService.php`, `resources/js/Components/dashboard/DashboardUpcomingTable.vue`, `resources/js/Components/dashboard/DashboardQuickActions.vue`, `resources/js/types/dashboard.ts`, `resources/js/Pages/Dashboard.vue` bila perlu
- Test: `tests/Feature/Schedules/DashboardTest.php`, `tests/Feature/Reports/DashboardSummaryTest.php`, `tests/Feature/Performance/ListQueryCountTest.php`, `resources/js/Components/dashboard/DashboardComponents.test.ts`, `resources/js/Pages/Dashboard.test.ts`

**Interfaces:**
- Produces: `ScheduleRepositoryInterface::upcoming(int $limit)` dan `ScheduleService::calendarEvents` mengabaikan sesi dibatalkan (`cancelled_at` null saja); `upcoming` menambah hitungan `waiting_count` (Menunggu verifikasi) dan `present_count` (Sudah rekaman).
- Produces: `ScheduleService::upcomingTable` — tiap baris tetap memuat `id, date, school_name, participants (= terisi), location, status` dan menambah `quota: int`, `waiting: int`, `present: int`.
- Produces: `DashboardService::charts()['monthly_trend']` — "Terdaftar" = jumlah pendaftaran dibuat per bulan tahun berjalan; "Sudah Rekam" = pendaftaran berstatus Sudah rekaman menurut bulan **tanggal sesi** pada tahun berjalan; keduanya lewat query agregat (`groupBy`), bukan memuat seluruh baris.
- Produces: `DashboardService::quickActions` — aksi "Pindai Tiket" (`href` = `/jadwal`, ikon baru `scan-line`) bagi pemegang `rekaman.pindai`; aksi "Input Hasil Perekaman" dan pemeriksaan izin `rekaman.tambah` dihapus.
- Produces: `UpcomingScheduleRow` memuat `quota`, `waiting`, `present`; baris berstatus "Hari Ini" menampilkan tautan "Pindai" ke `jadwal.pindai.show` bagi pemegang `rekaman.pindai`.

- [ ] **Step 1: Tulis tes yang gagal**
  - Sesi dibatalkan tidak muncul di `schedules_table`, `schedules`, maupun `calendar_events`; baris sesi memuat `quota` 30, `participants` (terisi: Menunggu+Perlu perbaikan+Terdaftar+Sudah rekaman+Belum rekaman), `waiting`, `present` yang benar untuk campuran status (Ditolak/Dibatalkan/Kedaluwarsa tidak dihitung terisi).
  - `monthly_trend`: entri Sudah rekaman pada sesi bulan Oktober dihitung di Oktober walau `updated_at` berubah bulan lain; entri bulan sebelumnya di tahun lain tidak dihitung; pembatalan penandaan mengeluarkan entri dari "Sudah Rekam".
  - `quick_actions`: admin memegang "Pindai Tiket" dengan `/jadwal`; pengguna tanpa `rekaman.pindai` tidak; tidak ada lagi "Input Hasil Perekaman".
  - `ListQueryCountTest`: beranda tidak menambah query per sesi.
  - Vitest: tabel menampilkan "12/30" (terisi/kuota) dan jumlah menunggu serta hadir sebagai teks; tautan "Pindai" hanya pada baris "Hari Ini" bagi pemegang izin; ikon aksi cepat baru terpetakan.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="DashboardTest|DashboardSummaryTest|ListQueryCountTest"` dan `npx vitest run resources/js/Components/dashboard resources/js/Pages/Dashboard.test.ts`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `withCount` dengan alias untuk hitungan baru; tren bulanan lewat `selectRaw('MONTH(...)')` (MySQL, sama dengan basis data tes); tabel tetap dapat digulir mendatar di layar sempit, kolom baru memakai teks (bukan warna saja).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tampilkan kuota dan kehadiran di beranda serta perbaiki metrik sesi dibatalkan`

---

### Task 7: Laporan kehadiran per sesi

**Files:**
- Create: `app/Domains/Reports/Exports/SessionRecapExport.php`, `resources/js/Pages/Laporan/Laporan.test.ts` (bila belum ada tes halaman Laporan)
- Modify: `app/Domains/Reports/Repositories/Contracts/ReportRepositoryInterface.php`, `app/Domains/Reports/Repositories/EloquentReportRepository.php`, `app/Domains/Reports/Services/ReportService.php`, `app/Domains/Reports/Http/Controllers/ReportController.php`, `app/Domains/Reports/Exports/RecapExport.php` (`safe` menjadi `public static`), `routes/reports.php`, `resources/js/Pages/Laporan/Index.vue`, `resources/js/types/report.ts`, `lang/id/activity.php` bila perlu
- Test: `tests/Feature/Reports/ReportServiceTest.php`, `ReportHttpTest.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`

**Interfaces:**
- Produces: `ReportRepositoryInterface::sessionStatusCounts(?string $from, ?string $to): array` — satu baris per sesi tidak dibatalkan dengan tanggal dalam rentang inklusif (`Y-m-d`): `id, date, start_time, end_time, school, location, quota, counts` (peta status → jumlah); terkena scope sekolah pengguna; urut tanggal lalu id.
- Produces: `ReportService::sessionRecap(?string $from, ?string $to): array{rows: list<array{id, date, start_time, end_time, school, location, quota, taken, present, not_recorded, absent, attendance}>, totals: array{quota, taken, present, not_recorded, absent, attendance}}` — `taken` = jumlah status penahan kursi, `present` = Sudah rekaman, `not_recorded` = Belum rekaman, `absent` = Tidak hadir, `attendance` = persen bulat `(present+not_recorded)/(present+not_recorded+absent)` atau `null` bila penyebut 0 (juga pada baris total).
- Produces: `ReportController::index` menambah prop `session_recap`; `GET laporan/ekspor-sesi` (`laporan.ekspor-sesi`, `can:laporan.lihat`, `throttle:20,1`) → `rekap-kehadiran-sesi.xlsx` (judul kolom Indonesia, baris total di akhir, sel teks berawalan `=+-@` dilindungi seperti `RecapExport`) dan mencatat `laporan.diekspor` dengan properti `jenis: 'kehadiran-sesi'`.

- [ ] **Step 1: Tulis tes yang gagal**
  - `ReportServiceTest`: dua sesi dengan campuran status → angka per baris dan total benar; kehadiran dibulatkan (mis. 3 hadir+1 belum rekam, 1 tidak hadir → 80); sesi tanpa Sudah/Belum/Tidak hadir → `attendance` null; sesi dibatalkan tidak dimuat; filter `dari`/`sampai` memakai tanggal sesi dan inklusif di kedua ujung; rentang kosong → `rows` kosong dan total nol; operator terikat sekolah hanya melihat sesinya.
  - `ReportHttpTest`: halaman memuat `session_recap`; ekspor sesi mengikuti filter, berkas `.xlsx`, tercatat di audit; pengguna tanpa `laporan.lihat` → 403; rate limit terpasang; nama sekolah berawalan `=` dilindungi pada ekspor.
  - `RoutePermissionMatrixTest`: baris `GET /laporan/ekspor-sesi` → `laporan.lihat`.
  - Vitest halaman Laporan: tabel kedua bertajuk "Kehadiran per sesi" dengan keterangan "menurut tanggal sesi", baris total, tombol "Ekspor Excel" kedua mengikuti filter yang diterapkan, keadaan kosong.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="ReportServiceTest|ReportHttpTest|RoutePermissionMatrixTest"` dan `npx vitest run resources/js/Pages/Laporan`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — satu query agregat `registration_students` × `schedules` seperti `schoolStatusCounts` (tanpa N+1); `SessionRecapExport` meniru `RecapExport`; halaman memakai `DataTable` yang sama dan keterangan pada kedua tabel.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter Step 2; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tambah laporan kehadiran per sesi dan ekspornya`

---

### Task 8: Dokumentasi dan verifikasi akhir fase

**Files:**
- Modify: `docs/deployment-guide.md` (bagian baru "12. Fase 7d"), `docs/operasional-queue.md`, `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`, memori proyek bila perlu
- Test: seluruh suite

- [ ] **Step 1: Dokumentasi**
  - Panduan penerapan: jalankan `php artisan migrate`, `php artisan db:seed --class=AccessSeeder` (izin `rekaman.pindai`), pastikan HTTPS (kamera), dan cron `schedule:run` tiap menit untuk dua perintah lama plus `sijebol:tandai-tidak-hadir`.
  - `operasional-queue.md`: tambah `sijebol:tandai-tidak-hadir` ke daftar perintah terjadwal, cara menjalankan manual, dan arti kode keluar.
  - Panduan UI: halaman Pindai (keadaan kamera, panel hasil, pesan penolakan), lencana KTP-el langsung, perubahan tabel beranda, tabel laporan kedua.
- [ ] **Step 2: Verifikasi penuh** — `php artisan test` (jalankan di latar belakang, sekitar 4–6 menit), `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vue-tsc --noEmit`, `npx vitest run` (ulangi dengan `--testTimeout=30000` bila time-out), `npx eslint` pada berkas yang disentuh, `npm run build`; Expected: semua hijau.
- [ ] **Step 3: Review** — `ponytail:ponytail-review` untuk seluruh perubahan dan `impeccable` untuk halaman Pindai, tabel beranda, dan Laporan terhadap panduan UI; perbaiki temuan yang valid, lalu verifikasi ulang.
- [ ] **Step 4: Penelusuran manual (gerbang rilis, dikerjakan user dengan data dev)** — daftar di akhir ledger: buat sesi hari ini + siswa disetujui, buka halaman pindai di HP lewat HTTPS, pindai QR dari HP lain; pindai ulang (pesan "sudah dipindai"); pindai tiket sesi lain; batalkan penandaan lalu pindai lagi; jalankan `sijebol:tandai-tidak-hadir` setelah menggeser tanggal sesi; periksa label KTP-el pada siswa uji; lihat beranda dan unduh kedua ekspor.
- [ ] **Step 5: Commit** — `docs: dokumentasikan fase 7d dan catat verifikasi akhir`
