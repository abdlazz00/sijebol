# Izinkan Kamera untuk Pindai Tiket Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, inline). Langkah memakai checkbox (`- [ ]`).

**Goal:** Kamera dapat dibuka di halaman Pindai tiket.

**Architecture:** Header `Permissions-Policy` di `SecurityHeaders` melarang kamera untuk semua origin (`camera=()`), sehingga `getUserMedia` ditolak browser sebelum dialog izin muncul. Ubah menjadi `camera=(self)`; mikrofon dan lokasi tetap dilarang.

**Tech Stack:** Laravel middleware, Pest.

**Spec:** Akar masalah dibuktikan di chat (2026-11-01): UAT mengirim `camera=()`, konsol browser mencatat "Permissions policy violation: camera is not allowed in this document". Spec hardening `2026-10-09-fase-6c1-hardening-design.md` baris 25 memuat nilai lama.

## Global Constraints

- `microphone=()` dan `geolocation=()` tidak berubah.
- Tanpa `Co-Authored-By` pada commit.

## Review Focus

- Header dikirim di semua respons (termasuk halaman error); tes yang sudah ada mencakupnya.

---

### Task 1: camera=(self)

**Files:**
- Modify: `app/Http/Middleware/SecurityHeaders.php:30`
- Test: `tests/Feature/Security/SecurityHeadersTest.php:20`
- Modify: `docs/superpowers/specs/2026-10-09-fase-6c1-hardening-design.md:25`

- [ ] **Step 1: Ubah assert** `Permissions-Policy` di tes menjadi `'camera=(self), microphone=(), geolocation=()'`.
- [ ] **Step 2: Jalankan** `php artisan test --compact tests/Feature/Security/SecurityHeadersTest.php`. Harapan: GAGAL pada assert header.
- [ ] **Step 3: Ubah nilai header** di middleware menjadi `camera=(self), microphone=(), geolocation=()`, dan nilai yang sama di spec baris 25.
- [ ] **Step 4: Jalankan ulang** perintah Step 2. Harapan: LULUS.
