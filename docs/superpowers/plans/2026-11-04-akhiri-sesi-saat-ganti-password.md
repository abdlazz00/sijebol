# Akhiri Sesi Saat Ganti Password Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Ganti atau reset password mengakhiri sesi di perangkat lain (F-07).

**Architecture:** `SessionService` (`endOthers`, `endAll`) mengambil alih logika dari `ProfileSessionController`; `PasswordController` dan `NewPasswordController` memanggilnya.

**Tech Stack:** Laravel 13 (sesi database), Pest.

**Spec:** `docs/superpowers/specs/2026-11-04-akhiri-sesi-saat-ganti-password-design.md`

## Global Constraints
- Perilaku `ProfileSessionController` dan tesnya tidak berubah.
- Tanpa migrasi dan dependensi baru.

## Review Focus
- Cookie "Ingat saya" perangkat saat ini tetap valid setelah token diputar (lewat `Auth::logoutOtherDevices`).
- Percobaan gagal (password lama salah, token reset salah) tidak mengeluarkan siapa pun.
- Sesi pengguna lain tidak tersentuh.

### Task 1: SessionService dan pemakaiannya
**Files:** Create `app/Domains/Access/Services/SessionService.php`; Modify `ProfileSessionController.php`, `Auth/PasswordController.php`, `Auth/NewPasswordController.php`; Test `tests/Feature/Auth/SessionRevocationTest.php`
- [ ] Tes gagal dulu: empat tes di `SessionRevocationTest` (dua gagal sebelum perbaikan).
- [ ] `endOthers(User, Request, string): void`, `endAll(User): void`; controller memakainya.
- [ ] Jalankan `tests/Feature/Auth`, `ProfileSessionTest`: lulus.

### Task 2: Dokumen dan verifikasi
- [ ] Panduan deployment: catatan perilaku dan syarat `SESSION_DRIVER=database`. Berkas review: status F-07.
- [ ] Pint, PHPStan, Pest penuh; review ponytail.
