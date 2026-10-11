# Menu Restructure and Seamless Scroll Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyederhanakan struktur pengelompokan menu sidebar dari 8 kategori yang terfragmentasi menjadi 4 kategori alur kerja terpadu (Utama, Pelayanan, Laporan, Sistem & Akses) serta menerapkan seamless invisible scrollbar pada sidebar untuk menghilangkan bilah scrollbar abu-abu native Windows.

**Architecture:** Memperbarui pemetaan array `ITEMS` pada `MenuService.php`, memperbarui tes `MenuTest.php` (Pest), memperbarui kelas Tailwind pada container navigasi `Sidebar.vue` untuk menyembunyikan scrollbar native sambil mempertahankan kemampuan scroll roda mouse, dan memverifikasi tampilan serta aksesibilitasnya.

**Tech Stack:** Laravel, PHP 8.2+, Vue 3, TypeScript, Tailwind CSS, Pest PHP, Vitest.

**Spec:** [`docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`](file:///C:/Users/abdulaziz/Documents/pribadi/SIJEBOL/docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md) (Bagian 4: Layout Shell - Sidebar & Kelompok Menu).

## Global Constraints

- Bahasa UI Bahasa Indonesia baku.
- Dilarang menambahkan baris `Co-Authored-By` pada git commit.
- Item menu tetap disaring otomatis sesuai permission pengguna; grup kosong dibuang.
- Seluruh 456+ Pest tests dan 88+ Vitest tests harus lolos tanpa regresi.

---

### Task 1: TDD Backend MenuService & MenuTest

**Files:**
- Modify: `tests/Feature/Access/MenuTest.php:8-47`
- Modify: `app/Domains/Access/Services/MenuService.php:9-21`

**Interfaces:**
- Consumes: `User` model, Spatie roles & permissions
- Produces: `MenuService::forUser(?User $user): array` dengan grup `['Utama', 'Pelayanan', 'Laporan', 'Sistem & Akses']`

- [ ] **Step 1: Perbarui asersi pengujian di `tests/Feature/Access/MenuTest.php` (RED)**

Sesuaikan asersi pengujian grup pada `tests/Feature/Access/MenuTest.php`:
- Operator sekolah: grup `['Utama', 'Pelayanan']` (berisi Siswa, Pendaftaran, Jadwal).
- Admin kecamatan: grup `['Utama', 'Pelayanan', 'Laporan', 'Sistem & Akses']`.
- Verifikasi keberadaan item di dalam grup barunya.

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (RED)**

Run: `php artisan test --filter=MenuTest`
Expected: FAIL (karena `MenuService` masih memakai nama grup lama).

- [ ] **Step 3: Implementasi 4 kelompok menu di `MenuService.php` (GREEN)**

Edit `app/Domains/Access/Services/MenuService.php`:
```php
    private const ITEMS = [
        ['group' => 'Utama', 'label' => 'Beranda', 'href' => '/dashboard', 'icon' => 'home', 'permission' => null],
        ['group' => 'Pelayanan', 'label' => 'Siswa', 'href' => '/siswa', 'icon' => 'graduation-cap', 'permission' => 'siswa.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Pendaftaran', 'href' => '/pendaftaran', 'icon' => 'clipboard-list', 'permission' => 'pendaftaran.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Antrean verifikasi', 'href' => '/verifikasi', 'icon' => 'badge-check', 'permission' => 'verifikasi.lihat'],
        ['group' => 'Pelayanan', 'label' => 'Jadwal', 'href' => '/jadwal', 'icon' => 'calendar-days', 'permission' => 'jadwal.lihat'],
        ['group' => 'Laporan', 'label' => 'Rekap pendaftaran', 'href' => '/laporan', 'icon' => 'file-spreadsheet', 'permission' => 'laporan.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Sekolah', 'href' => '/sekolah', 'icon' => 'school', 'permission' => 'sekolah.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Pengguna', 'href' => '/pengguna', 'icon' => 'users', 'permission' => 'user.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Role & Permission', 'href' => '/role', 'icon' => 'shield-check', 'permission' => 'role.lihat'],
        ['group' => 'Sistem & Akses', 'label' => 'Log Aktivitas', 'href' => '/log', 'icon' => 'scroll-text', 'permission' => 'log.lihat'],
    ];
```

- [ ] **Step 4: Jalankan pengujian kembali untuk memverifikasi kelulusan (GREEN)**

Run: `php artisan test --filter=MenuTest`
Expected: PASS (5 tests passed).

---

### Task 2: Frontend Sidebar Seamless Scrollbar & Vertical Spacing

**Files:**
- Modify: `resources/js/Layouts/partials/Sidebar.vue:58`
- Test: `resources/js/Layouts/partials/Sidebar.test.ts`

**Interfaces:**
- Consumes: `page.props.menu`
- Produces: Sidebar container dengan kelas `[scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden` dan ritme `space-y-4 pt-4`

- [ ] **Step 1: Tulis unit test untuk memverifikasi kelas seamless scrollbar di `Sidebar.test.ts` (RED)**

Buat file `resources/js/Layouts/partials/Sidebar.test.ts` yang memverifikasi bahwa container menu memiliki kelas scrollbar-hidden dan tidak menampilkan scrollbar default.

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (RED)**

Run: `npx vitest run resources/js/Layouts/partials/Sidebar.test.ts`
Expected: FAIL.

- [ ] **Step 3: Implementasi perubahan kelas di `Sidebar.vue` (GREEN)**

Ubah baris 58 di `resources/js/Layouts/partials/Sidebar.vue`:
```vue
            <div
                class="flex-1 space-y-4 overflow-y-auto px-4 pt-4 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
            >
```

- [ ] **Step 4: Jalankan test untuk memverifikasi kelulusan (GREEN)**

Run: `npx vitest run resources/js/Layouts/partials/Sidebar.test.ts`
Expected: PASS.

---

### Task 3: Verifikasi Suite Penuh

**Files:**
- Test: Seluruh unit & feature test suite

- [ ] **Step 1: Jalankan typecheck TypeScript**

Run: `npm run typecheck`
Expected: Selesai tanpa error tipe.

- [ ] **Step 2: Jalankan seluruh pengujian Vitest**

Run: `npm test`
Expected: Semua 25 test files PASS.

- [ ] **Step 3: Jalankan linting frontend**

Run: `npm run lint`
Expected: Lolos tanpa error/warning.

- [ ] **Step 4: Jalankan backend test suite (Pest)**

Run: `php artisan test --parallel`
Expected: 456 tests PASS (2.653 assertions).

---

### Task 4: Review Impeccable & Ponytail

**Files:**
- Review: `resources/js/Layouts/partials/Sidebar.vue`, `app/Domains/Access/Services/MenuService.php`

- [ ] **Step 1: Jalankan audit kebersihan ponytail (over-engineering check)**
Memastikan solusi minimal, tidak menambahkan dependensi JS eksternal untuk scrollbar.

- [ ] **Step 2: Jalankan audit visual & aksesibilitas impeccable**
Menjalankan `detect.mjs` dan memastikan navigasi keyboard dan tooltip pada sidebar saat collapsed/expanded tetap berfungsi normal.
