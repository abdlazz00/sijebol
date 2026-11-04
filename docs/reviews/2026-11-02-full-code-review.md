# Full Code Review SIJEBOL — 2 November 2026

Status: **hanya temuan, belum ada yang diperbaiki.** Setiap temuan menunggu pembahasan satu per satu.
Basis: branch `staging` @ `b085691`.

## 1. Cakupan dan batasan

**Dibaca penuh, baris demi baris:** semua file rute; middleware (`EnsureAccountIsActive`, `SecurityHeaders`, `HandleInertiaRequests`); `bootstrap/app.php`; `AppServiceProvider`; seluruh alur auth (login, reset, ganti password); modul Access (`ActivationService`, `UserService`, `RoleService`, `AccessGuard`, `UserController`, `RoleController`, `UserRequest`, `RoleRequest`); modul Students (pendaftaran akun, `StudentService`, `StudentDocumentService`, `StudentController`, `SelfStudentController`); modul Registrations (semua service dan controller); modul Schedules (`ScheduleService`, `RecordingService`, `ScanService`, `ScheduleRequest`); `SchoolService` dan model `School`; semua command artisan dan scheduler; Dockerfile, nginx, supervisor, compose, skrip deploy, contoh `.env`; `AccessSeeder`; migrasi konversi pendaftaran.

**Dipindai dengan pencarian pola, tidak dibaca seluruhnya:** modul Reports dan `DashboardService`, repository, kelas Notification, model lain, dan sekitar 295 file frontend (dicari `v-html`, `innerHTML`, `eval`, `window.open`, penyimpanan klien, `target=_blank`).

**Alat otomatis:** `composer audit`, `npm audit`, ESLint, `vue-tsc`, Prettier, PHPStan, Pint, dan pindai rahasia pada file yang dilacak git.

**Tidak dilakukan:** uji penetrasi dinamis, uji beban, pengecekan di browser, dan pemeriksaan lingkungan UAT/produksi yang sebenarnya. Temuan yang bergantung pada kondisi server ditandai **(perlu dikonfirmasi)**.

**Hasil alat otomatis:** PHPStan 0 galat, Pint bersih, `vue-tsc` bersih, ESLint 0 galat (333 peringatan), Pest 749/749 lulus, Vitest 269/269 lulus (terakhir dijalankan sebelum penggantian nama menu; setelahnya hanya subset yang dijalankan ulang).

## 2. Ringkasan

| Tingkat | Jumlah | ID |
|---|---|---|
| Tinggi | 2 | F-01, F-02 |
| Sedang | 9 | F-03 s.d. F-11 |
| Rendah | 19 | F-12 s.d. F-30 |

Tidak ditemukan: SQL injection (semua `selectRaw`/`orderByRaw` memakai binding atau kolom tetap), XSS (tanpa `v-html`/`innerHTML`), eksekusi perintah dari input, mass assignment dari input mentah, atau rahasia yang ter-commit.

**Satu hal yang perlu segera disadari:** F-01 berkaitan dengan instruksi rilis yang saya tulis sendiri (menjalankan `AccessSeeder` di UAT/produksi). Instruksi itu punya efek samping yang tidak saya sebut.

---

## 3. Tinggi

### F-01 — `AccessSeeder` menimpa izin role yang sudah diubah lewat menu Role
- **Lokasi:** `database/seeders/AccessSeeder.php:43` (`$role->syncPermissions(...)`) dan `:39-42` (`updateOrCreate` menimpa `description` serta `is_school_scoped`).
- **Masalah:** Setiap kali seeder dijalankan, seluruh izin `admin-kecamatan`, `operator-sekolah`, dan `siswa` dikembalikan ke daftar bawaan di kode. Perubahan yang dibuat admin lewat menu **Role & Permission** hilang tanpa peringatan.
- **Dampak:** Silent privilege reset. Izin yang sengaja dicabut client akan kembali (eskalasi), dan izin yang ditambahkan akan hilang (fitur mendadak 403). Instruksi rilis di `docs/deployment-guide.md` bagian 14 dan 15 serta saran saya sebelumnya menyuruh menjalankan seeder ini di UAT/produksi.
- **Saran:** Jadikan penambahan izin baru bersifat aditif: buat izin bila belum ada, dan hanya `givePermissionTo` ke role bawaan bila izin itu baru dibuat. `syncPermissions` hanya untuk instalasi pertama. Alternatifnya, pisahkan jadi perintah `sijebol:sinkron-izin`.
- **Status:** Terbukti dari kode. Belum dicek apakah role di UAT/produksi sudah diubah.
- **Keputusan (2 Nov 2026):** seeder dibuat aditif. **Sudah dikerjakan, belum di-commit.**
  - Role dibuat bila belum ada dan tidak ditimpa; hanya izin yang baru dibuat diberikan ke role bawaan; `super-admin` selalu sinkron penuh.
  - Tambahan dari builder: izin yang sudah tidak ada di kode dibersihkan, karena rilis lama memakai `syncPermissions` untuk mencabut izin (mis. `pendaftaran.ajukan`).
  - Konsekuensi yang diterima: mengurangi izin bawaan di kode tidak otomatis berlaku di server yang berjalan; lakukan lewat menu Role atau migrasi data.
  - Verifikasi: tujuh tes baru (empat gagal sebelum perbaikan), isi role di DB lokal identik sebelum dan sesudah run. Dokumen: `deployment-guide.md` bagian 16, spec dan rencana `2026-11-02-seeder-aditif`.

