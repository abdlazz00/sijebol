# Fase 7a — Akun Siswa, Link Sekolah, Kepemilikan Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Siswa dapat membuat akun lewat link sekolahnya, melengkapi data dan dokumen miliknya sendiri, sementara operator sekolah menjadi hanya-lihat dan admin kecamatan mendapat jalur koreksi data.

**Architecture:** Akun siswa memakai tabel `users` dengan role `siswa` (role biasa di RBAC) dan baris `students` yang terhubung lewat `students.user_id`. Aktivasi akun memakai ulang `ActivationService` (link email = verifikasi email + atur password). Isolasi antar siswa lewat global scope pemilik (`ScopedByOwner`) di samping scope sekolah yang ada. Hak akses diatur lewat permission baru di enum, diberikan ke role oleh seeder dan UI RBAC.

**Tech Stack:** Laravel + Inertia + Vue 3 + TypeScript, spatie/laravel-permission, Pest, Vitest, Larastan, Pint.

**Spec:** [`docs/superpowers/specs/2026-10-19-alur-mandiri-siswa-design.md`](../specs/2026-10-19-alur-mandiri-siswa-design.md) (bagian 3, 4.1, 4.2, 4.9, 7 sebagian, 8). Panduan UI: [`2026-10-07-sijebol-ui-guide.md`](../specs/2026-10-07-sijebol-ui-guide.md).

**Cakupan fase ini (dari spec §10, tahap 1):** akun siswa, link sekolah, kepemilikan data, role/permission, pembersihan data, operator hanya-lihat, koreksi admin (ubah data). **Di luar fase ini:** sesi dan kuota (7b), verifikasi/tiket/notifikasi sesi/scheduled command dan "buka kunci" (7c), scan QR/label KTP-el langsung/beranda dan laporan baru (7d). Pendaftaran batch dan jadwal lama tetap ada sampai 7b menggantinya; operator kehilangan hak pendaftaran di fase ini (admin tetap memilikinya).

## Mode eksekusi (pilihan user, 2026-10-19)

Kolaboratif, bukan native penuh dan bukan subagent: user ikut mengetik kode aplikasi. Pembagian peran:
- **Claude menulis:** semua file migrasi dan semua file tes (PHP Pest dan Vitest), menjalankan tes, memeriksa hasil ketikan user (membaca file, menjalankan tes/lint/typecheck), dan mereview.
- **User mengetik:** kode aplikasi (enum, model, service, controller, request, route, komponen Vue, dst.). Claude memberi kode per berkas, bertahap per task, dengan penjelasan singkat.
- **Perintah terminal** (membuat berkas, `artisan make:*`, menjalankan migrasi, dst.) selalu disebut lengkap oleh Claude; user yang menjalankannya, atau Claude yang menjalankan bila user meminta.
- Tiap task: Claude tulis tes → jalankan, tunjukkan gagal → beri kode → user ketik → Claude jalankan tes dan periksa → lanjut. Commit hanya bila user meminta.

## Penyimpangan dari spec (perlu persetujuan user)

1. **Password diatur lewat link email, bukan di form pendaftaran.** Spec §4.2 menulis langkah 1 = nama, email, password lalu verifikasi email. Rencana ini: form hanya nama + email; link aktivasi di email (memakai `ActivationService` yang sudah ada) sekaligus membuktikan kepemilikan email dan mengatur password. Hasil akhirnya sama (email terverifikasi, password terisi), kode baru lebih sedikit.
2. **Token link sekolah disimpan polos, bukan hash.** Spec §4.1 menulis "hanya hash bila memungkinkan"; operator harus dapat menyalin link berulang, jadi token harus dapat ditampilkan. Dilindungi token acak 40 karakter, throttle, dan reset oleh admin.
3. **`siswa.ubah` dipakai ulang sebagai izin koreksi staf** (spec §4.9 menyebut "permission baru"). Siswa memakai `profil.*`, sehingga izinnya sudah terpisah; tidak perlu izin ketiga.

## Hasil review rencana (ponytail + impeccable, 2026-10-19)

Diterapkan: trait `ScopedByOwner` diganti scope inline; `toUser`, hapus-permission-usang, log `activity` perintah pembersihan, throttle ganda, override channel, dan checklist kelengkapan terpisah dihapus; state "terkirim" menampilkan email + kirim ulang + ubah email; state Activate dan Login diperbaiki; alur toast aktivasi (`reflash`); halaman Saya dua langkah dengan keadaan terkunci baca-saja; perbaikan `FileUpload` (progres, galat non-validasi, pesan ukuran); NIK/NISN membuang non-angka dengan hint "bukan Nomor KK"; Bagikan via WhatsApp di halaman link; empty state dan banner sadar-peran; batas unggah server.

Ditolak/ditunda: **`students_count` tetap ada** (ponytail ingin hapus; dipertahankan karena operator memantau, label diperjelas "sudah mengisi data"); pencarian di halaman Tautan, radio jenis kelamin, penghitung digit NIK, tombol "Ambil foto", thumbnail pratinjau, menyembunyikan sidebar siswa, dan target sentuh 44 px (semua dicatat sebagai polesan 7d/UAT); **nomor kontak bantuan tidak dikarang** (menunggu saluran resmi dari user); pengecilan foto di klien menunggu keputusan user.

## Global Constraints

