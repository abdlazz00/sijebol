# Fase 6c-3 — Polesan UI dan aksesibilitas: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup sembilan celah aksesibilitas dan konsistensi hasil audit tanpa mengubah alur fitur: tautan lewati ke konten, fokus dan pengumuman saat navigasi, cincin fokus seragam, label input cari, nama dan scope tabel, locale bawaan `id`, target sentuh 24 px, alasan belum-eligible ringkas, dan `noindex`.

**Architecture:** Perilaku navigasi masuk lewat composable `useRouteFocus` (pola factory dengan router dan dokumen yang dapat disuntik, seperti `useLoadFailure`) yang dipasang sekali di `app.ts`; `AppLayout` menambah skip link, `main#konten`, dan wilayah status. Sisanya perubahan kecil pada komponen bersama (`SearchInput`, `Table`, `TableHead`, `DataTable`, `Breadcrumbs`, `StudentMultiSelect`), satu aturan CSS global, dan `SecurityHeaders` untuk `X-Robots-Tag`.

**Tech Stack:** Vue 3 + TS + Inertia v2.3, Tailwind v4, Vitest, Laravel 13, Pest, Larastan, Pint, Playwright dan Chrome DevTools (verifikasi).

**Spec:** `docs/superpowers/specs/2026-10-09-fase-6c3-polesan-aksesibilitas-design.md`

## Global Constraints

- Perpindahan fokus ke `#konten` hanya saat `page.component` berbeda dari halaman sebelumnya; tidak pada muat awal, partial reload, filter yang diketik, atau kunjungan ke komponen yang sama.
- Skip link: `<a href="#konten">Lewati ke konten</a>` sebagai elemen fokus pertama di `AppLayout`; `<main id="konten" tabindex="-1">`.
- Cincin fokus: satu aturan global `a:focus-visible` (outline 2 px `--ring`, offset 2 px) hanya untuk tautan konten (`main`) dan breadcrumb, mengecualikan `[data-slot='button']` dan sidebar yang sudah punya cincin sendiri.
- Tabel: `TableHead` ber-`scope="col"`; `DataTable` menerima prop `label` menjadi `aria-label` pada `<table>`; **semua** pemakaian `DataTable` wajib berlabel (dijaga tes).
- Target sentuh tautan teks minimal 24 px (tautan judul baris dan breadcrumb), tanpa mengubah tinggi baris tabel.
- `noindex`: meta robots di `app.blade.php`, header `X-Robots-Tag: noindex, nofollow` dari `SecurityHeaders`, dan `public/robots.txt` berisi `Disallow: /`.
- `config/app.php`: `'locale' => env('APP_LOCALE', 'id')`.
- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; jangan matikan Vite dev server milik user (`public/hot` hanya dipindah sementara untuk uji build produksi lalu dikembalikan); bila klik asli Playwright tidak menghasilkan event jalankan `browser_close`; skrip Python dengan backslash namespace PHP gagal (pakai Edit/Write); `npx prettier --write` pada file FE yang diubah; Pest `toThrow` memakai `Exception::class`; Larastan menolak `->pluck()->isNotEmpty()`.
- Suite penuh backend >120 detik: jalankan di background.
- Commit: pesan detail, **tanpa** `Co-Authored-By` atau atribusi tool/AI apa pun. Persetujuan rencana ini mencakup langkah commit di dalamnya.

## Rulings (keputusan saya yang melengkapi spec)

- **R1:** Pengumuman halaman memakai `setTimeout(0)` setelah `nextTick` supaya `document.title` sudah diperbarui oleh `<Head>`; bila verifikasi browser menunjukkan judul masih basi, naikkan jeda dan catat.
- **R2:** Aturan cincin fokus dibatasi ke `main a` dan breadcrumb (bukan semua `a`) agar tidak menggandakan cincin pada sidebar dan tombol berbentuk tautan.
- **R3:** Verifikasi locale bawaan memakai proses PHP polos (tanpa `.env`), bukan tes Pest, karena `env()` di dalam tes membaca variabel `.env` dan phpunit.
- **R4:** C1 (target 24 px) dan A3 (cincin fokus) bersifat visual dan dibuktikan lewat pemindaian dan pengukuran di browser (Task 6), bukan lewat tes unit berbasis kelas CSS yang rapuh.

## Review Focus