### F-02 — `AUTO_MIGRATE=true` di compose produksi dan UAT, tanpa cadangan atau mode pemeliharaan
- **Lokasi:** `docker-compose.prod.yml:13`, `docker-compose.uat.yml`, `docker/entrypoint.sh:22-25`, `scripts/deploy-prod.sh`.
- **Masalah:** Setiap `docker compose up -d --build` menjalankan `php artisan migrate --force` saat kontainer mulai, tanpa langkah cadangan DB dan tanpa `artisan down`.
- **Dampak:** Rilis produksi pertama akan menjalankan semua migrasi otomatis. Migrasi `2026_10_20_100001_convert_registrations_to_session_registrations` menjatuhkan tabel `registrations`. Migrasi itu punya pengaman (melempar galat bila masih ada data lama), tetapi bila terpicu, kontainer **crash-loop** (`restart: unless-stopped` + `set -e`) dan situs tidak naik. Tidak ada jalan mundur (`down()` melempar galat).
- **Saran:** Matikan `AUTO_MIGRATE` di produksi. Migrasi dijalankan manual setelah `mysqldump`, atau skrip deploy diubah: cadangan, lalu `down`, lalu `migrate`, lalu `up`. Untuk UAT boleh tetap otomatis.
- **Status:** Terbukti dari kode.
- **Keputusan (2 Nov 2026):** produksi manual + cadangan otomatis di skrip; UAT tetap otomatis; database produksi baru/kosong; cadangan di folder VPS dengan retensi 14 hari. **Sudah dikerjakan, belum di-commit.**
  - `AUTO_MIGRATE=false` di `docker-compose.prod.yml`. `scripts/deploy-prod.sh` ditulis ulang: build, daftar migrasi tertunda + konfirmasi, `artisan down`, cadangan (`mysqldump`, kata sandi lewat env), ganti kontainer, migrasi, `artisan up`.
  - Cadangan gagal = deploy berhenti dan aplikasi dibuka lagi. Migrasi gagal = aplikasi tetap dalam mode pemeliharaan dan perintah pemulihan dicetak.
  - Verifikasi: 21 pemeriksaan skrip bantu lulus (`bash scripts/test-deploy-lib.sh`), `docker compose config` menghasilkan `AUTO_MIGRATE: false`, dan simulasi dengan `docker`/`git` tiruan untuk empat skenario (sukses, cadangan gagal, migrasi gagal, konfirmasi ditolak) berurutan sesuai rancangan. **Belum dicoba di server sungguhan**: uji dulu di UAT atau dengan `--yes` pada lingkungan percobaan.
  - Belum tercakup: salinan cadangan di luar VPS. Dokumen: `deployment-guide.md` bagian 17.

---

## 4. Sedang

### F-03 — Endpoint pendaftaran akun siswa bisa dipakai menguras kuota email dan membatalkan link aktivasi orang lain
- **Lokasi:** `routes/students.php:12` (`throttle:5,1` per IP), `app/Domains/Students/Services/StudentSignupService.php:20-36`.
- **Masalah:** Siapa pun yang memegang link sekolah (dibagikan ke siswa) bisa mengirim email apa saja. Setiap permintaan mengirim email dan memanggil `ActivationService::issue()`, yang **mencabut** link aktivasi sebelumnya. Cooldown di halaman hanya ada di sisi klien.
- **Dampak:** (a) Satu IP bisa mengirim 300 email per jam, sedangkan kuota Resend gratis 100 per hari, sehingga email aktivasi dan reset password sah ikut gagal. (b) Email pihak ketiga bisa dispam. (c) Menembak email korban berulang kali membuat link aktivasi korban terus hangus.
- **Saran:** Cooldown per email di server (jangan terbitkan ulang bila token aktif masih baru, mis. 10 menit). Throttle per token sekolah. Opsional captcha.
- **Keputusan (2 Nov 2026):** batas per alamat email + Cloudflare Turnstile; **tanpa** batas per link sekolah (penyerang bisa sengaja menghabiskannya untuk mengunci satu sekolah). Paket Resend: gratis. **Sudah dikerjakan, belum di-commit.**
  - Catatan hasil telaah: dampak (c) lebih ringan dari tertulis di atas (korban tetap menerima email terbaru yang valid), dan ada dampak (d): akun siswa belum aktif menumpuk dari email acak. Cooldown per email tidak menutup (a) dan (d) bila penyerang memakai banyak email, itu sebabnya Turnstile dipilih.
  - Batas: 3 email per alamat per 15 menit (`StudentSignupService`, RateLimiter), diabaikan diam-diam dengan respons sama. Turnstile aktif hanya bila `TURNSTILE_SECRET_KEY` terisi, gagal tertutup bila Cloudflare tak terjangkau, widget `interaction-only`, di-reset setelah tiap kirim, CSP dilonggarkan hanya di rute `register.*`.
  - Verifikasi: Pest 765/765, Vitest 274/274, Pint, PHPStan, vue-tsc, ESLint bersih. Cek peramban nyata dengan kunci uji Cloudflare dan CSP menyala (APP_ENV=staging): skrip Cloudflare termuat tanpa pelanggaran CSP, token diperoleh otomatis, pendaftaran berhasil, dan "Kirim ulang" memakai token baru. **Belum dicoba dengan kunci Turnstile sungguhan di UAT/produksi.**
  - **Wajib sebelum rilis:** isi `TURNSTILE_SITE_KEY` dan `TURNSTILE_SECRET_KEY` di UAT dan produksi. Dokumen: `deployment-guide.md` bagian 18, spec dan rencana `2026-11-02-pengaman-pendaftaran-siswa`.

