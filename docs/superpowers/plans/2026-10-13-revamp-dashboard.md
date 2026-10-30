# Rencana Implementasi: Revamp Beranda / Dashboard SIJEBOL

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengubah Beranda SIJEBOL menjadi Pusat Kendali Operasional Terpadu modern dengan 5 KPI Cards, Bilah Aksi Cepat instan, 3 visualisasi ApexCharts (Bar, Donut, Line tren tahunan), Tabel Jadwal Terdekat, Notifikasi Terkini, dan Mini Kalender interaktif multi-role.

**Architecture:** Memperluas `DashboardService` dan `ScheduleService` untuk menyajikan data agregasi analitik efisien (KPI, distribusi status, rekap sekolah, tren tahunan Jan–Des, dan agenda kalender). Di sisi frontend, menambahkan library `apexcharts` dengan komponen client-wrapper yang aman dari SSR, serta memecah komponen Beranda ke dalam modul-modul fokus (`DashboardKpiCards`, `DashboardQuickActions`, `DashboardCharts`, `DashboardUpcomingSchedules`, `DashboardMiniCalendar`).

**Tech Stack:** Laravel 12, PHP 8.4+, Pest PHP, Vue 3, Inertia.js, Tailwind CSS v4, Reka UI, ApexCharts (`apexcharts`, `vue3-apexcharts`), Vitest.

