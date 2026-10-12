# Ganti Istilah Status Siswa Menjadi Memenuhi Syarat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti seluruh istilah pengguna dari "Eligible" dan "Belum eligible" menjadi Bahasa Indonesia baku "Memenuhi syarat" dan "Belum memenuhi syarat" pada seluruh modul Siswa, Dokumen, dan Pendaftaran.

**Architecture:** Memperbarui label teks, badge status, opsi filter, tombol aksi, serta kalimat deskripsi/bantuan pada komponen Vue di frontend. Properti internal DTO/model (`eligible: boolean`) dan query parameter URL (`filter[eligible]=1`) tetap dipertahankan agar tidak ada perubahan pada kontrak API backend maupun database.

**Tech Stack:** Inertia.js v2, Vue 3, TypeScript, Tailwind CSS v4, Vitest, Pest PHP.

**Spec:** Brainstorming session 2026-10-12 (Penyelarasan Istilah Status Kelayakan Siswa SIJEBOL).

## Global Constraints

- Bahasa UI wajib menggunakan Bahasa Indonesia baku resmi SIJEBOL.
- Dilarang menambahkan baris `Co-Authored-By` pada git commit.
- Kontrak data backend dan query string (`filter[eligible]=1`) tidak boleh diubah agar tidak merusak kompatibilitas API dan pengujian yang ada.
- Seluruh pengujian Vitest dan Pest PHP (461 tests) wajib lulus 100%.

---

## Ponytail Review (Anti-Overengineering & Simplicity)

- **Minimalist Diff:** Tidak mengubah schema basis data, method di repository, atau nama properti di API. Cukup perbarui string representasi yang ditampilkan ke pengguna.
- **No Abstraction Bloat:** Tidak perlu membuat mapper helper baru jika string literal sederhana di template sudah sangat jelas dan mudah dibaca (`row.eligible ? 'Memenuhi syarat' : 'Belum memenuhi syarat'`).
- **Reuse Existing Badges:** Memanfaatkan class utility warna badge yang sudah ada (`bg-success-soft text-success` dan `bg-neutral-soft text-neutral-foreground`).

---

## Impeccable Review (Craft, Layout, & Accessibility)

- **Lebar Kolom & Layout Rhythm:** Teks `"Belum memenuhi syarat"` memiliki panjang 22 karakter. Lebar kolom tabel di `Siswa/Index.vue` diperlebar dari `w-[144px]` menjadi `w-[184px]` serta ditambahkan `whitespace-nowrap` pada badge agar teks tidak patah menjadi dua baris atau terlihat berdesakan.
- **Keterbacaan & Bahasa Baku:** Mengganti kata serapan bahasa Inggris (*Eligible*) menjadi istilah resmi pemerintahan yang mudah dipahami oleh staf kecamatan maupun operator sekolah (*Memenuhi syarat*).
- **A11y & Kontras Warna:** Mempertahankan rasio kontras tinggi pada badge status, serta menjaga atribut `title` dan teks *screen reader* untuk alasan ketidaklayakan dokumen.
- **Konsistensi Tone of Voice:** Teks pendukung ("Pilih semua yang memenuhi syarat", "Hanya siswa yang memenuhi syarat yang dapat dipilih", dll.) diselaraskan agar tidak ada inkonsistensi terminologi di halaman pendaftaran.

---

### Task 1: Pembaruan Teks Antarmuka pada Modul Siswa & Dokumen

**Files:**
- Modify: `resources/js/Pages/Siswa/Index.vue`
- Modify: `resources/js/Pages/Siswa/Show.vue`
- Modify: `resources/js/Components/shared/FileUpload.vue`

**Interfaces:**
- Menampilkan teks "Memenuhi syarat" dan "Belum memenuhi syarat" pada badge status, opsi filter, serta teks konfirmasi dokumen.

- [ ] **Step 1: Perbarui tabel dan filter di `resources/js/Pages/Siswa/Index.vue`**
  - Ubah opsi filter:
    ```ts
    const eligibleOptions = [{ value: '1', label: 'Memenuhi syarat' }];
    ```
  - Ubah lebar kolom status menjadi `w-[184px]`:
    ```ts
    { key: 'eligible', label: 'Status', class: 'w-[184px]' },
    ```
  - Tambahkan `whitespace-nowrap` dan ganti teks badge:
    ```vue
    <span
        class="inline-flex h-6 items-center rounded-full px-2.5 text-xs font-medium whitespace-nowrap"
        :class="
            row.eligible
                ? 'bg-success-soft text-success'
                : 'bg-neutral-soft text-neutral-foreground'
        "
        :title="row.eligible ? undefined : row.reasons.join(' · ')"
    >
        {{ row.eligible ? 'Memenuhi syarat' : 'Belum memenuhi syarat' }}
    </span>
    ```