### F-04 — `SchoolService::create` dan `update` tidak memeriksa pelaku (perbaikan sebelumnya hanya menutup `delete`)
- **Lokasi:** `app/Domains/Schools/Services/SchoolService.php:37` dan `:43`; `SchoolController::store/update` tidak memeriksa pelaku.
- **Masalah:** Perbaikan IDOR sebelumnya memblokir role terikat sekolah dari izin `sekolah.buat/ubah/hapus` hanya untuk role yang dibuat atau diubah **sesudahnya** (`RoleService::assertScopeAllows`), dan menambah guard di `delete`. Role lama yang sudah memegang izin itu, dan tidak pernah disimpan ulang, masih lolos untuk **ubah** dan **tambah** sekolah (sekolah mana pun).
- **Dampak:** Operator sekolah dengan role lama bisa mengubah data sekolah lain. Hanya berlaku bila ada role lama seperti itu **(perlu dikonfirmasi di UAT/produksi)**.
- **Saran:** Tambahkan guard `$actor->school_id !== null` yang sama di `create`/`update` (dan teruskan `$actor` dari controller). Cek role terikat sekolah yang memegang izin ini di UAT/produksi.
- **Keputusan (4 Nov 2026):** guard yang sama dengan `delete`, lewat satu metode privat `assertDistrictStaff`; pelaku terikat sekolah ditolak juga untuk sekolahnya sendiri (data sekolah mencakup seluruh kecamatan). **Sudah dikerjakan, belum di-commit.** Tes: dua tes baru di `SchoolTest.php` (gagal sebelum perbaikan). Kueri pengecekan role lama ada di `deployment-guide.md` bagian 19; guard sudah menetralkan dampaknya walau role lamanya ada.

### F-05 — Mengubah role tidak memeriksa apakah pelaku boleh mengelola role itu
- **Lokasi:** `app/Domains/Access/Services/RoleService.php:44` (`update`), `RoleController::update` (`:64`) tanpa `Gate`/`canGrantRole`. Bandingkan `RoleService::delete` yang memeriksa `canGrantRole`.
- **Masalah:** Pelaku dengan `role.ubah` yang izinnya lebih sedikit dapat mengubah nama, deskripsi, dan mencabut izin yang ia pegang dari role yang lebih tinggi (mis. `admin-kecamatan`). Eskalasi izin tetap tertahan (`assertOwned`), tetapi sabotase tidak.
- **Dampak:** Admin menengah bisa melumpuhkan role admin lain atau mengganti namanya.
- **Saran:** Terapkan `AccessGuard::canGrantRole($actor, $role)` pada `update` dan `edit`, sama seperti `delete`.
- **Telaah (4 Nov 2026):** saran di atas bertabrakan dengan desain Fase 1 yang disengaja (penyunting boleh mengubah hanya izin miliknya; panduan UI menonaktifkan checkbox lain; tes `update menambah dan mencabut hanya izin milik penyunting`).
- **Keputusan (4 Nov 2026):** pertahankan edit izin parsial, **kunci identitas role**: pada role yang izinnya melebihi milik penyunting, nama, deskripsi, dan `is_school_scoped` tidak dapat diubah (`RoleService::update`); `Role/Form.vue` menonaktifkan kolomnya lewat prop `identity_locked`. Pencabutan izin yang dipegang penyunting sendiri tetap boleh (risiko diterima). **Sudah dikerjakan, belum di-commit.** Tes: `RoleServiceTest` (tiga kasus gagal sebelum perbaikan, plus izin parsial, `''`≡`null`, super-admin, role dalam jangkauan) dan `RoleHttpTest` (`identity_locked`).

