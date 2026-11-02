# Daftar Rekaman & KTP-el Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman "Laporan KTP-el pelajar" (satu tabel, tiga kelompok, filter) plus unduh Excel berisi NIK penuh untuk pemegang izin khusus.

**Architecture:** `ReportRepositoryInterface` mengambil siswa (sudah dibatasi sekolah oleh `ScopedBySchool`) beserta pendaftaran dan sesinya; `ReportService::studentRecords` menyusun baris, kelompok, jumlah, dan menerapkan filter di PHP (ratusan sampai ribuan siswa). `ReportController` menampilkan halaman berpaginasi dan mengunduh Excel dari baris yang sama. Aturan "genap 17 tahun" tetap satu sumber di `EligibilityService`.

**Tech Stack:** Laravel, `maatwebsite/excel`, Inertia + Vue 3 + TypeScript, Pest, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-01-daftar-rekaman-ktp-el-design.md`

## Global Constraints

- Teks UI dan Excel dalam bahasa Indonesia; zona waktu Asia/Jakarta; format tanggal di frontend lewat `lib/date.ts`.
- Izin baru: `laporan.rekaman` (halaman + Excel) dan `laporan.unduh-nik` (NIK penuh di Excel). Hanya `super-admin` dan `admin-kecamatan` yang mendapat default (seeder memberi semua izin; jangan ubah `operator-sekolah` dan `siswa`).
- Izin ini TIDAK dimasukkan ke daftar kecamatan-saja di `RoleService::assertScopeAllows`: data sudah dibatasi sekolah oleh `ScopedBySchool`.
- NIK tidak boleh muncul di props halaman. Di Excel: penuh bila pengunduh punya `laporan.unduh-nik`, selain itu `Nik::mask`.
- Satu siswa tepat satu kelompok; jumlah tiga kelompok = jumlah siswa dalam cakupan filter (selain filter kelompok).
- Teks tidak boleh mengesankan KTP-el sudah dicetak; gunakan `KtpLangsungBadge` yang ada.
- Pola proyek: service-repository, `useDataTable` (`filter[...]`), `DataTable`, `PageHeader`, `Pagination`, `RecapExport::safe` untuk sel teks.
- Commit hanya saat diminta; tanpa baris `Co-Authored-By`.

## Review Focus

- Lahir tepat 17 tahun pada tanggal sesi hanya "Menunggu genap 17" (butuh lebih dari 17 tahun): Task 1.
- Siswa yang punya pendaftaran lama `tidak_hadir` lalu pendaftaran baru aktif mengikuti pendaftaran terkini, bukan yang lama: Task 1.
- Siswa tanpa pendaftaran tetap muncul (Belum rekaman) walau rentang tanggal diisi: Task 1.
- NIK penuh tidak bocor ke props halaman, dan Excel tanpa izin NIK hanya termasking: Task 2 dan 3.
- Pengguna terikat sekolah hanya melihat sekolahnya; `filter[school_id]` sekolah lain menghasilkan daftar kosong: Task 2.
- NIK dan NISN di Excel berupa teks (bukan notasi ilmiah) dan nama berawalan `=` dinetralkan: Task 3.

---

### Task 1: Data dan aturan kelompok

**Files:**
- Modify: `app/Domains/Students/Services/EligibilityService.php` (tambah `directKtpFrom`, `directKtp` memakainya)
- Modify: `app/Domains/Reports/Repositories/Contracts/ReportRepositoryInterface.php`, `app/Domains/Reports/Repositories/EloquentReportRepository.php`
- Modify: `app/Domains/Reports/Services/ReportService.php`
- Test: `tests/Feature/Reports/StudentRecordsTest.php` (baru); tes `directKtp` yang ada tetap hijau

**Interfaces:**
- Produces: `EligibilityService::directKtpFrom(Student $student): Carbon` (ulang tahun ke-17 ditambah satu hari, `addYearsNoOverflow`).
- Produces: `ReportRepositoryInterface::studentsWithRegistrations(?int $schoolId, ?string $search): Collection<int, Student>` — dimuat dengan `school` dan `registrationEntries.schedule`, urut `name`, `id`; `search` memadankan nama atau NISN (sebagian).
- Produces: `ReportService::GROUPS` = `['langsung' => 'Langsung terbit', 'menunggu' => 'Menunggu genap 17', 'belum' => 'Belum rekaman']`.
- Produces: `ReportService::studentRecords(array $filters): array{rows: list<array>, counts: array<string,int>}`. `$filters` berkunci opsional `school_id`, `schedule_id`, `search`, `dari`, `sampai`, `group`. Setiap baris: `id`, `name`, `nik`, `nisn`, `birth_date` (Y-m-d), `gender` ('L'|'P'), `school`, `group`, `session_date` (?Y-m-d), `eligible_from` (Y-m-d), `reason` (?string). `counts` berkunci tiga kelompok, dihitung SEBELUM filter `group`; `rows` sesudahnya, terurut kelompok (langsung, menunggu, belum) lalu urutan repositori.

- [ ] **Step 1: Tulis tes yang gagal** di `StudentRecordsTest.php` (helper: `Student::factory()->for($sekolah)->create(['birth_date' => ...])`, `makeEntry(status, $sekolah, $siswa, makeSchedule($sekolah, 'Y-m-d'))`):
  - `kelompok mengikuti status rekaman dan umur pada tanggal sesi`: sesi 2026-10-20; lahir 2009-10-19 + `SudahRekaman` → `langsung`; lahir 2009-10-20 (tepat 17) + `SudahRekaman` → `menunggu` dengan `eligible_from` `2026-10-21`; lahir 2008-02-29 + `SudahRekaman` → `eligible_from` `2025-03-01`; `TidakHadir` → `belum` alasan `Tidak hadir`; `Ditolak` → `Ditolak`; `MenungguVerifikasi` → `Menunggu verifikasi`; tanpa pendaftaran → `belum` alasan `Belum mendaftar sesi`, `session_date` null.
  - `pendaftaran terkini yang menentukan`: siswa dengan `TidakHadir` lalu `Terdaftar` → `belum`, alasan `Terdaftar`.
  - `jumlah kelompok tidak terpengaruh filter kelompok dan totalnya sama dengan jumlah siswa`: `counts` sama untuk `group` kosong dan `group=belum`; `array_sum(counts)` = jumlah siswa; `rows` pada `group=belum` hanya kelompok itu.
  - `rentang tanggal menyaring tanggal sesi, siswa tanpa pendaftaran tetap tampil`: tiga siswa bersesi 2026-10-20, 2026-11-20, dan satu tanpa pendaftaran; `dari=2026-11-01` → hanya siswa 11-20 dan yang tanpa pendaftaran.
  - `filter sesi, sekolah, dan pencarian`: `schedule_id`; `school_id`; `search` cocok nama dan NISN sebagian, tidak cocok NIK sebagian.
  - `urutan: kelompok lalu nama`.
  - `directKtpFrom`: tes terpisah pada `EligibilityService` untuk tiga tanggal di atas.

- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `vendor/bin/pest tests/Feature/Reports/StudentRecordsTest.php` — Expected: FAIL (metode belum ada).

- [ ] **Step 3: Implementasi.** `directKtp` menjadi `$this->day($on)->toDateString() >= $this->directKtpFrom($student)->toDateString()` (perilaku sama). Repositori: `Student::query()->with([...])` dengan `when` untuk `school_id` dan `search` (`name like` atau `nisn like`). Servis: ambil siswa, `currentRegistration()` menentukan `session_date` (`->schedule->date`), kelompok (`SudahRekaman` + `directKtp(siswa, tanggal sesi)` → langsung, `SudahRekaman` lainnya → menunggu, sisanya belum), `reason` (`status->label()` untuk belum; `Belum mendaftar sesi` bila tanpa pendaftaran; null selain itu). Filter `schedule_id` dan rentang di PHP (siswa tanpa pendaftaran lolos rentang, tidak lolos `schedule_id`). Tandai `// ponytail:` bahwa semua siswa dimuat ke memori, naik ke query SQL bila siswa mencapai puluhan ribu.