1. Mengetik di kolom cari (partial reload dan komponen sama) tidak boleh memindahkan fokus dari kolom cari — tes `useRouteFocus` di Task 1.
2. Muat awal halaman tidak boleh memindahkan fokus atau mengumumkan apa pun — tes di Task 1.
3. `aria-label` pada `SearchInput` harus mendarat di `<input>`, sementara `id` dan `placeholder` tetap berfungsi dan wrapper tidak menerima atribut — tes di Task 3.
4. Pemakaian `DataTable` baru tanpa `label` harus membuat tes gagal — tes penjaga di Task 3.
5. Halaman error (404) juga membawa `X-Robots-Tag` — tes di Task 5.

---

## File Structure

**Dibuat**
- `resources/js/composables/{useRouteFocus,useRouteFocus.test}.ts`
- `resources/js/Components/shared/SearchInput.test.ts`
- `resources/js/Pages/datatable-label.test.ts`

**Diubah**
- FE: `resources/js/app.ts`, `resources/js/Layouts/AppLayout.vue`, `resources/css/app.css`, `resources/js/Components/shared/{SearchInput,DataTable,Breadcrumbs,StudentMultiSelect}.vue`, `resources/js/Components/shared/{DataTable,StudentMultiSelect}.test.ts`, `resources/js/Components/ui/table/{Table,TableHead}.vue`, dua belas halaman pemakai `DataTable` (daftar di Task 3), lima halaman dengan tautan judul baris (daftar di Task 2).
- BE: `config/app.php`, `resources/views/app.blade.php`, `app/Http/Middleware/SecurityHeaders.php`, `public/robots.txt`, `tests/Feature/Security/SecurityHeadersTest.php`.
- Dokumen: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`, `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`; memory `deferred-items.md`.

---

### Task 1: Skip link dan fokus/pengumuman saat navigasi (A1, A2)

**Files:**
- Create: `resources/js/composables/useRouteFocus.ts`, `resources/js/composables/useRouteFocus.test.ts`
- Modify: `resources/js/app.ts`, `resources/js/Layouts/AppLayout.vue`

**Interfaces:**
- Produces: `createRouteFocus(doc?: DocLike): { announcement: Readonly<Ref<string>>, attach(router?: RouterLike): () => void }`; `attachRouteFocus` (instansi bersama) dan `useRouteFocus(): { announcement }`.

- [ ] **Step 1: Tulis tes Vitest yang gagal**

`resources/js/composables/useRouteFocus.test.ts`:

```ts
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createRouteFocus } from './useRouteFocus';

type Handler = (event: { detail: Record<string, unknown> }) => unknown;

function fakeRouter() {
    const handlers: Handler[] = [];

    return {
        on(_event: string, callback: Handler) {
            handlers.push(callback);

            return () => handlers.splice(handlers.indexOf(callback), 1);
        },
        navigate(component: string) {
            handlers.forEach((h) => h({ detail: { page: { component } } }));
        },
        count: () => handlers.length,
    };
}

function fakeDoc(title = 'Jadwal - SIJEBOL', hasMain = true) {
    const focus = vi.fn();

    return {
        title,
        focus,
        getElementById: vi.fn((id: string) =>
            hasMain && id === 'konten' ? { focus } : null,
        ),
    };
}