### F-06 — Mengedit akun siswa lewat menu Pengguna bisa memindahkan sekolahnya tanpa memindahkan data siswa
- **Lokasi:** `app/Domains/Access/Services/UserService.php:77` (`school_id` ikut diubah untuk role terikat sekolah). Jalur yang benar ada di `StudentService::correct`, yang memindahkan `users.school_id`, `students.school_id`, dan melepas kursi dalam satu transaksi.
- **Masalah:** Akun dengan role `siswa` ikut tampil dan bisa diedit di halaman Pengguna. Mengubah `school_id` di sana mengubah `users.school_id` saja.
- **Dampak:** Akun siswa tidak sinkron dengan baris `students`/pendaftarannya. Scope sekolah membaca `users.school_id`, sehingga siswa kehilangan akses ke datanya sendiri atau melihat sesi sekolah yang salah.
- **Saran:** Blokir perubahan sekolah (atau seluruh edit) untuk akun `siswa` di halaman Pengguna, dan arahkan ke koreksi siswa.
- **Keputusan (4 Nov 2026):** blokir ubah dan role siswa; nonaktifkan/aktifkan dan kirim ulang aktivasi tetap. **Sudah dikerjakan, belum di-commit.**
  - Temuan tambahan saat telaah: form Pengguna menawarkan role `siswa` (lolos `canGrantRole`), sehingga akun siswa tanpa baris `students` bisa dibuat dari menu Pengguna. Role itu kini ditolak di `create`/`update` dan disaring dari form.
  - `UserService::assertEditable` (juga dipakai `UserController::edit`) menolak akun siswa; baris daftar membawa `is_student` dan tombol Ubah disembunyikan di `Pengguna/Index.vue` dan `Show.vue`. Kueri pengecekan akun siswa tanpa data siswa ada di `deployment-guide.md` bagian 19.
  - Tes baru di `UserServiceTest` dan `UserHttpTest`. Dokumen: spec dan rencana `2026-11-04-batas-wewenang-admin`.

### F-07 — Ganti atau reset password tidak mengakhiri sesi di perangkat lain
- **Lokasi:** `app/Http/Controllers/Auth/PasswordController.php:16` (tanpa `logoutOtherDevices`/rotasi `remember_token`); `NewPasswordController.php:51` merotasi `remember_token`, tetapi sesi database tetap hidup. Fitur "keluarkan perangkat lain" ada di `ProfileSessionController`, hanya manual.
- **Dampak:** Bila password diganti karena akun dicurigai bocor, sesi penyerang tetap aktif sampai kedaluwarsa (120 menit, diperpanjang tiap aktivitas).
- **Saran:** Setelah ganti atau reset password, hapus baris `sessions` milik pengguna selain sesi sekarang dan rotasi `remember_token`.
- **Keputusan (4 Nov 2026):** sesuai saran, tanpa pilihan desain. Logika dari `ProfileSessionController` dipindah ke `SessionService` (`endOthers` untuk ganti password, `endAll` untuk reset) dan dipakai ketiganya. **Sudah dikerjakan, belum di-commit.** Tes: empat tes di `tests/Feature/Auth/SessionRevocationTest.php` (dua gagal sebelum perbaikan); `ProfileSessionTest` lama tetap lulus tanpa diubah. Syarat: `SESSION_DRIVER=database` di server (panduan bagian 20). Dokumen: spec dan rencana `2026-11-04-akhiri-sesi-saat-ganti-password`.

### F-08 — Pembatasan login hanya per kombinasi email dan IP; `trustProxies('*')`
- **Lokasi:** `app/Http/Requests/Auth/LoginRequest.php` (`throttleKey`), `bootstrap/app.php:23`.
- **Masalah:** Satu IP bebas mencoba banyak email berbeda (password spraying) hingga batas umum 240 permintaan per menit. Tidak ada batas per IP khusus login. `trustProxies('*')` mempercayai header `X-Forwarded-For` dari mana pun; aman bila kontainer hanya terjangkau lewat proxy.
- **Saran:** Tambahkan limiter per IP untuk `POST /login` (mis. 20 per menit). Batasi `trustProxies` ke alamat proxy yang dikenal **(perlu dikonfirmasi: apakah port kontainer terekspos langsung?)**.
- **Telaah (4 Nov 2026):** di Laravel `trustProxies('*')` hanya mempercayai peer langsung (NPM) dan membaca `X-Forwarded-For` dari kanan; `docker-compose.prod.yml` tidak mempublikasikan port. Jadi bagian "mempercayai header dari mana pun" tidak berlaku. Yang tersisa adalah syarat bahwa IP yang sampai ke aplikasi harus IP pengunjung asli. Saran "20 per menit untuk semua permintaan" juga berisiko mengunci satu kelas di Wi-Fi sekolah.
- **Keputusan (4 Nov 2026):** limiter **kegagalan** per IP, 50 per 10 menit (env `SECURITY_LOGIN_IP_FAILURES` / `SECURITY_LOGIN_IP_WINDOW`), tidak direset oleh login sukses; `trustProxies` tidak diubah. **Sudah dikerjakan, belum di-commit.** Tes: `tests/Feature/Auth/LoginIpThrottleTest.php` (lima tes, tiga gagal sebelum perbaikan). **Belum dikonfirmasi di server:** jalankan kueri `sessions.ip_address` di `deployment-guide.md` bagian 21; bila yang muncul IP Cloudflare/Docker, proxy perlu dibenahi sebelum limiter ini (dan `throttle:5,1` pendaftaran) dipasang. Dokumen: spec dan rencana `2026-11-04-batas-login-per-ip`.