- Bahasa UI, pesan validasi, dan konten email: Bahasa Indonesia baku.
- Hak akses tidak boleh di-hardcode: perubahan hak lewat enum `Permission`, seeder, dan UI RBAC. Satu-satunya pengecualian adalah nama role `siswa` (konstanta `Role::SISWA`, seperti `Role::SUPER_ADMIN`) untuk menentukan scope pemilik.
- Kepemilikan data siswa: siswa hanya dapat mengakses datanya sendiri; data milik siswa lain mengembalikan 404.
- NIK 16 digit unik se-kecamatan, NISN 10 digit, dokumen PDF/JPG/PNG maksimal 5 MB di disk privat; NIK dimasker di tampilan staf dan log (aturan v1).
- Pola backend: Controller tipis → FormRequest → Service → Repository (interface + Eloquent) bila menyentuh query; semua perubahan data tercatat lewat `activity()`.
- Rute publik baru harus masuk `RouteGuardAuditTest` (`auditPublicNames`) dan memakai `throttle`.
- Tidak ada `Co-Authored-By` atau atribusi AI pada commit message (aturan CLAUDE.md mengalahkan atribusi bawaan); commit hanya setelah user memintanya, pesan commit detail.
- Kerjakan di branch baru dari HEAD saat ini (`feature/revamp-dashboard`) kecuali PR revamp sudah merge ke `main`.
- Verifikasi akhir fase: `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `npm run typecheck`, `npm run lint`, `npm test`, `npm run build`.

## Review Focus

1. Siswa A dan B satu sekolah: A tidak dapat melihat, mengunduh, mengunggah, atau menghapus data/dokumen B (404), bahkan bila A keliru diberi `siswa.lihat`. (Task 3)
2. Email sudah terdaftar atau sedang menunggu aktivasi: respons pendaftaran identik (tanpa membocorkan keberadaan akun), tidak ada baris ganda, link aktivasi diterbitkan ulang hanya untuk akun siswa yang belum aktif. (Task 4)
3. Link sekolah yang di-reset: token lama berhenti bekerja, akun siswa yang sudah ada tidak terpengaruh, operator tidak bisa mereset atau melihat link sekolah lain. (Task 5)
4. NIK koreksi admin bentrok dengan siswa lain, atau sekolah diganti saat siswa masih punya entri aktif: ditolak dengan pesan jelas, tidak ada perubahan tersimpan. (Task 7)
5. Perintah pembersihan data tanpa `--force` di mode non-interaktif tidak menghapus apa pun; akun pengguna dan log aktivitas tidak tersentuh; folder dokumen yang belum ada tidak membuatnya error. (Task 8)

---

### Task 1: Permission baru, role `siswa`, operator hanya-lihat

**Files:**
- Modify: `app/Domains/Access/Enums/Permission.php`, `resources/js/lib/permissions.ts`
- Modify: `app/Domains/Access/Models/Role.php`, `app/Models/User.php`
- Modify: `app/Domains/Access/Services/RoleService.php`
- Modify: `database/seeders/AccessSeeder.php`
- Test: `tests/Feature/Access/AccessSeederTest.php`, `tests/Feature/Access/RoleServiceTest.php`, `tests/Unit/PermissionTypescriptSyncTest.php` (sudah ada, harus tetap hijau)

**Interfaces:**
- Produces: enum `Permission` mendapat `ProfilLihat='profil.lihat'`, `ProfilUbah='profil.ubah'`, `SekolahTautan='sekolah.tautan'`, `SekolahResetTautan='sekolah.reset-tautan'`. `SiswaBuat` dan `SiswaImpor` **belum** dihapus di sini (masih dipakai rute dan banyak tes); dihapus di Task 6.
- Produces: `Role::SISWA = 'siswa'`; `Role::isSystem(): bool` (super-admin atau siswa); `User::isStudent(): bool`.

- [ ] **Step 1: Tulis tes yang gagal**
  - `AccessSeederTest`: seeder membuat 4 role (`super-admin`, `admin-kecamatan`, `operator-sekolah`, `siswa`); `siswa` terikat sekolah dan memegang tepat `profil.lihat`, `profil.ubah`, `dokumen.lihat`, `dokumen.unggah`, `dokumen.hapus`; `operator-sekolah` memegang tepat `siswa.lihat`, `jadwal.lihat`, `sekolah.tautan` dan **tidak** `dokumen.lihat`, `siswa.ubah`, `pendaftaran.buat`; `admin-kecamatan` memegang semua `Permission::values()`; menjalankan seeder dua kali tidak menggandakan.
  - `RoleServiceTest`: `RoleService::delete()` pada role `siswa` melempar `AccessRuleException`; `update()` yang mengganti nama role `siswa` melempar `AccessRuleException`, sedangkan mengubah izinnya tetap boleh.
  - `UserModelTest` (tambah): `User::isStudent()` true hanya untuk pemegang role `siswa`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="AccessSeederTest|RoleServiceTest|UserModelTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi**
  - Enum dan `permissions.ts` diperbarui bersamaan (tes `PermissionTypescriptSyncTest` menjaga sinkron): tambah empat izin baru saja.
  - `Role`: konstanta `SISWA`, `isSystem()`. `RoleService`: `delete` menolak role `isSystem()`; `update` menolak ganti nama role `siswa` (pesan: "Role siswa adalah role sistem; namanya tidak dapat diubah.").
  - `User::isStudent(): bool` memakai `hasRole(Role::SISWA)`.
  - `AccessSeeder`: tambah role `siswa` (`is_school_scoped` true, deskripsi "Siswa pendaftar"), set izin operator dan siswa seperti di Step 1. Baris permission usang di DB dibiarkan (tidak ada rute atau enum yang memakainya).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama + `php artisan test tests/Unit`; Expected: PASS. (Tes lain yang memakai `siswa.buat`/`siswa.impor` diperbaiki di Task 6.)
- [ ] **Step 5: Commit** — `feat: tambah permission profil/tautan sekolah, role siswa, dan jadikan operator hanya-lihat`

---

### Task 2: Skema dan model — token sekolah dan tautan akun siswa

**Files:**
- Create: `database/migrations/2026_10_19_100001_add_registration_token_to_schools_table.php`
- Create: `database/migrations/2026_10_19_100002_add_user_id_to_students_table.php`
- Modify: `app/Domains/Schools/Models/School.php`, `app/Domains/Schools/Repositories/Contracts/SchoolRepositoryInterface.php`, `app/Domains/Schools/Repositories/EloquentSchoolRepository.php`, `app/Domains/Schools/Services/SchoolService.php`
- Modify: `app/Domains/Students/Models/Student.php`, `app/Models/User.php`, `database/factories/StudentFactory.php`
- Test: `tests/Feature/Schools/SchoolTokenTest.php`, `tests/Feature/Students/StudentModelTest.php` (tambah)

**Interfaces:**
- Produces: kolom `schools.registration_token` (string 64, unik, wajib; sekolah lama diisi saat migrasi) dan `students.user_id` (nullable, unik, FK `users` `nullOnDelete`).
- Produces: token terisi otomatis saat `School` dibuat (`Str::random(40)`). (`School::registrationUrl()` baru dibuat di Task 5, setelah rute `daftar.show` ada di Task 4.)
- Produces: `SchoolRepositoryInterface::findByRegistrationToken(string $token): ?School`; `SchoolService::findByRegistrationToken(string $token): ?School`.
- Produces: `Student::user(): BelongsTo<User>`, `User::student(): HasOne<Student>`; `Student::$fillable` memuat `user_id`.

- [ ] **Step 1: Tulis tes yang gagal**
  - `SchoolTokenTest`: sekolah baru otomatis punya token 40 karakter dan token unik antar sekolah; `findByRegistrationToken` mengembalikan sekolah yang tepat dan `null` untuk token acak; `registrationUrl()` berakhir dengan token dan memakai path `/daftar/`.
  - `StudentModelTest`: `Student::factory()->for($user)` menyambung `user_id`; dua siswa tidak boleh memakai `user_id` yang sama (`UniqueConstraintViolationException`).
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="SchoolTokenTest|StudentModelTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi**
  - Migrasi token: tambah kolom nullable, isi tiap sekolah yang ada dengan `Str::random(40)` lewat `DB::table`, lalu jadikan non-null dan unik. `down()` menghapus kolom.
  - `School`: `booted()` mengisi `registration_token` saat `creating`; kolom ini **tidak** masuk `fillable` dan tidak masuk opsi activity log.
  - Repository: query `where('registration_token', $token)->first()` (tanpa pemeriksaan kecocokan huruf-besar-kecil khusus).
  - `StudentFactory` tetap menghasilkan `user_id` null.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: tambah token pendaftaran sekolah dan relasi akun pada siswa`

