# Email Aktivasi Staf Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Akun staf (operator sekolah, admin kecamatan, staf kecamatan lain) yang dibuat atau dikirim ulang aktivasinya lewat menu Pengguna otomatis menerima link aktivasi lewat email.

**Architecture:** `UserService::create` dan `resendActivation` mengirim satu notifikasi baru, `StaffActivationNotification`, sesudah token terbit dan transaksi selesai. Notifikasi turunan `BaseNotification` (antre, 3 percobaan) tetapi hanya lewat email, tanpa kanal database. Link di layar dan tombol WhatsApp tetap sebagai cadangan.

**Tech Stack:** Laravel notifications (queue database, Resend), Vue 3, Pest, Vitest.

**Spec:** Tidak ada dokumen desain terpisah. Perubahan kecil, keputusannya disepakati di percakapan: kirim email untuk semua staf; `sijebol:create-super-admin` tidak ikut karena terminalnya sudah menampilkan link.

## Global Constraints

- Aturan proyek: bahasa Indonesia untuk semua teks UI dan email; commit tanpa `Co-Authored-By`; commit hanya saat diminta.
- Masa berlaku link tetap `ActivationService::TTL_DAYS` (3 hari); jangan menulis angka 3 langsung.
- Jangan ubah `ActivationService`, `StudentSignupNotification`, atau `BaseNotification::via` (siswa tetap database + mail).
- Pengiriman email lewat antrean: kegagalan Resend tidak boleh menggagalkan pembuatan akun.

## Review Focus

- Notifikasi staf tidak ikut ke kanal database, sehingga link aktivasi mati tidak tertinggal di lonceng pengguna: tes `via() === ['mail']`.
- Email hanya terkirim bila pembuatan akun berhasil: ketika `create` menolak (role tidak diizinkan), tidak ada notifikasi.
- Kirim ulang untuk akun yang sudah aktif ditolak dan tidak mengirim email.
- Kirim ulang mengirim email dengan token baru (token lama sudah dicabut), bukan token lama.
- Email di-normalisasi (huruf kecil, tanpa spasi) sebelum dikirim, karena penerima diambil dari model yang sudah tersimpan.

---

### Task 1: Notifikasi dan pengiriman dari UserService

**Files:**
- Create: `app/Domains/Notifications/Notifications/StaffActivationNotification.php`
- Modify: `app/Domains/Access/Services/UserService.php` (`create`, `resendActivation`)
- Test: `tests/Feature/Access/UserServiceTest.php`

**Interfaces:**
- Produces: `StaffActivationNotification::__construct(string $token, ?string $role, ?string $school)`; `via()` mengembalikan `['mail']`; `url()` mengembalikan `"/activation/{$token}"`.
- Consumes: `BaseNotification` (title/body/url, `toMail`), `ActivationService::TTL_DAYS`.

- [ ] **Step 1: Tulis tes yang gagal** di `UserServiceTest.php` (tambahkan `Notification::fake()` di `beforeEach` dan import `Illuminate\Support\Facades\Notification` serta `StaffActivationNotification`):
  - `membuat user mengirim email aktivasi berisi token ke alamat yang sudah dinormalisasi`: `create` dengan email `'  Budi@X.TEST '`, lalu `Notification::assertSentTo($user, StaffActivationNotification::class, fn ($n, $channels) => $channels === ['mail'] && $n->url() === "/activation/{$token}")`.
  - `create yang ditolak tidak mengirim email`: role operator tanpa `school_id` melempar `AccessRuleException`, lalu `Notification::assertNothingSent()`.
  - `kirim ulang mengirim email dengan token baru`: buat user, panggil `resendActivation`, assert notifikasi terkirim dua kali ke user itu dan yang terakhir ber-`url()` `"/activation/{$new}"`.
  - `kirim ulang untuk akun aktif tidak mengirim email`: setelah `Notification::fake()` di-reset lewat `Notification::fake()` lagi, `resendActivation($aktif)` melempar, `assertNothingSent()`.
  - `isi email menyebut role, sekolah, dan masa berlaku`: `(new StaffActivationNotification('tok', 'operator-sekolah', 'SMAN 1'))->body()` mengandung `'Operator Sekolah'`, `'SMAN 1'`, dan `(string) ActivationService::TTL_DAYS.' hari'`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `vendor/bin/pest tests/Feature/Access/UserServiceTest.php`
