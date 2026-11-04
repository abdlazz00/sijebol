# Batas Login Per IP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Membatasi percobaan login gagal per IP (F-08).

**Architecture:** Kunci RateLimiter kedua `login-ip|<ip>` di `LoginRequest`, hanya dinaikkan saat gagal dan tidak pernah direset; angka dari `config/security.php`.

**Tech Stack:** Laravel 13 RateLimiter, Pest.

**Spec:** `docs/superpowers/specs/2026-11-04-batas-login-per-ip-design.md`

## Global Constraints
- Hanya kegagalan yang dihitung; sukses tidak mereset hitungan IP.
- `trustProxies` tidak diubah.
- Tes login yang ada tidak diubah.

## Review Focus
- Satu kelas dengan IP bersama yang login sukses beruntun tidak terkunci.
- Penyerang tidak bisa membersihkan hitungan dengan login ke akunnya.
- IP lain tidak terpengaruh; hitungan kedaluwarsa.

### Task 1: Limiter IP
**Files:** Modify `app/Http/Requests/Auth/LoginRequest.php`, `config/security.php`; Test `tests/Feature/Auth/LoginIpThrottleTest.php`
- [ ] Tes gagal dulu (lima tes, tiga gagal sebelum perbaikan).
- [ ] `ipThrottleKey()`, hit saat gagal dengan jendela dari config, cek di `ensureIsNotRateLimited`.
- [ ] Jalankan `tests/Feature/Auth` dan `LoginRulesTest`: lulus.

### Task 2: Dokumen dan verifikasi
- [ ] Panduan deployment bagian 21 (env, rasional trustProxies, kueri cek IP). Berkas review: status F-08.
- [ ] Pint, PHPStan, Pest penuh; review ponytail.
