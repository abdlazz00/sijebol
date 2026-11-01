# Syarat dan Status Pendaftaran di Daftar Siswa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, inline). Langkah memakai checkbox (`- [ ]`).

**Goal:** Daftar dan detail siswa (sisi staf/operator) tidak lagi menandai siswa yang sudah terdaftar sebagai "Belum memenuhi syarat". "Syarat" hanya menilai umur dan dokumen; status pendaftaran terkini tampil di kolom terpisah dan dapat difilter.

**Architecture:** `EligibilityService::check()` tidak berubah (aturan "tidak boleh pendaftaran aktif ganda" tetap untuk pendaftaran). `StudentController` memanggilnya dengan mengecualikan pendaftaran terkini siswa (parameter ketiga yang sudah ada, seperti `SelfStudentController`), dan mengirim `registration` (status dan tanggal sesi pendaftaran terkini). Pendaftaran terkini = yang aktif bila ada, selain itu yang terbaru (aturan `RegistrationService::currentEntry`, kini juga sebagai `Student::currentRegistration()` pada relasi yang sudah dimuat agar daftar tidak N+1).

**Tech Stack:** Laravel, Pest, Vue 3 + Inertia, Vitest.

**Spec:** Disepakati di chat 2026-11-01 (analisis "syarat vs pendaftaran", pilihan B: dua kolom).

## Global Constraints

- Siswa yang Tidak hadir, Belum rekaman, Ditolak, Dibatalkan, atau Kedaluwarsa tetap boleh mendaftar lagi; Sudah rekaman tidak (aturan lama, tidak diubah).
- Filter `eligible=1` berarti hanya umur dan dokumen (tanpa memperhatikan pendaftaran).
- Filter `registration`: `none` (belum pernah mendaftar) atau salah satu nilai `RegistrationStatus`; untuk status tidak aktif berarti siswa tanpa pendaftaran aktif yang pendaftaran terbarunya berstatus itu.
- Operator sekolah ikut melihat kolom Pendaftaran (data sekolahnya sendiri).
- Status tidak hanya lewat warna (badge bertulis); bahasa Indonesia.
- Tanpa `Co-Authored-By`; tanpa commit sampai diminta.

## Review Focus

- Siswa Tidak hadir yang kemudian mendaftar lagi: kolom dan filter memakai pendaftaran barunya, bukan yang lama.
- Siswa Tidak hadir/Ditolak yang tidak punya KK: "Belum memenuhi syarat" dengan alasan dokumen (pengecualian pendaftaran tidak menutupi syarat lain).
- Daftar 20 siswa tidak menambah query per baris.

---

### Task 1: Backend

**Files:**
- Modify: `app/Domains/Students/Models/Student.php` (`currentRegistration()`), `app/Domains/Students/Http/Controllers/StudentController.php` (`row`, `show`), `app/Domains/Students/Repositories/EloquentStudentRepository.php` (filter `eligible`, filter baru `registration`)
- Test: `tests/Feature/Students/StudentHttpTest.php`

**Interfaces:**
- Produces: `Student::currentRegistration(): ?RegistrationStudent` (relasi `registrationEntries` dan `schedule` harus termuat); `row()` memuat `registration: null | {status: string, date: 'Y-m-d'}`; `Siswa/Show` `eligibility` memakai pengecualian yang sama.

- [ ] **Step 1: Tes (RED)** di `StudentHttpTest.php`:
  - baris daftar: siswa lengkap berstatus Terdaftar → `eligible: true`, `reasons: []`, `registration.status: 'terdaftar'`, `registration.date` = tanggal sesinya; Sudah rekaman dan Tidak hadir (dengan KK dan akta) → `eligible: true`; siswa tanpa pendaftaran → `registration: null`; siswa Tidak hadir tanpa KK → `eligible: false` dan alasan dokumen;
  - siswa Tidak hadir lalu mendaftar lagi (Terdaftar): `registration.status: 'terdaftar'`;
  - detail siswa Terdaftar: `eligibility.eligible: true`;
  - ganti tes "filter eligible tidak menampilkan siswa dengan entri aktif" menjadi "filter eligible hanya menilai umur dan dokumen" (siswa berpendaftaran aktif ikut tampil);
  - filter `registration`: `none` hanya siswa tanpa pendaftaran; `terdaftar`; `tidak_hadir` hanya siswa yang pendaftaran terbarunya Tidak hadir (bukan yang sudah mendaftar lagi, bukan yang Tidak hadir lama lalu Dibatalkan baru); nilai asing diabaikan.