---

### Task 3: Isolasi data siswa (scope pemilik)

**Files:**
- Modify: `app/Domains/Students/Models/Student.php` (global scope `owner` di `booted()`)
- Test: `tests/Feature/Students/StudentOwnershipTest.php`

**Interfaces:**
- Consumes: `User::isStudent()` (Task 1).
- Produces: model `Student` otomatis terbatas ke baris milik akun siswa yang login, sehingga route model binding `{student}` (halaman, dokumen) mengembalikan 404 untuk milik orang lain.

- [ ] **Step 1: Tulis tes yang gagal** (helper: `makeUserWith([...izin siswa], $school, true, 'siswa')` memakai nama role `siswa`)
  - `siswa A tidak dapat membaca atau mengubah dokumen siswa B satu sekolah`: GET unduh/preview, POST unggah, DELETE hapus pada `/siswa/{B}/dokumen/kk` → 404 untuk A; untuk dokumen A sendiri → bukan 404.
  - `siswa hanya melihat barisnya sendiri walau diberi siswa.lihat`: `/siswa` untuk A (diberi `siswa.lihat` agar skenario salah-konfigurasi teruji) mengembalikan tepat 1 baris; `/siswa/{B}` → 404.
  - `staf dan operator tetap melihat semua siswa sekolahnya`: operator sekolah melihat A dan B; staf tanpa sekolah melihat semua (tes yang sudah ada di `StudentHttpTest` tetap valid).
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter=StudentOwnershipTest`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — di `Student::booted()` tambahkan `static::addGlobalScope('owner', …)` yang menambahkan `where(qualifyColumn('user_id'), Auth::id())` hanya bila `Auth::hasUser() && Auth::user()->isStudent()` (`hasUser()` mencegah rekursi, seperti `ScopedBySchool`). Dijadikan trait hanya bila 7b menambah pemakai kedua (`RegistrationStudent`).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama + `php artisan test tests/Feature/Students`; Expected: PASS (kecuali tes yang bergantung pada rute yang dihapus di Task 6).
- [ ] **Step 5: Commit** — `feat: batasi data siswa ke pemiliknya untuk akun berrole siswa`

---

### Task 4: Pendaftaran akun lewat link sekolah

**Files:**
- Create: `app/Domains/Students/Http/Controllers/StudentSignupController.php`, `app/Domains/Students/Http/Requests/StudentSignupRequest.php`, `app/Domains/Students/Services/StudentSignupService.php`
- Create: `app/Domains/Notifications/Notifications/StudentSignupNotification.php`
- Modify: `routes/students.php` (rute publik `daftar`)
- Modify: `tests/Feature/Setup/NoPublicRegistrationTest.php`, `tests/Feature/Security/RouteGuardAuditTest.php`
- Test: `tests/Feature/Students/StudentSignupTest.php`

**Interfaces:**
- Consumes: `SchoolService::findByRegistrationToken`, `ActivationService::issue(User): string`, `Role::SISWA`.
- Produces: `StudentSignupService::signup(School $school, string $name, string $email): void` — membuat user (`school_id` sekolah, role `siswa`, `activated_at` null, password null), menerbitkan token aktivasi, mengirim `StudentSignupNotification`; email dinormalisasi huruf kecil dan di-trim.
- Produces: rute `GET daftar/{token}` (`daftar.show`) dan `POST daftar/{token}` (`daftar.store`), keduanya publik; hanya `daftar.store` memakai `throttle:5,1` (GET mengikuti limiter web global).
- Produces: halaman Inertia `Daftar/Index` dengan props `valid: bool`, `school?: string`, `sent: bool`, `ttl_days: int` (dari `ActivationService::TTL_DAYS`).

- [ ] **Step 1: Tulis tes yang gagal** (`Notification::fake()`)
  - `token sah menampilkan form dan nama sekolah`; `token tidak dikenal menampilkan valid=false`.
  - `pendaftaran membuat akun siswa belum aktif di sekolah pemilik token`: user baru berrole `siswa`, `school_id` benar, `activated_at` null, `StudentSignupNotification` terkirim ke email itu, respons redirect dengan `sent=true`; **tidak** ada baris `students` (baru dibuat saat siswa menyimpan data diri, Task 7a).
  - `email yang sama dua kali (belum aktif) menerbitkan ulang link dan tidak membuat baris ganda`; token lama tidak berlaku lagi.
  - `email milik akun aktif atau non-siswa memberi respons identik dan tidak mengirim apa pun`.
  - `validasi`: nama wajib (maks 150), email wajib valid (maks 255).
  - `link aktivasi dari email mengaktifkan akun`: ikuti `/aktivasi/{token}` → set password → login (`assertAuthenticated`, `activated_at` terisi). (Pengalihan ke `/saya` diuji di Task 7a.)
  - `rute daftar dibatasi`: lebih dari 5 POST per menit → 429.
  - `akun siswa belum aktif yang memakai "Lupa password"`: setelah reset berhasil akun dapat masuk. Periksa perilaku `NewPasswordController`; bila `activated_at` tidak terisi, isi saat reset berhasil (email terbukti dimiliki) supaya siswa tidak terkunci di luar akunnya.
  - Perbarui `NoPublicRegistrationTest`: `/register` dan `/verify-email` tetap 404; tambahkan bahwa `/daftar/token-acak` tampil sebagai halaman `valid=false` (bukan 404). Tambahkan `daftar.show`, `daftar.store` ke `auditPublicNames()`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="StudentSignupTest|NoPublicRegistrationTest|RouteGuardAuditTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi**
  - `signup()`: cari user dengan email itu (tanpa scope); jika belum ada → buat + `assignRole(Role::SISWA)`; jika ada, `isStudent()` dan `activated_at === null` → pakai user itu; selain itu keluar diam-diam. Terbitkan token dan kirim notifikasi hanya pada dua kasus pertama. Controller selalu redirect ke `daftar.show` dengan flash `sent` (respons sama untuk semua kasus).
  - `StudentSignupNotification extends BaseNotification` (tanpa override channel); judul "Aktifkan akun SIJEBOL"; isi menyebut nama sekolah, masa berlaku link (`ActivationService::TTL_DAYS`, bukan angka tertulis), dan "Abaikan email ini bila Anda tidak mendaftar"; `url()` = `/aktivasi/{token}`.
  - Controller membaca `session('sent')` ke prop `sent` (pola `AuthenticatedSessionController`).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: pendaftaran akun siswa lewat link sekolah`

