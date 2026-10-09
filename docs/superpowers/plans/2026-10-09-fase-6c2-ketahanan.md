# Fase 6c-2 — Ketahanan: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplikasi tetap jelas bagi pengguna saat jaringan bermasalah (banner "gagal memuat" dengan Coba lagi), pemilih siswa di form pendaftaran memuat 20 siswa per halaman dengan pencarian server, dan impor Excel diproses di queue dengan hasil tersimpan dan dibuka lewat notifikasi.

**Architecture:** (1) Composable `useLoadFailure` memasang pendengar peristiwa router Inertia sekali di `app.ts` dan `LoadFailedBanner` di `AppLayout`. (2) `StudentRepositoryInterface::searchForSchool` memaginasi dan mencari; `RegistrationController` mengirim `candidates` berbentuk paginator dan `selected`; `StudentMultiSelect` memuat ulang lewat partial reload. (3) `StudentImportService` dipecah menjadi `prepare` (pra-periksa sinkron, simpan berkas, buat baris `student_imports`, dispatch) dan `run` (isi `ImportStudentsJob`); dialog melakukan polling JSON.

**Tech Stack:** Laravel 13 / PHP 8.5 / MySQL 8, maatwebsite/excel, Inertia v2.3 + Vue 3 + TS, @vueuse/core, Vitest, Pest, Larastan level 5, Pint, Playwright (verifikasi).

**Spec:** `docs/superpowers/specs/2026-10-09-fase-6c2-ketahanan-design.md`

## Global Constraints

- Banner global di `AppLayout`: hanya peristiwa `exception` (jaringan) dan `invalid` (respons non-Inertia; `preventDefault()` agar modal HTML tidak muncul) yang menyetel gagal; "Coba lagi" mengulang kunjungan terakhir (dilacak lewat peristiwa `before`, prefetch diabaikan), bukan `router.reload()`.
- Pemilih siswa: 20 per halaman, cari nama atau NISN di server (wildcard `%` dan `_` dianggap literal), pilihan lintas halaman tetap tampil sebagai "Terpilih (N)", `selected` memuat siswa entri draf yang diubah; validasi pengajuan di server tidak berubah.
- Impor: pra-periksa sinkron (format, kolom wajib, maksimal 500 baris, sekolah) menghasilkan galat validasi `file`/`school_id` seketika; berkas disimpan di disk privat `imports/{uuid}.xlsx`; `ImportStudentsJob` `$tries = 1`, `$timeout = 120`; berkas **dihapus setelah diproses (sukses maupun gagal)**; hasil (`imported`, `failed` json) tersimpan di `student_imports`; notifikasi "Impor siswa selesai"/"Impor siswa gagal" ke pembuat dengan tautan `/siswa?impor={id}`.
- Hasil impor hanya terlihat oleh pembuatnya dan staf kecamatan (`school_id` kosong) pemegang `siswa.impor`; selain itu 404 (rute `siswa.impor.show`) atau `recent_import` null (`/siswa?impor=`).
- Polling dialog tiap 2 detik; bila masih `antri` lebih dari 20 detik tampil petunjuk queue worker.
- Impor kini butuh queue worker (`php artisan queue:work`) di lokal; `docs/operasional-queue.md` diperbarui.
- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; dataset Pest tanpa closure; tes yang memicu notifikasi memakai `Notification::fake()` dan membuat permission yang dicari; helper tes global bernama unik; Larastan menolak `->pluck()->isNotEmpty()` dan nullsafe pada nilai non-null; `UploadedFile::fake()` menebak MIME dari nama (pakai berkas sungguhan untuk uji isi); jangan edit PHP dengan Python heredoc yang memuat backslash namespace (pakai Edit/Write).
- Suite penuh backend >120 detik: jalankan di background. File FE yang diubah dijalankan `npx prettier --write`. Jangan matikan Vite dev server milik user; bila klik asli Playwright tidak menghasilkan event, jalankan `browser_close`; jangan klik tautan unduhan.
- Commit: pesan detail, **tanpa** `Co-Authored-By` atau atribusi tool/AI apa pun. Persetujuan rencana ini mencakup langkah commit di dalamnya.

## Rulings (keputusan saya yang melengkapi spec)

- **R1:** `StudentRepositoryInterface::forSchool`, `StudentService::forSchool` dihapus (hanya `RegistrationController` yang memakainya) dan diganti `searchForSchool`/`candidatesPage`.
- **R2:** Parameter `q` yang bukan string (mis. `q[]=x`) diperlakukan kosong; pencarian memakai `LIKE` dengan `\`, `%`, `_` di-escape.
- **R3:** `StudentImportService::run` tidak mengirim notifikasi; job yang mengirimnya (`handle` sukses, `failed()` gagal), supaya layanan tetap murni.
- **R4:** Tes kegagalan job memakai `ImportStudentsJob::dispatchSync()`: pada queue `sync`, kegagalan memanggil `failed()` lalu melempar ulang ke pemanggil, sehingga tes menegaskan `toThrow` dan status `gagal`.
- **R5:** Logika polling diekstrak ke composable `useImportStatus` (injeksi `fetcher`) agar teruji dengan Vitest tanpa memasang dialog.
- **R6:** Pelacakan kunjungan terakhir dan pendengar peristiwa dipasang satu kali di `resources/js/app.ts` (bukan di `AppLayout`, yang dipasang ulang pada setiap navigasi).

## Review Focus

1. Berkas impor berisi NIK dihapus setelah impor berhasil dan setelah gagal — tes di Task 4.
2. Operator sekolah lain tidak boleh membaca hasil impor orang lain (`siswa.impor.show` 404 dan `recent_import` null) — tes di Task 4.
3. Pencarian `%` atau `_` tidak boleh mencocokkan semua siswa, dan `q[]=x` tidak boleh error — tes di Task 2.
4. Siswa terpilih di halaman lain (draf yang diubah) tetap ada di `selected` dan tetap dikirim saat simpan — tes di Task 2 dan Task 3.
5. Banner: `invalid` tidak memunculkan modal, retry memakai kunjungan terakhir (bukan halaman saat ini), dan kunjungan prefetch tidak menimpa kunjungan terakhir — tes di Task 1.

---

## File Structure

**Dibuat**
- `resources/js/composables/{useLoadFailure,useLoadFailure.test,useImportStatus,useImportStatus.test}.ts`, `resources/js/Components/shared/LoadFailedBanner.vue`
- `database/migrations/2026_10_09_500001_create_student_imports_table.php`
- `app/Domains/Students/Models/StudentImport.php`, `app/Domains/Students/Jobs/ImportStudentsJob.php`, `app/Domains/Notifications/Notifications/StudentImportFinishedNotification.php`
- `tests/Feature/Registrations/CandidatePickerTest.php`, `tests/Feature/Students/StudentImportQueueTest.php`

**Diubah**
- FE: `resources/js/app.ts`, `resources/js/Layouts/AppLayout.vue`, `resources/js/Components/shared/{StudentMultiSelect.vue,StudentMultiSelect.test.ts,ImportStudentsDialog.vue}`, `resources/js/Pages/Pendaftaran/Form.vue`, `resources/js/Pages/Siswa/Index.vue`, `resources/js/types/{student.ts,index.d.ts}`
- BE: `app/Domains/Students/Repositories/{Contracts/StudentRepositoryInterface,EloquentStudentRepository}.php`, `app/Domains/Students/Services/{StudentService,StudentImportService}.php`, `app/Domains/Students/Http/Controllers/{StudentController,StudentImportController}.php`, `app/Domains/Registrations/Http/Controllers/RegistrationController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/students.php`
- Tes: `tests/Feature/Registrations/RegistrationHttpTest.php`, `tests/Feature/Students/StudentImportTest.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`
- Dokumen: `docs/operasional-queue.md`, `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`, `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`; memory `deferred-items.md`.

---

### Task 1: Banner "gagal memuat" global

**Files:**
- Create: `resources/js/composables/useLoadFailure.ts`, `resources/js/composables/useLoadFailure.test.ts`, `resources/js/Components/shared/LoadFailedBanner.vue`
- Modify: `resources/js/app.ts`, `resources/js/Layouts/AppLayout.vue`

**Interfaces:**
- Produces: `attachLoadFailure(router?: RouterLike): () => void` (memasang pendengar, mengembalikan pembatal), `retryLoad(router?: RouterLike): void`, `dismissLoadFailure(): void`, `useLoadFailure(): { failed: Readonly<Ref<boolean>> }`.

- [ ] **Step 1: Tulis tes Vitest yang gagal**

`resources/js/composables/useLoadFailure.test.ts`:

```ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    attachLoadFailure,
    dismissLoadFailure,
    retryLoad,
    useLoadFailure,
} from './useLoadFailure';

type Handler = (event: {
    detail: Record<string, unknown>;
    preventDefault: () => void;
}) => unknown;

function fakeRouter() {
    const handlers: Record<string, Handler[]> = {};

    return {
        visit: vi.fn(),
        on(event: string, callback: Handler) {
            (handlers[event] ??= []).push(callback);

            return () => {
                handlers[event] = (handlers[event] ?? []).filter(
                    (h) => h !== callback,
                );
            };
        },
        emit(event: string, detail: Record<string, unknown> = {}) {
            const e = { detail, preventDefault: vi.fn() };
            (handlers[event] ?? []).forEach((h) => h(e));

            return e;
        },
        count(event: string) {
            return (handlers[event] ?? []).length;
        },
    };
}