- [ ] **Step 2: Jalankan** `php artisan test --compact tests/Feature/Students/StudentHttpTest.php`. Harapan: GAGAL.
- [ ] **Step 3: `Student::currentRegistration()`**: dari `registrationEntries` yang sudah dimuat, urut `id` menurun, ambil yang `status->isActive()` bila ada, selain itu yang pertama.
- [ ] **Step 4: Controller**: metode privat `check(Student $s): array` = `eligibility->check($s, null, $s->currentRegistration()?->id)`; `row()` memakainya dan menambah `registration`; `show()` memakai `check()` yang sama untuk prop `eligibility`.
- [ ] **Step 5: Repository**: filter `eligible` kehilangan `whereDoesntHave` pendaftaran aktif; tambah `AllowedFilter::callback('registration', …)` sesuai Global Constraints (status tidak aktif memakai subquery `max(id)` per siswa pada `registration_students`).
- [ ] **Step 6: Jalankan ulang** perintah Step 2, lalu `php artisan test --compact` penuh, Pint, PHPStan. Harapan: lulus semua.

### Task 2: Frontend

**Files:**
- Modify: `resources/js/types/student.ts` (`StudentRow.registration`), `resources/js/Pages/Siswa/Index.vue`
- Test: `resources/js/Pages/Siswa/Siswa.test.ts`

**Interfaces:**
- Consumes: `row.registration: { status: RegistrationStatus; date: string } | null`, filter query `registration`.

- [ ] **Step 1: Tes (RED)**: kolom bertajuk "Syarat" dan "Pendaftaran"; siswa `registration: null` menampilkan "Belum mendaftar"; siswa `registration.status = 'terdaftar'` menampilkan badge "Terdaftar" dan tanggal sesinya ("20 Okt 2026"); siswa Terdaftar dengan `eligible: true` menampilkan "Memenuhi syarat"; filter tersedia dengan label "Syarat" dan "Pendaftaran", opsi Pendaftaran memuat "Belum mendaftar" dan "Tidak hadir".
- [ ] **Step 2: Jalankan** `npx vitest run resources/js/Pages/Siswa`. Harapan: GAGAL.
- [ ] **Step 3: Index.vue**: kolom `eligible` berlabel "Syarat" dan kolom baru `registration` ("Pendaftaran", lebar 208) setelah Syarat; sel Pendaftaran: `StatusBadge kind="registration"` plus `formatDate(date)` di bawahnya, atau teks "Belum mendaftar" (muted); filter "Status" menjadi "Syarat" (opsi "Memenuhi syarat", semua = "Semua"); filter baru "Pendaftaran" (nilai `registration`, opsi `none` = "Belum mendaftar" lalu sembilan status berlabel dari `registrationStatus()`), ikut `useDataTable`, `watch`, dan `hasFilters`.
- [ ] **Step 4: Jalankan ulang** perintah Step 2. Harapan: LULUS.

### Task 3: Dokumen dan verifikasi

- [ ] **Step 1:** perbarui `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` pada bagian halaman Siswa (grep `Memenuhi syarat`) dengan dua kolom dan dua filter.
- [ ] **Step 2:** `npx vitest run`, `npm run typecheck`, `npm run build`, `php artisan test --compact`, Pint, PHPStan.
- [ ] **Step 3:** review `ponytail:ponytail-review` atas diff dan detector impeccable (`detect.mjs --json`) atas `Siswa/Index.vue`; perbaiki temuan valid.
