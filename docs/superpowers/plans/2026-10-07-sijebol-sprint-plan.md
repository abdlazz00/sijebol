# SIJEBOL v1 — Sprint Plan

Tanggal: 2026-10-07. Status: draf menunggu review.
Acuan: `specs/2026-10-07-sijebol-v1-design.md` (spec sistem), `specs/2026-10-07-sijebol-ui-guide.md` (panduan UI), `CLAUDE.md` (aturan development).

## Aturan sprint

- Tiap fase dibagi **Track BE** lalu **Track FE**. Track FE dimulai setelah seluruh exit criteria Track BE terpenuhi (endpoint dan test hijau), sehingga FE membangun di atas BE yang sudah jadi.
- Tiap fase diawali brainstorming singkat dan rencana tugas rinci lewat `superpowers:writing-plans`; rencana rinci ditulis ke `docs/superpowers/plans/` saat fase dimulai, bukan sekarang.
- Review sebelum fase ditutup: `ponytail-review` untuk semua perubahan, `impeccable` untuk perubahan FE, lalu verifikasi (test, Larastan, Pint, build).
- Ukuran: **S** (±1–2 hari), **M** (±3–5 hari), **L** (±1–2 minggu) untuk satu orang. Durasi sprint ditentukan kemudian dari kapasitas tim.

## Ringkasan fase

| Fase | Fokus | Ukuran BE | Ukuran FE |
|---|---|---|---|
| 0 | Setup project | S | S |
| 1 | Akses dan autentikasi (RBAC, aktivasi, user, role, sekolah) | L | L |
| 2 | Notifikasi dan audit log | M | M |
| 3 | Siswa dan dokumen | M | M |
| 4 | Pendaftaran dan verifikasi | L | L |
| 5 | Jadwal dan penandaan rekaman | M | M |
| 6 | Beranda, laporan dasar, hardening, rilis | M | M |

---

## Fase 0 — Setup project

**Tujuan:** kerangka BE dan FE siap dikembangkan, dengan tooling kualitas dan konvensi folder terpasang.

### Track BE
1. `git init` dan `.gitignore`; commit awal berisi dokumen yang ada.
2. Buat proyek Laravel (stabil terbaru, PHP 8.3+) di root repo; konfigurasi `.env.example` (DB, queue `database`, mail, disk privat `local`).
3. Pasang Breeze (stack Inertia + Vue + TypeScript).
4. Pasang package: `spatie/laravel-permission`, `spatie/laravel-activitylog`, `maatwebsite/excel`, `spatie/laravel-query-builder`; jalankan migrasi bawaannya.
5. Tooling: Pest, Larastan (level awal 5, naik bertahap), Pint; skrip `composer test`, `composer lint`, `composer analyse`.
6. Struktur `app/Domains/{Access,Schools,Students,Registrations,Schedules,Notifications}` dengan subfolder standar; autoload dan service provider yang meng-*bind* repository (Contracts → Eloquent).
7. Kelas dasar: `BaseRepository` (kontrak minimum, tipis), konvensi Service, enum `Permission` dan enum status, trait `ScopedBySchool` (belum dipakai penuh).
8. Hapus registrasi publik dan verifikasi email bawaan Breeze (route, controller, halaman).
9. Satu test smoke (halaman login terbuka) dan pipeline lokal hijau.

**Exit criteria BE:** `composer test`, `composer analyse`, dan `composer lint` lulus; `php artisan migrate` berjalan; tidak ada route registrasi publik; struktur folder sesuai spec.

### Track FE
1. Konfigurasi Tailwind dengan token dari panduan UI (warna, tipografi Inter, radius, ruang) sebagai variabel CSS/tema.
2. Pasang shadcn-vue dan ikon Lucide; tema disesuaikan ke token.
3. `resources/js/lib/`: `date.ts`, `format.ts`, `status.ts` (satu sumber formatter); konstanta permission dan status sebagai mirror enum PHP.
4. Composable dasar: `usePermission()`, `useFlash()`, `useDataTable()` (kerangka).
5. Komponen `ui/` dasar: Button, Input, PasswordInput, Select, Checkbox, Badge, Card, Dialog, Toast, Skeleton, EmptyState, Avatar, Breadcrumb.
6. Komponen `shared/` kerangka: `DataTable`, `PageHeader`, `ConfirmDialog`.
7. Layout `AuthLayout` (split screen) dan `AppLayout` (sidebar, topbar) sebagai kerangka dengan menu statis sementara.
8. Konfigurasi ESLint, Prettier, `vue-tsc`; skrip `npm run build`, `lint`, `typecheck`.

**Exit criteria FE:** `npm run build`, `lint`, dan `typecheck` lulus; halaman login memakai `AuthLayout` dan terlihat sesuai token; halaman contoh memakai `AppLayout`.

---

## Fase 1 — Akses dan autentikasi