**Spec:** [`docs/superpowers/specs/2026-10-13-revamp-dashboard-design.md`](file:///C:/Users/abdulaziz/Documents/pribadi/SIJEBOL/docs/superpowers/specs/2026-10-13-revamp-dashboard-design.md)

## Global Constraints

- Dilarang menyertakan `Co-Authored-By` pada git commit (`CLAUDE.md`).
- Multi-tenant school data isolation: Operator sekolah hanya dapat melihat data sekolahnya sendiri (`school_id`).
- Permission guard pada semua tombol Aksi Cepat.
- Komponen ApexCharts harus dibungkus dengan aman agar tidak error saat kompilasi SSR atau lingkungan testing `jsdom`.
- Menjaga accessibility (ARIA labels pada kalender dan tombol aksi) serta dukungan Dark Mode.
- Seluruh quality gates harus lulus 100%: Pest PHP (parallel), PHPStan level 8 (0 errors), Laravel Pint, Vitest (semua file lulus), dan `npm run build`.

---

### Task 1: Pemasangan Dependensi & Wrapper ApexCharts SSR-Safe

**Files:**
- Modify: `package.json`
- Create: `resources/js/Components/shared/ApexChart.vue`
- Create: `resources/js/Components/shared/ApexChart.test.ts`

**Interfaces:**
- Consumes: `type: 'bar' | 'donut' | 'line' | 'area'`, `options: ApexOptions`, `series: ApexAxisChartSeries | ApexNonAxisChartSeries`, `height: number | string`
- Produces: Komponen `<ApexChart>` yang hanya dimuat saat di browser (`window !== undefined` dan `onMounted`), serta fallback skeleton saat belum siap.

- [ ] **Step 1: Pasang dependensi `apexcharts` dan `vue3-apexcharts`**
  - Jalankan `npm install apexcharts vue3-apexcharts`
- [ ] **Step 2: Tulis pengujian unit di `ApexChart.test.ts`**
  - Uji rendering komponen wrapper tanpa crash di lingkungan Vitest (mocked browser check).
- [ ] **Step 3: Implementasikan komponen `ApexChart.vue`**
  - Gunakan `defineAsyncComponent` atau dynamic import di `onMounted` agar tidak memicu error SSR.
  - Tambahkan cleanup event/instance pada `onBeforeUnmount`.
- [ ] **Step 4: Jalankan Vitest untuk memastikan lulus**
  - Jalankan `npx vitest run resources/js/Components/shared/ApexChart.test.ts`
- [ ] **Step 5: Commit perubahan Task 1**

---

### Task 2: Backend Data Provider (DashboardService & TypeScript Types)

**Files:**
- Modify: `resources/js/types/dashboard.ts`
- Modify: `app/Domains/Reports/Services/DashboardService.php`
- Modify: `app/Domains/Schedules/Services/ScheduleService.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `tests/Feature/Reports/DashboardSummaryTest.php`
- Modify: `tests/Feature/Schedules/DashboardTest.php`

**Interfaces:**
- Consumes: `User $user`, `RegistrationStudent`, `Schedule`, `School`
- Produces: Payload Inertia Beranda yang diperluas:
  - `kpi`: `{ total, scheduled, scheduled_pct, recorded, recorded_pct, in_process, in_process_pct, unscheduled, unscheduled_pct }`
  - `quick_actions`: `list<{ label, href, icon, color, permission }>`
  - `charts`:
    - `school_recap`: `{ categories: string[], series: number[] }` (khusus admin) atau `{ categories: string[], series: number[] }` (dokumen operator)
    - `recording_status`: `{ labels: string[], series: number[], total: number }`
    - `monthly_trend`: `{ months: string[], registered: number[], recorded: number[] }` (Jan–Des tahun berjalan)
  - `schedules_table`: `list<{ id, date, school_name, participants, location, status }>`
  - `calendar_events`: `list<{ id, date, school_name, participants, location, status }>`

- [ ] **Step 1: Perbarui definisi tipe TypeScript di `resources/js/types/dashboard.ts`**
- [ ] **Step 2: Tulis failing test di `DashboardSummaryTest.php` dan `DashboardTest.php`**
  - Uji struktur baru payload `kpi`, `charts`, `quick_actions`, dan `calendar_events` untuk Admin.
  - Uji pemisahan data khusus untuk Operator Sekolah.
- [ ] **Step 3: Implementasikan query agregasi di `DashboardService.php` dan `ScheduleService.php`**
  - Buat method `kpiSummary(User $user)`.
  - Buat method `chartData(User $user)`.
  - Buat method `quickActions(User $user)`.
  - Buat method `calendarEvents(?int $schoolId = null, ?int $year = null, ?int $month = null)` di `ScheduleService`.
- [ ] **Step 4: Jalankan tes Pest untuk memastikan lulus**
  - Jalankan `php artisan test tests/Feature/Reports/DashboardSummaryTest.php tests/Feature/Schedules/DashboardTest.php`
- [ ] **Step 5: Jalankan PHPStan dan Pint**
- [ ] **Step 6: Commit perubahan Task 2**

---

### Task 3: Pembuatan Komponen Visual Dashboard

**Files:**
- Create: `resources/js/Components/dashboard/DashboardKpiCards.vue`
- Create: `resources/js/Components/dashboard/DashboardQuickActions.vue`
- Create: `resources/js/Components/dashboard/DashboardCharts.vue`
- Create: `resources/js/Components/dashboard/DashboardUpcomingTable.vue`
- Create: `resources/js/Components/dashboard/DashboardMiniCalendar.vue`
- Create: `resources/js/Components/dashboard/DashboardCalendarDialog.vue`
- Create: `resources/js/Components/dashboard/DashboardComponents.test.ts`

**Interfaces:**
- Consumes: Props dari payload `DashboardService`
- Produces: Komponen visual mandiri, modular, dan teruji.

- [ ] **Step 1: Buat `DashboardKpiCards.vue`**
  - 5 Card dengan latar pastel lembut: Biru, Hijau, Kuning Emas, Merah Muda, Slate.
  - Tampilkan angka format ribuan (`1.248`), label, dan persentase progress.
- [ ] **Step 2: Buat `DashboardQuickActions.vue`**
  - Bilah horizontal bertajuk `⚡ Aksi Cepat`.
  - Desain tombol interaktif berwarna tegas/solid dengan ikon (Biru, Hijau, Kuning/Oranye, Merah) yang sangat kontras dengan kartu KPI.
- [ ] **Step 3: Buat `DashboardCharts.vue`**
  - Mengintegrasikan 3 chart ApexCharts:
    1. Bar Chart: Rekap Pendaftaran per Sekolah (palette warna serasi).
    2. Donut Chart: Status Perekaman dengan total count di tengah dan legenda persentase.
    3. Line Chart: Tren Perekaman Jan–Des dengan 2 kurva halus dan area gradient.
- [ ] **Step 4: Buat `DashboardUpcomingTable.vue`**
  - Tabel ringkas jadwal terdekat dengan badge status ("Hari Ini" bernuansa biru berdenyut, "Terlaksana" hijau, "Dijadwalkan" kuning) dan tautan "Lihat Semua".
- [ ] **Step 5: Buat `DashboardMiniCalendar.vue` & `DashboardCalendarDialog.vue`**
  - Kalender mini interaktif 7 kolom dengan tombol navigasi bulan (`<` dan `>`).
  - Highlight warna pada tanggal yang memiliki kegiatan.
  - Hover: Tooltip nama sekolah & kuota.
  - Klik tanggal: Dialog popover rincian jadwal kegiatan pada tanggal tersebut.
- [ ] **Step 6: Tulis tes unit di `DashboardComponents.test.ts` dan jalankan Vitest**
- [ ] **Step 7: Commit perubahan Task 3**

---

### Task 4: Integrasi Halaman Utama `Dashboard.vue` & Multi-Role Layout

**Files:**
- Modify: `resources/js/Pages/Dashboard.vue`
- Create: `resources/js/Pages/Dashboard.test.ts`

**Interfaces:**
- Consumes: Data props dari `DashboardController`
- Produces: Tampilan Beranda lengkap yang adaptif untuk Staf Kecamatan dan Operator Sekolah.

- [ ] **Step 1: Perbarui `resources/js/Pages/Dashboard.vue`**
  - Ganti susunan lama dengan layout baru:
    1. Header ucapan salam & tanggal.
    2. Section 1: `DashboardKpiCards`.
    3. Section 2: `DashboardQuickActions` (Opsi B - tepat di bawah KPI).
    4. Section 3: `DashboardCharts` (3 grafik ApexCharts).
    5. Section 4: Grid 2 Kolom Asimetris (Kiri: `DashboardUpcomingTable` ~60%, Kanan: Notifikasi Terbaru & `DashboardMiniCalendar` ~40%).
- [ ] **Step 2: Uji rendering untuk role Staf Kecamatan dan Operator Sekolah**
  - Pastikan grafik dan aksi cepat beradaptasi sesuai role.
- [ ] **Step 3: Tulis dan jalankan tes integrasi di `Dashboard.test.ts`**
  - Jalankan `npx vitest run resources/js/Pages/Dashboard.test.ts`
- [ ] **Step 4: Commit perubahan Task 4**

---

### Task 5: Review Ponytail & Impeccable + Verifikasi Menyeluruh

**Files:**
- Code review across all modified & created files

- [ ] **Step 1: Review Ponytail (Efisiensi & Anti-Bloat)**
  - Pastikan tidak ada query N+1 pada `DashboardService`.
  - Pastikan event listeners dan ApexCharts instances di-destroy dengan bersih pada unmount.
- [ ] **Step 2: Review Impeccable (Estetika & Aksesibilitas)**
  - Cek kontras warna teks terhadap latar pastel kartu KPI.
  - Cek perbedaan visual antara tombol aksi dan kartu metrik.
  - Cek responsivitas pada resolusi mobile (390px), tablet (768px), dan desktop (1440px).
  - Cek kompatibilitas dark mode.
- [ ] **Step 3: Jalankan seluruh Quality Gates**
  - `php artisan test --parallel` (semua tes fitur dan unit PHP lolos).
  - `vendor/bin/pint --test` (formatting rapi).
  - `vendor/bin/phpstan analyse` (0 errors).
  - `npx vitest run` (semua unit test frontend lolos).
  - `npm run build` (build aset produksi sukses tanpa kendala).
- [ ] **Step 4: Final commit untuk Task 5**