---

### Task 5: Link pendaftaran sekolah (operator menyalin, admin mereset)

**Files:**
- Create: `app/Domains/Schools/Http/Controllers/SchoolLinkController.php`
- Modify: `routes/access.php`, `app/Domains/Schools/Services/SchoolService.php`, `app/Domains/Access/Services/MenuService.php`
- Modify: `tests/Feature/Access/RoutePermissionMatrixTest.php`, `tests/Feature/Access/MenuTest.php`
- Test: `tests/Feature/Schools/SchoolLinkTest.php`

**Interfaces:**
- Consumes: `School::registrationUrl()`, `Permission::SekolahTautan`, `Permission::SekolahResetTautan`.
- Produces: `SchoolService::linksFor(User $actor): list<array{id: int, name: string, npsn: string, url: string, students_count: int}>` — operator mendapat sekolahnya saja, staf tanpa sekolah mendapat semua urut nama.
- Produces: `SchoolService::resetRegistrationToken(School $school, User $actor): School` — `AccessRuleException` bila `$actor->school_id` terisi dan bukan sekolah itu; mencatat `activity('akses')` `sekolah.tautan_direset`.
- Produces: rute `GET tautan-pendaftaran` (`tautan.index`, `can:sekolah.tautan`) dan `POST tautan-pendaftaran/{school}/reset` (`tautan.reset`, `can:sekolah.reset-tautan`, `throttle:20,1`); halaman `Sekolah/Tautan` dengan prop `schools` (bentuk `linksFor`).
- Produces: item menu `Pelayanan › Link pendaftaran` (`/tautan-pendaftaran`, ikon `link`, izin `sekolah.tautan`).

- [ ] **Step 1: Tulis tes yang gagal**
  - `operator hanya melihat link sekolahnya; staf melihat semua`; `url` memakai token sekolah dan `students_count` benar.
  - `reset mengganti token, token lama tidak lagi menemukan sekolah, siswa lama tetap`; tercatat di activity log.
  - `operator tidak dapat mereset sekolah lain` (diberi izin reset lewat `makeUserWith`) → pesan error flash, token tidak berubah.
  - `menu`: operator seeded melihat "Link pendaftaran"; siswa tidak.
  - Matriks izin: tambah `['GET','/tautan-pendaftaran','sekolah.tautan']`, `['POST','/tautan-pendaftaran/{school}/reset','sekolah.reset-tautan']`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="SchoolLinkTest|MenuTest|RoutePermissionMatrixTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `linksFor` memakai `withCount('students')` (scope siswa mengikuti pelaku). `resetRegistrationToken` mengganti token lewat repository `update`. Controller tipis. Tambah item menu di `MenuService::ITEMS`.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: halaman link pendaftaran sekolah dengan reset oleh admin`

---

### Task 6: Hapus alur input siswa oleh operator (tambah, impor) dan rapikan rute

**Files:**
- Delete: `app/Domains/Students/Http/Controllers/StudentImportController.php`, `app/Domains/Students/Services/StudentImportService.php`, `app/Domains/Students/Jobs/ImportStudentsJob.php`, `app/Domains/Students/Models/StudentImport.php`, `app/Domains/Students/Exports/StudentTemplateExport.php`, `app/Domains/Notifications/Notifications/StudentImportFinishedNotification.php`
- Delete: `tests/Feature/Students/StudentImportTest.php`, `tests/Feature/Students/StudentImportQueueTest.php`
- Create: `database/migrations/2026_10_19_100003_drop_student_imports_table.php`
- Modify: `routes/students.php`, `app/Domains/Students/Http/Controllers/StudentController.php`, `app/Domains/Students/Services/StudentService.php`, `app/Http/Middleware/HandleInertiaRequests.php` (hapus flash `import`), `app/Domains/Reports/Services/DashboardService.php` (hapus aksi "Tambah Siswa"), `lang/id/activity.php` (hapus `siswa.diimpor`; `lang/id/actions.php` hanya bila `grep` menunjukkan kunci impor tidak dipakai lagi), `docs/operasional-queue.md`, `docs/deployment-guide.md` (hapus bagian impor)
- Modify: `app/Domains/Access/Enums/Permission.php` dan `resources/js/lib/permissions.ts` (hapus `SiswaBuat`, `SiswaImpor` — baru di task ini karena Task 1–5 masih bergantung padanya)
- Modify (hapus rujukan `siswa.buat`/`siswa.impor`): `tests/Feature/Access/RoutePermissionMatrixTest.php` (juga `StudentImport` di `resolveUri`), `tests/Feature/Access/RoleServiceTest.php` dan `RoleHttpTest.php` (ganti dengan izin lain di luar milik penyunting, mis. `siswa.hapus`), `tests/Feature/Security/SecurityHeadersTest.php` (uji unduhan dipindah ke rute unduhan lain yang masih ada, mis. `laporan.ekspor`), `tests/Feature/Reports/DashboardSummaryTest.php`, `tests/Feature/Students/StudentHttpTest.php`, `tests/Feature/Students/StudentLockTest.php`, `tests/Feature/Performance/ListQueryCountTest.php` bila memakai impor

**Interfaces:**
- Produces: rute `siswa.create`, `siswa.store`, `siswa.impor.*` tidak ada lagi. Rute siswa yang tersisa: `index`, `show`, `edit`, `update`, `destroy`, dan dokumen.
- Produces: `StudentService::create()` dihapus; `StudentController` tanpa `create/store` dan tanpa dependensi impor.

- [ ] **Step 1: Tulis/ubah tes**
  - `StudentHttpTest`: hapus tes `operator menambah siswa…`, `staf tanpa sekolah wajib memilih sekolah`, `sekolah tidak berubah saat ubah`, `perubahan siswa tercatat…`, `hapus siswa memerlukan izin…`, dan `operator tidak mendapat petunjuk…` (yang tentang ubah/hapus ditulis ulang di Task 7b). Sisanya (daftar memasker NIK dan menyaring, detail, pencarian NIK persis, 404 antar sekolah, filter eligible) tetap, dengan `operatorSekolah()` dipersempit ke izin `['siswa.lihat']`. Tambah: `GET /siswa/buat` → 404 (tertangkap rute `siswa/{student}` bernilai non-angka tetap 404), `POST /siswa` → 405, `POST /siswa/impor` → 404.
  - `StudentLockTest`: dihapus di task ini; versi baru untuk siswa pemilik ditulis di Task 7a.
  - `RoutePermissionMatrixTest`: hapus 5 baris rute yang hilang dan `StudentImport` dari `resolveUri`.