**Tujuan:** admin dapat membuat role, sekolah, dan user; operator dapat aktivasi akun dan login; semua akses terkendali RBAC dengan scope sekolah.

### Track BE
1. Migrasi: `schools`, penyesuaian `users` (`phone`, `school_id`, status aktif), `account_activations`.
2. Seeder permission (daftar tetap dari spec bagian 6) dan role awal (`super-admin`, `admin-kecamatan`, `operator-sekolah`), super-admin pertama lewat seeder/command.
3. Enforcement RBAC: middleware `can:` per route, Gate untuk `super-admin`, cache permission, aturan anti eskalasi, proteksi `super-admin`.
4. Scope sekolah: global scope/trait dan aturan 404 untuk data sekolah lain; test lintas sekolah.
5. Aktivasi akun: service pembuat token (hash, 3×24 jam, sekali pakai), endpoint aktivasi, kirim ulang aktivasi, rate limit.
6. Auth: login dengan pemeriksaan akun nonaktif, rate limit, lupa/reset password (bawaan Breeze), profil dan ubah password.
7. CRUD Sekolah, User, Role (FormRequest, Service, Repository, Policy, Controller); filter dan paginasi via query-builder; aksi nonaktif/aktifkan.
8. Menu server-side: daftar menu dengan permission pemicu, dikirim lewat Inertia shared props bersama daftar permission pengguna.
9. Audit log untuk user, role/permission, login/logout (activitylog; password/token dikecualikan).
10. Test Pest: aktivasi (valid, kedaluwarsa, dipakai dua kali), permission route, scope sekolah, anti eskalasi, super-admin, nonaktif tidak bisa login.

**Exit criteria BE:** semua endpoint fase ini punya test; matriks permission diverifikasi; tidak ada akses lintas sekolah di test; Larastan lulus.

### Track FE
1. Halaman auth: Login, Aktivasi akun (termasuk state token tidak berlaku), Lupa password, Reset password (panduan UI 5.1–5.5).
2. `AppLayout` final: sidebar dari menu server, lipat/buka, topbar, menu pengguna; drawer mobile.
3. Beranda dengan blok mengikuti permission (data contoh sementara; sumber data riil di Fase 6).
4. Pengguna: daftar (filter, tabel, menu aksi), form tambah/ubah (halaman terpisah), layar sukses dengan Salin link dan Kirim via WhatsApp, detail (kartu informasi; kartu aktivitas aktif setelah Fase 2).
5. Role & Permission: daftar, form dengan matriks modul × aksi (pilih semua per baris, sticky header dan bar simpan, checkbox nonaktif untuk anti eskalasi).
6. Sekolah: daftar dan form.
7. Profil (informasi dan ubah password); halaman error 403/404/500.
8. State lengkap sesuai panduan UI: loading, kosong, hasil filter kosong, error validasi; tampilan mobile.

**Exit criteria FE:** seluruh halaman panduan UI bagian 5.2–5.13, 5.15–5.16 terimplementasi; `build`/`lint`/`typecheck` lulus; alur end-to-end (buat user → salin link → aktivasi → login) berjalan manual; review `impeccable` selesai.

---

## Fase 2 — Notifikasi dan audit log

**Tujuan:** infrastruktur notifikasi (in-app dan email) dan penampil log siap dipakai fase berikutnya.

*Prasyarat desain:* tambahkan ke panduan UI bagian halaman Log Aktivitas (sebelum Track FE).

### Track BE
1. Tabel `notifications`; kelas dasar notifikasi dengan channel `database` + `mail` (queued), penerima ditentukan lewat permission.
2. Endpoint: daftar notifikasi (filter Semua/Belum dibaca, paginasi), tandai satu dibaca, tandai semua dibaca, hitung belum dibaca (shared props).
3. Konfigurasi queue worker (database) dan template email dasar (bahasa Indonesia); retry dan `failed_jobs`.
4. Notifikasi pertama sebagai pembuktian pola (contoh: akun diaktifkan → admin pembuat).
5. Audit log: viewer API (filter pengguna, tanggal, jenis) di balik `log.lihat`; pemasangan log di model yang sudah ada; masker NIK di log; scheduled command pembersihan retensi 1 tahun.
6. Test: penerima sesuai permission, tandai dibaca, email di-queue (fake), log hanya baca.

**Exit criteria BE:** test lulus; worker memproses email di lingkungan lokal; log terbaca hanya oleh pemilik `log.lihat`.

### Track FE
1. Komponen `NotificationBell` (badge, dropdown 8 item, tandai satu/semua dibaca, polling 60 detik), halaman `/notifikasi` (segmen Semua/Belum dibaca, paginasi).
2. Halaman Log Aktivitas (tabel dan filter) dan kartu "Aktivitas terbaru" di detail pengguna.

**Exit criteria FE:** panduan UI 5.14 terimplementasi; log viewer sesuai desain; `impeccable` selesai.

