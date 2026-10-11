# Sticky Header Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menjadikan header navigasi atas (`Topbar.vue`) berposisi sticky di bagian atas layar agar tetap terlihat dan dapat diakses saat pengguna menggulung (*scroll*) konten halaman ke bawah.

**Architecture:** Menambahkan kelas `sticky top-0 z-20` pada tag `<header>` di komponen `Topbar.vue`. Memastikan latar belakang solid `bg-card` dan garis batas `border-b` mencegah konten di belakangnya tembus pandang, serta memastikan z-index tidak menutupi komponen modal, dialog, atau drawer mobile (`z-50`).

**Tech Stack:** Vue 3, TypeScript, Tailwind CSS, Vitest, Pest PHP.

**Spec:** [`docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`](file:///C:/Users/abdulaziz/Documents/pribadi/SIJEBOL/docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md) (Bagian 4: Layout Shell).

## Global Constraints

- Bahasa UI Bahasa Indonesia baku.
- Dilarang menambahkan baris `Co-Authored-By` pada git commit.
- Topbar harus tetap berada di bawah komponen dialog/modal/sheet mobile drawer (`z-50`).
- Semua pengujian Vitest dan Pest PHP harus tetap 100% lulus tanpa regresi.

---

### Task 1: TDD Pengujian dan Implementasi Sticky Topbar

**Files:**
- Modify: `resources/js/Layouts/partials/Topbar.vue:32-34`
- Test: `resources/js/Layouts/AppLayout.test.ts`

**Interfaces:**
- Consumes: Komponen `Topbar.vue` yang dirender di dalam `AppLayout.vue`
- Produces: Tag `<header>` dengan kelas `sticky top-0 z-20`

- [ ] **Step 1: Tulis asersi tes baru di `AppLayout.test.ts` untuk memverifikasi kelas sticky header**

Tambahkan pengujian pada `resources/js/Layouts/AppLayout.test.ts`:
```ts
    it('memiliki header Topbar yang berposisi sticky di bagian atas dengan z-index aman', () => {
        const wrapper = mount(AppLayout, {
            props: { title: 'Test Sticky Header' },
        });

        const header = wrapper.find('header');
        expect(header.exists()).toBe(true);
        expect(header.classes()).toContain('sticky');
        expect(header.classes()).toContain('top-0');
        expect(header.classes()).toContain('z-20');
    });
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan awal (RED)**

Run: `npx vitest run resources/js/Layouts/AppLayout.test.ts`
Expected: FAIL (karena `header` belum memiliki kelas `sticky top-0 z-20`).

- [ ] **Step 3: Implementasi perubahan kelas di `Topbar.vue` (GREEN)**

Edit `resources/js/Layouts/partials/Topbar.vue` baris 32-34:
```vue
    <header
        class="bg-card sticky top-0 z-20 flex h-16 items-center justify-between border-b px-4 md:px-6"
    >
```

- [ ] **Step 4: Jalankan unit test kembali (GREEN verification)**

Run: `npx vitest run resources/js/Layouts/AppLayout.test.ts`
Expected: PASS (2 tests passed).

---

### Task 2: Verifikasi Regresi Suite Penuh

**Files:**
- Test: Seluruh unit/feature test suite

- [ ] **Step 1: Jalankan typecheck TypeScript**

Run: `npm run typecheck`
Expected: Selesai tanpa error tipe.

- [ ] **Step 2: Jalankan seluruh pengujian Vitest**

Run: `npm test`
Expected: Semua 24 test file (88 tests) PASS.

- [ ] **Step 3: Jalankan linting frontend**

Run: `npm run lint`
Expected: Lolos tanpa peringatan atau error ESLint.

- [ ] **Step 4: Jalankan backend test suite (Pest)**

Run: `php artisan test --parallel`
Expected: 456 tests PASS (2.653 assertions).

---

### Task 3: Review Impeccable & Ponytail

**Files:**
- Review: `resources/js/Layouts/partials/Topbar.vue`

- [ ] **Step 1: Jalankan audit kebersihan ponytail (over-engineering check)**
Memastikan solusi minimal, tidak menambahkan wrapper div atau styling tambahan yang rumit.

- [ ] **Step 2: Jalankan audit visual & aksesibilitas impeccable**
Menjalankan `detect.mjs` dan memastikan layering visual (z-index) bersahabat dengan dropdown, tooltip, dialog, dan sheet drawer.