- [ ] **Step 2: Jalankan** — `php artisan test`; Expected: FAIL pada tes yang masih memakai rute/permission lama (diperbaiki di langkah berikut).
- [ ] **Step 3: Implementasi** — hapus berkas di daftar Delete; migrasi `drop_student_imports_table` (`Schema::dropIfExists`, `down()` membuat ulang struktur dari migrasi `2026_10_09_500001`); bersihkan route, controller, service, shared props, dan salinan bahasa; `grep -rn "impor\|Import" app resources/js lang docs --include=...` untuk memastikan tidak ada rujukan tersisa (kecuali `RecapExport`/`excel.php` yang dipakai laporan).
- [ ] **Step 4: Jalankan, pastikan lulus** — `php artisan test` dan `vendor/bin/phpstan analyse`; Expected: PASS.
- [ ] **Step 5: Commit** — `refactor: hapus input siswa dan impor Excel oleh operator`

---

### Task 7a: Data diri siswa (halaman `/saya`)

**Files:**
- Create: `app/Domains/Students/Http/Controllers/SelfStudentController.php`, `app/Domains/Students/Http/Requests/SelfStudentRequest.php`
- Modify: `routes/students.php`, `app/Domains/Students/Services/StudentService.php`, `app/Domains/Access/Services/MenuService.php`
- Modify: `app/Http/Controllers/DashboardController.php`, `app/Domains/Students/Services/EligibilityService.php`, `app/Domains/Students/Support/StudentRules.php`
- Test: `tests/Feature/Students/EligibilityServiceTest.php`, `tests/Feature/Students/StudentRulesTest.php` (perbarui teks), `tests/Feature/Students/SelfStudentTest.php`, `tests/Feature/Students/StudentLockTest.php` (ditulis ulang), `tests/Feature/Access/RoutePermissionMatrixTest.php`, `tests/Feature/Access/MenuTest.php`

**Interfaces:**
- Consumes: `StudentRules::fields(?int $ignoreId)` dan `messages()`, `EligibilityService::check`, `StudentDocumentService::rows`, `Student::isLocked()`.
- Produces: `StudentService::saveOwn(User $actor, array $data): Student` — membuat `Student` (`school_id` = `$actor->school_id`, `user_id` = `$actor->id`) bila belum ada, selain itu memperbarui lewat `update()` (menghormati kunci). Mencatat activity.
- Produces: rute `GET saya` (`saya.show`, `can:profil.lihat`) dan `PUT saya` (`saya.save`, `can:profil.ubah`, `throttle:60,1`); keduanya `abort(404)` bila pelaku bukan `isStudent()`.
- Produces: halaman `Saya/Index` dengan props `student: {id, nik, nisn, name, birth_date, gender}|null`, `school: string`, `account_name: string` (nama saat daftar, dipakai mengisi awal form), `documents` (bentuk `StudentDocumentService::rows`), `eligibility: {eligible, reasons}`, `locked: bool`.
- Produces: item menu `Pelayanan › Data saya` (`/saya`, ikon `graduation-cap`, izin `profil.lihat`).