Expected: FAIL (kelas `StaffActivationNotification` belum ada).

- [ ] **Step 3: Buat `StaffActivationNotification`** (turunan `BaseNotification`). `title()` = `'Aktifkan akun SIJEBOL'`. `body()` menyebut role (pakai `Str::headline` agar `operator-sekolah` menjadi "Operator Sekolah"), sekolah bila ada, tombol di bawah, masa berlaku `TTL_DAYS` hari, dan "Abaikan email ini bila Anda tidak merasa dibuatkan akun." `via()` dioverride menjadi `['mail']`.

- [ ] **Step 4: Kirim dari `UserService`.** Di `create`, simpan hasil `DB::transaction` ke variabel, lalu panggil `sendActivation` sesudahnya (bukan di dalam closure, agar email tidak terkirim bila transaksi dibatalkan), dengan role `$role->name` dan sekolah `$user->school?->name`. Di `resendActivation`, panggil `notify` dengan token baru sebelum `return`. Satu private method `sendActivation(User $user, string $token): void` dipakai kedua jalur.

- [ ] **Step 5: Jalankan, pastikan lulus**

Run: `vendor/bin/pest tests/Feature/Access`
Expected: PASS.

- [ ] **Step 6: Commit** (hanya bila diminta pengguna).

### Task 2: Teks halaman "Akun dibuat"

**Files:**
- Modify: `app/Domains/Access/Http/Controllers/UserController.php` (`done`: tambah `'email' => $user->email` pada array `user`)
- Modify: `resources/js/Pages/Pengguna/Berhasil.vue` (prop `user.email`; teks)
- Modify: `resources/js/types/` bila tipe pengguna halaman ini didefinisikan di sana
- Test: `tests/Feature/Access/UserHttpTest.php` (assert `user.email` di prop halaman `Pengguna/Berhasil`, baris sekitar 37), dan `resources/js/Pages/Pengguna/Berhasil.test.ts` (baru)

**Interfaces:**
- Consumes: Task 1 (email sudah dikirim saat halaman ini tampil).
- Produces: prop `user.email: string` pada `Pengguna/Berhasil`.

- [ ] **Step 1: Tulis tes yang gagal.** Pest: tambahkan `->where('user.email', ...)` pada tes yang sudah memeriksa komponen `Pengguna/Berhasil`. Vitest (`Berhasil.test.ts`, ikuti pola stub `route` dan `AppLayout` pada tes halaman lain): halaman menampilkan "Email aktivasi dikirim ke budi@x.test" pada mode `dibuat` dan `ulang`, dan tetap menampilkan "Link aktivasi" serta tombol WhatsApp.

- [ ] **Step 2: Jalankan, pastikan gagal.**

Run: `vendor/bin/pest tests/Feature/Access/UserHttpTest.php` dan `npx vitest run resources/js/Pages/Pengguna/Berhasil.test.ts`
Expected: FAIL.

- [ ] **Step 3: Implementasi.** Tambah `email` pada `done()` dan prop `user.email`. Ganti kalimat `'Pengguna perlu membuka link aktivasi dan membuat kata sandi sebelum dapat masuk.'` dan subjudul halaman agar menyebut email: "Email aktivasi dikirim ke {email}. Link di bawah dapat dibagikan manual bila email belum sampai." Kalimat mode `ulang` ("Link sebelumnya tidak berlaku lagi.") dipertahankan. Teks tombol WhatsApp tetap.

- [ ] **Step 4: Jalankan, pastikan lulus.**

Run: `vendor/bin/pest tests/Feature/Access` dan `npx vitest run`
Expected: PASS.

### Task 3: Dokumen dan verifikasi akhir

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (halaman Pengguna/Berhasil: satu kalimat bahwa email aktivasi ikut terkirim)
- Modify: `docs/deployment-guide.md` bila ada bagian email yang mendaftar jenis email keluar

- [ ] **Step 1:** `Grep` "Kirim via WhatsApp" dan "link aktivasi" di `docs/`; perbarui kalimat yang menyebut pengiriman hanya manual.
- [ ] **Step 2:** Review `ponytail:ponytail-review` (semua perubahan) dan `impeccable` (Berhasil.vue); perbaiki temuan yang valid.
- [ ] **Step 3: Verifikasi** — Pest penuh, Vitest penuh, Pint, PHPStan, `npx vue-tsc --noEmit`; semua hijau.