### F-09 — Tidak ada CI; pengujian tidak menjadi syarat deploy
- **Lokasi:** tidak ada `.github/`; `scripts/deploy-*.sh` melakukan `git pull` lalu build langsung di server.
- **Dampak:** Pest, Vitest, PHPStan, `vue-tsc`, dan Pint hanya berjalan bila diingat. Branch yang gagal tes bisa ter-deploy.
- **Saran:** Workflow CI (tes, analisis statis, lint, `composer audit`) sebagai syarat sebelum push ke `staging`/`main`.
- **Keputusan (4 Nov 2026):** GitHub Actions; syarat merge lewat branch protection (check `Backend` dan `Frontend`), bukan lewat skrip deploy; audit dependensi terpisah (mingguan, tidak memblokir). **Sudah dikerjakan, belum di-commit dan belum dijalankan di GitHub.**
  - `.github/workflows/ci.yml`: Backend (PHP 8.4, MySQL 8.0, Pint, PHPStan, Pest, tes pustaka skrip deploy) dan Frontend (Node 20, ESLint galat saja, Vitest, `npm run build`). `.github/workflows/audit.yml`: `composer audit` dan `npm audit --omit=dev` tiap Senin.
  - Dibuktikan lokal: YAML valid, setiap perintah job dijalankan manual, Pest penuh dengan lingkungan tiruan CI (`.env.example` + kunci baru). **Belum dibuktikan:** run di GitHub. **Tindakan pemilik:** aktifkan branch protection (panduan bagian 22; repositori privat butuh paket berbayar).
  - Belum menjadi syarat: Prettier `--check` dan ESLint `--max-warnings 0` (F-21). Dokumen: spec dan rencana `2026-11-04-ci-github-actions`.

### F-10 — Kerentanan dependensi
- **PHP:** `symfony/mime` v8.1.7 terdampak CVE-2026-45070 (medium, header injection pada nama parameter MIME; diperbaiki di ≥ 8.1.10). Relevansi rendah karena aplikasi tidak menerima lampiran dari pengguna, tetapi mudah diperbaiki: `composer update symfony/mime`.
- **npm:** 26 advisory (1 kritis, 8 tinggi, 17 sedang, 1 rendah). Yang kritis (`shell-quote` lewat `concurrently`) dan rantai `vite`/`stylus`/`css`/`decode-uri-component`, `eslint`, `body-parser`, `undici` adalah **alat build/dev**. `shadcn-vue` (CLI, menarik `zod`, `ts-morph`) berada di `dependencies`, seharusnya `devDependencies`. Dari paket runtime browser (`apexcharts`, `reka-ui`, `qrcode`, `qr-scanner`, `vue-sonner`, dll.) tidak ada advisory. Saran "fix" dari `npm audit` mengusulkan turun versi mayor (`eslint@4`, `vite@6.4.5`), jangan diikuti membabi buta.
- **Saran:** Perbarui `symfony/mime`; pindahkan `shadcn-vue` ke `devDependencies`; jadwalkan pembaruan toolchain terpisah; jalankan audit di CI.
- **Telaah (4 Nov 2026):** basis data advisori bertambah, sehingga `npm audit` kini melaporkan 47 (bukan 26). Tahap produksi Docker hanya menyalin `public/build`, jadi paket npm tidak ikut ke image yang melayani pengguna. `shadcn-vue` ternyata dipakai saat build (`@import 'shadcn-vue/tailwind.css'`), bukan hanya CLI, tetapi tetap tepat berada di `devDependencies`.
- **Keputusan (4 Nov 2026):** kerjakan bagian yang aman, tunda toolchain mayor. **Sudah dikerjakan, belum di-commit.**
  - `symfony/mime` v8.1.7 menjadi v8.1.10: `composer audit` bersih.
  - `npm audit fix` (tanpa `--force`) menghilangkan dua advisori kritis dan tiga advisori tinggi, termasuk `shell-quote`/`concurrently`, `undici`, `body-parser`; ikut menaikkan `vite` 8.3.5 dan `shadcn-vue` 2.9.0. `shadcn-vue` dipindah ke `devDependencies`; `npm audit --omit=dev` kini 0.
  - **Sisa 38 advisori npm** (6 tinggi, 31 sedang, 1 rendah), semuanya alat build/dev: rantai `stylus`/`css`/`decode-uri-component` (peer `vite`, `vue-metamorph` milik CLI `shadcn-vue`), `zod`/`deepmerge`/`ts-morph` (CLI `shadcn-vue`), serta `eslint` 8 dengan plugin-nya, `vue-tsc` 2, dan `js-beautify`. Perbaikannya butuh pembaruan mayor toolchain (ESLint 9/10 flat config, vue-tsc 3, dan sebagainya), dijadwalkan terpisah. Saran `npm audit fix --force` (turun ke `eslint@4`, `vite@6.4.5`, `shadcn-vue@0.11.4`) tidak diikuti.
  - Verifikasi: `npm run build` sukses, `vue-tsc` bersih, ESLint 0 galat (332 peringatan), Pest 794/794, Vitest 274/274 (lihat F-30 untuk timeout di bawah beban). Dokumen: spec dan rencana `2026-11-04-perbarui-dependensi-aman`.