describe('useRouteFocus', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('muat awal tidak memindahkan fokus dan tidak mengumumkan apa pun', async () => {
        const doc = fakeDoc();
        const { attach, announcement } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('Jadwal/Index');
        await vi.runAllTimersAsync();

        expect(doc.focus).not.toHaveBeenCalled();
        expect(announcement.value).toBe('');
    });

    it('pindah ke komponen lain memfokuskan konten tanpa gulir dan mengumumkan judul', async () => {
        const doc = fakeDoc('Siswa - SIJEBOL');
        const { attach, announcement } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('Jadwal/Index');
        router.navigate('Siswa/Index');
        await vi.runAllTimersAsync();

        expect(doc.focus).toHaveBeenCalledWith({ preventScroll: true });
        expect(announcement.value).toBe('Halaman: Siswa - SIJEBOL');
    });

    it('komponen yang sama (filter, partial reload) tidak memindahkan fokus', async () => {
        const doc = fakeDoc();
        const { attach } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('Jadwal/Index');
        router.navigate('Jadwal/Index');
        router.navigate('Jadwal/Index');
        await vi.runAllTimersAsync();

        expect(doc.focus).not.toHaveBeenCalled();
    });

    it('tanpa elemen konten tidak melempar galat', async () => {
        const doc = fakeDoc('X', false);
        const { attach } = createRouteFocus(doc);
        const router = fakeRouter();
        attach(router);

        router.navigate('A');
        router.navigate('B');

        await expect(vi.runAllTimersAsync()).resolves.not.toThrow();
    });

    it('pelepas menghentikan pemantauan', () => {
        const { attach } = createRouteFocus(fakeDoc());
        const router = fakeRouter();

        const detach = attach(router);
        expect(router.count()).toBe(1);
        detach();
        expect(router.count()).toBe(0);
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npx vitest run resources/js/composables/useRouteFocus.test.ts`
Expected: FAIL (modul `./useRouteFocus` tidak ada).

- [ ] **Step 3: Implementasi**

`resources/js/composables/useRouteFocus.ts`:

```ts
import { router } from '@inertiajs/vue3';
import { nextTick, readonly, ref } from 'vue';

type NavigateEvent = { detail: Record<string, unknown> };

/** Bagian router Inertia yang dipakai; memudahkan pengujian tanpa memasang aplikasi. */
export interface RouterLike {
    on(
        event: string,
        callback: (event: NavigateEvent) => unknown,
    ): () => void;
}

interface DocLike {
    title: string;
    getElementById(id: string): { focus(options?: FocusOptions): void } | null;
}

/**
 * Setelah pindah ke halaman lain (komponen berbeda): fokus ke konten utama dan umumkan judul halaman.
 * Muat awal, partial reload, dan filter pada halaman yang sama tidak memindahkan fokus.
 */
export function createRouteFocus(doc: DocLike = document) {
    const announcement = ref('');
    let last: string | null = null;

    function attach(
        target: RouterLike = router as unknown as RouterLike,
    ): () => void {
        return target.on('navigate', (event) => {
            const { component } = event.detail.page as { component: string };
            const changed = last !== null && component !== last;
            last = component;
            if (!changed) return;

            void nextTick(() =>
                // Jeda singkat: <Head> memperbarui document.title setelah render.
                setTimeout(() => {
                    doc.getElementById('konten')?.focus({ preventScroll: true });
                    announcement.value = `Halaman: ${doc.title}`;
                }, 0),
            );
        });
    }

    return { announcement: readonly(announcement), attach };
}

const shared = createRouteFocus();

export const attachRouteFocus = shared.attach;

export function useRouteFocus() {
    return { announcement: shared.announcement };
}
```

`resources/js/app.ts` — impor `import { attachRouteFocus } from '@/composables/useRouteFocus';` dan panggil di `setup` setelah `attachLoadFailure();`:

```ts
        attachLoadFailure();
        attachRouteFocus();
```

`resources/js/Layouts/AppLayout.vue` — impor `import { useRouteFocus } from '@/composables/useRouteFocus';`, tambahkan di `<script setup>`: `const { announcement } = useRouteFocus();`. Di template: tambahkan sebagai elemen pertama di dalam `<div class="bg-background flex min-h-dvh">` (sebelum `<aside>`):

```vue
        <a
            href="#konten"
            class="focus:bg-background focus:text-foreground focus:ring-ring sr-only rounded-md px-4 py-2 text-sm font-medium focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:ring-2"
        >
            Lewati ke konten
        </a>
```
ubah `<main ...>` menjadi:

```vue
            <main
                id="konten"
                tabindex="-1"
                class="mx-auto w-full max-w-[1280px] flex-1 p-4 focus:outline-none sm:p-6"
            >
                <LoadFailedBanner />
                <slot />
            </main>
```
dan tambahkan sebelum `<Toaster />`:

```vue
        <p class="sr-only" role="status">{{ announcement }}</p>
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `npx prettier --write resources/js/composables/useRouteFocus.ts resources/js/composables/useRouteFocus.test.ts resources/js/app.ts resources/js/Layouts/AppLayout.vue && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useRouteFocus.ts resources/js/composables/useRouteFocus.test.ts resources/js/app.ts resources/js/Layouts/AppLayout.vue
git commit -m "feat: tambah tautan lewati ke konten dan fokus saat berpindah halaman" -m "AppLayout memuat tautan Lewati ke konten sebagai elemen fokus pertama dan main#konten yang dapat difokuskan. Composable useRouteFocus memindahkan fokus ke konten dan mengumumkan judul halaman lewat wilayah status hanya saat komponen halaman berganti; muat awal, partial reload, dan filter pada halaman yang sama tidak memindahkan fokus sehingga kolom cari tidak kehilangan fokus."
```

---

### Task 2: Cincin fokus seragam dan target sentuh 24 px (A3, C1)

**Files:**
- Modify: `resources/css/app.css`, `resources/js/Components/shared/Breadcrumbs.vue`, `resources/js/Pages/Jadwal/Index.vue`, `resources/js/Pages/Jadwal/Show.vue`, `resources/js/Pages/Pendaftaran/Index.vue`, `resources/js/Pages/Pendaftaran/Show.vue`, `resources/js/Pages/Siswa/Index.vue`

**Interfaces:** Produces: kelas visual saja (tanpa antarmuka kode). Dibuktikan di browser pada Task 6 (R4).

- [ ] **Step 1: Aturan CSS cincin fokus**

`resources/css/app.css` — tambahkan blok berikut tepat sebelum komentar `/* Pengguna yang meminta gerak minimal`:

```css
/* Cincin fokus seragam untuk tautan teks di konten dan breadcrumb (cincin bawaan browser terlalu tipis).
   Tombol berbentuk tautan dan sidebar sudah punya cincin sendiri. */
main a:not([data-slot='button']):focus-visible,
nav[aria-label='Breadcrumb'] a:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
    border-radius: 0.25rem;
}
```

- [ ] **Step 2: Target 24 px**

Pada kelima halaman, ganti kelas tautan judul baris (string identik di semua berkas):
`class="block truncate text-sm leading-5 font-semibold hover:underline"`
menjadi
`class="block truncate py-0.5 text-sm leading-5 font-semibold hover:underline"`.
Tempat: `Jadwal/Index.vue` (~baris 140), `Jadwal/Show.vue` (~243), `Pendaftaran/Index.vue` (~156), `Pendaftaran/Show.vue` (~176), `Siswa/Index.vue` (~185). Cek juga `Pengguna/Index.vue` untuk `<Link>` pada kolom nama dan beri `py-0.5` bila ada.

`resources/js/Components/shared/Breadcrumbs.vue` — ubah `class="hover:underline"` pada `<Link>` menjadi `class="inline-block py-0.5 hover:underline"`.

- [ ] **Step 3: Format, typecheck, lint, build**

Run: `npx prettier --write resources/css/app.css resources/js/Components/shared/Breadcrumbs.vue resources/js/Pages/Jadwal/Index.vue resources/js/Pages/Jadwal/Show.vue resources/js/Pages/Pendaftaran/Index.vue resources/js/Pages/Pendaftaran/Show.vue resources/js/Pages/Siswa/Index.vue && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau (belum ada tes baru; efek visual diukur di Task 6).

- [ ] **Step 4: Commit**

```bash
git add resources/css/app.css resources/js/Components/shared/Breadcrumbs.vue resources/js/Pages
git commit -m "feat: seragamkan cincin fokus tautan dan perbesar target sentuh" -m "Satu aturan global memberi tautan teks di konten dan breadcrumb cincin fokus 2 px yang jelas (tombol dan sidebar tidak berubah), dan tautan judul baris tabel serta breadcrumb mendapat padding vertikal sehingga tingginya minimal 24 px tanpa mengubah tinggi baris."
```

---

### Task 3: Label dan semantik (B1, B2, B3)

**Files:**
- Create: `resources/js/Components/shared/SearchInput.test.ts`, `resources/js/Pages/datatable-label.test.ts`
- Modify: `resources/js/Components/shared/SearchInput.vue`, `resources/js/Components/ui/table/Table.vue`, `resources/js/Components/ui/table/TableHead.vue`, `resources/js/Components/shared/DataTable.vue`, `resources/js/Components/shared/DataTable.test.ts`, dua belas halaman pemakai `DataTable`, `config/app.php`

**Interfaces:**
- Produces: `DataTable` prop `label?: string`; `SearchInput` meneruskan `$attrs` ke `<input>`.

- [ ] **Step 1: Tulis tes yang gagal**

`resources/js/Components/shared/SearchInput.test.ts`:

```ts
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SearchInput from './SearchInput.vue';

describe('SearchInput', () => {
    it('aria-label dan atribut lain mendarat di input, bukan di pembungkus', () => {
        const wrapper = mount(SearchInput, {
            props: { modelValue: '', placeholder: 'Cari' },
            attrs: { 'aria-label': 'Cari siswa', 'data-uji': 'x' },
        });

        const input = wrapper.find('input');
        expect(input.attributes('aria-label')).toBe('Cari siswa');
        expect(input.attributes('data-uji')).toBe('x');
        expect(wrapper.element.getAttribute('aria-label')).toBeNull();
    });

    it('id dan placeholder tetap berfungsi dan v-model memperbarui nilai', async () => {
        const wrapper = mount(SearchInput, {
            props: {
                modelValue: '',
                id: 'cari',
                placeholder: 'Cari lokasi',
                'onUpdate:modelValue': (v: string) =>
                    wrapper.setProps({ modelValue: v }),
            },
        });

        const input = wrapper.find('input');
        expect(input.attributes('id')).toBe('cari');
        expect(input.attributes('placeholder')).toBe('Cari lokasi');

        await input.setValue('abc');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual(['abc']);
    });
});
```

Tambahkan di `resources/js/Components/shared/DataTable.test.ts` (di dalam `describe`, sebelum penutupnya):

```ts
    it('memberi nama tabel lewat prop label dan semua th ber-scope col', () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows: [], label: 'Daftar uji' },
        });

        expect(wrapper.find('table').attributes('aria-label')).toBe(
            'Daftar uji',
        );
        const heads = wrapper.findAll('th');
        expect(heads.length).toBe(columns.length);
        expect(heads.every((th) => th.attributes('scope') === 'col')).toBe(
            true,
        );
    });
