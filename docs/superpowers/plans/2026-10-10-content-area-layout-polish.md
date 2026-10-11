# Content Area Layout Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghilangkan excessive whitespace dan sensasi tampilan melayang di tengah pada monitor berlayar besar dengan mengubah layout container konten utama di `AppLayout.vue` menjadi fluid rata-kiri dengan batas maksimal longgar 1600 px sesuai revisi spesifikasi UI Guide Bagian 4.

**Architecture:** Memperbarui kelas kontainer `<main id="konten">` pada shell layout aplikasi (`AppLayout.vue`) dari `mx-auto w-full max-w-[1280px]` menjadi `w-full max-w-[1600px]`. Menjaga konten form tetap ergonomis dengan batas `max-w-[720px]` yang sudah ada di level halaman, sementara halaman tabel dan metrik memanfaatkan ruang secara proporsional.

**Tech Stack:** Vue 3, TypeScript, Tailwind CSS, Inertia.js, Vitest, Pest PHP.

**Spec:** [`docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`](file:///C:/Users/abdulaziz/Documents/pribadi/SIJEBOL/docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md) (Bagian 4: Layout Shell).

## Global Constraints

- Bahasa UI wajib Bahasa Indonesia baku sesuai panduan UI.
- Padding konten tetap 24 px (`sm:p-6`) pada desktop dan 16 px (`p-4`) pada mobile sesuai spesifikasi Bagian 4.
- Dilarang menambahkan baris `Co-Authored-By` pada git commit.
- Semua pengujian Vitest dan Pest PHP harus tetap 100% lulus tanpa regresi.

---

### Task 1: Update Layout Container di AppLayout.vue

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue:68-75`
- Test: `resources/js/Layouts/AppLayout.test.ts` (buat unit test baru untuk memastikan struktur shell dan atribut aksesibilitas tetap terjaga)

**Interfaces:**
- Consumes: `AppLayout.vue` slot dan struktur DOM
- Produces: `<main id="konten">` dengan class `w-full max-w-[1600px] flex-1 p-4 focus:outline-none sm:p-6`

- [ ] **Step 1: Tulis unit test untuk verifikasi class dan atribut `AppLayout`**

Buat file `resources/js/Layouts/AppLayout.test.ts`:
```ts
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AppLayout from './AppLayout.vue';

// Mock Inertia router dan link
vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({
        props: {
            auth: {
                user: {
                    name: 'Admin Test',
                    email: 'admin@example.com',
                    roles: ['admin-kecamatan'],
                },
            },
            permissions: [],
            flash: {},
        },
        url: '/',
    }),
}));

describe('AppLayout Shell Layout', () => {
    it('memiliki kontainer main dengan atribut aksesibilitas dan kelas fluid 1600px', () => {
        const wrapper = mount(AppLayout, {
            props: { title: 'Test Page' },
            slots: { default: '<div id="test-content">Konten Uji</div>' },
        });

        const main = wrapper.find('main#konten');
        expect(main.exists()).toBe(true);
        expect(main.attributes('tabindex')).toBe('-1');
        
        // Memastikan tidak ada mx-auto yang membuat layout melayang di tengah
        expect(main.classes()).not.toContain('mx-auto');
        // Memastikan batas lebar menggunakan 1600px
        expect(main.classes()).toContain('max-w-[1600px]');
        expect(main.classes()).toContain('w-full');
    });
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan awal (RED)**

Run: `npx vitest run resources/js/Layouts/AppLayout.test.ts`
Expected: FAIL (karena `AppLayout.vue` saat ini masih memiliki class `mx-auto` dan belum memiliki `max-w-[1600px]`).

- [ ] **Step 3: Implementasi perubahan class di AppLayout.vue (GREEN)**

Edit `resources/js/Layouts/AppLayout.vue` baris 68-75:
```vue
            <main
                id="konten"
                tabindex="-1"
                class="w-full max-w-[1600px] flex-1 p-4 focus:outline-none sm:p-6"
            >
                <LoadFailedBanner />
                <slot />
            </main>
```

- [ ] **Step 4: Jalankan unit test kembali (GREEN verification)**

Run: `npx vitest run resources/js/Layouts/AppLayout.test.ts`
Expected: PASS (1 test passed).

---

### Task 2: Verifikasi Regresi Suite & Typecheck

**Files:**
- Test: Seluruh unit/feature test suite

- [ ] **Step 1: Jalankan typecheck TypeScript**

Run: `npm run typecheck`
Expected: Selesai tanpa error tipe.

- [ ] **Step 2: Jalankan seluruh pengujian Vitest**

Run: `npm test`
Expected: Semua 24 file test (87 tests) PASS.

- [ ] **Step 3: Jalankan linting frontend**

Run: `npm run lint`
Expected: Lolos tanpa pelanggaran ESLint/Prettier.

- [ ] **Step 4: Jalankan backend test suite (Pest)**

Run: `php artisan test --parallel`
Expected: 456 tests PASS (2.653 assertions).

---

### Task 3: Review Kode (Ponytail & Impeccable)

**Files:**
- Review: `resources/js/Layouts/AppLayout.vue`, `resources/js/Layouts/AppLayout.test.ts`

- [ ] **Step 1: Jalankan audit kebersihan ponytail (over-engineering check)**
Memastikan solusi minimal, tidak menambahkan wrapper div atau dependensi CSS ekstra yang tidak dibutuhkan.

- [ ] **Step 2: Jalankan audit visual & aksesibilitas impeccable**
Memastikan padding 24 px (`sm:p-6`) dan 16 px (`p-4`), fokus keyboard `#konten`, dan konsistensi dengan form `max-w-[720px]` terjaga rapi.