---

## Fase 3 — Siswa dan dokumen

**Prasyarat desain:** panduan UI putaran 2 untuk halaman Siswa (daftar, form, impor Excel, dokumen).

### Track BE
1. Migrasi `students` (NIK unik, NISN, nama, tanggal lahir, jenis kelamin, `school_id`) dan `student_documents` (KK, akta lahir; disk privat).
2. CRUD siswa (Service/Repository/Policy), scope sekolah, validasi NIK 16 digit.
3. Upload dokumen: batas tipe dan ukuran, pembersihan file bila gagal, route unduh bergerbang permission dan scope, audit unduhan.
4. Impor Excel: template, validasi per baris, laporan baris gagal dengan nomor baris; baris benar tetap masuk.
5. Layanan eligibility (umur 16–17 pada tanggal submit, data lengkap, dokumen lengkap) dengan test batas umur.
6. Test: scope, akses dokumen lintas sekolah ditolak, impor sebagian gagal.

### Track FE
1. Daftar siswa (filter, status kelengkapan dokumen, badge eligible), form tambah/ubah, detail dengan dokumen.
2. Komponen `FileUpload` dengan pratinjau dan validasi sisi klien; dialog impor Excel dan laporan error.

---

## Fase 4 — Pendaftaran dan verifikasi

### Track BE
1. Migrasi `registrations`, `registration_students`, `registration_status_histories`; unique index parsial untuk satu entri aktif per siswa.
2. `RegistrationService`: buat draf, pilih siswa eligible (multi-select), submit dengan validasi ulang di server dan transaksi.
3. `VerificationService`: setujui, minta perbaikan, tolak per siswa (catatan wajib); pengajuan ulang untuk status perbaikan; mesin status dengan transisi tervalidasi.
4. Event dan notifikasi (diajukan, diputuskan) memakai infrastruktur Fase 2; kunci pendaftaran setelah diajukan.
5. Test: transisi sah dan ilegal, pendaftaran ganda bersamaan, scope sekolah, notifikasi tepat sasaran.

### Track FE
1. Operator: daftar pendaftaran, form pendaftaran dengan `StudentMultiSelect` (siswa tak eligible nonaktif + tombol lengkapi dokumen), halaman detail dan perbaikan.
2. Petugas: antrean verifikasi, halaman keputusan per siswa (data, dokumen, catatan), timeline riwayat status.

---

## Fase 5 — Jadwal dan penandaan rekaman

### Track BE
1. Migrasi `schedules`; penempatan siswa `Terverifikasi` sekolah ke jadwal (status `Terjadwal`); ubah jadwal dengan notifikasi.
2. Penandaan massal: `Sudah rekaman`, `Tidak hadir`, `Belum rekaman`; siswa dengan status akhir dapat didaftarkan ulang.
3. Test: transisi ke Terjadwal dan status akhir, notifikasi jadwal, scope, hak admin saja.

### Track FE
1. Admin: daftar jadwal, form jadwal, penempatan siswa, layar penandaan rekaman dengan ceklis massal.
2. Operator: tampilan jadwal sekolahnya di beranda dan halaman jadwal.

---

## Fase 6 — Beranda, laporan dasar, hardening, rilis

### Track BE
1. Data blok beranda sesuai permission (ringkasan angka, perlu tindakan, jadwal terdekat).
2. Laporan dasar: rekap per sekolah dan per status; ekspor Excel.
3. Hardening: tinjauan keamanan (permission semua route, rate limit, header keamanan, validasi unggah), indeks database, performa daftar besar.
4. Persiapan produksi: konfigurasi supervisor (queue), cron (scheduler), backup DB dan file, `.env` produksi, seeder awal.

### Track FE
1. Beranda memakai data riil; halaman laporan dan tombol ekspor.
2. Polesan: konsistensi UI menyeluruh, aksesibilitas (keyboard, kontras), responsif, performa bundle.
3. UAT dengan pengguna kecamatan dan sekolah percontohan; perbaikan temuan.

**Exit criteria rilis:** seluruh test hijau; review `ponytail` dan `impeccable` bersih; UAT disetujui; runbook deploy tertulis.

---

## Risiko dan keputusan yang menyertai

- **Panduan UI untuk Fase 2–6** belum ada; tiap fase membutuhkan putaran desain UI sebelum Track FE-nya.
- **Pendaftaran ulang siswa** (`Tidak hadir`/`Belum rekaman`) sudah diasumsikan lewat pendaftaran baru; konfirmasi dengan kecamatan sebelum Fase 5.
- **Umur dihitung pada tanggal submit** (asumsi spec); konfirmasi sebelum Fase 3.
- **SMTP** belum ditentukan; dibutuhkan paling lambat di Fase 2.
- **WhatsApp otomatis, penjadwalan otomatis, dan feedback** berada di luar v1.