- [ ] **Step 4: Jalankan, pastikan lulus.** Run: `vendor/bin/pest tests/Feature/Reports tests/Feature/Students` — Expected: PASS.

### Task 2: Izin, rute, menu, dan halaman sisi server

**Files:**
- Modify: `app/Domains/Access/Enums/Permission.php` (`LaporanRekaman = 'laporan.rekaman'`, `LaporanUnduhNik = 'laporan.unduh-nik'`)
- Modify: `routes/reports.php`, `app/Domains/Reports/Http/Controllers/ReportController.php`, `app/Domains/Reports/Services/ReportService.php` (`recordExport` menerima `array $extra = []`)
- Create: `app/Domains/Reports/Http/Requests/StudentRecordRequest.php`
- Modify: `app/Domains/Access/Services/MenuService.php`, `resources/js/lib/permissions.ts`
- Test: `tests/Feature/Reports/StudentRecordHttpTest.php` (baru), `tests/Feature/Access/RoutePermissionMatrixTest.php`, `tests/Feature/Access/MenuTest.php`

**Interfaces:**
- Consumes: `ReportService::studentRecords`, `ReportService::GROUPS` (Task 1).
- Produces: rute `reports.students` (`GET /reports/students`) dan `reports.students.export` (`GET /reports/students/export`, `throttle:20,1`), keduanya `can:laporan.rekaman`.
- Produces: `StudentRecordRequest::filters(): array` — memetakan `filter.school_id|schedule_id|search|group|dari|sampai` ke kunci `$filters` Task 1; aturan: id integer, `search` string maks 100, `group` in `langsung,menunggu,belum`, `dari`/`sampai` `date_format:Y-m-d` dan `sampai` `after_or_equal:dari` hanya bila `dari` terisi (pola `ReportRequest`), pesan Indonesia yang sama.
- Produces: halaman Inertia `Laporan/Siswa` dengan props `records` (paginator 25 baris/halaman, `withQueryString`, baris TANPA `nik`, `birth_date`, `gender`), `groups` (`list<{key, label, count}>`), `filters` (nilai yang berlaku), `schools` (`{id,name}[]` atau `null` bila pengguna terikat sekolah), `schedules` (`{id, label}[]`, label `"{tanggal} · {sekolah}"`, urut tanggal turun).
- Produces: item menu "Laporan KTP-el pelajar" pada grup Laporan, href `/reports/students`, ikon `clipboard-list`, izin `laporan.rekaman`.