- [ ] **Step 1: Tulis tes yang gagal**
  - `siswa baru melihat form kosong`: `student` null, `school` nama sekolahnya, `documents` kosong, `eligibility.eligible` false.
  - `menyimpan data pertama membuat baris siswa terhubung ke akun dan sekolah`: setelah `PUT /saya` valid, `Student` ada dengan `user_id`, `school_id` sekolah akun (nilai `school_id` kiriman klien diabaikan).
  - `menyimpan ulang memperbarui baris yang sama`, `NIK milik siswa lain ditolak (nik.unique)`, `NIK sendiri tidak dianggap bentrok`, validasi NIK/NISN/tanggal lahir mengikuti `StudentRules`.
  - `data terkunci ditolak` (`StudentLockTest` baru, pelaku siswa pemilik): dengan entri `Diajukan` atau `Terverifikasi` (helper `makeEntry`) → `PUT /saya`, unggah, dan hapus dokumen menghasilkan flash `error` `Student::LOCKED_MESSAGE` dan data tidak berubah; entri `Draf` atau `PerluPerbaikan` diizinkan.
  - `bukan siswa → 404`: staf dengan `profil.lihat` yang bukan berrole `siswa` mendapat 404.
  - Menu: siswa seeded melihat "Data saya".
  - Matriks izin: `['GET','/saya','profil.lihat']`, `['PUT','/saya','profil.ubah']`.
  - `siswa yang login ke /dashboard dialihkan ke /saya`; setelah aktivasi akun (alur Task 4) siswa mendarat di `/saya` dengan flash `success` ("Akun aktif. Selamat datang.") masih terbawa.
  - `alasan kelayakan memakai sudut pandang "Anda"/netral`: `EligibilityService::check` untuk siswa yang sudah punya entri aktif menghasilkan "Sudah terdaftar di pendaftaran lain." (bukan "Siswa sudah …"); perbarui `EligibilityServiceTest` yang membandingkan teks lama.
  - `pesan nik.unique`: "NIK ini sudah terdaftar pada akun lain. Bila menurut Anda keliru, hubungi operator sekolah atau admin kecamatan." (perbarui `StudentRulesTest`; trade-off keunikan NIK se-kecamatan tetap).
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="SelfStudentTest|StudentLockTest|MenuTest|RoutePermissionMatrixTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `DashboardController::__invoke` memanggil `session()->reflash()` lalu mengembalikan `redirect()->route('saya.show')` bila `$request->user()->isStudent()` (return type menjadi `Response|RedirectResponse`; `reflash` agar toast aktivasi tidak hilang di pengalihan perantara). Ubah teks di `EligibilityService` dan `StudentRules::messages()['nik.unique']` sesuai tes. `SelfStudentRequest::rules()` = `StudentRules::fields($this->user()->student?->id)`, tanpa `school_id`; pesan dari `StudentRules::messages()`. Controller `show` memuat `student` (NIK **tidak** dimasker, milik sendiri) dan memanggil `EligibilityService::check` bila `student` ada. Dokumen memakai rute `siswa.dokumen.*` yang sudah ada (izin `dokumen.*` + scope pemilik).
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: halaman data diri dan dokumen siswa (/saya)`

---

### Task 7b: Koreksi data oleh admin

**Files:**
- Rename: `app/Domains/Students/Http/Requests/StudentRequest.php` → `StudentCorrectionRequest.php`
- Create: `app/Domains/Notifications/Notifications/StudentCorrectedNotification.php`
- Modify: `app/Domains/Students/Http/Controllers/StudentController.php`, `app/Domains/Students/Services/StudentService.php`
- Test: `tests/Feature/Students/StudentCorrectionTest.php`

**Interfaces:**
- Consumes: `Permission::SiswaUbah` (kini bermakna koreksi staf), `Student::registrationEntries`, `RegistrationStatus::active()`.
- Produces: `StudentService::correct(Student $student, array $data, string $note, User $actor): Student` — mengabaikan kunci data; mengizinkan `school_id` diganti hanya bila pelaku tanpa sekolah; menolak (`AccessRuleException`) bila sekolah diganti dan siswa punya entri aktif; mencatat `activity('siswa')` `siswa.dikoreksi` dengan properti `catatan`; mengirim `$student->user?->notify(new StudentCorrectedNotification($note))`.
- Produces: `StudentCorrectionRequest` = `StudentRules::fields($student->id)` + `school_id` (`nullable|exists:schools,id`, hanya dipertimbangkan untuk pelaku tanpa sekolah) + `note` (`required|string|min:5|max:500`, pesan "Catatan koreksi wajib diisi (minimal 5 karakter).").
- Produces: `PUT /siswa/{student}` (`siswa.update`) memanggil `correct`; `GET /siswa/{student}/ubah` menyertakan `schools` (daftar sekolah untuk staf, `null` untuk pelaku bersekolah).

- [ ] **Step 1: Tulis tes yang gagal**
  - `admin mengoreksi NIK/nama walau data terkunci`: dengan entri `Diajukan`, `PUT` valid + `note` → berhasil, data berubah, activity `siswa.dikoreksi` memuat catatan, siswa menerima notifikasi (`Notification::fake`).
  - `catatan wajib`: tanpa `note` → `assertSessionHasErrors('note')`.
  - `NIK bentrok dengan siswa lain ditolak`; tidak ada perubahan tersimpan.
  - `ganti sekolah`: tanpa entri aktif → berhasil; dengan entri `Diajukan` → flash `error`, `school_id` tetap.
  - `operator bersekolah tidak dapat memindah sekolah`: `school_id` kiriman diabaikan.
  - `operator tanpa siswa.ubah → 403` (sudah dicakup matriks izin).
  - `NIK pada log tetap termasking` (pertahankan tes yang ada di `StudentHttpTest`, dipindah ke berkas ini bila perlu).
  - Pulihkan tes `hapus siswa` (`DELETE /siswa/{id}` izin `siswa.hapus`, ditolak bila punya entri) dan `StudentLockTest` bagian "siswa dengan entri apa pun tidak dapat dihapus".
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter="StudentCorrectionTest|StudentHttpTest|StudentLockTest"`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — `update()` lama (menghormati kunci) tetap dipakai `saveOwn`; `correct()` memanggil repository `update` langsung. Notifikasi: judul "Data Anda dikoreksi admin", isi memuat catatan, `url()` `/saya`.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama + `php artisan test`; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: koreksi data siswa oleh admin dengan catatan dan notifikasi`

---

### Task 8: Perintah pembersihan data siswa

**Files:**
- Create: `app/Console/Commands/ResetStudentData.php`
- Test: `tests/Feature/Console/ResetStudentDataTest.php`
- Modify: `docs/deployment-guide.md` (catat langkah sekali jalan)

**Interfaces:**
- Produces: perintah `sijebol:bersihkan-data-siswa {--force}` — menghapus (urut FK) `registration_status_histories`, `registration_students`, `registrations`, `schedules`, `student_documents`, `students`, dan folder disk `local:students`; tidak menyentuh `users`, `roles`, `schools`, `activity_log`, `notifications`. Tanpa `--force` meminta konfirmasi (default tidak).

- [ ] **Step 1: Tulis tes yang gagal**
  - `tanpa konfirmasi tidak menghapus apa pun`: `artisan(...)->expectsConfirmation(..., 'no')` → data utuh.
  - `dengan --force menghapus data siswa, pendaftaran, jadwal, dan berkas, tetapi akun, sekolah, dan log tetap` (siapkan data lewat factory/`makeScheduledEntry`, `Storage::fake('local')` dengan berkas di `students/1/kk.pdf`).
  - `folder dokumen belum ada tidak membuat error`.
- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test --filter=ResetStudentDataTest`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — satu `DB::transaction` dengan `DB::table(...)->delete()` per tabel, lalu `Storage::disk('local')->deleteDirectory('students')`. Output konsol berisi jumlah baris terhapus per tabel.
- [ ] **Step 4: Jalankan, pastikan lulus** — filter yang sama; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: perintah pembersihan data siswa untuk memulai dari kosong`

> Menjalankan perintah ini pada database lokal dilakukan **manual oleh user** setelah fase ini selesai dan disetujui; bukan bagian eksekusi rencana.

---

### Task 9: Frontend — pendaftaran akun, data diri, link sekolah

**Files:**
- Create: `resources/js/Pages/Daftar/Index.vue`, `resources/js/Pages/Saya/Index.vue`, `resources/js/Pages/Sekolah/Tautan.vue`
- Modify: `resources/js/types/student.ts` (tambah `SelfStudent`; hapus `ImportStatus`), `resources/js/types/access.ts` (tambah `SchoolLink`), `resources/js/config/menuIcons.ts` (+ikon `link` dari `Link2`), `resources/js/config/menuIcons.test.ts`
- Modify: `resources/js/Pages/Auth/Activate.vue` (state tidak berlaku, lihat bawah), `resources/js/Pages/Auth/Login.vue` (sub-judul: "Masuk dengan akun SIJEBOL Anda." — kalimat "akun yang diberikan admin" tidak lagi benar), `resources/js/Components/shared/FileUpload.vue`
- Create: `resources/js/lib/imageResize.ts`
- Test: `resources/js/Pages/Saya/Saya.test.ts`, `resources/js/Pages/Daftar/Daftar.test.ts`, `resources/js/lib/imageResize.test.ts`

**Interfaces:**
- Produces: `fitWithin(width: number, height: number, maxSide: number): {width: number; height: number}` (murni, sisi terpanjang ≤ `maxSide`, rasio tetap, tidak membesarkan) dan `shrinkImage(file: File, maxSide = 2400, quality = 0.85): Promise<File>` — hanya untuk `image/jpeg|png`: bila berkas > 1 MB atau sisinya > `maxSide`, digambar ulang lewat canvas dan dikembalikan sebagai JPEG baru (nama sama, ekstensi `.jpg`); PDF, berkas kecil, atau kegagalan decode mengembalikan berkas asli apa adanya.
- Consumes: props dari Task 4, 5, 7a; komponen `AuthLayout`, `AppLayout`, `FormField`, `Input`, `SelectField`, `FileUpload`, `DocumentPreviewDialog`, `CopyField`, `ConfirmDialog`, `InlineAlert`, `PageHeader`, `InfoList`, `focusFirstInvalid`, `lib/whatsapp.ts` (pola tautan `wa.me` yang sudah ada).
- Produces: halaman dengan perilaku:
  - `Daftar/Index` (`AuthLayout`): tiga keadaan.
    - *Token tidak berlaku*: ikon jam, "Link pendaftaran tidak berlaku. Minta link terbaru ke operator sekolah Anda."
    - *Form*: judul "Buat akun SIJEBOL" (bukan "Daftar", agar tidak tertukar dengan pendaftaran sesi), nama sekolah dari token, satu kalimat tujuan ("Data Anda dipakai Kecamatan Sungai Beduk untuk perekaman KTP-el pelajar."), nama lengkap (`autocomplete="name"`, hint "Sesuai Kartu Keluarga"), email (`type="email" inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false"`), tombol "Buat akun"; validasi klien memakai pesan server.
    - *Terkirim*: menampilkan alamat yang diketik ("Jika alamat **budi@gmail.com** dapat didaftarkan, link aktivasi sudah dikirim. Buka email, lalu ketuk **Aktifkan akun**. Berlaku {TTL} hari. Tidak ada di Kotak Masuk? Cek folder Spam atau Promosi."; teks sama untuk semua kasus). Tombol "Salah email? Ubah" kembali ke form dengan nilai terisi dan tombol "Kirim ulang" yang nonaktif 60 detik dengan hitungan mundur terbaca pembaca layar. Alamat disimpan di `sessionStorage` agar tidak hilang saat siswa berpindah ke aplikasi email dan kembali; nilai TTL dikirim dari server sebagai prop `ttl_days`.
    - Tanpa tautan daftar di halaman Login.
  - `Auth/Activate` state tidak berlaku: judul "Link aktivasi tidak berlaku"; satu kalimat untuk kedaluwarsa dan sudah dipakai sekaligus; dua aksi yang dapat ditindaklanjuti: "Masuk" (bila link sudah dipakai) dan teks "Belum bisa masuk? Buka lagi link pendaftaran dari sekolah Anda dan buat akun dengan email yang sama untuk menerima link baru."; tanpa nomor kontak karangan (belum ada saluran resmi).
  - `Saya/Index` (`AppLayout`): dua kartu bernomor, "Langkah 1 dari 2: Data diri" dan "Langkah 2 dari 2: Dokumen".
    - *Data diri*: NIK, NISN, nama, tanggal lahir, jenis kelamin; maks 720 px; submit `PUT saya.save` dengan tombol "Simpan data" (lebar penuh di mobile). NIK dan NISN membuang karakter non-angka saat diketik/ditempel (`inputmode="numeric" pattern="[0-9]*"`), hint NIK "NIK siswa, 16 digit — bukan Nomor KK. Ada di tabel anggota keluarga pada Kartu Keluarga.", hint NISN "10 digit — bukan NIS sekolah." Kolom Sekolah baca-saja.
    - *Dokumen*: `FileUpload` ×2, kartu selalu terlihat; sebelum data tersimpan keduanya nonaktif dengan alasan "Simpan data diri di Langkah 1 agar bisa mengunggah dokumen." Setelah simpan pertama berhasil: toast "Data disimpan. Sekarang unggah dokumen." dan fokus pindah ke kartu Dokumen.
    - *Kelengkapan*: bila `eligibility.reasons` tidak kosong tampilkan sebagai daftar bantu (satu sumber, tanpa checklist terpisah); bila lengkap, kartu sukses "Data dan dokumen Anda sudah lengkap." tanpa menjanjikan jadwal (belum ada di fase ini).
    - *Terkunci* (`locked`): data tampil sebagai `InfoList` baca-saja dan `FileUpload` tanpa Ganti/Hapus; banner `warning` dengan ikon: "Data Anda sedang diperiksa petugas dan tidak dapat diubah. Bila ada yang salah, petugas akan meminta perbaikan; atau hubungi operator sekolah / admin kecamatan."
  - `FileUpload` (komponen bersama, dipakai juga halaman staf): gambar JPG/PNG dikecilkan otomatis lewat `shrinkImage` sebelum validasi ukuran (siswa tidak perlu memilih resolusi); PDF lebih dari 5 MB tetap ditolak dengan pesan ukuran. Bilah progres tinggi 8 px dengan teks terlihat "Mengunggah 43%… jangan tutup halaman"; galat non-validasi (413, jaringan putus) tampil di slot yang sama sebagai "Gagal mengunggah. Periksa sinyal, lalu coba lagi." dengan tombol **Coba lagi** yang mengunggah ulang berkas yang sama (bukan banner global "Gagal memuat data"); galat ukuran menyebut ukuran berkas ("Foto 7,2 MB terlalu besar (maks 5 MB). Ambil ulang dengan resolusi lebih rendah, atau pilih PDF."); jarak antar-aksi di baris dokumen terunggah ≥ 8 px; prop `hide-download` menyembunyikan "Unduh" untuk siswa; teks konfirmasi hapus: "Hapus {label}? Anda perlu mengunggah ulang agar data lengkap."
  - `Sekolah/Tautan` (`AppLayout`): per sekolah satu kartu (`SectionCard`) berisi `CopyField` dengan `url`, tombol "Bagikan via WhatsApp" (memakai `lib/whatsapp.ts`; pesan siap kirim memuat nama sekolah dan link), dan teks "{N} siswa sudah mengisi data" (akun yang belum menyimpan data memang belum terhitung). Tombol "Reset link" (hanya bila `can(SekolahResetTautan)`) dipisah dari "Salin link", bergaya outline/teks `danger`, dengan dialog: judul "Reset link {nama sekolah}?", isi "Link yang sudah dibagikan di grup tidak berlaku lagi; bagikan link baru. Siswa yang sudah mendaftar tidak terpengaruh." (fokus awal pada Batal); setelah sukses, toast dan link baru langsung tampil. Empty state bila tidak ada sekolah.

