# feat: revamp beranda operasional sijebol dengan apexcharts, kpi komprehensif, dan kalender interaktif

## Overview

Pull Request ini mengimplementasikan pembaruan menyeluruh (*revamp*) pada antarmuka Beranda (**Dashboard**) SIJEBOL untuk bertransformasi menjadi pusat pemantauan operasional (*Operational Command Center*). Pembaruan ini mengintegrasikan visualisasi analitik interaktif berbasis **ApexCharts**, kartu KPI komprehensif, bilah aksi cepat, tabel jadwal operasional, serta kalender mini kegiatan jemput bola dengan pemisahan konteks multi-peran (*Staf Kecamatan* vs *Operator Sekolah*).

---

## Changes

### 1. Frontend & Visual Analytics
* **ApexCharts SSR-Safe Wrapper:** Mengintegrasikan pustaka `apexcharts` dan `vue3-apexcharts` melalui komponen pembungkus asinkron (`ApexChart.vue`) guna memastikan kompatibilitas penuh dengan *server-side rendering* Inertia.js.
* **5 KPI Cards Modular (`DashboardKpiCards.vue`):** Kartu metrik dengan palet warna terpisah, format angka ribuan, dan indikator persentase progres.
* **Quick Actions Bar (`DashboardQuickActions.vue`):** Penempatan bilah aksi cepat horizontal tepat di bawah kartu KPI dengan tombol berkontras tinggi dan penyaringan berbasis *permissions*.
* **Tiga Panel Grafik Analitik (`DashboardCharts.vue`):**
  * *Bar Chart:* Rekap pendaftar per sekolah (Staf Kecamatan) atau distribusi kelengkapan berkas (Operator Sekolah).
  * *Donut Chart:* Komposisi status perekaman KTP-el (*Sudah Rekam*, *Dijadwalkan*, *Belum Dijadwalkan*) dengan counter total di tengah lingkaran.
  * *Line/Area Chart:* Tren pendaftaran dan perekaman tahunan (Januari–Desember) tahun berjalan.
* **Tabel Operasional Terdekat (`DashboardUpcomingTable.vue`):** Menampilkan daftar agenda terdekat beserta indikator status (*Hari Ini*, *Terlaksana*, *Dijadwalkan*).
* **Kalender Kegiatan Interaktif (`DashboardMiniCalendar.vue` & `DashboardCalendarDialog.vue`):** Kalender bulanan 7 kolom dengan penanda tanggal kegiatan, tooltip sekolah, dan dialog popover rincian agenda.
* **Integrasi Halaman Utama (`Dashboard.vue`):** Rekonstruksi layout vertikal responsif dan tata letak 2 kolom asimetris (60% operasional kiri : 40% notifikasi & kalender kanan).

### 2. Backend & Data Layer
* **Ekspansi `DashboardService`:**
  * Penambahan metode `kpiSummary()`, `quickActions()`, dan `charts()` dengan agregasi SQL teroptimasi tanpa risiko N+1 query.
  * Pengelompokan tren bulanan secara database-agnostik pada layer service untuk portabilitas SQLite (pengujian) dan MySQL (produksi).
* **Ekspansi `ScheduleService`:**
  * Penambahan metode `upcomingTable()` dan `calendarEvents()` untuk penyediaan data jadwal terdekat dan kalender agenda.
* **Model Relationship:** Menambahkan relasi `registrationEntries` dan `students` pada model `School`.

---

## Role Differentiation Matrix

| Komponen | Staf / Admin Kecamatan | Operator Sekolah |
|---|---|---|
| **KPI Metrics** | Data agregat se-Kecamatan Sungai Beduk | Data terisolasi khusus sekolah terkait (`school_id`) |
| **Bar Chart** | Top 5 Sekolah Pendaftar Terbanyak + Kategori *Lainnya* | Kesiapan Berkas Siswa (*KK & Akta*, *Hanya KK*, *Hanya Akta*, *Belum Ada*) |
| **Bilah Aksi** | Aksi operasional kecamatan (*Buat Jadwal*, *Input Hasil*, dll.) | Aksi administrasi sekolah (*Tambah Siswa*, *Ajukan Pendaftaran*, dll.) |
| **Jadwal & Kalender** | Seluruh jadwal kegiatan se-kecamatan | Jadwal pelayanan khusus sekolah terkait |

---

## Quality Gates & Verification

Semua pemeriksaan kualitas dan pengujian otomatis telah dijalankan dan dinyatakan lulus 100%:

| Tool | Target | Status | Detail |
|---|---|---|---|
| **Pest PHP** | Backend Unit & Feature Tests | ✅ PASSED | 465 passed (2.714 assertions) |
| **Vitest** | Frontend Component & Page Tests | ✅ PASSED | 29 files passed (107/107 tests) |
| **PHPStan** | Static Analysis (Level 8) | ✅ PASSED | 0 errors |
| **Laravel Pint** | Code Formatting & Standards | ✅ PASSED | All files PSR-compliant |
| **TypeScript / Vite** | `npm run build` | ✅ PASSED | Build & typecheck sukses |

---

## Dependency Updates

* `apexcharts`: `^8.1.0`
* `vue3-apexcharts`: `^1.11.3`