### F-11 — `sijebol:bersihkan-data-siswa` tanpa pengaman lingkungan
- **Lokasi:** `app/Console/Commands/ResetStudentData.php:11`.
- **Masalah:** Menghapus seluruh siswa, pendaftaran, jadwal, dokumen (dan berkasnya). Dengan `--force` tidak ada konfirmasi. Command ikut terpasang di image produksi.
- **Saran:** Tolak di `APP_ENV=production` kecuali ada flag tegas tambahan atau konfirmasi yang mengetik nama lingkungan. Pertimbangkan membuat cadangan otomatis sebelum menghapus.
- **Keputusan (4 Nov 2026):** ketik nama lingkungan. Di luar lokal/testing `--force` diabaikan; perintah menampilkan database dan jumlah baris per tabel, lalu meminta nama lingkungan (atau `--lingkungan=<nama>` untuk skrip); salah atau kosong = batal. Ditolak total di produksi tidak dipilih karena alur rilis terdokumentasi memakai perintah ini. **Sudah dikerjakan, belum di-commit.** Tes: empat tes baru di `ResetStudentDataTest.php` (gagal sebelum perbaikan), tes lama tidak diubah. Cadangan otomatis tidak dibuat oleh perintah (mysqldump ada di `deploy-prod.sh`); panduan tetap meminta cadangan manual. Dokumen: `deployment-guide.md` bagian "Memulai dari data kosong", spec dan rencana `2026-11-04-pengaman-bersihkan-data-siswa`.

---

## 5. Rendah

### F-12 — Token aktivasi siswa tersimpan polos di antrean
`StudentSignupNotification` tidak `ShouldBeEncrypted`, berbeda dengan `StaffActivationNotification`. Token ada di payload tabel `jobs`, dan tetap di `failed_jobs` bila job gagal. Token berlaku 3 hari dan sekali pakai, tetapi tidak konsisten. Saran: terapkan `ShouldBeEncrypted`.

### F-13 — Ziggy `@routes` membuka seluruh nama dan pola rute ke pengunjung anonim
`resources/views/app.blade.php:15`. Tidak ada `config/ziggy.php`, sehingga halaman login pun memuat daftar rute admin. Bukan celah langsung (semua rute dijaga), tetapi membantu pengintaian. Saran: batasi dengan `only`/`except` atau kelompok rute.

### F-14 — Penolakan pindai tiket membocorkan nama sekolah dan tanggal sesi lain
`app/Domains/Schedules/Services/ScanService.php:64`. Pencarian tiket memakai `withoutGlobalScopes()`, sehingga petugas sekolah A yang memindai tiket sekolah B mendapat "untuk sesi lain (nama sekolah, tanggal)". Dampak kecil. Saran: pesan netral "Tiket bukan untuk sesi ini".

### F-15 — Kepemilikan data siswa bergantung pada global scope implisit
`SelfRegistrationController`, `StudentDocumentController`, dan lainnya mengandalkan scope `owner`/`school` pada route-model binding, tanpa Policy eksplisit. Scope di-bypass dengan `withoutGlobalScopes()` di beberapa tempat sah (scan, scheduler, kuota). Bila ada pemakaian baru yang tak sengaja melewati scope, tidak ada lapisan kedua. Saran: Policy untuk `Student`, `RegistrationStudent`, dan `StudentDocument`, atau minimal tes IDOR lintas sekolah dan lintas siswa per rute.