- [ ] **Step 1: Tulis tes Vitest yang gagal**
  - `Daftar.test.ts`: dengan `valid=false` form tidak dirender dan pesan "tidak berlaku" tampil; dengan `sent=true` alamat email yang diketik tampil di pesan, form tidak tampil, "Kirim ulang" nonaktif lalu aktif setelah hitung mundur (fake timers), dan "Salah email? Ubah" mengembalikan form dengan email terisi; submit dengan email tidak valid menampilkan pesan dan tidak memanggil `router.post`.
  - `Saya.test.ts`: `student=null` → kedua `FileUpload` nonaktif dengan alasan terlihat; mengetik/menempel "3201 1234 1234 1234" ke NIK menghasilkan `3201123412341234`; `locked=true` → data tampil sebagai `InfoList`, tombol Simpan tidak ada, banner tampil; `eligibility.reasons` kosong → kartu "sudah lengkap" tampil.
  - `imageResize.test.ts`: `fitWithin(4000, 3000, 2400)` → `{2400, 1800}`; `fitWithin(3000, 4000, 2400)` → `{1800, 2400}`; `fitWithin(800, 600, 2400)` → tidak berubah; `shrinkImage` mengembalikan berkas yang sama persis untuk PDF dan untuk gambar < 1 MB berdimensi kecil.
  - `FileUpload` (uji komponen): PDF 7,2 MB menampilkan pesan ukuran dengan angka; kegagalan non-validasi menampilkan "Coba lagi" yang memanggil unggah dengan berkas yang sama.
  - `menuIcons.test.ts`: kunci `link` terpetakan (bukan ikon fallback `Circle`).
