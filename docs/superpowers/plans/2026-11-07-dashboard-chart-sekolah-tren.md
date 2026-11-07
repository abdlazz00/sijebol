# Beranda: Bar Chart Semua Sekolah dan Hitungan Tren Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Bar chart sekolah menampilkan semua sekolah; garis "Pendaftar" di tren menghitung siswa unik non-gugur sekali pada bulan pendaftaran pertamanya; rentang bulan tren tidak menampilkan bulan kosong yang tidak berguna.

**Architecture:** Perubahan di `DashboardService::charts()` (query agregat) dan `DashboardCharts.vue` (bar horizontal). Tanpa migrasi, tanpa dependensi baru.

**Tech Stack:** Laravel/Eloquent, Pest, Vue 3, ApexCharts, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-07-dashboard-chart-sekolah-tren-design.md`

## Global Constraints
- Tampilan operator sekolah ("Kesiapan Berkas") tidak berubah.
- Donut dan KPI tidak diubah.
- Garis "Sudah Rekam" tidak diubah (hanya ikut terpotong rentang bulan).
- Jangan menyentuh perubahan di working tree yang bukan bagian tugas ini (perbaikan CSP/Pindai belum di-commit; `package.json`, `package-lock.json`, `routes/web.php`, `docs/manual-book/`, `scripts/*manual*`, `docs/arsitektur-backend.md`).

## Review Focus
- Siswa unik: tidak ada hitungan ganda; tahun lain dan pendaftaran gugur tidak ikut.
- Query tetap agregat (tidak memuat semua baris); ONLY_FULL_GROUP_BY aman di MySQL.
- Operator sekolah: tren tetap dibatasi sekolahnya (ScopedBySchool).
- Kartu bar: tidak membengkakkan tinggi baris grafik; area gulir bisa diakses keyboard.

### Task 1: Backend (`DashboardService::charts`)
**Files:** Modify `app/Domains/Reports/Services/DashboardService.php`; Test `tests/Feature/Reports/DashboardSummaryTest.php`
- [ ] Tes gagal dulu: tren Pendaftar (siswa unik, bulan pertama, gugur/tahun lain diabaikan), rentang bulan, tanpa data, bar semua sekolah.
- [ ] Ubah tes lama yang mengunci definisi lama ("Terdaftar", 12 bulan).
- [ ] Implementasi; Pest, PHPStan, Pint lulus.

### Task 2: Frontend (`DashboardCharts.vue`)
**Files:** Modify `resources/js/Components/dashboard/DashboardCharts.vue`, `resources/js/types/dashboard.ts` (bila perlu); Test `resources/js/Components/dashboard/DashboardComponents.test.ts`
- [ ] Tes gagal dulu: bar staf horizontal + area gulir berlabel + tinggi mengikuti jumlah kategori; operator tetap vertikal.
- [ ] Implementasi; Vitest, vue-tsc, ESLint lulus.
- [ ] Ukur di Chrome nyata (data lokal 8 sekolah dan data banyak).

### Task 3: Review dan dokumen
- [ ] `ponytail:ponytail-review` dan `impeccable` critique pada kartu bar; perbaiki temuan valid.
- [ ] Perbarui `docs/superpowers/specs/2026-10-13-revamp-dashboard-design.md` bagian C (catatan perubahan) dan panduan UI bila ada bagian dasbor.