### F-16 — Data pribadi: NIK dan tanggal lahir lengkap di log aktivitas; NIK penuh di form koreksi
`Student::getActivitylogOptions()` (`logOnly` memuat `nik`, `birth_date`) menyimpan nilai asli di `activity_log`; penutupan (`ActivityPropertyMasker`) hanya saat ditampilkan. `StudentController::edit` (`:68`) mengirim NIK penuh ke setiap pemegang `siswa.ubah`. Bukan celah baru (tabel `students` pun menyimpan NIK asli), tetapi perlu diputuskan kebijakannya, terutama setelah ada izin terpisah `laporan.unduh-nik`.

### F-17 — Setiap pratinjau/unduh dokumen menulis satu baris log
`StudentDocumentService::download/preview`. Tiap pembukaan pratinjau (dan permintaan ulang oleh penampil) menambah satu baris, sehingga `activity_log` membengkak dan menutupi aksi penting. Saran: log sekali per pengguna, dokumen, dan jendela waktu.

### F-18 — Reset password mengaktifkan akun yang belum aktif tanpa melalui alur aktivasi
`NewPasswordController.php:51`. Akun staf yang belum diaktifkan bisa jadi aktif lewat "lupa password", tanpa log `aktivasi.selesai` dan tanpa notifikasi ke pembuat akun. Secara keamanan aman (pemilik email yang menerima tautan), tetapi jejak audit tidak lengkap.

### F-19 — Dockerfile: entri cron terdaftar dua kali dan perkakas pengembangan di image runtime
`Dockerfile:79` memuat `/etc/cron.d/laravel-scheduler` (format sistem, ada kolom user) sebagai crontab pengguna `www-data` yang formatnya berbeda, sehingga menurut format crontab entri itu tak valid (kata `www-data` terbaca sebagai perintah) **(perlu dikonfirmasi di kontainer: `docker exec sijebol-uat crontab -u www-data -l` dan log cron)**; scheduler sendiri kemungkinan tetap jalan lewat `/etc/cron.d`. Image runtime juga memuat `git`, `unzip`, dan `composer`, dan build memakai `--ignore-platform-reqs`. Saran: hapus `crontab -u`, buang perkakas yang tak perlu.

### F-20 — `.gitignore` tidak mencakup `.env.uat`
Hanya `.env`, `.env.backup`, dan `.env.production` yang diabaikan. File `.env.uat` yang dibuat dari contoh bisa ter-commit. Saran: tambahkan `.env.*` dengan pengecualian `!.env*.example`.

### F-21 — Lint dan format frontend menyimpang
41 file gagal `prettier --check`, ESLint 330 peringatan `prettier/prettier`, dan 3 `no-unused-vars` (`StudentUpcomingSchedules.vue:5`, `Student/Dashboard.vue:11`). Peringatan yang berlebihan menutupi masalah nyata.

### F-22 — Laporan KTP-el memuat semua siswa ke memori
`ReportService::studentRecords` (`:98`, sudah bertanda `ponytail`). Aman untuk ribuan siswa; pada puluhan ribu perlu dipindah ke query SQL. `memory_limit` kontainer 256M. Catatan terkait: `phpunit.xml` dinaikkan ke 512M karena satu proses menjalankan seluruh suite.

### F-23 — Cookie sesi `secure` hanya diatur lewat env
`config/session.php` (`'secure' => env('SESSION_SECURE_COOKIE')`, bawaan `null`). Contoh env UAT/produksi sudah `true`, tetapi bila variabel terlewat, cookie tidak ber-flag Secure. Saran: bawaan `app()->isProduction() || app()->environment('staging')`.

### F-24 — Data dummy: saklar dan kata sandi bawaan
`SIJEBOL_ALLOW_DUMMY_DATA` bila terbawa ke `.env` produksi membuka perintah dummy di sana (di luar lokal wajib `--sandi` kuat dan konfirmasi). Kata sandi bawaan lokal `password` dan format email dummy diketahui publik. Saran: tegaskan di checklist rilis bahwa saklar tidak boleh ada di produksi; pertimbangkan menolak total bila `APP_ENV=production`.

### F-25 — Pencarian LIKE tidak meng-escape wildcard; balapan pendaftaran akun
Filter pencarian (`'like', "%{$v}%"`) di enam repository tidak meng-escape `%` dan `_` (hasil lebih luas, bukan injeksi). `StudentSignupService`: dua permintaan serentak dengan email baru yang sama bisa memicu galat unik 500.

### F-26 — Hardening nginx
`docker/nginx.conf` tanpa `server_tokens off`, kompresi, dan header cache untuk aset Vite ber-hash (`/build/*`). Murni peningkatan performa dan hardening.

### F-27 — Retensi log audit 365 hari dan dapat dihapus
`routes/console.php`: `logs:prune` harian dengan bawaan 365 hari. Jika log audit dipakai sebagai bukti, periode dan perlindungan perlu diputuskan, dan tabelnya dapat diubah oleh akun DB aplikasi.

