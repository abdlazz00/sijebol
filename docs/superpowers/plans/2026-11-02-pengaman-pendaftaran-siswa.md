# Pengaman Pendaftaran Siswa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi inline). Langkah memakai kotak centang.

**Goal:** Menutup penguras kuota email dan spam lewat pendaftaran siswa publik (F-03).

**Architecture:** Batas 3 email per alamat per 15 menit di `StudentSignupService` (RateLimiter), ditambah verifikasi Cloudflare Turnstile di `StudentSignupRequest` yang aktif hanya bila secret terisi; widget di halaman `Daftar/Index.vue`.

**Tech Stack:** Laravel 13 (Http, RateLimiter), Vue 3 + Inertia, Vitest, Pest.

**Spec:** `docs/superpowers/specs/2026-11-02-pengaman-pendaftaran-siswa-design.md`

## Global Constraints

- Respons pendaftaran identik untuk semua kasus (tidak membuka celah menebak email).
- Turnstile aktif hanya bila `services.turnstile.secret` terisi; verifikasi gagal atau galat jaringan = ditolak.
- Tanpa dependensi Composer/npm baru.

## Review Focus

- Cloudflare tidak terjangkau: pendaftaran ditolak dengan pesan, bukan 500. (Tes verifier galat jaringan.)
- Kirim ulang sesudah sukses butuh token baru; widget harus di-reset. (Tes Vitest.)
- Siswa sah mengirim ulang 2 kali tidak terblokir. (Tes batas per email.)
- CSP produksi tidak memblokir widget. (Tes header + cek peramban.)
- Email berbeda huruf besar/kecil dihitung satu alamat. (Tes batas per email.)

### Task 1: Batas per alamat email

**Files:** Modify `app/Domains/Students/Services/StudentSignupService.php`; Test `tests/Feature/Students/StudentSignupTest.php`

**Interfaces:** Produces: `StudentSignupService::MAX_PER_WINDOW = 3`, `WINDOW_SECONDS = 900`.

- [ ] Tes (gagal dulu): 4 POST untuk email yang sama dengan nama berbeda huruf besar/kecil → semua redirect dengan `sent`, `assertSentToTimes(..., 3)`, satu baris user, satu aktivasi. Tes kedua: email lain tidak terpengaruh.
- [ ] Bungkus pembuatan akun + notify dengan `RateLimiter::attempt("siswa-daftar:".sha1($email), MAX, fn, WINDOW)` setelah pemeriksaan akun aktif/non-siswa.
- [ ] Jalankan `vendor/bin/pest tests/Feature/Students/StudentSignupTest.php` → lulus.

### Task 2: Verifikasi Turnstile di server

**Files:** Create `app/Domains/Students/Support/TurnstileVerifier.php`; Modify `config/services.php`, `.env.example`, `StudentSignupRequest.php`, `StudentSignupController.php`; Test `tests/Feature/Students/StudentSignupTurnstileTest.php`

**Interfaces:** Produces: `TurnstileVerifier::enabled(): bool`, `passes(?string $token, ?string $ip): bool`; prop Inertia `turnstile_site_key: ?string`.

- [ ] Tes (gagal dulu) dengan `Http::fake`: nonaktif tanpa secret (POST tanpa token tetap sukses dan tidak memanggil HTTP); aktif: tanpa token → galat `turnstile`, tak ada akun/email; token sah (`success: true`) → sukses dan permintaan siteverify membawa secret, response, remoteip; `success: false` → galat; `ConnectionException` → galat (bukan 500); prop `turnstile_site_key` terisi/null.
- [ ] Implementasi verifier dan aturan request (`Rule::when(enabled)`), konfigurasi, prop.
- [ ] Jalankan tes file ini → lulus.

### Task 3: CSP untuk widget

**Files:** Modify `app/Http/Middleware/SecurityHeaders.php`; Test `tests/Feature/Security/SecurityHeadersTest.php`

- [ ] Tes (gagal dulu): dengan `services.turnstile.site_key` terisi, `GET /register/{token}` memuat `https://challenges.cloudflare.com` di `script-src`, `frame-src`, `connect-src`; `/login` tidak; tanpa site key, rute daftar tidak.
- [ ] Tambahkan sumber itu hanya untuk `register.*` saat site key terisi.

### Task 4: Frontend

**Files:** Create `resources/js/Components/shared/TurnstileWidget.vue`; Modify `resources/js/Pages/Daftar/Index.vue`, `resources/js/Pages/Daftar/Daftar.test.ts`

**Interfaces:** `TurnstileWidget` props `siteKey: string`; emit `update:token(string)`; expose `reset()`.

- [ ] Tes (gagal dulu): tanpa `turnstile_site_key` perilaku lama; dengan site key tombol "Buat akun" nonaktif sampai widget memberi token; post membawa `turnstile`; setelah post selesai widget di-reset dan token dikosongkan; galat `turnstile` tampil di keadaan "Periksa email".
- [ ] Implementasi widget (muat `api.js?render=explicit` sekali, `turnstile.render` dengan `appearance: 'interaction-only'`, `expired-callback` mengosongkan token) dan integrasi di halaman.

### Task 5: Dokumen dan verifikasi

- [ ] `docs/deployment-guide.md`: bagian pengaturan Turnstile (buat widget di dashboard Cloudflare, domain produksi dan UAT, isi `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`, kunci uji untuk lokal).
- [ ] Berkas review: status F-03, tambah F-29 (kuota email gratis dan throttle IP).
- [ ] Pint, PHPStan, Pest penuh, Vitest, vue-tsc, ESLint; cek peramban dengan kunci uji dan CSP menyala.
- [ ] Review ponytail + impeccable, perbaiki temuan valid.
