# Pindai Tiket di Desktop: CSP Worker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Pindai tiket berfungsi di desktop/webcam (peramban tanpa `BarcodeDetector`) pada lingkungan ber-CSP, dan kegagalan mesin pemindai tidak lagi senyap.

**Architecture:** Satu direktif CSP baru di `SecurityHeaders` dan penanganan `onDecodeError` di `Pindai.vue`. Tanpa dependensi baru, tanpa perubahan basis data.

**Tech Stack:** Laravel middleware, Pest, Vue 3, qr-scanner, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-07-pindai-csp-worker-design.md`

## Global Constraints
- `script-src` tidak dilonggarkan; hanya `worker-src` ditambah.
- Jangan menyentuh perubahan di working tree yang bukan bagian tugas ini (`package.json`, `package-lock.json`, `routes/web.php`, `docs/manual-book/`, `scripts/*manual*`, `docs/arsitektur-backend.md`).

## Review Focus
- CSP: `worker-src 'self' blob:` ada di semua respons, tidak ada pelonggaran lain.
- `onDecodeError`: tidak memicu pada `NO_QR_CODE_FOUND`; tidak menampilkan pesan ganda; kamera berhenti.

### Task 1: CSP
**Files:** Modify `app/Http/Middleware/SecurityHeaders.php`; Test `tests/Feature/Security/SecurityHeadersTest.php`
- [ ] Tes gagal dulu: CSP memuat `worker-src 'self' blob:`.
- [ ] Tambah direktif; Pest lulus.

### Task 2: Pindai.vue
**Files:** Modify `resources/js/Pages/Jadwal/Pindai.vue`; Test `resources/js/Pages/Jadwal/Pindai.test.ts`
- [ ] Tes gagal dulu: galat mesin → pemindai dihentikan/dihancurkan, pesan tampil, tautan manual ada; `NO_QR_CODE_FOUND` diabaikan.
- [ ] Implementasi `onDecodeError`; Vitest, vue-tsc, ESLint lulus.

### Task 3: Bukti peramban dan review
- [ ] Ulang uji Chrome dengan CSP baru: QR terbaca.
- [ ] Review `ponytail:ponytail-review` (semua) dan `impeccable` (frontend, pesan galat), perbaiki temuan valid.
- [ ] Catat di `docs/deployment-guide.md` bila perlu (CSP berubah); pemilik menguji di UAT.