### F-28 — Contoh kata sandi lemah di panduan deployment
`docs/deployment-guide.md` (sekitar baris 261 dan 263) memuat contoh `MYSQL_ROOT_PASSWORD: 'PasswordRootKuat123!'` dan `MYSQL_PASSWORD: 'PasswordSijebolKuat123!'` pada compose MySQL. Port MySQL hanya di-bind ke `127.0.0.1`, jadi tidak terbuka ke internet, tetapi bila nilai contoh ini dipakai apa adanya di server, kata sandi root dan aplikasi menjadi tebakan publik karena tertulis di repositori. Saran: ganti contoh dengan placeholder (`<ganti-dengan-sandi-acak>`). Dikonfirmasi pemilik: nilai tersebut hanya contoh dan tidak dipakai di server, jadi rotasi kata sandi tidak diperlukan; tersisa mengganti contoh dengan placeholder di panduan.

### F-29 — Kuota email gratis Resend (100/hari) tidak cukup untuk pendaftaran massal; throttle IP bisa mengenai siswa sah
Ditemukan saat membahas F-03. Pendaftaran mandiri ±1.500 siswa mengirim satu email aktivasi per siswa, ditambah kirim ulang, reset password, dan notifikasi. Kuota gratis Resend 100 email per hari berarti pendaftaran massal tertahan berhari-hari dan email staf/reset ikut terhambat; pilihan: paket berbayar, atau pendaftaran bergelombang per sekolah. Terkait: `throttle:5,1` per IP di `routes/students.php` memakai IP sebagai kunci; siswa di Wi-Fi sekolah yang sama berbagi IP, sehingga siswa keenam dalam semenit mendapat 429. Setelah Turnstile aktif, batas ini bisa dilonggarkan (mis. 30 per menit). **Perlu keputusan pemilik** (rencana kuota email dan angka throttle).

### F-30 — Vitest sering timeout 5 detik saat dijalankan penuh dengan beban CPU
Ditemukan saat memverifikasi F-07, F-10. Pada `npx vitest run` penuh (default worker), 2 sampai 4 tes yang tidak berkaitan (mis. `Dashboard.test.ts`, `DocumentPreviewDialog.test.ts`, `Laporan/Siswa.test.ts`) gagal dengan `Test timed out in 5000ms`, padahal lulus bila dijalankan sendiri; kumpulan yang gagal berganti-ganti antarproses. Dengan `--maxWorkers=3` seluruh 274 tes lulus (189 detik). Sebab: beban jsdom per berkas (49 lingkungan dibuat) menekan CPU. Saran: naikkan `test.testTimeout` (mis. 15 detik) dan/atau batasi `maxWorkers` di konfigurasi Vitest; hal ini perlu diselesaikan sebelum Vitest menjadi syarat CI (F-09).

**Status (4 Nov 2026):** `testTimeout: 15_000` ditambahkan di `vitest.config.ts`; `npx vitest run` penuh dengan worker bawaan lulus 274/274 (sebelumnya 2 sampai 4 gagal). **Sudah dikerjakan bersama F-09, belum di-commit.**

---

## 6. Hal yang sudah baik

- Semua rute yang bukan publik memakai `auth`; semua rute sensitif punya `can:<izin>`. Yang hanya `auth` (dashboard, notifikasi, profil, password, logout) memang untuk pengguna login.
- Alur status pendaftaran satu pintu (`StatusTransitioner`) dengan kunci baris, peta transisi, dan riwayat; pendaftaran di bawah kunci sesi dengan indeks unik `active_student_id`; pemindaian tiket aman terhadap balapan; kode tiket 32 karakter acak dan unik.
- Pengamanan dasar: CSP bernonce, `nosniff`, `X-Frame-Options`, HSTS, `Referrer-Policy`, pembatasan laju khusus pada aksi sensitif (aksi tulis staf seperti kelola pengguna, role, sekolah, dan ubah siswa hanya terkena batas umum 240 per menit), pesan reset password tidak membocorkan keberadaan akun, penonaktifan akun berlaku di permintaan berikutnya.
- Unggahan dokumen divalidasi (`mimes`, 5 MB), nama berkas di server acak (UUID), disimpan di disk privat, dan dilayani dengan `Content-Type` dari server.
- Nilai sel Excel dinetralkan dari injeksi formula; semua ekspor tercatat di log.
- Tidak ada rahasia di file yang dilacak git; `.dockerignore` menyingkirkan `.env`.
- Cakupan tes luas (749 Pest + 269 Vitest) dan analisis statis bersih.

## 7. Usulan urutan pembahasan

1. **F-01 dan F-02** — sebelum rilis berikutnya ke UAT/produksi.
2. **F-04, F-05, F-06** — celah otorisasi/integritas data yang saling terkait.
3. **F-03, F-07, F-08** — penyalahgunaan dan pengamanan akun.
4. **F-09, F-10, F-11** — proses dan dependensi.
5. Sisanya (rendah) dapat dikelompokkan dalam satu putaran perapian.