const visit = (url: string, extra: Record<string, unknown> = {}) => ({
    url: new URL(url, 'http://localhost'),
    method: 'get',
    data: {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: [],
    prefetch: false,
    ...extra,
});

describe('useLoadFailure', () => {
    beforeEach(() => dismissLoadFailure());

    it('kegagalan jaringan menyetel gagal; kunjungan baru dan sukses mengosongkan', () => {
        const router = fakeRouter();
        attachLoadFailure(router);
        const { failed } = useLoadFailure();

        router.emit('exception');
        expect(failed.value).toBe(true);

        router.emit('start');
        expect(failed.value).toBe(false);

        router.emit('exception');
        router.emit('success');
        expect(failed.value).toBe(false);
    });

    it('respons non-Inertia menyetel gagal dan mencegah modal bawaan', () => {
        const router = fakeRouter();
        attachLoadFailure(router);

        const event = router.emit('invalid');

        expect(useLoadFailure().failed.value).toBe(true);
        expect(event.preventDefault).toHaveBeenCalledOnce();
    });

    it('coba lagi mengulang kunjungan terakhir, bukan halaman saat ini, dan mengabaikan prefetch', () => {
        const router = fakeRouter();
        attachLoadFailure(router);

        router.emit('before', { visit: visit('/jadwal?filter[period]=lewat') });
        router.emit('before', {
            visit: visit('/jadwal?halaman-lain', { prefetch: true }),
        });
        router.emit('exception');
        retryLoad(router);

        expect(router.visit).toHaveBeenCalledOnce();
        const [url, options] = router.visit.mock.calls[0];
        expect(String(url)).toBe(
            'http://localhost/jadwal?filter[period]=lewat',
        );
        expect(options).toMatchObject({
            method: 'get',
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
        expect(useLoadFailure().failed.value).toBe(false);
    });

    it('coba lagi tanpa kunjungan tercatat tidak melempar galat', () => {
        const router = fakeRouter();

        expect(() => retryLoad(router)).not.toThrow();
    });

    it('tutup mengosongkan dan pembatal melepas pendengar', () => {
        const router = fakeRouter();
        const detach = attachLoadFailure(router);

        router.emit('exception');
        dismissLoadFailure();
        expect(useLoadFailure().failed.value).toBe(false);

        detach();
        expect(router.count('exception')).toBe(0);
        router.emit('exception');
        expect(useLoadFailure().failed.value).toBe(false);
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npx vitest run resources/js/composables/useLoadFailure.test.ts`
Expected: FAIL (modul `./useLoadFailure` tidak ada).

- [ ] **Step 3: Implementasi**

`resources/js/composables/useLoadFailure.ts`:

```ts
import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';

type VisitLike = {
    url: URL;
    method: string;
    data: unknown;
    preserveState?: unknown;
    preserveScroll?: unknown;
    replace?: boolean;
    only?: string[];
    prefetch?: boolean;
};

type EventLike = {
    detail: Record<string, unknown>;
    preventDefault: () => void;
};

/** Bagian router Inertia yang dipakai; memudahkan pengujian tanpa memasang aplikasi. */
export interface RouterLike {
    on(event: string, callback: (event: EventLike) => unknown): () => void;
    visit(url: string | URL, options?: Record<string, unknown>): void;
}

// Status dibagi seluruh halaman: banner di AppLayout membaca instansi yang sama.
const failed = ref(false);
let lastVisit: VisitLike | null = null;

/**
 * Memantau kegagalan jaringan (`exception`) dan respons non-Inertia (`invalid`).
 * Dipasang sekali di app.ts; mengembalikan fungsi pelepas.
 */
export function attachLoadFailure(
    target: RouterLike = router as unknown as RouterLike,
): () => void {
    const offs = [
        target.on('before', (e) => {
            const visit = e.detail.visit as VisitLike;
            if (!visit.prefetch) lastVisit = visit;
        }),
        target.on('start', () => {
            failed.value = false;
        }),
        target.on('success', () => {
            failed.value = false;
        }),
        target.on('exception', () => {
            failed.value = true;
        }),
        target.on('invalid', (e) => {
            // Mencegah modal HTML mentah bawaan Inertia.
            e.preventDefault();
            failed.value = true;
        }),
    ];

    return () => offs.forEach((off) => off());
}

/** Mengulang kunjungan terakhir (bukan memuat ulang halaman saat ini, yang bisa berbeda). */
export function retryLoad(
    target: RouterLike = router as unknown as RouterLike,
): void {
    const visit = lastVisit;
    failed.value = false;
    if (!visit) return;

    target.visit(visit.url, {
        method: visit.method,
        data: visit.data,
        preserveState: visit.preserveState,
        preserveScroll: visit.preserveScroll,
        replace: visit.replace,
        only: visit.only,
    });
}

export function dismissLoadFailure(): void {
    failed.value = false;
}

export function useLoadFailure() {
    return { failed: readonly(failed) };
}
```

`resources/js/Components/shared/LoadFailedBanner.vue`:

```vue
<script setup lang="ts">
import InlineAlert from '@/Components/shared/InlineAlert.vue';
import { Button } from '@/Components/ui/button';
import {
    dismissLoadFailure,
    retryLoad,
    useLoadFailure,
} from '@/composables/useLoadFailure';

const { failed } = useLoadFailure();
</script>

<template>
    <InlineAlert v-if="failed" class="mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <span class="min-w-0 flex-1"
                >Gagal memuat data. Periksa koneksi Anda lalu coba lagi.</span
            >
            <Button
                type="button"
                variant="outline"
                class="h-9 px-3"
                @click="retryLoad()"
            >
                Coba lagi
            </Button>
            <Button
                type="button"
                variant="ghost"
                class="h-9 px-3"
                @click="dismissLoadFailure()"
            >
                Tutup
            </Button>
        </div>
    </InlineAlert>
</template>
```

`resources/js/app.ts` — impor `import { attachLoadFailure } from '@/composables/useLoadFailure';` (alias `@` dipakai halaman; bila belum tersedia di `app.ts`, gunakan `./composables/useLoadFailure`) dan panggil sekali di `setup`:

```ts
    setup({ el, App, props, plugin }) {
        attachLoadFailure();
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
```

`resources/js/Layouts/AppLayout.vue` — impor `import LoadFailedBanner from '@/Components/shared/LoadFailedBanner.vue';` dan tambahkan `<LoadFailedBanner />` sebagai elemen pertama di dalam `<main ...>` sebelum `<slot />`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `npx prettier --write resources/js/composables/useLoadFailure.ts resources/js/composables/useLoadFailure.test.ts resources/js/Components/shared/LoadFailedBanner.vue resources/js/app.ts resources/js/Layouts/AppLayout.vue && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau. Bila `typecheck` menolak tipe `router as unknown as RouterLike`, sesuaikan tipe `EventLike` agar kompatibel tanpa mengubah perilaku.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useLoadFailure.ts resources/js/composables/useLoadFailure.test.ts resources/js/Components/shared/LoadFailedBanner.vue resources/js/app.ts resources/js/Layouts/AppLayout.vue
git commit -m "feat: tambah banner gagal memuat global dengan coba lagi" -m "Composable useLoadFailure memantau kegagalan jaringan dan respons non-Inertia lewat peristiwa router (dipasang sekali di app.ts), mencegah modal HTML bawaan, dan Coba lagi mengulang kunjungan terakhir (bukan memuat ulang halaman saat ini; prefetch diabaikan). LoadFailedBanner di AppLayout menampilkan pesan dengan tombol Coba lagi dan Tutup untuk semua halaman tanpa mengubah halaman daftar satu per satu."
```

---

### Task 2: Pemilih siswa — backend (paginasi dan pencarian server)

**Files:**
- Create: `tests/Feature/Registrations/CandidatePickerTest.php`
- Modify: `app/Domains/Students/Repositories/Contracts/StudentRepositoryInterface.php`, `app/Domains/Students/Repositories/EloquentStudentRepository.php`, `app/Domains/Students/Services/StudentService.php`, `app/Domains/Registrations/Http/Controllers/RegistrationController.php`, `tests/Feature/Registrations/RegistrationHttpTest.php`

**Interfaces:**
- Produces: `StudentRepositoryInterface::searchForSchool(int $schoolId, ?string $q, int $perPage = 20): LengthAwarePaginator<Student>`; `StudentService::candidatesPage(int $schoolId, ?string $q): LengthAwarePaginator`; props `Pendaftaran/Form`: `candidates` (paginator dengan `data: CandidateRow[]`) dan `selected: CandidateRow[]` (siswa entri draf yang diubah; kosong saat buat). Menghapus `forSchool` (R1).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Registrations/CandidatePickerTest.php`:

```php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['pendaftaran.buat'], $this->school, true, 'op-picker');
});

test('kandidat dipaginasi 20 per halaman', function () {
    Student::factory()->for($this->school)->count(25)->create();
    $this->actingAs($this->op);

    $this->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 20)->where('candidates.total', 25)->where('candidates.last_page', 2));
    $this->get('/pendaftaran/buat?page=2')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 5));
});

test('pencarian menurut nama atau NISN; wildcard literal; q bukan string diabaikan', function () {
    Student::factory()->for($this->school)->create(['name' => 'Ani Lestari', 'nisn' => '0011223344']);
    Student::factory()->for($this->school)->create(['name' => 'Budi Santoso', 'nisn' => '0055667788']);
    $this->actingAs($this->op);

    $this->get('/pendaftaran/buat?q=lestari')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Ani Lestari'));
    $this->get('/pendaftaran/buat?q=0055667788')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Budi Santoso'));
    $this->get('/pendaftaran/buat?q=%25')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 0));
    $this->get('/pendaftaran/buat?q=_')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 0));
    $this->get('/pendaftaran/buat?q[]=x')->assertOk()->assertInertia(fn (Assert $p) => $p->has('candidates.data', 2));
});

test('kandidat hanya dari sekolah yang berlaku dan siswa sekolah lain tidak bocor', function () {
    $lain = School::factory()->create();
    Student::factory()->for($lain)->create(['name' => 'Siswa Lain']);
    Student::factory()->for($this->school)->create(['name' => 'Siswa Sendiri']);

    $this->actingAs($this->op)->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Siswa Sendiri'));

    $staf = makeUserWith(['pendaftaran.buat'], roleName: 'staf-picker');
    $this->actingAs($staf)->get("/pendaftaran/buat?school_id={$lain->id}")->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 1)->where('candidates.data.0.name', 'Siswa Lain'));
    $this->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p->has('candidates.data', 0)->where('candidates.total', 0));
});

test('ubah draf: selected memuat siswa entri walau di halaman lain, dengan alasan bila tidak eligible', function () {
    Student::factory()->for($this->school)->count(25)->create(['name' => 'Aaa Siswa']);
    $terakhir = Student::factory()->for($this->school)->create(['name' => 'Zzz Terakhir']);
    $entry = makeEntry(S::Draf, $this->school, $terakhir);

    $this->actingAs($this->op)->get("/pendaftaran/{$entry->registration_id}/ubah")->assertInertia(fn (Assert $p) => $p
        ->has('candidates.data', 20)
        ->has('selected', 1)->where('selected.0.name', 'Zzz Terakhir')->where('selected.0.selected', true)
        ->where('selected.0.eligible', false)->has('selected.0.reasons'));
});

test('buat pendaftaran tidak memiliki siswa terpilih', function () {
    Student::factory()->for($this->school)->create();

    $this->actingAs($this->op)->get('/pendaftaran/buat')->assertInertia(fn (Assert $p) => $p->has('selected', 0));
});
```

Perbarui `tests/Feature/Registrations/RegistrationHttpTest.php` (bentuk baru `candidates.data`):
- pada tes `staf kecamatan wajib memilih sekolah ...`: ganti `->has('candidates', 1)` menjadi `->has('candidates.data', 1)`, `->where('candidates.0.eligible', true)` menjadi `->where('candidates.data.0.eligible', true)`, dan `->has('candidates', 0)` menjadi `->has('candidates.data', 0)`.
- pada tes `operator melihat kandidat sekolahnya ...`: `->has('candidates', 1)->where('candidates.0.selected', false)` menjadi `->has('candidates.data', 1)->where('candidates.data.0.selected', false)`.

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Registrations/CandidatePickerTest.php tests/Feature/Registrations/RegistrationHttpTest.php`
Expected: FAIL (`candidates.data` tidak ada; `selected` tidak ada).

- [ ] **Step 3: Implementasi**

`StudentRepositoryInterface` — ganti deklarasi `forSchool` dengan:

```php
    /**
     * Siswa sekolah, dicari menurut nama atau NISN (`$q`, `%` dan `_` literal), urut nama; memuat dokumen dan
     * entri pendaftaran untuk pemeriksaan kelayakan.
     *
     * @return LengthAwarePaginator<int, Student>
     */
    public function searchForSchool(int $schoolId, ?string $q, int $perPage = 20): LengthAwarePaginator;
```

`EloquentStudentRepository` — ganti method `forSchool` dengan:

```php
    public function searchForSchool(int $schoolId, ?string $q, int $perPage = 20): LengthAwarePaginator
    {
        $like = $q === null || trim($q) === ''
            ? null
            : '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($q)).'%';

        return Student::query()->where('school_id', $schoolId)
            ->when($like !== null, fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('nisn', 'like', $like)))
            ->with(['documents', 'registrationEntries'])->orderBy('name')->orderBy('id')
            ->paginate($perPage)->withQueryString();
    }
```
(hapus `use Illuminate\Database\Eloquent\Collection;` bila tidak dipakai lagi; Pint merapikan.)

`StudentService` — ganti `forSchool` dengan:

```php
    /** @return LengthAwarePaginator<int, Student> */
    public function candidatesPage(int $schoolId, ?string $q): LengthAwarePaginator
    {
        return $this->students->searchForSchool($schoolId, $q);
    }
```

`RegistrationController` — ubah `create`, `edit`, dan `candidates` serta tambahkan `candidateRow` (impor `Illuminate\Pagination\LengthAwarePaginator`):

```php
    public function create(Request $request): Response
    {
        $user = $request->user();
        $schoolId = $user->school_id ?? ($request->integer('school_id') ?: null);

        return Inertia::render('Pendaftaran/Form', [
            'registration' => null,
            'candidates' => $this->candidates($schoolId, null, $request),
            'selected' => [],
            'schools' => $this->schools->optionsFor($user),
            'school_id' => $schoolId,
        ]);
    }
```

```php
    public function edit(Request $request, Registration $registration): Response|RedirectResponse
    {
        if (! $registration->isDraft()) {
            return redirect()->route('pendaftaran.show', $registration)
                ->with('error', 'Pendaftaran yang sudah diajukan tidak dapat diubah.');
        }

        $registration->load(['entries.student.documents', 'entries.student.registrationEntries']);

        return Inertia::render('Pendaftaran/Form', [
            'registration' => ['id' => $registration->id, 'title' => $registration->title, 'expected_count' => $registration->expected_count],
            'candidates' => $this->candidates($registration->school_id, $registration, $request),
            // Pilihan awal dikirim terpisah: siswa terpilih bisa berada di halaman lain dari hasil pencarian.
            'selected' => $registration->entries->map(fn (RegistrationStudent $e) => $this->candidateRow($e->student, $registration, true))->values()->all(),
            'schools' => null,
            'school_id' => $registration->school_id,
        ]);
    }
```

```php
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    private function candidates(?int $schoolId, ?Registration $registration, Request $request): LengthAwarePaginator
    {
        if ($schoolId === null) {
            return new LengthAwarePaginator([], 0, 20);
        }

        $q = $request->query('q');
        $selected = $registration?->entries->pluck('student_id')->all() ?? [];

        return $this->students->candidatesPage($schoolId, is_string($q) ? $q : null)
            ->through(fn (Student $s) => $this->candidateRow($s, $registration, in_array($s->id, $selected, true)));
    }

    /** @return array<string, mixed> */
    private function candidateRow(Student $s, ?Registration $registration, bool $selected): array
    {
        $check = $this->eligibility->check($s, null, $registration?->id);

        return [
            'id' => $s->id,
            'name' => $s->name,
            'nisn' => $s->nisn,
            'age' => $this->eligibility->age($s),
            'eligible' => $check['eligible'],
            'reasons' => $check['reasons'],
            'missing_docs' => collect(['kk', 'akta_lahir'])->diff($s->documents->pluck('type'))->isNotEmpty(),
            'selected' => $selected,
        ];
    }
```
(hapus method `candidates` lama; `LengthAwarePaginator::through` mengembalikan paginator yang sama.)

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Registrations tests/Feature/Students`
Expected: PASS. Bila `candidates` kosong memicu galat serialisasi paginator tanpa jalur, buat `new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url()])`.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Students app/Domains/Registrations tests/Feature/Registrations
git commit -m "feat: paginasi dan pencarian server untuk kandidat pendaftaran" -m "Kandidat siswa kini dipaginasi 20 per halaman dengan pencarian nama atau NISN di server (karakter wildcard dianggap literal dan parameter bukan string diabaikan); kelayakan hanya dihitung untuk siswa di halaman itu. Pilihan awal mode ubah dikirim terpisah sebagai selected sehingga siswa di halaman lain tetap terbawa. forSchool yang memuat semua siswa dihapus."
```

---

### Task 3: Pemilih siswa — frontend

**Files:**
- Modify: `resources/js/Components/shared/StudentMultiSelect.vue`, `resources/js/Components/shared/StudentMultiSelect.test.ts`, `resources/js/Pages/Pendaftaran/Form.vue`

**Interfaces:**
- Consumes: props `candidates: Paginator<CandidateRow>` dan `selected: CandidateRow[]` (Task 2); `Paginator` dari `@/types/access`.
- Produces: `StudentMultiSelect` props `candidates`, `selectedRows?`, `errors?`, `reloadUrl`, `reloadQuery?`, `v-model: number[]`.

- [ ] **Step 1: Tulis ulang tes yang gagal**

`resources/js/Components/shared/StudentMultiSelect.test.ts` (ganti seluruh isi):

```ts
import type { Paginator } from '@/types/access';
import type { CandidateRow } from '@/types/registration';
import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import StudentMultiSelect from './StudentMultiSelect.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn() },
    Link: { template: '<a><slot /></a>' },
}));

const base = { age: 16, missing_docs: false, selected: false, reasons: [] };
const candidates: CandidateRow[] = [
    { ...base, id: 1, name: 'Ani', nisn: '0000000001', eligible: true },
    { ...base, id: 2, name: 'Budi', nisn: '0000000002', eligible: true },
    {
        ...base,
        id: 3,
        name: 'Citra',
        nisn: '0000000003',
        eligible: false,
        missing_docs: true,
        reasons: ['Kartu Keluarga belum diunggah.'],
    },
];

function page(
    rows: CandidateRow[],
    over: Partial<Paginator<CandidateRow>> = {},
): Paginator<CandidateRow> {
    return {
        data: rows,
        current_page: 1,
        last_page: 1,
        from: 1,
        to: rows.length,
        total: rows.length,
        per_page: 20,
        links: [],
        ...over,
    };
}

function mountSelect(
    modelValue: number[] = [],
    props: Record<string, unknown> = {},
) {
    return mount(StudentMultiSelect, {
        props: {
            candidates: page(candidates),
            modelValue,
            reloadUrl: '/pendaftaran/buat',
            'onUpdate:modelValue': () => {},
            ...props,
        },
        global: { mocks: { route: (n: string, p: unknown) => `/${n}/${p}` } },
    });
}

describe('StudentMultiSelect', () => {
    beforeEach(() => vi.clearAllMocks());
    afterEach(() => vi.useRealTimers());

    it('menampilkan alasan sebagai teks untuk siswa belum eligible', () => {
        const text = mountSelect().text();
        expect(text).toContain('Kartu Keluarga belum diunggah.');
        expect(text).toContain('Lengkapi dokumen');
    });

    it('"Pilih semua yang eligible di halaman ini" menambah yang eligible ke pilihan yang ada', async () => {
        const wrapper = mountSelect([9]);
        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Pilih semua'))!;
        await button.trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[9, 1, 2]]);
    });

    it('penghitung mengikuti jumlah terpilih', () => {
        expect(mountSelect([1, 2]).find('[role="status"]').text()).toContain(
            '2 dipilih',
        );
    });

    it('siswa terpilih dari halaman lain tetap tampil dan dapat dihapus', async () => {
        const zed: CandidateRow = {
            ...base,
            id: 9,
            name: 'Zed',
            nisn: '0000000009',
            eligible: true,
        };
        const wrapper = mountSelect([9], { selectedRows: [zed] });

        expect(wrapper.text()).toContain('Terpilih (1)');
        expect(wrapper.text()).toContain('Zed');

        await wrapper
            .find('button[aria-label="Hapus Zed dari pilihan"]')
            .trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[]]);
    });

    it('pencarian memuat ulang kandidat dari server (debounce), hanya prop candidates', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect([], { reloadQuery: { school_id: 7 } });

        await wrapper.find('input').setValue('budi');
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledWith(
            '/pendaftaran/buat',
            { school_id: 7, q: 'budi' },
            expect.objectContaining({
                only: ['candidates'],
                preserveState: true,
            }),
        );
    });

    it('tombol halaman memuat ulang halaman berikutnya dan menonaktifkan di ujung', async () => {
        const wrapper = mountSelect([], {
            candidates: page(candidates, {
                current_page: 1,
                last_page: 2,
                total: 25,
            }),
        });
        const next = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Berikutnya')!;
        const prev = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Sebelumnya')!;

        expect(prev.attributes('disabled')).toBeDefined();
        await next.trigger('click');
        expect(router.get).toHaveBeenCalledWith(
            '/pendaftaran/buat',
            { page: 2 },
            expect.anything(),
        );
    });

    it('pesan kosong membedakan belum ada siswa dari hasil pencarian kosong', async () => {
        const wrapper = mountSelect([], { candidates: page([]) });
        expect(wrapper.text()).toContain('Belum ada siswa di sekolah ini.');

        vi.useFakeTimers();
        await wrapper.find('input').setValue('xyz');
        expect(wrapper.text()).toContain('Tidak ada siswa yang sesuai pencarian.');
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npx vitest run resources/js/Components/shared/StudentMultiSelect.test.ts`
Expected: FAIL (props dan perilaku baru belum ada).

- [ ] **Step 3: Implementasi**

`resources/js/Components/shared/StudentMultiSelect.vue` (ganti seluruh isi):

```vue
<script setup lang="ts">
import SearchInput from '@/Components/shared/SearchInput.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import type { Paginator } from '@/types/access';
import type { CandidateRow } from '@/types/registration';
import { Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';

const selected = defineModel<number[]>({ required: true });
const props = defineProps<{
    candidates: Paginator<CandidateRow>;
    /** Siswa terpilih sejak awal (mode ubah), termasuk yang tidak ada di halaman ini. */
    selectedRows?: CandidateRow[];
    errors?: Record<number, string>;
    reloadUrl: string;
    reloadQuery?: Record<string, unknown>;
}>();

const search = ref('');

// Baris yang pernah terlihat: pilihan dari halaman lain tetap dapat ditampilkan dan dihapus.
const known = reactive(new Map<number, CandidateRow>());
const remember = (rows: CandidateRow[]) =>
    rows.forEach((r) => known.set(r.id, r));
remember(props.selectedRows ?? []);
watch(() => props.candidates.data, remember, { immediate: true });

const chosen = computed(() =>
    selected.value
        .map((id) => known.get(id))
        .filter((r): r is CandidateRow => r !== undefined),
);

function reload(page = 1) {
    const q = search.value.trim();
    router.get(
        props.reloadUrl,
        {
            ...props.reloadQuery,
            ...(q ? { q } : {}),
            ...(page > 1 ? { page } : {}),
        },
        {
            only: ['candidates'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const onSearch = useDebounceFn(() => reload(1), 300);
watch(search, () => onSearch());

// Siswa yang terpilih tetapi sudah tidak eligible tetap bisa dibatalkan.
const isDisabled = (c: CandidateRow) =>
    !c.eligible && !selected.value.includes(c.id);

function toggle(c: CandidateRow, on: boolean) {
    selected.value = on
        ? [...selected.value, c.id]
        : selected.value.filter((id) => id !== c.id);
}

function selectAllEligible() {
    const ids = new Set(selected.value);
    props.candidates.data.filter((c) => c.eligible).forEach((c) => ids.add(c.id));
    selected.value = [...ids];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <SearchInput
                    v-model="search"
                    placeholder="Cari nama atau NISN"
                    aria-label="Cari siswa"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                class="h-10 px-4"
                @click="selectAllEligible"
            >
                Pilih semua yang eligible di halaman ini
            </Button>
        </div>

        <p role="status" class="text-muted-foreground text-sm">
            {{ selected.length }} dipilih
        </p>

        <div v-if="chosen.length" class="flex flex-col gap-2">
            <p class="text-sm font-medium">Terpilih ({{ chosen.length }})</p>
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="c in chosen"
                    :key="c.id"
                    class="bg-primary-soft text-primary flex items-center gap-1 rounded-full py-1 pr-1 pl-3 text-xs"
                >
                    {{ c.name }}
                    <button
                        type="button"
                        class="hover:bg-primary/10 flex size-6 items-center justify-center rounded-full"
                        :aria-label="`Hapus ${c.name} dari pilihan`"
                        @click="toggle(c, false)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </li>
            </ul>
        </div>

        <ul v-if="candidates.data.length" class="divide-y rounded-lg border">
            <li
                v-for="c in candidates.data"
                :key="c.id"
                class="flex items-start gap-3 px-4 py-3"
                :class="{ 'bg-secondary/50': isDisabled(c) }"
            >
                <Checkbox
                    :id="`siswa-${c.id}`"
                    class="mt-0.5"
                    :model-value="selected.includes(c.id)"
                    :disabled="isDisabled(c)"
                    @update:model-value="toggle(c, $event === true)"
                />
                <div class="min-w-0 flex-1 text-sm leading-5">
                    <label
                        :for="`siswa-${c.id}`"
                        class="block font-medium break-words"
                    >
                        {{ c.name }}
                        <span class="text-muted-foreground font-normal">
                            · NISN {{ c.nisn }} · {{ c.age }} th
                        </span>
                    </label>
                    <ul
                        v-if="!c.eligible"
                        class="text-muted-foreground mt-1 list-disc pl-4 text-xs leading-4"
                    >
                        <li v-for="reason in c.reasons" :key="reason">
                            {{ reason }}
                        </li>
                    </ul>
                    <Link
                        v-if="c.missing_docs"
                        :href="route('siswa.show', c.id)"
                        class="text-primary mt-1 inline-block text-xs underline"
                    >
                        Lengkapi dokumen
                    </Link>
                    <p v-if="errors?.[c.id]" class="text-danger mt-1 text-xs">
                        {{ errors[c.id] }}
                    </p>
                </div>
            </li>
        </ul>
        <p v-else class="text-muted-foreground text-sm">
            {{
                search.trim()
                    ? 'Tidak ada siswa yang sesuai pencarian.'
                    : 'Belum ada siswa di sekolah ini.'
            }}
        </p>

        <div
            v-if="candidates.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-muted-foreground text-xs leading-4">
                Halaman {{ candidates.current_page }} dari
                {{ candidates.last_page }} · {{ candidates.total }} siswa
            </p>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 px-3"
                    :disabled="candidates.current_page <= 1"
                    @click="reload(candidates.current_page - 1)"
                >
                    Sebelumnya
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 px-3"
                    :disabled="candidates.current_page >= candidates.last_page"
                    @click="reload(candidates.current_page + 1)"
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    </div>
</template>
```

`resources/js/Pages/Pendaftaran/Form.vue`:
- Impor `import type { Paginator } from '@/types/access';`.
- Props: ganti `candidates: CandidateRow[];` dengan `candidates: Paginator<CandidateRow>;` dan tambahkan `selected: CandidateRow[];`.
- `student_ids: props.selected.map((c) => c.id),` (menggantikan filter `props.candidates`).
- Tambahkan computed (di bawah `schoolOptions`):

```ts
const reloadUrl = computed(() =>
    props.registration
        ? route('pendaftaran.edit', props.registration.id)
        : route('pendaftaran.create'),
);
const reloadQuery = computed(() =>
    props.schools && form.school_id !== '' ? { school_id: form.school_id } : {},
);
```
- Template: pada `<StudentMultiSelect ...>` tambahkan `:selected-rows="selected"`, `:reload-url="reloadUrl"`, `:reload-query="reloadQuery"`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `npx prettier --write resources/js/Components/shared/StudentMultiSelect.vue resources/js/Components/shared/StudentMultiSelect.test.ts resources/js/Pages/Pendaftaran/Form.vue && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau. Bila tes "pesan kosong" gagal karena pembaruan DOM setelah `setValue`, tambahkan `await wrapper.vm.$nextTick()` sebelum asersi.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/shared/StudentMultiSelect.vue resources/js/Components/shared/StudentMultiSelect.test.ts resources/js/Pages/Pendaftaran/Form.vue
git commit -m "feat: pemilih siswa berpaginasi dengan pencarian server" -m "StudentMultiSelect memuat ulang kandidat lewat partial reload (debounce 300 ms, tombol Sebelumnya dan Berikutnya) alih-alih menyaring seluruh daftar di klien. Pilihan lintas halaman tampil sebagai daftar Terpilih dengan tombol hapus, pilihan awal mode ubah datang dari prop selected, dan Pilih semua yang eligible kini berlaku untuk halaman yang tampil."
```

---

### Task 4: Impor Excel lewat queue — backend

**Files:**
- Create: `database/migrations/2026_10_09_500001_create_student_imports_table.php`, `app/Domains/Students/Models/StudentImport.php`, `app/Domains/Students/Jobs/ImportStudentsJob.php`, `app/Domains/Notifications/Notifications/StudentImportFinishedNotification.php`, `tests/Feature/Students/StudentImportQueueTest.php`
- Modify: `app/Domains/Students/Services/StudentImportService.php`, `app/Domains/Students/Http/Controllers/{StudentImportController,StudentController}.php`, `routes/students.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `tests/Feature/Students/StudentImportTest.php`, `tests/Feature/Access/RoutePermissionMatrixTest.php`

**Interfaces:**
- Produces: `StudentImport` (konstanta `ANTRI`, `BERJALAN`, `SELESAI`, `GAGAL`; `payload(): array{id:int,status:string,imported:int,failed:list<array{row:int,reason:string}>,error:?string}`; `visibleTo(User): bool`); `StudentImportService::prepare(UploadedFile $file, User $actor, ?int $schoolId): StudentImport`, `::run(StudentImport $import): void`, `::markFailed(StudentImport $import): void`, `::findVisible(int $id, User $user): ?StudentImport`; `ImportStudentsJob(int $importId)`; `StudentImportFinishedNotification::finished(StudentImport)`/`::failed(StudentImport)`; rute `siswa.impor.show` (`GET /siswa/impor/{import}` JSON); flash `import` = `{id}` (menggantikan `import_result`); prop `recent_import` pada `Siswa/Index`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Students/StudentImportQueueTest.php`:

```php
<?php

use App\Domains\Notifications\Notifications\StudentImportFinishedNotification;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Jobs\ImportStudentsJob;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;

/** Berkas xlsx sungguhan dengan kolom NIK dan NISN bertipe teks. */
function queueXlsx(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $ws = $book->getActiveSheet();
    $ws->fromArray([['NIK', 'NISN', 'Nama', 'Tanggal lahir', 'Jenis kelamin'], ...$rows], null, 'A1', true);
    foreach ($ws->getRowIterator(2) as $row) {
        foreach (['A', 'B'] as $col) {
            $cell = $ws->getCell($col.$row->getRowIndex());
            $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->school = School::factory()->create();
    $this->op = makeUserWith(['siswa.lihat', 'siswa.impor'], $this->school, true, 'op-antri');
    $this->baris = [
        ['3201123412341234', '0012345678', 'Budi', '2009-05-01', 'L'],
        ['123', '0012345679', 'NIK Salah', '2009-05-01', 'P'],
    ];
});

test('impor diproses job: hasil tersimpan, berkas dihapus, satu log dengan pembuat sebagai penyebab', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)])
        ->assertRedirect()->assertSessionHas('import.id');

    $import = StudentImport::query()->sole();
    expect($import->status)->toBe(StudentImport::SELESAI)
        ->and($import->imported)->toBe(1)
        ->and($import->failed)->toBe([['row' => 3, 'reason' => 'NIK harus 16 digit angka.']])
        ->and($import->path)->toBeNull()
        ->and($import->started_at)->not->toBeNull()->and($import->finished_at)->not->toBeNull()
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([])
        ->and(Student::count())->toBe(1);

    $log = Activity::where('description', 'siswa.diimpor')->sole();
    expect($log->causer_id)->toBe($this->op->id);
});

test('pra-periksa menolak berkas bermasalah seketika tanpa baris impor dan tanpa berkas tersimpan', function () {
    $this->actingAs($this->op);
    $tanpaKolom = queueXlsx([]);
    $lebih = array_map(
        fn ($i) => ['32'.str_pad((string) $i, 14, '0', STR_PAD_LEFT), str_pad((string) $i, 10, '0', STR_PAD_LEFT), "S$i", '2009-05-01', 'L'],
        range(1, 501),
    );

    $this->post('/siswa/impor', ['file' => queueXlsx($lebih)])->assertSessionHasErrors('file');

    $staf = makeUserWith(['siswa.impor'], roleName: 'staf-antri');
    $this->actingAs($staf)->post('/siswa/impor', ['file' => $tanpaKolom])->assertSessionHasErrors('school_id');

    expect(StudentImport::count())->toBe(0)->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('notifikasi selesai dikirim ke pembuat dengan jumlah dan tautan hasil', function () {
    Notification::fake();

    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)]);

    $import = StudentImport::query()->sole();
    Notification::assertSentTo($this->op, StudentImportFinishedNotification::class, fn (StudentImportFinishedNotification $n) => $n->title() === 'Impor siswa selesai'
        && $n->body() === '1 siswa ditambahkan, 1 baris gagal.'
        && $n->url() === "/siswa?impor={$import->id}");
});

test('kegagalan job menandai gagal, menghapus berkas, dan memberi tahu pembuat', function () {
    Notification::fake();
    Storage::disk('local')->put('imports/rusak.xlsx', 'bukan xlsx');
    $import = StudentImport::create([
        'user_id' => $this->op->id, 'school_id' => $this->school->id, 'original_name' => 'rusak.xlsx', 'path' => 'imports/rusak.xlsx',
    ]);

    // Pada queue sync, kegagalan memanggil failed() lalu melempar ulang ke pemanggil.
    expect(fn () => ImportStudentsJob::dispatchSync($import->id))->toThrow(Throwable::class);

    $import->refresh();
    expect($import->status)->toBe(StudentImport::GAGAL)
        ->and($import->error)->not->toBeNull()->and($import->path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
    Notification::assertSentTo($this->op, StudentImportFinishedNotification::class, fn (StudentImportFinishedNotification $n) => $n->title() === 'Impor siswa gagal');
});

test('hasil impor hanya untuk pembuat dan staf kecamatan; operator lain 404', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)]);
    $import = StudentImport::query()->sole();

    $this->get("/siswa/impor/{$import->id}")->assertOk()->assertJson([
        'id' => $import->id, 'status' => 'selesai', 'imported' => 1, 'failed' => [['row' => 3, 'reason' => 'NIK harus 16 digit angka.']], 'error' => null,
    ]);

    $staf = makeUserWith(['siswa.impor'], roleName: 'staf-hasil');
    $this->actingAs($staf)->get("/siswa/impor/{$import->id}")->assertOk();

    $lain = makeUserWith(['siswa.impor'], School::factory()->create(), true, 'op-lain-impor');
    $this->actingAs($lain)->get("/siswa/impor/{$import->id}")->assertNotFound();
});

test('daftar siswa memuat recent_import hanya bila berhak', function () {
    $this->actingAs($this->op)->post('/siswa/impor', ['file' => queueXlsx($this->baris)]);
    $import = StudentImport::query()->sole();

    $this->get("/siswa?impor={$import->id}")->assertInertia(fn (Assert $p) => $p
        ->where('recent_import.id', $import->id)->where('recent_import.status', 'selesai'));
    $this->get('/siswa')->assertInertia(fn (Assert $p) => $p->where('recent_import', null));

    $lain = makeUserWith(['siswa.lihat', 'siswa.impor'], School::factory()->create(), true, 'op-lain-impor2');
    $this->actingAs($lain)->get("/siswa?impor={$import->id}")->assertInertia(fn (Assert $p) => $p->where('recent_import', null));
});
```

Adaptasi `tests/Feature/Students/StudentImportTest.php` ke alur baru (queue `sync` memproses job di dalam request; hasil dibaca dari tabel). Tambahkan impor `use App\Domains\Students\Models\StudentImport;` dan `use Illuminate\Support\Facades\Storage;`; pada `beforeEach` tambahkan `Storage::fake('local');`; tambahkan helper:

```php
/** Impor terakhir yang tersimpan; queue sync memproses job di dalam request. */
function lastImport(): StudentImport
{
    return StudentImport::query()->latest('id')->firstOrFail();
}
```
dan ganti empat asersi `assertSessionHas('import_result', fn ...)`:
- tes `sebagian baris gagal...`: ganti rantai `->assertRedirect()->assertSessionHas('import_result', fn ($r) => ... )` menjadi `->assertRedirect()->assertSessionHas('import.id');` lalu tambahkan `expect(lastImport()->imported)->toBe(2)->and(lastImport()->failed)->toBe([['row' => 3, 'reason' => 'NIK harus 16 digit angka.'], ['row' => 5, 'reason' => 'NIK ganda di berkas.']]);` sebelum `expect(Student::count())...`.
- tes `NIK bertipe angka...`: hapus `->assertSessionHas(...)` dan tambahkan `expect(lastImport()->imported)->toBe(1)->and(lastImport()->failed)->toBe([['row' => 2, 'reason' => 'NIK harus diisi sebagai teks (format kolom NIK = Teks).']]);`.
- tes `tanggal bernomor serial...`: hapus `->assertSessionHas(...)` dan tambahkan `expect(lastImport()->imported)->toBe(2)->and(lastImport()->failed)->toBe([]);`.
- tes `tepat 500 baris diterima`: hapus `->assertSessionHas(...)` dan tambahkan `expect(lastImport()->imported)->toBe(500)->and(lastImport()->failed)->toBe([]);`.
Tes lain (validasi, sekolah, log ringkas, template, penyamaran) tetap karena pra-periksa dan job berjalan sinkron.

`tests/Feature/Access/RoutePermissionMatrixTest.php`: tambahkan baris dataset `['GET', '/siswa/impor/{import}', 'siswa.impor'],`; pada `resolveUri` tambahkan `use App\Domains\Students\Models\StudentImport;`, di dalam fungsi `$import = StudentImport::create(['user_id' => $target->id, 'school_id' => $school->id, 'original_name' => 'x.xlsx']);`, dan tambahkan `'{import}'` ke array pencari serta `$import->id` ke array pengganti pada posisi yang sama.

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Students/StudentImportQueueTest.php tests/Feature/Students/StudentImportTest.php`
Expected: FAIL (model, job, notifikasi, dan rute belum ada).

- [ ] **Step 3: Implementasi**

`database/migrations/2026_10_09_500001_create_student_imports_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('school_id')->constrained();
            $table->string('status', 12)->default('antri');
            $table->string('original_name');
            // Berkas berisi NIK: dikosongkan dan dihapus setelah diproses.
            $table->string('path')->nullable();
            $table->unsignedInteger('imported')->default(0);
            $table->json('failed')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_imports');
    }
};
```

`app/Domains/Students/Models/StudentImport.php`:

```php
<?php

namespace App\Domains\Students\Models;

use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $school_id
 * @property string $status
 * @property string $original_name
 * @property ?string $path
 * @property int $imported
 * @property ?list<array{row: int, reason: string}> $failed
 * @property ?string $error
 */
class StudentImport extends Model
{
    public const ANTRI = 'antri';

    public const BERJALAN = 'berjalan';

    public const SELESAI = 'selesai';

    public const GAGAL = 'gagal';

    protected $fillable = ['user_id', 'school_id', 'status', 'original_name', 'path', 'imported', 'failed', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['failed' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Hasil impor hanya untuk pembuatnya dan staf kecamatan (tidak terikat sekolah). */
    public function visibleTo(User $user): bool
    {
        return $user->school_id === null || $this->user_id === $user->id;
    }

    /** @return array{id: int, status: string, imported: int, failed: list<array{row: int, reason: string}>, error: ?string} */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'imported' => $this->imported,
            'failed' => $this->failed ?? [],
            'error' => $this->error,
        ];
    }
}
```

`app/Domains/Notifications/Notifications/StudentImportFinishedNotification.php`:

```php
<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Students\Models\StudentImport;

class StudentImportFinishedNotification extends BaseNotification
{
    private function __construct(
        private readonly int $importId,
        private readonly string $title,
        private readonly string $body,
    ) {}

    public static function finished(StudentImport $import): self
    {
        $failed = count($import->failed ?? []);

        return new self($import->id, 'Impor siswa selesai', "{$import->imported} siswa ditambahkan, {$failed} baris gagal.");
    }

    public static function failed(StudentImport $import): self
    {
        return new self($import->id, 'Impor siswa gagal', 'Berkas tidak dapat diproses. Periksa berkas lalu coba lagi.');
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function url(): string
    {
        return "/siswa?impor={$this->importId}";
    }
}
```

`app/Domains/Students/Jobs/ImportStudentsJob.php`:

```php
<?php

namespace App\Domains\Students\Jobs;

use App\Domains\Notifications\Notifications\StudentImportFinishedNotification;
use App\Domains\Students\Models\StudentImport;
use App\Domains\Students\Services\StudentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Tanpa percobaan ulang: NIK unik membuat percobaan kedua melaporkan semua baris sebagai ganda.
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $importId) {}

    public function handle(StudentImportService $service): void
    {
        $import = StudentImport::query()->findOrFail($this->importId);
        $service->run($import);
        $import->user->notify(StudentImportFinishedNotification::finished($import->refresh()));
    }

    public function failed(Throwable $exception): void
    {
        $import = StudentImport::query()->find($this->importId);
        if ($import === null) {
            return;
        }

        app(StudentImportService::class)->markFailed($import);
        $import->user->notify(StudentImportFinishedNotification::failed($import->refresh()));
    }
}
```

`StudentImportService` — ganti method `import()` dengan `prepare`, `run`, `markFailed`, `findVisible`, dan ubah `read()` agar menerima berkas atau path:

```php
    /**
     * Pra-periksa sinkron (sekolah, format, kolom wajib, batas baris), simpan berkas, buat baris impor, dan antrekan job.
     *
     * @throws ValidationException
     */
    public function prepare(UploadedFile $file, User $actor, ?int $schoolId): StudentImport
    {
        $schoolId = $actor->school_id ?? $schoolId;
        if ($schoolId === null) {
            throw ValidationException::withMessages(['school_id' => 'Pilih sekolah.']);
        }

        $this->read($file);

        $import = StudentImport::create([
            'user_id' => $actor->id,
            'school_id' => $schoolId,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->storeAs('imports', Str::uuid().'.xlsx', 'local'),
        ]);
        ImportStudentsJob::dispatch($import->id);

        return $import;
    }

    /** Isi job: memproses baris, menyimpan hasil, dan menghapus berkas (juga bila gagal di tengah jalan). */
    public function run(StudentImport $import): void
    {
        $import->update(['status' => StudentImport::BERJALAN, 'started_at' => now()]);

        try {
            $rows = $this->read((string) $import->path, 'local');
            $imported = 0;
            $failed = [];
            $seen = [];

            foreach ($rows as $index => $row) {
                if ($this->isBlank($row)) {
                    continue;
                }

                $reason = $this->importRow($row, $import->school_id, $seen);
                if ($reason === null) {
                    $imported++;
                } else {
                    $failed[] = ['row' => $index + 2, 'reason' => $reason]; // baris 1 adalah judul
                }
            }

            $import->update(['status' => StudentImport::SELESAI, 'imported' => $imported, 'failed' => $failed, 'finished_at' => now()]);
            activity('siswa')->causedBy($import->user)->withProperties(['berhasil' => $imported, 'gagal' => count($failed)])->log('siswa.diimpor');
        } finally {
            $this->discardFile($import);
        }
    }

    public function markFailed(StudentImport $import): void
    {
        $import->update([
            'status' => StudentImport::GAGAL,
            'error' => 'Impor gagal diproses. Periksa berkas lalu coba lagi.',
            'finished_at' => now(),
        ]);
        $this->discardFile($import);
    }

    public function findVisible(int $id, User $user): ?StudentImport
    {
        $import = StudentImport::query()->find($id);

        return $import !== null && $user->can('siswa.impor') && $import->visibleTo($user) ? $import : null;
    }

    private function discardFile(StudentImport $import): void
    {
        if ($import->path !== null) {
            Storage::disk('local')->delete($import->path);
            $import->update(['path' => null]);
        }
    }
```
Ubah `read()`:

```php
    private function read(UploadedFile|string $file, ?string $disk = null): array
    {
        ...
        $rows = Excel::toArray($reader, $file, $disk)[0] ?? [];
```
(docblock `@throws ValidationException` tetap). Tambahkan impor: `ImportStudentsJob`, `StudentImport`, `Illuminate\Support\Facades\Storage`, `Illuminate\Support\Str`. Hapus method `import()` lama beserta variabel yang tidak dipakai.

`StudentImportController`:

```php
    public function store(Request $request): RedirectResponse
    {
        $request->validate([...]);  // validasi yang sudah ada tidak berubah

        $import = $this->import->prepare($request->file('file'), $request->user(), $request->integer('school_id') ?: null);

        return back()->with('import', ['id' => $import->id]);
    }

    public function show(Request $request, StudentImport $import): JsonResponse
    {
        abort_unless($import->visibleTo($request->user()), 404);

        return response()->json($import->payload());
    }
```
(impor `JsonResponse`, `StudentImport`.)

`routes/students.php` — tambahkan setelah rute `siswa.impor.store`:

```php
    Route::get('siswa/impor/{import}', [StudentImportController::class, 'show'])->middleware('can:siswa.impor')->name('siswa.impor.show');
```

`HandleInertiaRequests::share` — ganti `'import_result' => ...` dengan `'import' => fn () => $request->session()->get('import'),`.

`StudentController` — inject `StudentImportService $imports` (konstruktor) dan tambahkan ke props `index`:

```php
            'recent_import' => $request->filled('impor')
                ? $this->imports->findVisible($request->integer('impor'), $request->user())?->payload()
                : null,
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan migrate && php artisan test tests/Feature/Students tests/Feature/Access/RoutePermissionMatrixTest.php`
Expected: PASS. Bila Larastan menandai `findVisible(...)?->payload()` sebagai nullsafe tak perlu, itu tidak berlaku karena return `?StudentImport`; bila `UploadedFile::storeAs` mengembalikan `false|string`, tangani dengan `?: throw new RuntimeException('Gagal menyimpan berkas impor.')`.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app database tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_10_09_500001_create_student_imports_table.php app/Domains/Students app/Domains/Notifications/Notifications/StudentImportFinishedNotification.php app/Http/Middleware/HandleInertiaRequests.php routes/students.php tests/Feature/Students tests/Feature/Access/RoutePermissionMatrixTest.php
git commit -m "feat: proses impor siswa lewat queue dengan hasil tersimpan" -m "Pra-periksa (sekolah, format, kolom wajib, batas 500 baris) tetap sinkron; berkas disimpan di disk privat, baris student_imports dibuat, dan ImportStudentsJob memproses baris, menyimpan hasil, mencatat satu log dengan pembuat sebagai penyebab, menghapus berkas berisi NIK baik saat sukses maupun gagal, lalu memberi tahu pembuat dengan tautan ke hasil. Hasil dapat dibuka lewat GET /siswa/impor/{id} dan ?impor= hanya oleh pembuat atau staf kecamatan."
```

---

### Task 5: Impor Excel lewat queue — frontend

**Files:**
- Create: `resources/js/composables/useImportStatus.ts`, `resources/js/composables/useImportStatus.test.ts`
- Modify: `resources/js/types/student.ts`, `resources/js/types/index.d.ts`, `resources/js/Components/shared/ImportStudentsDialog.vue`, `resources/js/Pages/Siswa/Index.vue`

**Interfaces:**
- Consumes: flash `import: {id}` dan rute `siswa.impor.show` (JSON `ImportStatus`), prop `recent_import` (Task 4).
- Produces: tipe `ImportStatus`; `useImportStatus(fetcher?)` mengembalikan `{ status, stalled, start(id), show(initial), stop }`.

- [ ] **Step 1: Tulis tes Vitest yang gagal**

`resources/js/composables/useImportStatus.test.ts`:

```ts
import type { ImportStatus } from '@/types/student';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { useImportStatus } from './useImportStatus';

const result = (over: Partial<ImportStatus> = {}): ImportStatus => ({
    id: 5,
    status: 'antri',
    imported: 0,
    failed: [],
    error: null,
    ...over,
});

function setup(fetcher: (id: number) => Promise<ImportStatus>) {
    const scope = effectScope();
    const api = scope.run(() => useImportStatus(fetcher))!;

    return { api, scope };
}

describe('useImportStatus', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('polling tiap 2 detik sampai selesai lalu berhenti', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockResolvedValueOnce(result({ status: 'berjalan' }))
            .mockResolvedValue(result({ status: 'selesai', imported: 3 }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(0);
        expect(api.status.value?.status).toBe('berjalan');

        await vi.advanceTimersByTimeAsync(2000);
        expect(api.status.value).toMatchObject({ status: 'selesai', imported: 3 });

        const calls = fetcher.mock.calls.length;
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetcher.mock.calls.length).toBe(calls);
        scope.stop();
    });

    it('berhenti saat gagal', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockResolvedValue(result({ status: 'gagal', error: 'x' }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(0);
        const calls = fetcher.mock.calls.length;
        await vi.advanceTimersByTimeAsync(6000);

        expect(api.status.value?.status).toBe('gagal');
        expect(fetcher.mock.calls.length).toBe(calls);
        scope.stop();
    });

    it('menandai stalled bila masih antri lebih dari 20 detik', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockResolvedValue(result({ status: 'antri' }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(18000);
        expect(api.stalled.value).toBe(false);

        await vi.advanceTimersByTimeAsync(4000);
        expect(api.stalled.value).toBe(true);
        scope.stop();
    });

    it('show menampilkan hasil yang sudah selesai tanpa polling', async () => {
        const fetcher = vi.fn<(id: number) => Promise<ImportStatus>>();
        const { api, scope } = setup(fetcher);

        api.show(result({ status: 'selesai', imported: 2 }));
        await vi.advanceTimersByTimeAsync(10000);

        expect(api.status.value?.imported).toBe(2);
        expect(fetcher).not.toHaveBeenCalled();
        scope.stop();
    });

    it('kegagalan jaringan sesaat tidak menghentikan polling', async () => {
        const fetcher = vi
            .fn<(id: number) => Promise<ImportStatus>>()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue(result({ status: 'selesai' }));
        const { api, scope } = setup(fetcher);

        api.start(5);
        await vi.advanceTimersByTimeAsync(0);
        await vi.advanceTimersByTimeAsync(2000);

        expect(api.status.value?.status).toBe('selesai');
        scope.stop();
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `npx vitest run resources/js/composables/useImportStatus.test.ts`
Expected: FAIL (modul dan tipe `ImportStatus` belum ada).

- [ ] **Step 3: Implementasi**

`resources/js/types/student.ts` — ganti tipe `ImportResult` dengan:

```ts
export type ImportStatus = {
    id: number;
    status: 'antri' | 'berjalan' | 'selesai' | 'gagal';
    imported: number;
    failed: { row: number; reason: string }[];
    error: string | null;
};
```

`resources/js/types/index.d.ts` — ganti impor `ImportResult` menjadi `ImportStatus` dan ganti baris `import_result?: ImportResult | null;` dengan `import?: { id: number } | null;` (hapus impor `ImportStatus` bila tidak dipakai di berkas ini setelah perubahan).

`resources/js/composables/useImportStatus.ts`:

```ts
import type { ImportStatus } from '@/types/student';
import { onScopeDispose, ref } from 'vue';

const POLL_MS = 2000;
const STALLED_MS = 20000;

async function fetchStatus(id: number): Promise<ImportStatus> {
    const response = await fetch(route('siswa.impor.show', id), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    if (!response.ok) throw new Error(`Status ${response.status}`);

    return (await response.json()) as ImportStatus;
}

const isDone = (s: ImportStatus) => s.status === 'selesai' || s.status === 'gagal';

/** Memantau impor di queue lewat polling; berhenti saat selesai/gagal dan saat cakupan dibuang. */
export function useImportStatus(
    fetcher: (id: number) => Promise<ImportStatus> = fetchStatus,
) {
    const status = ref<ImportStatus | null>(null);
    const stalled = ref(false);
    let timer: ReturnType<typeof setInterval> | null = null;
    let startedAt = 0;

    function stop() {
        if (timer !== null) clearInterval(timer);
        timer = null;
    }

    async function tick(id: number) {
        try {
            const next = await fetcher(id);
            status.value = next;
            stalled.value =
                next.status === 'antri' && Date.now() - startedAt > STALLED_MS;
            if (isDone(next)) stop();
        } catch {
            // Gangguan sesaat: polling berikutnya mencoba lagi.
        }
    }

    function start(id: number) {
        stop();
        startedAt = Date.now();
        stalled.value = false;
        status.value = { id, status: 'antri', imported: 0, failed: [], error: null };
        void tick(id);
        timer = setInterval(() => void tick(id), POLL_MS);
    }

    /** Menampilkan hasil yang sudah ada; hanya memantau bila belum selesai. */
    function show(initial: ImportStatus) {
        stop();
        status.value = initial;
        if (!isDone(initial)) start(initial.id);
    }

    onScopeDispose(stop);

    return { status, stalled, start, show, stop };
}
```

`resources/js/Components/shared/ImportStudentsDialog.vue` — perubahan:
- Skrip: ganti impor `ImportResult` dengan `ImportStatus` (`import type { ImportStatus } from '@/types/student';`), tambahkan `import { useImportStatus } from '@/composables/useImportStatus';`, `watch` dari `vue`, dan ikon `Clock` tidak diperlukan. Tambahkan prop `recentImport?: ImportStatus | null` pada `defineProps`.
- Ganti `const result = ref<ImportResult | null>(null);` dengan `const { status, stalled, start, show, stop } = useImportStatus();` dan tambahkan:

```ts
const finished = computed(
    () => status.value?.status === 'selesai' || status.value?.status === 'gagal',
);
const processing = computed(() => status.value !== null && !finished.value);

// Hasil dari tautan notifikasi (?impor=ID) dibuka langsung.
watch(
    () => props.recentImport,
    (value) => {
        if (value) show(value);
    },
    { immediate: true },
);
```
- Pada `submit()` ganti `onSuccess` dengan:

```ts
        onSuccess: () => {
            const id = page.props.flash.import?.id;
            if (id) start(id);
        },
```
- Ganti `again()` menjadi:

```ts
function again() {
    stop();
    status.value = null;
    form.reset();
    form.clearErrors();
}
```
- Template: ganti blok `<div v-if="result" ...>` (hasil) dengan dua blok:

```vue
            <div v-if="processing" class="flex flex-col gap-3 text-sm">
                <InlineAlert variant="warning">
                    <span class="inline-flex items-center gap-2">
                        <LoaderCircle
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        Impor sedang diproses. Anda boleh menutup dialog ini;
                        notifikasi dikirim saat selesai.
                    </span>
                </InlineAlert>
                <p v-if="stalled" class="text-muted-foreground text-xs leading-4">
                    Menunggu proses latar belakang; pastikan queue worker
                    berjalan.
                </p>
                <DialogFooter>
                    <Button @click="close">Tutup</Button>
                </DialogFooter>
            </div>

            <div v-else-if="finished && status" class="flex flex-col gap-3 text-sm">
                <InlineAlert v-if="status.status === 'gagal'">
                    {{ status.error ?? 'Impor gagal diproses.' }}
                </InlineAlert>
                <InlineAlert
                    v-else
                    :variant="status.failed.length ? 'warning' : 'success'"
                >
                    {{ status.imported }} siswa berhasil ditambahkan,
                    {{ status.failed.length }} baris gagal.
                </InlineAlert>
                <div
                    v-if="status.failed.length > 0"
                    class="max-h-[320px] overflow-auto rounded-lg border"
                >
                    <table class="w-full text-left text-sm">
                        <thead
                            class="bg-secondary text-muted-foreground text-xs"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="w-16 px-3 py-2 font-medium"
                                >
                                    Baris
                                </th>
                                <th scope="col" class="px-3 py-2 font-medium">
                                    Alasan
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="f in status.failed"
                                :key="f.row"
                                class="border-t align-top"
                            >
                                <td class="px-3 py-2">{{ f.row }}</td>
                                <td class="px-3 py-2">{{ f.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="again">Impor lagi</Button>
                    <Button @click="close">Tutup</Button>
                </DialogFooter>
            </div>
```
Hapus blok hasil lama (`<div v-if="result" ...>`) yang digantikan dua blok di atas, dan biarkan `<form v-else ...>` tetap `v-else` (kini cabang ketiga).

`resources/js/Pages/Siswa/Index.vue` — tambahkan impor `import type { ImportStatus } from '@/types/student';` (bila belum), prop `recent_import: ImportStatus | null;` pada `defineProps`, ubah `const importOpen = ref(false);` menjadi `const importOpen = ref(props.recent_import !== null);`, dan ubah pemakaian dialog menjadi `<ImportStudentsDialog v-model:open="importOpen" :schools="schools" :recent-import="recent_import" />`.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `npx prettier --write resources/js/composables/useImportStatus.ts resources/js/composables/useImportStatus.test.ts resources/js/types/student.ts resources/js/types/index.d.ts resources/js/Components/shared/ImportStudentsDialog.vue resources/js/Pages/Siswa/Index.vue && npx vitest run && npm run typecheck && npm run lint && npm run build`
Expected: semua hijau.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useImportStatus.ts resources/js/composables/useImportStatus.test.ts resources/js/types resources/js/Components/shared/ImportStudentsDialog.vue resources/js/Pages/Siswa/Index.vue
git commit -m "feat: tampilkan proses dan hasil impor siswa lewat polling" -m "Dialog impor kini menampilkan layar diproses dengan polling tiap 2 detik lewat useImportStatus (berhenti saat selesai atau gagal, petunjuk queue worker bila masih antri lebih dari 20 detik), lalu hasil yang sama seperti sebelumnya atau pesan gagal. Dialog boleh ditutup saat menunggu, dan tautan notifikasi ?impor= membuka hasil tersimpan langsung."
```

---

### Task 6: Verifikasi, review, dokumentasi, dan penutupan

**Files:**
- Modify: `docs/operasional-queue.md`, `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (5.20, 5.22, bagian 6), `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`; memory `deferred-items.md`.

- [ ] **Step 1: Suite penuh di background**

Run (background): `php artisan test > /tmp/f6c2-full.txt 2>&1` lalu pantau. Expected: semua lulus (443 sebelumnya + tes baru). Juga `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vitest run`, `npm run typecheck`, `npm run lint`, `npm run build` hijau.

- [ ] **Step 2: Verifikasi browser (Playwright, 1440 dan 390 px)**

Jalankan `php artisan serve --port=8099` (background); jangan matikan Vite dev server user. Login lewat `browser_run_code_unsafe` (`page.getByLabel('Email').fill(...)`; bila `/login` mengalihkan, langsung buka halaman). Data demo sementara lewat tinker `include` file di scratchpad lalu pulihkan setelahnya.
1. **Banner gagal memuat:** di `/jadwal` panggil `await page.context().setOffline(true)`, ganti filter Periode; banner "Gagal memuat data..." muncul; `setOffline(false)` lalu klik **Coba lagi**: banner hilang dan filter yang gagal terterapkan; **Tutup** juga berfungsi.
2. **Pemilih siswa:** buat 25 siswa demo untuk sekolah demo; `/pendaftaran/buat` sebagai `op.siswa@example.com` menampilkan 20 siswa dan "Halaman 1 dari 2"; cari nama memuat ulang hasil; pilih satu siswa, cari nama lain, pilih satu lagi: "Terpilih (2)" memuat keduanya; simpan draf lalu ubah: `Terpilih` memuat keduanya walau di halaman lain.
3. **Impor:** buat berkas xlsx kecil (lewat skrip PhpSpreadsheet di scratchpad, disalin sementara ke direktori proyek karena Playwright hanya membaca di sana, dihapus sesudahnya); unggah lewat `page.locator('input[type=file]').setInputFiles(...)`. Tanpa worker: dialog "Impor sedang diproses" dan petunjuk worker setelah 20 detik. Lalu jalankan `php artisan queue:work --stop-when-empty`: dialog berubah menjadi hasil; buka tautan notifikasi `/siswa?impor={id}` menampilkan hasil yang sama; pastikan `storage/app/private/imports` kosong.
4. Tanpa overflow horizontal pada 390 px, konsol bersih. Pulihkan data demo, matikan server 8099, hapus tangkapan layar dan berkas sementara.

- [ ] **Step 3: Review**

`ponytail:ponytail-review` pada `git diff 30ded9c..HEAD -- app routes resources/js tests database` dan `impeccable` (critique) pada `StudentMultiSelect.vue`, `ImportStudentsDialog.vue`, `LoadFailedBanner.vue`, dan `Pendaftaran/Form.vue` dengan panduan UI. Perbaiki temuan valid dengan TDD dan commit terpisah "refactor: terapkan hasil review Fase 6c-2".

- [ ] **Step 4: Dokumentasi dan memory**

`docs/operasional-queue.md`: tambahkan bagian "Impor siswa" (impor kini diproses di queue; worker wajib berjalan; berkas unggahan dihapus setelah diproses; tabel `student_imports` menyimpan hasil; `queue:work` lokal). Panduan UI: 5.20 (proses latar belakang, layar diproses, petunjuk worker, hasil dari notifikasi), 5.22 (pemilih berpaginasi, "Terpilih (N)", "Pilih semua yang eligible di halaman ini"), dan bagian 6 pola lintas halaman (banner gagal memuat dengan Coba lagi). Sprint plan: "Status Fase 6c-2 (2026-10-09): SELESAI"; 6c-3 belum dimulai; rilis ditahan. Memory `deferred-items.md`: tandai 6c-2 selesai (hapus butir gagal memuat, pemilih siswa server-side, impor queue), sisakan 6c-3 dan kelompok D; catat bahwa impor butuh worker dan `UploadedFile::fake()` hanya menebak dari nama.

- [ ] **Step 5: Verifikasi akhir dan commit**

Jalankan ulang semua pemeriksaan Step 1; semuanya hijau sebelum klaim selesai.

```bash
git add docs
git commit -m "docs: tandai Fase 6c-2 selesai dan perbarui panduan UI" -m "Panduan UI memuat pola banner gagal memuat, pemilih siswa berpaginasi, dan proses impor latar belakang; dokumen operasional queue menjelaskan kebutuhan worker untuk impor; rencana sprint mencatat status Fase 6c-2, 6c-3 belum dimulai, dan rilis masih ditahan."
```
