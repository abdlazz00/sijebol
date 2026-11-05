# Detail Sesi Rekaman Tampilan HP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Halaman `/schedules/{id}` nyaman di HP: tanpa geser menyamping dan status peserta langsung terlihat.

**Architecture:** Hanya perubahan kelas Tailwind dan satu elemen badge di `Jadwal/Show.vue`; tidak ada perubahan backend.

**Tech Stack:** Vue 3, Tailwind, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-05-jadwal-detail-mobile-design.md`

## Global Constraints
- Desktop (md ke atas) tidak berubah.
- `Jadwal/Index.vue` dan `Rekaman.vue` tidak diubah.
- Tanpa dependensi baru.

## Review Focus
- Nol overflow horizontal di 360, 390, 768, 1440 px.
- Tombol tunggal dan tombol berderet tetap rapi (sesi dibatalkan, sesi kosong, sesi berpeserta).
- Nama panjang turun baris di HP (`whitespace-normal`), dipotong hanya dari `md`.

### Task 1: Show.vue
**Files:** Modify `resources/js/Pages/Jadwal/Show.vue`; Test `resources/js/Pages/Jadwal/Jadwal.test.ts`
- [ ] Tes gagal dulu (blok "tampilan HP"): grid header, tombol tunggal, kolom Status `hidden md:table-cell`, badge HP, nama, jumlah peserta.
- [ ] Implementasi kelas dan badge; Vitest, vue-tsc, ESLint lulus.
- [ ] Ukur di peramban nyata (Chrome headless) pada empat lebar dan empat keadaan data.

### Task 2: Dokumen
- [ ] Panduan UI §5.26.
- [ ] Review impeccable (critique 28/40) dan perbaikan temuan yang dipilih pemilik.