- [ ] **Step 1: Tulis tes yang gagal.**
  - `RoutePermissionMatrixTest`: tambah `GET /reports/students` dan `GET /reports/students/export` dengan `laporan.rekaman`. `MenuTest`: pemegang `laporan.rekaman` melihat item baru, pemegang `laporan.lihat` saja tidak.
  - `StudentRecordHttpTest`: props halaman (komponen `Laporan/Siswa`, `groups` berisi tiga kelompok dengan label dan jumlah, `records.data.0` tidak punya kunci `nik`); `json_encode` seluruh props tidak memuat NIK penuh maupun yang termasking; tanpa `laporan.rekaman` → 403; pengguna terikat sekolah (`makeUserWith(['laporan.rekaman'], $sekolah, true, 'op-lap')`) hanya melihat siswa sekolahnya, `schools` null, dan `filter[school_id]=<sekolah lain>` menghasilkan `records.data` kosong; `filter[group]=bukan` dan rentang terbalik → galat validasi; `AccessSeeder`: `admin-kecamatan` dan `super-admin` memegang kedua izin, `operator-sekolah` tidak.

- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `vendor/bin/pest tests/Feature/Reports/StudentRecordHttpTest.php tests/Feature/Access/MenuTest.php` — Expected: FAIL.

- [ ] **Step 3: Implementasi.** Tambah dua kasus enum; rute di `routes/reports.php`; `ReportController::students(StudentRecordRequest)` memakai `ReportService::studentRecords`, memotong baris dengan `Arr::only` (id, name, nisn, school, group, session_date, eligible_from, reason), membuat `LengthAwarePaginator` manual dengan `path` dan `withQueryString`; daftar `schools` lewat `SchoolService::optionsFor($user)` (pola `StudentController`), `schedules` dari `Schedule` (sudah `ScopedBySchool`) tanpa sesi dibatalkan. `ReportService::recordExport` menerima `$extra` yang digabung ke properti log. Menu dan `permissions.ts` (`LaporanRekaman`, `LaporanUnduhNik`).

- [ ] **Step 4: Jalankan, pastikan lulus.** Run: `vendor/bin/pest tests/Feature/Reports tests/Feature/Access tests/Unit` — Expected: PASS.

### Task 3: Unduh Excel

**Files:**
- Create: `app/Domains/Reports/Exports/StudentRecordExport.php`
- Modify: `app/Domains/Reports/Http/Controllers/ReportController.php` (`exportStudents`)
- Test: `tests/Feature/Reports/StudentRecordHttpTest.php`

**Interfaces:**
- Consumes: `ReportService::studentRecords` (baris lengkap, termasuk `nik`), `ReportService::GROUPS`, `RecapExport::safe`, `App\Support\Nik::mask`.
- Produces: `StudentRecordExport::__construct(array $rows, bool $fullNik)`; `FromArray`, `WithHeadings`, `WithCustomValueBinder` (turunan `PhpOffice\PhpSpreadsheet\Cell\StringValueBinder` agar semua sel berupa teks); `headings()` = `['Nama', 'NIK', 'NISN', 'Tanggal lahir', 'Jenis kelamin', 'Sekolah', 'Tanggal rekaman', 'KTP-el', 'Terbit mulai', 'Alasan']`; `Tanggal rekaman` terisi hanya untuk kelompok langsung dan menunggu; `Jenis kelamin` = `Laki-laki`/`Perempuan`; `KTP-el` = label kelompok; `Terbit mulai` = `eligible_from`.
- Produces: berkas `laporan-ktp-el-pelajar.xlsx`; log `laporan.diekspor` dengan `jenis: ktp-el-pelajar`, filter yang dipakai, `nik_penuh` bool, `jumlah` baris.

