# Pengaman Bersihkan Data Siswa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** `sijebol:bersihkan-data-siswa` tidak bisa terjalan di UAT/produksi hanya dengan `--force` (F-11).

**Architecture:** Metode privat `confirmed()` di `ResetStudentData`: lokal memakai konfirmasi lama, selain itu meminta nama lingkungan (prompt atau `--lingkungan`).

**Tech Stack:** Laravel 13 console, Pest.

**Spec:** `docs/superpowers/specs/2026-11-04-pengaman-bersihkan-data-siswa-design.md`

## Global Constraints
- Perilaku di lokal/testing tidak berubah (tes lama tidak diubah).
- Tanpa env atau dependensi baru.

## Review Focus
- `--force` di produksi tidak menghapus apa pun.
- Beda huruf besar/kecil pada nama lingkungan ditolak.
- Mode non-interaktif tanpa `--lingkungan` ditolak.

### Task 1: confirmed()
**Files:** Modify `app/Console/Commands/ResetStudentData.php`; Test `tests/Feature/Console/ResetStudentDataTest.php`
- [ ] Tes gagal dulu (empat tes di blok `di luar lingkungan lokal`), lalu implementasi `confirmed()` dan opsi `--lingkungan=`.
- [ ] Jalankan `ResetStudentDataTest`: delapan tes lulus.

### Task 2: Dokumen dan verifikasi
- [ ] Panduan deployment (bagian "Memulai dari data kosong"), berkas review F-11.
- [ ] Pint, PHPStan, Pest penuh; review ponytail.