```

`resources/js/Pages/datatable-label.test.ts` (penjaga: setiap pemakaian `DataTable` wajib berlabel):

```ts
import { describe, expect, it } from 'vitest';

const pages = import.meta.glob('./**/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
}) as Record<string, string>;

describe('DataTable berlabel', () => {
    it('setiap pemakaian DataTable di halaman memiliki prop label', () => {
        const withTable = Object.entries(pages).filter(([, source]) =>
            source.includes('<DataTable'),
        );
        expect(withTable.length).toBeGreaterThan(0);

        const unlabeled = withTable
            .filter(
                ([, source]) =>
                    !/<DataTable[^>]*\blabel=/s.test(source),
            )
            .map(([path]) => path);

        expect(unlabeled).toEqual([]);
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npx vitest run resources/js/Components/shared/SearchInput.test.ts resources/js/Components/shared/DataTable.test.ts resources/js/Pages/datatable-label.test.ts`
Expected: FAIL (aria-label di pembungkus; tabel tanpa `aria-label`/`scope`; dua belas halaman tanpa `label`).

- [ ] **Step 3: Implementasi**

`resources/js/Components/shared/SearchInput.vue` — ganti blok `<script setup>` dan elemen `<Input>`:

```vue
<script setup lang="ts">
import { Input } from '@/Components/ui/input';
import { Search } from '@lucide/vue';

// Atribut luar (mis. aria-label) harus mendarat di input, bukan di div pembungkus.
defineOptions({ inheritAttrs: false });
defineProps<{ placeholder?: string; id?: string }>();

const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative">
        <Search
            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            aria-hidden="true"
        />
        <Input
            v-bind="$attrs"
            :id="id"
            v-model="model"
            class="h-10 bg-white pl-9"
            :placeholder="placeholder"
        />
    </div>
</template>
```
(sebelum mengedit, cari `<SearchInput` yang memberi `class` langsung: `grep -rn "<SearchInput" resources/js | grep class`; bila ada, pindahkan kelas itu ke elemen pembungkus di pemakainya.)

`resources/js/Components/ui/table/Table.vue` — teruskan atribut ke `<table>`:

```vue
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div data-slot="table-container" class="relative w-full overflow-x-auto">
        <table
            v-bind="$attrs"
            data-slot="table"
            :class="cn('w-full caption-bottom text-sm', props.class)"
        >
            <slot />
        </table>
    </div>
</template>
```

`resources/js/Components/ui/table/TableHead.vue` — tambahkan `scope="col"` pada `<th`:

```vue
    <th
        data-slot="table-head"
        scope="col"
```

`resources/js/Components/shared/DataTable.vue` — tambahkan `label?: string;` ke tipe props (sebelum `rowClass`) dan ubah `<Table :class="tableClass">` menjadi `<Table :class="tableClass" :aria-label="label">`.

Beri `label` pada dua belas halaman memakai skrip (tanpa backslash namespace; aman). Jalankan:

```bash
python - <<'PYEOF'
import re
labels = {
    'resources/js/Pages/Jadwal/Index.vue': 'Daftar jadwal',
    'resources/js/Pages/Jadwal/Show.vue': 'Peserta jadwal',
    'resources/js/Pages/Jadwal/Rekaman.vue': 'Siswa sudah ditandai',
    'resources/js/Pages/Laporan/Index.vue': 'Rekap pendaftaran per sekolah',
    'resources/js/Pages/Log/Index.vue': 'Log aktivitas',
    'resources/js/Pages/Pendaftaran/Index.vue': 'Daftar pendaftaran',
    'resources/js/Pages/Pendaftaran/Show.vue': 'Entri pendaftaran',
    'resources/js/Pages/Pengguna/Index.vue': 'Daftar pengguna',
    'resources/js/Pages/Role/Index.vue': 'Daftar role',
    'resources/js/Pages/Sekolah/Index.vue': 'Daftar sekolah',
    'resources/js/Pages/Siswa/Index.vue': 'Daftar siswa',
    'resources/js/Pages/Verifikasi/Index.vue': 'Antrean verifikasi',
}
for path, label in labels.items():
    s = open(path, encoding='utf-8').read()
    new, n = re.subn(r'<DataTable(\s+):columns=', lambda m: '<DataTable' + m.group(1) + 'label="' + label + '"' + m.group(1) + ':columns=', s, count=1)
    assert n == 1, path
    open(path, 'w', encoding='utf-8').write(new)
PYEOF
```

`config/app.php` — ubah baris `'locale' => env('APP_LOCALE', 'en'),` menjadi `'locale' => env('APP_LOCALE', 'id'),`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `npx prettier --write resources/js/Components/shared/SearchInput.vue resources/js/Components/shared/SearchInput.test.ts resources/js/Components/shared/DataTable.vue resources/js/Components/shared/DataTable.test.ts resources/js/Components/ui/table/Table.vue resources/js/Components/ui/table/TableHead.vue resources/js/Pages && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau. Lalu verifikasi locale bawaan (R3): `php -r 'require "vendor/autoload.php"; $c = require "config/app.php"; echo $c["locale"];'` Expected: `id`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components resources/js/Pages config/app.php
git commit -m "feat: beri nama pada input cari dan tabel dan jadikan id locale bawaan" -m "SearchInput meneruskan atribut luar ke input sehingga aria-label tidak lagi jatuh ke div pembungkus. Th tabel ber-scope col, DataTable menerima prop label menjadi aria-label tabel, dan seluruh dua belas pemakaiannya diberi label (dijaga tes agar pemakaian baru tanpa label gagal). Locale bawaan aplikasi menjadi id."
```

---

### Task 4: Alasan belum-eligible ringkas (C2)

**Files:**
- Modify: `resources/js/Components/shared/StudentMultiSelect.vue`, `resources/js/Components/shared/StudentMultiSelect.test.ts`

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan di `resources/js/Components/shared/StudentMultiSelect.test.ts` (di dalam `describe`):

```ts
    it('alasan belum eligible digabung menjadi satu baris ringkas', () => {
        const wrapper = mountSelect([], {
            candidates: page([
                {
                    ...base,
                    id: 4,
                    name: 'Dewi',
                    nisn: '0000000004',
                    eligible: false,
                    missing_docs: true,
                    reasons: [
                        'Kartu Keluarga belum diunggah.',
                        'Akta kelahiran belum diunggah.',
                    ],
                },
            ]),
        });

        expect(wrapper.text()).toContain(
            'Belum memenuhi: Kartu Keluarga belum diunggah. · Akta kelahiran belum diunggah.',
        );
        expect(wrapper.findAll('li li')).toHaveLength(0);
    });
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npx vitest run resources/js/Components/shared/StudentMultiSelect.test.ts`
Expected: FAIL (masih daftar poin `li li`).

- [ ] **Step 3: Implementasi**

`StudentMultiSelect.vue` — ganti blok daftar alasan:

```vue
                    <ul
                        v-if="!c.eligible"
                        class="text-muted-foreground mt-1 list-disc pl-4 text-xs leading-4"
                    >
                        <li v-for="reason in c.reasons" :key="reason">
                            {{ reason }}
                        </li>
                    </ul>
```
dengan:

```vue
                    <p
                        v-if="!c.eligible"
                        class="text-muted-foreground mt-1 text-xs leading-4"
                    >
                        Belum memenuhi: {{ c.reasons.join(' · ') }}
                    </p>
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `npx prettier --write resources/js/Components/shared/StudentMultiSelect.vue resources/js/Components/shared/StudentMultiSelect.test.ts && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau (tes lama "menampilkan alasan sebagai teks" tetap lulus).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/shared/StudentMultiSelect.vue resources/js/Components/shared/StudentMultiSelect.test.ts
git commit -m "feat: ringkas alasan belum eligible di pemilih siswa" -m "Alasan kelayakan yang sebelumnya berupa daftar poin per siswa kini satu baris Belum memenuhi dengan pemisah titik tengah, sehingga halaman pemilih jauh lebih pendek di mobile; teks alasan dan tautan Lengkapi dokumen tidak berubah."
```

---

### Task 5: Tidak terindeks mesin pencari (E1)

**Files:**
- Modify: `app/Http/Middleware/SecurityHeaders.php`, `resources/views/app.blade.php`, `public/robots.txt`, `tests/Feature/Security/SecurityHeadersTest.php`

- [ ] **Step 1: Tulis tes yang gagal**

Di `tests/Feature/Security/SecurityHeadersTest.php`: pada tes `semua respons web membawa header keamanan dasar` tambahkan rantai `->assertHeader('X-Robots-Tag', 'noindex, nofollow')` setelah `Permissions-Policy`, dan pada tes `halaman error dan unduhan ikut membawa header` ubah baris 404 menjadi:

```php
    $this->get('/tidak-ada')->assertStatus(404)->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
```
Tambahkan tes baru di akhir berkas:

```php
test('halaman memuat meta robots noindex dan robots.txt menutup seluruh situs', function () {
    $this->get('/login')->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    // Akhir baris dinormalkan: berkas bisa berakhiran CRLF di Windows.
    expect(trim(str_replace("\r\n", "\n", (string) file_get_contents(public_path('robots.txt')))))->toBe("User-agent: *\nDisallow: /");
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Security/SecurityHeadersTest.php`
Expected: FAIL (header, meta, dan robots.txt belum ada).

- [ ] **Step 3: Implementasi**

`SecurityHeaders::handle` — tambahkan setelah header `Permissions-Policy`:

```php
        // Sistem internal: tidak boleh diindeks (mencakup halaman error dan berkas).
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
```

`resources/views/app.blade.php` — tambahkan di `<head>` setelah meta viewport:

```blade
        <meta name="robots" content="noindex, nofollow">
```

`public/robots.txt` (ganti isi):

```
User-agent: *
Disallow: /
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Security tests/Feature/ErrorPagesTest.php && vendor/bin/pint app tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: PASS dan bersih.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/SecurityHeaders.php resources/views/app.blade.php public/robots.txt tests/Feature/Security/SecurityHeadersTest.php
git commit -m "feat: cegah indeksasi sistem oleh mesin pencari" -m "Semua respons web membawa X-Robots-Tag noindex, nofollow (termasuk halaman error), halaman memuat meta robots yang sama, dan robots.txt menutup seluruh situs karena SIJEBOL adalah sistem internal kecamatan."
```

---

### Task 6: Verifikasi, review, dokumentasi, dan penutupan

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (bagian 6), `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`; memory `deferred-items.md`.

- [ ] **Step 1: Suite penuh di background**

Run (background): `php artisan test > /tmp/f6c3-full.txt 2>&1` lalu pantau. Expected: semua lulus (455 sebelumnya + tes baru). Juga `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vitest run`, `npm run typecheck`, `npm run lint`, `npm run build` hijau.

- [ ] **Step 2: Verifikasi browser dengan build produksi**

`npm run build`; pindahkan sementara `public/hot` (`mv public/hot public/hot.bak`); jalankan `APP_ENV=production APP_DEBUG=false php artisan serve --port=8099` (background). Login lewat `browser_run_code_unsafe` (`page.getByLabel('Email').fill(...)`; bila `/login` mengalihkan langsung buka halaman). Periksa pada 1440 dan 390 px:
1. **Skip link:** `Tab` pertama menyorot tautan "Lewati ke konten" (terlihat); `Enter` memindahkan fokus ke `main#konten` (`document.activeElement.id === 'konten'`).
2. **Fokus saat navigasi:** klik tautan sidebar "Siswa": setelah halaman berganti `document.activeElement.id === 'konten'` dan wilayah `role=status` berisi "Halaman: Siswa - SIJEBOL" (bila judul basi, naikkan jeda `setTimeout` dan catat Ruling). Mengetik di kolom cari `/jadwal` (partial reload) **tidak** memindahkan fokus dari kolom cari.
3. **Cincin fokus:** `Tab` ke tautan judul baris di `/jadwal`: `getComputedStyle(el).outlineWidth` bernilai `2px` dan warna sesuai `--ring`; tombol dan sidebar tidak mendapat cincin ganda.
4. **Target sentuh:** pindai `button, a[href], input, [role=checkbox], [role=combobox]` pada `/dashboard`, `/jadwal`, `/siswa`, `/pendaftaran/buat`, `/jadwal/2` di 390 px: tidak ada tautan teks di bawah 24 px tinggi (kotak centang 16 px yang dibungkus label boleh).
5. **Label:** `SearchInput` di `/pendaftaran/buat` punya `aria-label="Cari siswa"` pada `<input>`; tiap tabel memiliki `aria-label`; `th` ber-`scope="col"`.
6. **Pemilih siswa di mobile:** halaman `/pendaftaran/buat` (data demo sementara bila perlu, dipulihkan) jauh lebih pendek dibanding sebelum perubahan (alasan satu baris).
7. **Lighthouse (Chrome DevTools):** aksesibilitas tetap 100 di login dan beranda; SEO membaik; header `X-Robots-Tag` ada (`curl -sI`).
Bersihkan: matikan server 8099, **kembalikan** `mv public/hot.bak public/hot`, hapus tangkapan layar; pastikan Vite dev server user di 5173 masih hidup.

- [ ] **Step 3: Review**

`ponytail:ponytail-review` pada `git diff a258ae2..HEAD -- app resources routes tests config public` dan `impeccable` (critique) pada `AppLayout.vue`, `StudentMultiSelect.vue`, dan aturan CSS fokus dengan panduan UI. Perbaiki temuan valid dengan TDD; commit terpisah "refactor: terapkan hasil review Fase 6c-3".

- [ ] **Step 4: Dokumentasi dan memory**

Panduan UI bagian 6 (Pola lintas halaman): tambahkan butir "**Navigasi keyboard**": tautan "Lewati ke konten" sebagai fokus pertama; setelah pindah halaman fokus ke `main#konten` dan wilayah status mengumumkan judul; tautan teks di konten dan breadcrumb memakai cincin fokus 2 px seragam; target sentuh tautan teks minimal 24 px; tabel selalu berlabel (`label` pada `DataTable`) dan input cari memakai `SearchInput` dengan `aria-label` bila tanpa label tampak. Sprint plan: "Status Fase 6c-3 (2026-10-09): SELESAI" dan nyatakan **Fase 6 (6a, 6b, 6c) selesai**; rilis (supervisor/cron/backup/`.env` produksi/seeder awal, UAT, runbook) masih ditahan dan menunggu pembahasan ulang item tertunda. Memory `deferred-items.md`: tandai 6c-3 selesai; sisa daftar kini hanya kelompok D dan konfigurasi rilis.

- [ ] **Step 5: Verifikasi akhir dan commit**

Jalankan ulang semua pemeriksaan Step 1; semuanya hijau sebelum klaim selesai.

```bash
git add docs
git commit -m "docs: tandai Fase 6c-3 dan seluruh Fase 6 selesai" -m "Panduan UI memuat pola navigasi keyboard (lewati ke konten, fokus saat berpindah halaman, cincin fokus seragam, target 24 px, tabel berlabel); rencana sprint mencatat Fase 6c-3 selesai sehingga 6a, 6b, dan 6c selesai, sedangkan bagian rilis tetap ditahan menunggu pembahasan ulang item tertunda."
```
