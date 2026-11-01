# Konten Fluid di Layar Lebar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, inline) untuk mengerjakan rencana ini. Langkah memakai checkbox (`- [ ]`).

**Goal:** Area konten mengikuti lebar layar tanpa batas 1600 px (tidak ada ruang kosong di kanan pada monitor 2560 px), dan komponen dashboard yang terlalu renggang di layar lebar dirapikan.

**Architecture:** Hapus `max-w-[1600px]` dari `<main id="konten">` di `AppLayout.vue`. Kalender mini berhenti memakai sel `aspect-square` (tingginya ikut lebar kolom) dan memakai tinggi tetap. Kolom kanan dasbor (notifikasi + kalender) menjadi dua kolom saat kolomnya sendiri lebar, lewat container query Tailwind v4 (`@container`, `@3xl:`), bukan breakpoint viewport, supaya tetap benar saat sidebar dilipat.

**Tech Stack:** Vue 3, Tailwind v4, Vitest.

**Spec:** Disepakati di chat (analisis layout 2026-10-31, pilihan C); panduan UI `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` §4 dan §6; melanjutkan `2026-10-10-content-area-layout-polish.md`.

## Global Constraints

- Form tetap `max-w-[720px]` dan rata kiri; tabel, matriks permission, dan kartu metrik memakai lebar penuh area.
- Padding konten tetap 24 px (`sm:p-6`) dan 16 px (`p-4`); tanpa `mx-auto` (rata kiri, tidak terpusat).
- Tampilan di bawah 1536 px tidak boleh berubah selain tinggi sel kalender.
- Tanpa `Co-Authored-By` pada commit.

## Review Focus

- Kalender di kolom 900 px tidak boleh menjadi setinggi 900 px (sel `aspect-square`): dijaga lewat tinggi sel tetap.
- Sidebar dilipat (72 px) menambah lebar konten: container query merespons lebar kolom, bukan viewport.

---

### Task 1: Lepas batas lebar di AppLayout

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue:71`
- Test: `resources/js/Layouts/AppLayout.test.ts` (tes `memiliki kontainer main ...`)

- [ ] **Step 1: Ubah tes**: ganti judul menjadi `... dan kelas fluid tanpa batas lebar`, dan ganti `expect(main.classes()).toContain('max-w-[1600px]')` dengan `expect(main.classes().some((c) => c.startsWith('max-w-'))).toBe(false)`; pertahankan assert `not.toContain('mx-auto')` dan `toContain('w-full')`.
- [ ] **Step 2: Jalankan** `npx vitest run resources/js/Layouts/AppLayout.test.ts`. Harapan: GAGAL.
- [ ] **Step 3: Hapus** kelas `max-w-[1600px]` dari `<main>`.
- [ ] **Step 4: Jalankan ulang** perintah Step 2. Harapan: LULUS.

### Task 2: Rapikan dasbor untuk layar lebar

**Files:**
- Modify: `resources/js/Components/dashboard/DashboardMiniCalendar.vue` (sel hari, `aspect-square` → `h-10`)
- Modify: `resources/js/Pages/Dashboard.vue` (kolom kanan: pembungkus `@container lg:col-span-5`, isi `flex flex-col gap-6 @3xl:grid @3xl:grid-cols-2 @3xl:items-start`)

- [ ] **Step 1: Ubah sel hari kalender** dari `flex aspect-square items-center justify-center p-0.5` menjadi `flex h-10 items-center justify-center p-0.5`.
- [ ] **Step 2: Ubah kolom kanan `Dashboard.vue`**: `<div class="flex flex-col gap-6 lg:col-span-5">` menjadi pembungkus `<div class="@container lg:col-span-5">` berisi `<div class="flex flex-col gap-6 @3xl:grid @3xl:grid-cols-2 @3xl:items-start">`; isi (kartu notifikasi dan `DashboardMiniCalendar`) tidak berubah. Rapikan indentasi dengan `npx prettier --write resources/js/Pages/Dashboard.vue` hanya bila berkas itu sudah bersih sebelum diubah.
- [ ] **Step 3: Jalankan** `npx vitest run` (seluruh Vitest) dan `npm run typecheck`. Harapan: lulus semua.

### Task 3: Perbarui panduan UI

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (§4 butir **Konten**, §6 butir **Responsif**)

- [ ] **Step 1: §4 Konten**: ganti "dengan batas maksimal longgar 1600 px pada monitor lebar" menjadi "tanpa batas lebar maksimum (diverifikasi sampai 2560 px)"; tambahkan satu kalimat bahwa komponen yang tidak nyaman terlalu lebar membatasi dirinya sendiri (form 720 px; kolom kanan dasbor menjadi dua kolom lewat container query).
- [ ] **Step 2: §6 Responsif**: tambahkan "diperiksa juga di 1920 dan 2560" setelah kalimat lebar kerja 1440 dan 390.

### Task 4: Verifikasi

- [ ] **Step 1:** `npm run typecheck`, `npx vitest run`, `npm run build`. Harapan: lulus; lint hanya untuk berkas yang diubah.
- [ ] **Step 2:** Cek visual dasbor di 1440, 1920, dan 2560 px (kartu KPI, grafik, kalender, notifikasi) bila browser otomatis tersedia; bila tidak, laporkan apa adanya.