- [ ] **Step 2: Perbarui badge profil dan deskripsi dokumen di `resources/js/Pages/Siswa/Show.vue`**
  - Ubah badge profil:
    ```vue
    <span
        class="inline-flex h-6 items-center rounded-full px-2.5 text-xs font-medium whitespace-nowrap"
        :class="
            eligibility.eligible
                ? 'bg-success-soft text-success'
                : 'bg-neutral-soft text-neutral-foreground'
        "
    >
        {{
            eligibility.eligible
                ? 'Memenuhi syarat'
                : 'Belum memenuhi syarat'
        }}
    </span>
    ```
  - Ubah petunjuk kartu Dokumen:
    ```html
    <p class="text-muted-foreground text-xs leading-4">
        Kartu Keluarga dan Akta Kelahiran wajib diunggah agar siswa memenuhi syarat.
    </p>
    ```

- [ ] **Step 3: Perbarui pesan konfirmasi di `resources/js/Components/shared/FileUpload.vue`**
  - Ubah deskripsi `ConfirmDialog`:
    ```vue
    description="Berkas akan dihapus permanen dan siswa dapat menjadi belum memenuhi syarat."
    ```

---

### Task 2: Pembaruan Teks Antarmuka pada Modul Pendaftaran & Pengujian

**Files:**
- Modify: `resources/js/Components/shared/StudentMultiSelect.vue`
- Modify: `resources/js/Pages/Pendaftaran/Form.vue`
- Modify: `resources/js/Pages/Pendaftaran/Index.vue`
- Modify: `resources/js/Components/shared/StudentMultiSelect.test.ts`

**Interfaces:**
- Menyelaraskan teks tombol pemilih siswa, info pendaftaran, deskripsi halaman pendaftaran, dan pengujian Vitest.

- [ ] **Step 1: Perbarui tombol pemilih di `resources/js/Components/shared/StudentMultiSelect.vue`**
  - Ganti teks tombol:
    ```html
    <Button
        type="button"
        variant="outline"
        size="sm"
        class="h-9"
        @click="selectAllEligible"
    >
        Pilih semua yang memenuhi syarat di halaman ini
    </Button>
    ```

- [ ] **Step 2: Perbarui teks peringatan di `resources/js/Pages/Pendaftaran/Form.vue`**
  - Ganti teks info:
    ```html
    <InlineAlert variant="info">
        Pilih siswa yang akan didaftarkan pada perekaman ini. Hanya siswa yang memenuhi syarat yang dapat dipilih.
    </InlineAlert>
    ```

- [ ] **Step 3: Perbarui deskripsi di `resources/js/Pages/Pendaftaran/Index.vue`**
  - Ganti deskripsi header:
    ```html
    <PageHeader
        title="Pendaftaran"
        description="Kelompokkan siswa yang memenuhi syarat untuk diajukan ke verifikasi."
    >
    ```
  - Ganti deskripsi empty state:
    ```html
    <EmptyState
        :icon="FileCheck"
        title="Belum ada pendaftaran"
        description="Buat pendaftaran untuk mengelompokkan siswa yang memenuhi syarat dan mengajukannya ke verifikasi."
    >
    ```

- [ ] **Step 4: Perbarui pengujian unit di `resources/js/Components/shared/StudentMultiSelect.test.ts`**
  - Sesuaikan judul dan ekspektasi teks test:
    ```ts
    it('"Pilih semua yang memenuhi syarat di halaman ini" menambah yang memenuhi syarat ke pilihan yang ada', async () => {
    ```

---

### Task 3: Verifikasi Menyeluruh & Quality Gates

**Files:**
- Audit & Test All

- [ ] **Step 1: Jalankan seluruh test frontend Vitest**
  Run: `npx vitest run`
  Expected: Seluruh 26 test file / 94 test lulus (100% PASS).

- [ ] **Step 2: Jalankan build frontend**
  Run: `npm run build`
  Expected: Typecheck `vue-tsc` dan bundle Vite berhasil tanpa error.

- [ ] **Step 3: Jalankan seluruh test backend Pest**
  Run: `php artisan test --parallel`
  Expected: 461 / 461 tests PASS (0 failures).

- [ ] **Step 4: Jalankan Larastan & Pint**
  Run: `vendor/bin/phpstan analyse` dan `vendor/bin/pint --test`
  Expected: 0 error dan lolos formatting.