- [ ] **Step 2: Jalankan, pastikan gagal** — `npm test -- Saya Daftar menuIcons`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — ikuti pola halaman yang ada (`Siswa/Form.vue` untuk validasi klien + `focusFirstInvalid`, `Auth/Activate.vue` untuk layout auth, `Sekolah/Index.vue` untuk header). Nilai teks dan token desain dari panduan UI (§2–§5); tinggi kontrol 40 px, target sentuh ≥ 40 px, label terhubung ke input, status tidak hanya lewat warna.
- [ ] **Step 4: Jalankan, pastikan lulus** — `npm test`, `npm run typecheck`, `npm run lint`; Expected: PASS.
- [ ] **Step 5: Commit** — `feat: halaman pendaftaran akun, data diri siswa, dan link sekolah`

---

### Task 10: Frontend — halaman Siswa untuk staf dan operator

**Files:**
- Modify: `resources/js/Pages/Siswa/Index.vue`, `resources/js/Pages/Siswa/Show.vue`, `resources/js/Pages/Siswa/Form.vue`
- Delete: `resources/js/Components/shared/ImportStudentsDialog.vue`, `resources/js/composables/useImportStatus.ts`, `resources/js/composables/useImportStatus.test.ts`
- Modify: `resources/js/Pages/datatable-label.test.ts` bila merujuk dialog impor
- Test: `resources/js/Pages/Siswa/Siswa.test.ts`

**Interfaces:**
- Consumes: props `Siswa/Index` tanpa `recent_import`; `Siswa/Form` dengan `student` selalu terisi, `schools: {id, name}[] | null`, dan field `note`.
- Produces:
  - `Siswa/Index`: tanpa tombol/dialog Impor dan "Tambah siswa"; empty state "Belum ada siswa yang mengisi data dari sekolah ini." dengan satu aksi "Lihat link pendaftaran" (ke `tautan.index`, hanya bila `can(SekolahTautan)`; panduan UI §3 mensyaratkan satu aksi bila berizin).
  - `Siswa/Show`: bagian Dokumen tampil hanya bila `can(DokumenLihat)`; tombol "Koreksi data" (ganti "Ubah") tampil bila `can(SiswaUbah)` **tanpa** syarat `!locked`; banner kunci sadar-peran — pemegang `SiswaUbah`: "Terkunci untuk siswa. Anda tetap dapat mengoreksi; siswa akan diberi tahu."; selain itu: "Data tidak dapat diubah selama pendaftaran diproses."
  - `Siswa/Form`: mode koreksi saja — judul "Koreksi data siswa", semua field (NIK/NISN berperilaku sama seperti di `Saya/Index`) + (untuk staf) pilihan Sekolah dengan hint "Pindah sekolah ditolak bila siswa masih punya pendaftaran aktif." + `Catatan koreksi` (textarea wajib, hint "Alasan koreksi; dikirim ke siswa dan dicatat di log."); submit `PUT siswa.update`.

- [ ] **Step 1: Tulis tes Vitest yang gagal** (`Siswa.test.ts`): `Show` tanpa `DokumenLihat` tidak merender `FileUpload`; tombol "Koreksi data" tampil untuk pemegang `SiswaUbah` walau `locked=true` dan banner kunci memakai teks yang sesuai peran; `Index` kosong menampilkan aksi "Lihat link pendaftaran" hanya bagi pemegang `SekolahTautan`; `Form` memblokir submit saat catatan kosong dan menampilkan pesan.
- [ ] **Step 2: Jalankan, pastikan gagal** — `npm test -- Siswa`; Expected: FAIL.
- [ ] **Step 3: Implementasi** — hapus kode impor dan percabangan mode tambah; bersihkan impor yang tak terpakai.
- [ ] **Step 4: Jalankan, pastikan lulus** — `npm test`, `npm run typecheck`, `npm run lint`, `npm run build`; Expected: PASS.
- [ ] **Step 5: Commit** — `refactor: sesuaikan halaman siswa untuk operator hanya-lihat dan koreksi admin`

---

### Task 11: Verifikasi akhir fase dan pemeriksaan manual

**Files:** — (tidak ada perubahan kode kecuali perbaikan temuan)

- [ ] **Step 1: Jalankan seluruh verifikasi** — `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `npm run typecheck`, `npm run lint`, `npm test`, `npm run build`; Expected: semuanya lulus.
- [ ] **Step 1b: Periksa batas unggah server** — `upload_max_filesize` dan `post_max_size` PHP serta `client_max_body_size` nginx minimal 6 MB (aturan aplikasi menyatakan 5 MB sah); catat di `docs/deployment-guide.md`.
- [ ] **Step 2: Periksa alur di browser** (server dev + queue worker; email `MAIL_MAILER=log`): admin membuka `/tautan-pendaftaran` dan menyalin link sekolah → buka link di jendela privat → daftar → ambil link aktivasi dari log → atur password → masuk ke `/saya` → simpan data → unggah foto KK dan akta langsung dari kamera HP (ukuran asli > 5 MB harus lolos setelah dikecilkan) → operator sekolah login: melihat siswa tanpa dokumen dan tanpa tombol tambah/impor, menyalin link → admin mengoreksi NIK siswa dengan catatan → siswa menerima notifikasi di lonceng.
- [ ] **Step 3: Review wajib** — `ponytail:ponytail-review` untuk seluruh perubahan dan `impeccable` untuk perubahan frontend (kualitas UI/UX, aksesibilitas, konsistensi panduan UI); perbaiki temuan valid, jalankan ulang Step 1 (`superpowers:verification-before-completion`).
- [ ] **Step 4: Catat ke memori dan serahkan** — perbarui `deferred-items` (tahap 7b–7d, "buka kunci" ke 7c, permintaan menjalankan `sijebol:bersihkan-data-siswa` di DB lokal); laporkan ke user, dan jalankan perintah pembersihan hanya bila user memintanya.