- [ ] **Step 1: Tulis tes yang gagal** (`Excel::fake()` untuk isi, unduhan sungguhan untuk berkas):
  - pemegang `laporan.unduh-nik`: sel NIK `3201123412341234`; tanpa izin itu: `3201********1234`.
  - judul kolom dan isi baris untuk tiga kelompok; `Tanggal rekaman` kosong untuk kelompok belum.
  - ekspor mengikuti filter (`filter[group]=belum`) dan mencakup semua halaman (lebih dari 25 siswa).
  - nama berawalan `=` dan `@` dinetralkan (`'=...`).
  - `bindValue` mengikat string numerik `3201123412341234` sebagai tipe string pada sel `Worksheet`.
  - unduhan sungguhan: `assertDownload('laporan-ktp-el-pelajar.xlsx')`, log dengan `jenis`, `nik_penuh`, `jumlah`; pengguna tanpa `laporan.rekaman` → 403.

- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `vendor/bin/pest tests/Feature/Reports/StudentRecordHttpTest.php` — Expected: FAIL.

- [ ] **Step 3: Implementasi** `StudentRecordExport` dan `exportStudents` (`$request->user()->can('laporan.unduh-nik')` menentukan `fullNik`; catat log sebelum mengunduh seperti ekspor lain).

- [ ] **Step 4: Jalankan, pastikan lulus.** Run: `vendor/bin/pest tests/Feature/Reports` — Expected: PASS.

### Task 4: Halaman frontend

**Files:**
- Create: `resources/js/Pages/Laporan/Siswa.vue`
- Modify: `resources/js/types/report.ts` (`StudentRecordRow`, `StudentRecordGroup`)
- Test: `resources/js/Pages/Laporan/Siswa.test.ts` (baru)

**Interfaces:**
- Consumes: props `Laporan/Siswa` dari Task 2; `useDataTable(route('reports.students'), {...})`; `route('reports.students.export', { filter })`.
- Produces: komponen `Laporan/Siswa.vue`.

- [ ] **Step 1: Tulis tes Vitest yang gagal** (pola `Sekolah/Tautan.test.ts`: stub `route`, mock `@inertiajs/vue3` dan `AppLayout`):
  - tiga kotak kelompok menampilkan label dan jumlah; mengklik satu memanggil `router.get` dengan `filter.group`, mengklik lagi melepasnya; kotak aktif `aria-pressed="true"`.
  - baris: nama, NISN, sekolah (kolom Sekolah hanya bila `schools` tidak null), tanggal rekaman, `KTP-el langsung` untuk kelompok langsung, "Terbit mulai {tanggal}" untuk menunggu, alasan untuk belum; tidak ada teks NIK.
  - tautan Unduh Excel memuat filter aktif; keadaan kosong menampilkan `EmptyState`; catatan "terbit = memenuhi syarat umur, pencetakan tidak dicatat" tampil.

- [ ] **Step 2: Jalankan, pastikan gagal.** Run: `npx vitest run resources/js/Pages/Laporan/Siswa.test.ts` — Expected: FAIL.

- [ ] **Step 3: Implementasi** `Siswa.vue` dengan `PageHeader`, kotak kelompok (tombol toggle), filter (pencarian debounce 300 ms seperti `Siswa/Index.vue`, sekolah bila ada, sesi, dari, sampai), `DataTable`, `Pagination`. Kolom: Nama, NISN, Sekolah (bersyarat), Tanggal rekaman, KTP-el, Alasan. Periksa logika item aktif sidebar agar `/reports/students` tidak menandai "Rekap pendaftaran" ikut aktif (cocokkan href terpanjang); perbaiki bila perlu beserta tesnya.

- [ ] **Step 4: Jalankan, pastikan lulus.** Run: `npx vitest run` dan `npx vue-tsc --noEmit` — Expected: PASS.

### Task 5: Dokumen dan verifikasi akhir

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (bagian halaman Laporan KTP-el pelajar), `docs/deployment-guide.md` (catatan rilis: jalankan `AccessSeeder` di UAT/produksi untuk izin `laporan.rekaman` dan `laporan.unduh-nik`)

- [ ] **Step 1:** Perbarui kedua dokumen.
- [ ] **Step 2:** Review `ponytail:ponytail-review` (semua perubahan) dan `impeccable` (`Siswa.vue`); perbaiki temuan yang valid.
- [ ] **Step 3: Verifikasi** — Pest penuh, Vitest penuh, Pint, PHPStan, `npx vue-tsc --noEmit`, `npx prettier --check` pada berkas yang berubah; semua hijau.
