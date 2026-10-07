# Fase 1 FE — Autentikasi dan User Management (dari Figma) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasi halaman Fase 1 yang sudah didesain di Figma di atas backend Fase 1 yang sudah jadi, lalu direview dengan `impeccable`.

**Architecture:** Inertia + Vue 3 + TypeScript. Logika murni (format, matriks izin, link WhatsApp, salin teks) berada di `resources/js/lib/` dengan Vitest; markup diturunkan dari `get_design_context` Figma lalu disesuaikan ke komponen shadcn-vue/`shared/` yang sudah ada. Menu sidebar kini dari prop server `menu`.

**Tech Stack:** Vue 3, Inertia v2, TypeScript, Tailwind v4, shadcn-vue (reka-ui), `@lucide/vue`, Vitest, Pest (untuk perubahan BE kecil).

**Spec:** `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (panduan), desain Figma `https://www.figma.com/design/MtvFbsI68mf00D6De0dw9L/SIJEBOL` (file key `MtvFbsI68mf00D6De0dw9L`), kontrak data BE di `docs/superpowers/specs/2026-10-07-fase-1-backend-design.md`.

## Global Constraints

- **Figma adalah target visual.** Untuk setiap halaman: panggil `get_design_context` (dengan `skillNames: "figma-design-to-code"`), jadikan screenshot-nya target, **adaptasikan** kode referensi ke komponen proyek (jangan salin posisi absolut); semua aset statis (logo lambang) diunduh ke repo, tanpa URL Figma sementara di kode.
- Reuse sebelum membuat: `Components/ui/*` (shadcn), `Components/shared/*`, `lib/*`, `composables/*`. Tidak ada formatter yang diduplikasi (`lib/date.ts`, `lib/format.ts`).
- Token warna/radius mengikuti `resources/css/app.css` (sudah sesuai panduan UI). Jika Figma memakai nilai berbeda, laporkan di ledger sebagai ruling, jangan menimpa token diam-diam.
- Penamaan folder mengikuti Breeze: `resources/js/Components`, `Layouts`, `Pages`; folder baru huruf kecil (`lib`, `composables`, `config`).
- Nama halaman Inertia dan props **harus persis** kontrak BE: `Auth/Login`, `Dashboard`, `Pengguna/Index|Form|Show|Berhasil`, `Role/Index|Form` (lihat tipe di Task 2).
- Filter daftar dikirim sebagai `filter[search]`, `filter[role]`, `filter[school_id]`, `filter[status]` (query-builder BE).
- Halaman di luar Figma (Aktivasi, Lupa/Reset password, Sekolah, Profil, error) **tidak** dikerjakan di rencana ini; halaman Breeze lama dibiarkan.
- Beranda: BE belum menyediakan data ringkasan/jadwal/notifikasi. Blok dibuat sebagai komponen dengan props opsional; tanpa data tampil empty state. **Dilarang** memasukkan data contoh ke kode produksi.
- Aksesibilitas: label terhubung ke input, fokus terlihat, status tidak hanya warna, target sentuh ≥ 40 px, kontras ≥ 4.5:1.
- Commit: pesan detail, **tanpa `Co-Authored-By`/atribusi AI** (`CLAUDE.md`). Eksekutor meminta persetujuan sekali di awal bahwa langkah commit di rencana ini boleh dijalankan.
- Verifikasi tiap task: `npm test`, `npm run typecheck`, `npm run lint`, `npm run build`; task BE juga `composer test`, `composer analyse`, `composer lint`. Jalankan `npm run format` sebelum lint.
- Verifikasi visual: bandingkan screenshot halaman hasil (desktop 1440 dan mobile 390) dengan screenshot Figma. Catatan: input mouse/keyboard Playwright MCP tidak mencapai halaman di sesi ini; interaksi diuji lewat `element.click()` programatik atau `page.evaluate`.
- Review akhir: skill `impeccable` (critique + audit) atas seluruh perubahan FE, perbaiki temuan valid, lalu `superpowers:verification-before-completion`.

## Peta Figma → Inertia

| Figma (node) | Halaman Inertia | Task |
|---|---|---|
| Login desktop `6:348`, mobile `6:382`, state `6:416` | `Auth/Login` | 4 |
| Beranda desktop `6:4190`, mobile `6:4379`, state `6:4532` | `Dashboard` | 5 |
| Pengguna desktop `6:9962`, mobile `6:10237`, state `6:10481` | `Pengguna/Index` | 6 |
| Tambah pengguna `13:1942`/`13:2034`, state `13:2090`; Ubah `22:3861`/`22:3954`, state `22:4170` | `Pengguna/Form` | 7 |
| Akun dibuat `16:1145`/`16:1233`, state `16:1284`; Kirim ulang `22:4013`/`22:4109` | `Pengguna/Berhasil` | 8 |
| Detail pengguna `19:2443`/`19:2587`, state `19:2699` | `Pengguna/Show` | 8 |
| Role & Permission `24:2961`/`24:3083`, state `24:3175` | `Role/Index` | 9 |
| Tambah role `26:6431`/`26:6768`, Ubah role `26:7074`/`26:7437`, state `26:7769` | `Role/Form` | 9 |
| Navigation parts `6:2207`, Desktop expanded `6:2353`, collapsed `6:2398`, mobile `6:2433`/`6:2448` | shell (`AppLayout`, `Sidebar`, `Topbar`) | 3 |
| Logo `2:14` | aset lambang | 3 |

## Review Focus

1. Ikon menu dari server dengan kunci tak dikenal: jatuh ke ikon default, bukan crash (Task 3).
2. Label role dari slug (`admin-kecamatan` → "Admin kecamatan"); nilai kosong/`null` tidak melempar (Task 2).
3. Salin link di konteks non-HTTPS (`navigator.clipboard` tidak ada): fallback, dan umpan balik jelas bila gagal (Task 2, 8).
4. Tombol WhatsApp: nomor `null`/tidak valid → nonaktif; `08…` dinormalisasi ke `62…` (Task 2, 8).
5. Matriks izin: checkbox yang tidak dimiliki penyunting tidak bisa diubah **tetapi tetap ikut terkirim bila sudah tercentang** (jika tidak, izin itu terhapus tanpa sengaja); "Pilih semua" hanya memengaruhi yang editable (Task 2, 9).
6. Daftar: mengubah filter mengembalikan ke halaman 1; filter kosong tidak ikut dikirim; state kosong dan "hasil filter kosong" berbeda (Task 2, 6).
7. Flash `error` dari `AccessRuleException` tampil sebagai toast (sudah di `AppLayout`; dipastikan di Task 6/7 lewat uji manual satu kasus).
8. Halaman `Berhasil` dibuka ulang: BE mengarahkan ke detail; FE tidak menyimpan link di storage (Task 8).

## File Structure

```
app/Domains/Access/Http/Controllers/UserController.php   (BE kecil: can_manage per baris, mode berhasil)
app/Http/Middleware/HandleInertiaRequests.php             (BE kecil: auth.user.role)
resources/js/types/{index.d.ts, access.ts}
resources/js/lib/{format.ts(humanize), whatsapp.ts, clipboard.ts, permissionMatrix.ts, status.ts?}
resources/js/composables/useDataTable.ts                  (format filter[...])
resources/js/config/menuIcons.ts                          (hapus config/navigation.ts)
resources/js/assets/brand/lambang.(png|svg)               (dari Figma)
resources/js/Components/shared/{Breadcrumbs,FormField,SelectField,SearchInput,Pagination,InfoList,CopyField,RowActions}.vue
resources/js/Layouts/{AuthLayout,AppLayout}.vue, Layouts/partials/{Sidebar,Topbar}.vue
resources/js/Pages/{Auth/Login,Dashboard}.vue
resources/js/Pages/Pengguna/{Index,Form,Show,Berhasil}.vue
resources/js/Pages/Role/{Index,Form}.vue
```

---

### Task 1: Kontrak BE untuk desain (role di header, mode berhasil, can_manage per baris)

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `app/Domains/Access/Http/Controllers/UserController.php`
- Test: `tests/Feature/Setup/SharedPropsTest.php`, `tests/Feature/Access/UserHttpTest.php`

**Interfaces:**
- Produces: `auth.user.role: string|null` (nama role, slug); `Pengguna/Berhasil` props tambahan `mode: 'dibuat'|'ulang'`; setiap baris `Pengguna/Index` memuat `can_manage: bool`.

- [ ] **Step 1: Test (gagal dulu)**

Di `tests/Feature/Setup/SharedPropsTest.php` ubah test pertama agar memeriksa role:
```php
test('pengguna login menerima daftar permission dan nama role miliknya', function () {
    PermissionModel::findOrCreate('user.lihat');
    $user = User::factory()->create();
    $user->givePermissionTo('user.lihat');
    $user->assignRole(Role::create(['name' => 'penguji']));

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.id', $user->id)
        ->where('auth.user.role', 'penguji')
        ->where('auth.permissions', ['user.lihat'])
    );
});
```
(tambahkan `use App\Domains\Access\Models\Role;`). Di `tests/Feature/Access/UserHttpTest.php` tambah:
```php
test('baris daftar memuat can_manage dan halaman berhasil memuat mode', function () {
    $super = User::factory()->create()->assignRole('super-admin');
    User::factory()->create()->assignRole('operator-sekolah');

    $this->actingAs($this->admin)->get('/pengguna')->assertInertia(fn (Assert $p) => $p
        ->where('users.data', fn ($rows) => collect($rows)->firstWhere('id', $super->id)['can_manage'] === false
            && collect($rows)->firstWhere('role', 'operator-sekolah')['can_manage'] === true));

    $target = User::factory()->pending()->create(['school_id' => $this->school->id])->assignRole('operator-sekolah');
    $this->post("/pengguna/{$target->id}/kirim-ulang-aktivasi");
    $this->get("/pengguna/{$target->id}/berhasil")->assertInertia(fn (Assert $p) => $p->where('mode', 'ulang'));

    $this->post('/pengguna', ['name' => 'Baru', 'email' => 'baru@x.test', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]);
    $baru = User::firstWhere('email', 'baru@x.test');
    $this->get("/pengguna/{$baru->id}/berhasil")->assertInertia(fn (Assert $p) => $p->where('mode', 'dibuat'));
});
```
Run: `php artisan test --filter="SharedPropsTest|UserHttpTest"` → Expected: FAIL.

- [ ] **Step 2: Implementasi**

`HandleInertiaRequests::share()`: ubah `'user' => $user?->only([...])` menjadi
```php
'user' => $user ? $user->only(['id', 'name', 'email']) + ['role' => $user->currentRole()?->name] : null,
```
`UserController`: ganti `toSuccess`, `done`, dan `row` agar membawa mode dan `can_manage`:
```php
public function resend(User $user): RedirectResponse
{
    Gate::authorize('manage-user', $user);

    return $this->toSuccess($user, $this->users->resendActivation($user), 'ulang');
}

private function toSuccess(User $user, string $token, string $mode = 'dibuat'): RedirectResponse
{
    return redirect()->route('pengguna.done', $user)
        ->with('activation_link', route('aktivasi.show', $token))
        ->with('activation_mode', $mode);
}
```
di `done()` tambahkan `'mode' => session('activation_mode', 'dibuat'),` pada props. Di `row()` tambahkan parameter dan kunci: ubah signatur menjadi `row(User $u, ?User $actor = null)` dan tambahkan `'can_manage' => $actor?->can('manage-user', $u) ?? false`; di `index()` panggil `$this->row($u, $request->user())`; di `show()` gunakan `$this->row($user, $request->user())` dan hapus kunci `can_manage` terpisah.

- [ ] **Step 3: Jalankan dan commit**

Run: `composer test && composer analyse && ./vendor/bin/pint` → Expected: hijau.
```bash
git add -A
git commit -m "feat: sertakan role, mode berhasil, dan can_manage untuk antarmuka" -m "auth.user.role dibagikan untuk header, halaman Berhasil membawa mode dibuat/ulang, dan baris daftar pengguna memuat can_manage agar menu aksi dapat disembunyikan."
```

### Task 2: Pustaka FE (tipe, format, WhatsApp, clipboard, matriks izin, useDataTable)

**Files:**
- Create: `resources/js/types/access.ts`, `resources/js/lib/whatsapp.ts`, `resources/js/lib/clipboard.ts`, `resources/js/lib/permissionMatrix.ts` dan `*.test.ts` masing-masing
- Modify: `resources/js/lib/format.ts` (+`humanize`), `resources/js/lib/format.test.ts`, `resources/js/types/index.d.ts`, `resources/js/composables/useDataTable.ts`, `useDataTable.test.ts`

**Interfaces:**
- Produces:
  - `humanize(text?: string | null): string`
  - `whatsappUrl(phone: string | null | undefined, link: string, name: string): string | null`
  - `copyText(text: string): Promise<boolean>`
  - `CatalogModule`, `CatalogAction`, `otherActions(m)`, `moduleState(selected, m)`, `toggleModule(selected, m, on)`, `setPermission(selected, value, on)`, `MATRIX_COLUMNS`
  - `useDataTable(url, initial)` mengirim `{ filter: {...} }`
  - tipe `MenuGroup`, `UserRow`, `RoleRow`, `Paginator<T>`, dll. di `types/access.ts`; `PageProps` memuat `menu` dan `auth.user.role`.

- [ ] **Step 1: Test lib (gagal dulu)**

Tambah ke `resources/js/lib/format.test.ts`:
```ts
describe('humanize', () => {
    it('mengubah slug menjadi teks berkapital awal', () => {
        expect(humanize('admin-kecamatan')).toBe('Admin kecamatan');
        expect(humanize('kirim-ulang-aktivasi')).toBe('Kirim ulang aktivasi');
        expect(humanize('user')).toBe('User');
    });

    it('aman untuk nilai kosong', () => {
        expect(humanize(null)).toBe('—');
        expect(humanize('')).toBe('—');
        expect(humanize(undefined)).toBe('—');
    });
});
```
(impor `humanize` dari `./format`.) `resources/js/lib/whatsapp.test.ts`:
```ts
import { describe, expect, it } from 'vitest';
import { whatsappUrl } from './whatsapp';

describe('whatsappUrl', () => {
    it('mengubah 08… menjadi 62… dan menyertakan pesan', () => {
        const url = whatsappUrl('081234567890', 'https://x.test/aktivasi/abc', 'Budi');
        expect(url).toStartWith?.('https://wa.me/6281234567890?text=');
        expect(decodeURIComponent(url!.split('text=')[1])).toContain('https://x.test/aktivasi/abc');
    });

    it('menerima format +62 dengan spasi atau strip', () => {
        expect(whatsappUrl('+62 812-3456-7890', 'l', 'B')).toContain('wa.me/6281234567890');
    });

    it('null untuk nomor kosong atau tidak valid', () => {
        expect(whatsappUrl(null, 'l', 'B')).toBeNull();
        expect(whatsappUrl('', 'l', 'B')).toBeNull();
        expect(whatsappUrl('abc', 'l', 'B')).toBeNull();
    });
});
```
(ganti `toStartWith?.` dengan `expect(url!.startsWith('https://wa.me/6281234567890?text=')).toBe(true);`.) `resources/js/lib/clipboard.test.ts`:
```ts
import { afterEach, describe, expect, it, vi } from 'vitest';
import { copyText } from './clipboard';

afterEach(() => vi.restoreAllMocks());

describe('copyText', () => {
    it('memakai navigator.clipboard bila tersedia', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        expect(await copyText('halo')).toBe(true);
        expect(writeText).toHaveBeenCalledWith('halo');
    });

    it('jatuh ke execCommand saat clipboard API tidak ada', async () => {
        vi.stubGlobal('navigator', {});
        document.execCommand = vi.fn().mockReturnValue(true);
        expect(await copyText('halo')).toBe(true);
        expect(document.execCommand).toHaveBeenCalledWith('copy');
    });

    it('false bila semuanya gagal', async () => {
        vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('x')) } });
        document.execCommand = vi.fn().mockReturnValue(false);
        expect(await copyText('halo')).toBe(false);
    });
});
```
`resources/js/lib/permissionMatrix.test.ts`:
```ts
import { describe, expect, it } from 'vitest';
import { moduleState, otherActions, setPermission, toggleModule, type CatalogModule } from './permissionMatrix';

const user: CatalogModule = {
    module: 'user',
    actions: [
        { value: 'user.lihat', action: 'lihat', editable: true },
        { value: 'user.buat', action: 'buat', editable: true },
        { value: 'user.kirim-ulang-aktivasi', action: 'kirim-ulang-aktivasi', editable: false },
    ],
};

describe('permissionMatrix', () => {
    it('aksi di luar kolom standar masuk kolom Lainnya', () => {
        expect(otherActions(user).map((a) => a.action)).toEqual(['kirim-ulang-aktivasi']);
    });

    it('toggleModule hanya mengubah izin yang dapat diedit dan mempertahankan sisanya', () => {
        const on = toggleModule(['user.kirim-ulang-aktivasi', 'siswa.lihat'], user, true);
        expect(on.sort()).toEqual(['siswa.lihat', 'user.buat', 'user.kirim-ulang-aktivasi', 'user.lihat']);

        const off = toggleModule(on, user, false);
        expect(off.sort()).toEqual(['siswa.lihat', 'user.kirim-ulang-aktivasi']);
    });

    it('moduleState dihitung dari izin yang dapat diedit saja', () => {
        expect(moduleState([], user)).toBe('none');
        expect(moduleState(['user.lihat'], user)).toBe('some');
        expect(moduleState(['user.lihat', 'user.buat'], user)).toBe('all');
    });

    it('setPermission menambah tanpa duplikat dan mencabut', () => {
        expect(setPermission(['a'], 'b', true)).toEqual(['a', 'b']);
        expect(setPermission(['a', 'b'], 'b', true)).toEqual(['a', 'b']);
        expect(setPermission(['a', 'b'], 'a', false)).toEqual(['b']);
    });
});
```
Ubah `resources/js/composables/useDataTable.test.ts` agar mengharapkan bentuk bersarang:
```ts
expect(router.get).toHaveBeenCalledWith(
    '/pengguna',
    { filter: { search: 'budi' } },
    { preserveState: true, preserveScroll: true, replace: true },
);
```
dan pada test reset: `expect(router.get).toHaveBeenCalledWith('/pengguna', {}, expect.any(Object));`.
Run: `npm test` → Expected: FAIL (modul tidak ada).

- [ ] **Step 2: Implementasi lib**

Tambahkan ke `resources/js/lib/format.ts`:
```ts
export function humanize(text?: string | null): string {
    if (!text) return '—';
    const spaced = text.replace(/[-_]+/g, ' ').trim();
    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}
```
`resources/js/lib/whatsapp.ts`:
```ts
/** Tautan wa.me berisi pesan siap kirim; null bila nomor tidak dapat dipakai. */
export function whatsappUrl(phone: string | null | undefined, link: string, name: string): string | null {
    if (!phone) return null;
    const digits = phone.replace(/\D/g, '');
    const international = digits.startsWith('62') ? digits : digits.startsWith('0') ? `62${digits.slice(1)}` : null;
    if (!international || !/^628\d{8,12}$/.test(international)) return null;

    const message = `Halo ${name}, berikut link aktivasi akun SIJEBOL Anda: ${link}`;

    return `https://wa.me/${international}?text=${encodeURIComponent(message)}`;
}
```
`resources/js/lib/clipboard.ts`:
```ts
/** Menyalin teks; fallback untuk konteks tanpa Clipboard API (mis. HTTP). */
export async function copyText(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        // lanjut ke fallback
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    const ok = document.execCommand?.('copy') ?? false;
    area.remove();

    return ok;
}
```
`resources/js/lib/permissionMatrix.ts`:
```ts
export interface CatalogAction {
    value: string;
    action: string;
    editable: boolean;
}

export interface CatalogModule {
    module: string;
    actions: CatalogAction[];
}

export const MATRIX_COLUMNS = ['lihat', 'buat', 'ubah', 'hapus'] as const;

export function otherActions(module: CatalogModule): CatalogAction[] {
    return module.actions.filter((a) => !(MATRIX_COLUMNS as readonly string[]).includes(a.action));
}

export function moduleState(selected: string[], module: CatalogModule): 'all' | 'some' | 'none' {
    const editable = module.actions.filter((a) => a.editable);
    const chosen = editable.filter((a) => selected.includes(a.value)).length;
    if (chosen === 0) return 'none';
    return chosen === editable.length ? 'all' : 'some';
}

export function toggleModule(selected: string[], module: CatalogModule, on: boolean): string[] {
    const editable = module.actions.filter((a) => a.editable).map((a) => a.value);
    const kept = selected.filter((value) => !editable.includes(value));
    return on ? [...kept, ...editable] : kept;
}

export function setPermission(selected: string[], value: string, on: boolean): string[] {
    const without = selected.filter((v) => v !== value);
    return on ? [...without, value] : without;
}
```
`resources/js/composables/useDataTable.ts` ubah `apply()`:
```ts
function apply() {
    const filter = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined),
    );
    router.get(url, Object.keys(filter).length ? { filter } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
```
`resources/js/types/access.ts`:
```ts
export interface MenuItem {
    label: string;
    href: string;
    icon: string;
}

export interface MenuGroup {
    group: string;
    items: MenuItem[];
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface UserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string | null;
    school: string | null;
    status: 'aktif' | 'menunggu' | 'kedaluwarsa' | 'nonaktif';
    last_login_at: string | null;
    can_manage: boolean;
}

export interface RoleRow {
    id: number;
    name: string;
    description: string | null;
    users_count: number;
    permissions_count: number;
    is_school_scoped: boolean;
    protected: boolean;
}

export interface Option {
    id: number;
    name: string;
}
```
Di `resources/js/types/index.d.ts` tambahkan ke `PageProps`: `menu: MenuGroup[];` (impor dari `./access`) dan ubah `auth.user` menjadi `(User & { role?: string | null }) | null`.

- [ ] **Step 3: Jalankan dan commit**

Run: `npm run format && npm test && npm run typecheck && npm run lint` → Expected: hijau.
```bash
git add -A
git commit -m "feat: tambah pustaka FE untuk halaman akses" -m "humanize, whatsappUrl, copyText, helper matriks izin, tipe kontrak BE, dan useDataTable memakai format filter[...] untuk query-builder. Semua dengan Vitest."
```

### Task 3: Shell dari Figma (sidebar, header, logo, menu server)

**Files:**
- Create: `resources/js/config/menuIcons.ts`, `menuIcons.test.ts`, `resources/js/assets/brand/*`, `resources/js/Components/shared/Breadcrumbs.vue`
- Modify: `resources/js/Layouts/partials/Sidebar.vue`, `Topbar.vue`, `AppLayout.vue`, `AuthLayout.vue`
- Delete: `resources/js/config/navigation.ts`, `navigation.test.ts`

**Interfaces:**
- Consumes: prop `menu` (`MenuGroup[]`), `auth.user.role`.
- Produces: `menuIcon(key: string): Component`; `<Breadcrumbs :items="[{label, href?}]" />`; `AppLayout` menerima prop `breadcrumbs?: {label: string; href?: string}[]`; logo lambang sebagai aset lokal.

- [ ] **Step 1: Test ikon menu (gagal dulu)**

`resources/js/config/menuIcons.test.ts`:
```ts
import { Circle, Home, Users } from '@lucide/vue';
import { describe, expect, it } from 'vitest';
import { menuIcon } from './menuIcons';

describe('menuIcon', () => {
    it('memetakan kunci ikon dari server', () => {
        expect(menuIcon('home')).toBe(Home);
        expect(menuIcon('users')).toBe(Users);
    });

    it('kunci tak dikenal jatuh ke ikon default', () => {
        expect(menuIcon('tidak-ada')).toBe(Circle);
    });
});
```
Run: `npm test -- menuIcons` → Expected: FAIL.

- [ ] **Step 2: Implementasi peta ikon**

`resources/js/config/menuIcons.ts`:
```ts
import { Circle, Home, School, ShieldCheck, Users } from '@lucide/vue';
import type { Component } from 'vue';

const icons: Record<string, Component> = {
    home: Home,
    users: Users,
    'shield-check': ShieldCheck,
    school: School,
};

export function menuIcon(key: string): Component {
    return icons[key] ?? Circle;
}
```
Hapus `config/navigation.ts` dan `config/navigation.test.ts`. Run: `npm test -- menuIcons` → PASS.

- [ ] **Step 3: Ambil desain shell dari Figma**

Panggil `get_design_context` untuk node `6:2207` (Navigation parts), `6:2353` (expanded), `6:2398` (collapsed), `6:2433` dan `6:2448` (mobile) dengan `fileKey=MtvFbsI68mf00D6De0dw9L`, `skillNames="figma-design-to-code"`, dan unduh aset lambang sesuai instruksi respons ke `resources/js/assets/brand/`. Catat: ukuran sidebar 264/72, tinggi header 64, teks "SIJEBOL / Kec. Sungai Beduk", tooltip sidebar ringkas, menu pengguna (Profil, Keluar merah), lonceng dengan badge, header mobile (hamburger, judul halaman, lonceng, avatar).

- [ ] **Step 4: Implementasi shell**

- `Sidebar.vue`: baca `usePage<PageProps>().props.menu`; render grup/item memakai `menuIcon(item.icon)`; header berisi lambang + "SIJEBOL" + "Kec. Sungai Beduk" (disembunyikan saat collapsed, tersisa lambang); pertahankan `aria-current`, `aria-label`, cincin fokus putih; tooltip untuk mode ringkas.
- `Topbar.vue`: slot breadcrumb diganti komponen `Breadcrumbs`; kanan: lonceng (statis, tanpa dropdown; notifikasi Fase 2) dan menu pengguna dengan nama + `humanize(user.role)`, item "Profil" (`route('profile.edit')`) dan "Keluar" (merah, POST logout). Mobile: hanya avatar + lonceng, judul halaman menggantikan breadcrumb.
- `Components/shared/Breadcrumbs.vue`: daftar item dengan pemisah ">" (mengikuti Figma), item terakhir tebal, item lain tautan bila ada `href`.
- `AppLayout.vue`: tambah prop `breadcrumbs`, teruskan ke `Topbar`.
- `AuthLayout.vue`: panel kiri sesuai Figma Login (lambang besar + wordmark + tiga baris nama sistem + kalimat penutup di bawah); form kanan tetap `max-w-[400px]`.

- [ ] **Step 5: Verifikasi**

Run: `npm run format && npm test && npm run typecheck && npm run lint && npm run build` → Expected: hijau. Jalankan `php artisan serve --port=8099`, buat user uji dengan role `admin-kecamatan` (`php artisan tinker`), login, dan bandingkan screenshot desktop (expanded dan collapsed via `element.click()`) dan mobile (drawer) dengan Figma `6:2353`, `6:2398`, `6:2433`, `6:2448`. Perbaiki selisih.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: terapkan shell dari Figma dengan menu dari server" -m "Sidebar membaca prop menu dari server dengan peta ikon, header memakai lambang dan breadcrumb, menu pengguna menampilkan role. Peta navigasi statis lama dihapus."
```

### Task 4: Login

**Files:** Modify `resources/js/Pages/Auth/Login.vue`; Create `resources/js/Components/shared/FormField.vue` (dipakai juga task berikut)

**Interfaces:**
- Produces: `<FormField label :required :hint :error :for>` (slot default = kontrol); `Login` memakai `PasswordInput`, checkbox "Ingat saya", tautan "Lupa password?".

- [ ] **Step 1:** `get_design_context` node `6:348` (desktop), `6:382` (mobile), dan board state `6:416`; screenshot adalah target.
- [ ] **Step 2:** Buat `FormField.vue` (label 14/500 dengan bintang merah bila `required`, teks "(opsional)" bila tidak wajib dan `optional` true, hint 12 muted, error 12 merah dengan ikon, `aria-describedby`/`aria-invalid` ke kontrol lewat slot props `{ id, describedBy, invalid }`).
- [ ] **Step 3:** Tulis ulang `Login.vue`: judul "Masuk", sub-judul "Gunakan akun yang diberikan admin kecamatan.", field Email dan Password (`PasswordInput`), "Ingat saya", "Lupa password?" (`route('password.request')`), tombol **Masuk** lebar penuh (spinner saat `processing`). Error kredensial/throttle tampil sebagai banner danger di atas form; pesan di bawah field email dipakai bila hanya satu pesan. State yang ada di board `6:416` (error, throttle, nonaktif) dipetakan dari `form.errors.email` + `status`.
- [ ] **Step 4:** Verifikasi seperti Task 3 (build + screenshot `/login` desktop 1440 dan mobile 390 vs Figma). Uji manual: login salah → banner; login benar → `/dashboard`.
- [ ] **Step 5:** Commit: `feat: terapkan halaman login dari Figma`.

### Task 5: Beranda

**Files:** Modify `resources/js/Pages/Dashboard.vue`; Create `resources/js/Components/shared/{StatCard,SectionCard}.vue` bila diperlukan desain.

**Interfaces:**
- Props opsional (belum disuplai BE): `summary?: { label: string; value: number; caption: string; icon: string }[]`, `actions?: { title: string; description: string; label: string; href: string }[]`, `schedules?: { date: string; school: string; location: string; participants: number }[]`, `notifications?: { title: string; description: string; at: string }[]`.

- [ ] **Step 1:** `get_design_context` node `6:4190` (desktop), `6:4379` (mobile), board `6:4532`.
- [ ] **Step 2:** Implementasi: header "Selamat datang, {nama depan}" + sub-judul `humanize(role)` + tanggal hari ini (`formatDate`); kartu ringkasan 3 kolom (1 kolom di mobile) hanya bila `summary` ada; "Perlu tindakan", "Jadwal terdekat", "Notifikasi terbaru" sebagai kartu bagian dengan **empty state** (komponen `EmptyState`) bila datanya kosong/ tak ada. Tanpa data contoh di kode.
- [ ] **Step 3:** Verifikasi: build; screenshot desktop/mobile `/dashboard` — struktur header dan empty state benar. Catat di ledger bahwa tampilan berisi data (Figma) baru dapat dilihat setelah BE menyuplai props (Fase 3+).
- [ ] **Step 4:** Commit: `feat: terapkan beranda dari Figma dengan empty state`.

### Task 6: Pengguna — daftar

**Files:** Modify `resources/js/Pages/Pengguna/Index.vue` (buat); Create `Components/shared/{SearchInput,SelectField,Pagination,RowActions}.vue`; Modify `Components/shared/StatusBadge.vue` bila Figma berbeda.

**Interfaces:**
- Consumes: props BE `users: Paginator<UserRow>`, `filters`, `roles`, `schools`, `statuses`; `useDataTable('/pengguna', {search, role, school_id, status})`.

- [ ] **Step 1:** `get_design_context` node `6:9962`, `6:10237`, board `6:10481` (loading, kosong, hasil filter kosong, menu aksi).
- [ ] **Step 2:** `SearchInput` (ikon kaca pembesar, `v-model`, debounce 300 ms memanggil `apply`), `SelectField` (pembungkus `Select` shadcn dengan opsi "Semua …"), `Pagination` ("Menampilkan 1–8 dari 8", tombol sebelumnya/berikutnya memakai `links` Inertia; `Link` dengan `preserve-state`), `RowActions` (menu tiga titik berbasis `DropdownMenu`; item sesuai prop).
- [ ] **Step 3:** `Pengguna/Index.vue`: `PageHeader` dengan tombol "+ Tambah pengguna" (hanya bila `can(PERMISSIONS.UserBuat)`); baris filter 4 kolom; `DataTable` kolom Pengguna (avatar inisial + nama tebal + email kecil), Role (`humanize`), Sekolah (— bila null), Status (`StatusBadge`), Login terakhir (`relativeTime`, — bila null), Aksi. Menu aksi per baris: Lihat detail; Ubah (bila `can_manage` dan izin `user.ubah`); Kirim ulang aktivasi (bila status `menunggu`/`kedaluwarsa`, `can_manage`, izin); Nonaktifkan/Aktifkan (bila `can_manage` dan izin `user.nonaktifkan`; nonaktifkan memakai `ConfirmDialog` destruktif yang menyebut nama pengguna; kirim `router.post`). Empty state "Belum ada pengguna" (tanpa filter) berbeda dari "Tidak ada hasil" (ada filter, dengan tombol reset). Mobile: tabel menjadi daftar kartu (nama, email, role/sekolah, status, aksi) sesuai Figma `6:10237`.
- [ ] **Step 4:** Verifikasi: build/typecheck; buat 8 pengguna uji lewat factory (`php artisan tinker`), screenshot desktop/mobile vs Figma; uji filter lewat `page.evaluate` (set nilai input + dispatch `input`) dan periksa URL `?filter[search]=…`; uji satu kasus flash error (nonaktifkan diri sendiri) memunculkan toast.
- [ ] **Step 5:** Commit: `feat: terapkan daftar pengguna dari Figma`.

### Task 7: Pengguna — tambah dan ubah

**Files:** Create `resources/js/Pages/Pengguna/Form.vue`

**Interfaces:**
- Consumes: `user: null|{id,name,email,phone,role_id,school_id,activated}`, `roles: {id,name,is_school_scoped}[]`, `schools: Option[]`. Submit: `POST /pengguna` atau `PUT /pengguna/{id}` via `useForm`.

- [ ] **Step 1:** `get_design_context` node `13:1942`, `13:2034`, board `13:2090`, `22:3861`, `22:3954`, board `22:4170`.
- [ ] **Step 2:** Form satu kartu "Data akun" (bintang wajib, hint "Gunakan nomor yang diawali 08."); field Sekolah hanya tampil dan wajib bila role terpilih `is_school_scoped` (di-reset ke null saat role berganti ke non-scoped); pada mode ubah, email dikunci bila `user.activated` dengan hint "Email tidak dapat diubah setelah akun aktif."; catatan info "Link aktivasi dibuat setelah akun disimpan." hanya pada mode tambah; tombol Batal (`route('pengguna.index')`) dan Simpan (spinner). Error validasi per field lewat `FormField`; breadcrumb "Pengguna > Tambah pengguna" / "Ubah pengguna".
- [ ] **Step 3:** Verifikasi: screenshot tambah/ubah desktop+mobile; uji kirim form valid (redirect ke `/berhasil`) dan tidak valid (error field) lewat `page.evaluate`.
- [ ] **Step 4:** Commit: `feat: terapkan form tambah dan ubah pengguna dari Figma`.

### Task 8: Pengguna — akun dibuat/kirim ulang dan detail

**Files:** Create `resources/js/Pages/Pengguna/Berhasil.vue`, `Pengguna/Show.vue`, `Components/shared/{CopyField,InfoList}.vue`

**Interfaces:**
- Berhasil: `user: {id,name,phone,role,school}`, `link: string`, `mode: 'dibuat'|'ulang'`. Show: `user: UserRow & {created_by: string|null, created_at: string, can_manage: boolean}`, `activities?: {at,action,summary}[]` (belum disuplai BE).

- [ ] **Step 1:** `get_design_context` node `16:1145`, `16:1233`, board `16:1284`, `22:4013`, `22:4109`, `19:2443`, `19:2587`, board `19:2699`.
- [ ] **Step 2 (Berhasil):** judul/sub-judul bergantung `mode` (`dibuat`: "Akun dibuat untuk {nama}" + "Bagikan link agar pengguna dapat mengaktifkan akun."; `ulang`: "Kirim ulang aktivasi" / "Link aktivasi baru dibuat" + "Link sebelumnya tidak berlaku lagi."); `StatusBadge status="menunggu"`; `CopyField` (kolom link monospace + tombol "Salin link" memakai `copyText`; setelah klik berubah "Tersalin" 2 detik; bila gagal tampilkan toast error "Tidak dapat menyalin. Salin manual."); catatan "Berlaku 3 hari…"; tombol "Kirim via WhatsApp" memakai `whatsappUrl` (nonaktif dengan teks "Nomor HP belum diisi." bila `null`); "Kembali ke daftar". Link tidak disimpan di storage.
- [ ] **Step 3 (Show):** kartu profil (avatar besar, nama, email, `StatusBadge`), tombol "Ubah" (bila `can_manage` dan izin `user.ubah`) dan menu "…" (kirim ulang aktivasi, nonaktifkan/aktifkan sesuai status dan izin); kartu "Informasi akun" memakai `InfoList` dua kolom (Role, Sekolah, Nomor HP "Belum diisi", Dibuat oleh, Tanggal dibuat `formatDate`, Login terakhir `relativeTime`); kartu "Aktivitas terbaru" **hanya bila `activities` ada** (tabel Waktu/Aksi/Ringkasan + "Lihat semua").
- [ ] **Step 4:** Verifikasi: alur nyata tambah → Berhasil (salin dan WhatsApp), kirim ulang, detail; screenshot vs Figma.
- [ ] **Step 5:** Commit: `feat: terapkan halaman berhasil dan detail pengguna dari Figma`.

### Task 9: Role & Permission

**Files:** Create `resources/js/Pages/Role/Index.vue`, `Role/Form.vue`, `Components/shared/PermissionMatrix.vue`

**Interfaces:**
- Index: `roles: RoleRow[]`. Form: `role: null|{id,name,description,is_school_scoped,permissions: string[],protected}`, `catalog: CatalogModule[]`. Submit `POST /role` / `PUT /role/{id}` dengan `{name, description, is_school_scoped, permissions}`; hapus: `DELETE /role/{id}`.

- [ ] **Step 1:** `get_design_context` node `24:2961`, `24:3083`, board `24:3175`, `26:6431`, `26:6768`, `26:7074`, `26:7437`, board `26:7769`.
- [ ] **Step 2 (Index):** tabel Nama role (gembok + "Role dilindungi" untuk `protected`), Deskripsi, Jumlah pengguna, Jumlah permission (rata kanan), Aksi "Ubah" biru dan "Hapus" merah (tidak ada untuk `protected`; Hapus memakai `ConfirmDialog`, dinonaktifkan dengan keterangan bila `users_count > 0`); "3 role" di bawah tabel; tombol "+ Tambah role" bila izin `role.buat`.
- [ ] **Step 3 (Form + matriks):** kartu "Data role" (Nama role wajib, Deskripsi opsional, dan toggle "Terikat sekolah" bila belum dipakai pengguna; pada role sudah dipakai tampil teks terkunci); `PermissionMatrix` menerima `catalog` dan `v-model` array izin, kolom Modul / Pilih semua / Lihat / Buat / Ubah / Hapus / Lainnya, sel tak tersedia "—", counter "N dari M permission dipilih" (M = total aksi katalog), `moduleState` untuk checkbox "Pilih semua" (indeterminate bila `some`), checkbox non-editable `disabled` dan tetap tercentang bila izin sudah ada di role; catatan kaki legenda; bar aksi bawah (Batal, Simpan). Seluruh izin yang tercentang, termasuk yang non-editable, dikirim apa adanya. Untuk role `protected` seluruh form hanya-baca.
- [ ] **Step 4:** Verifikasi: screenshot Index/Form desktop+mobile vs Figma; uji simpan role baru, ubah izin, hapus role terpakai (flash error), dan bahwa izin non-editable tetap terkirim (periksa payload lewat `page.evaluate` atau setelah simpan izin itu tetap ada di DB).
- [ ] **Step 5:** Commit: `feat: terapkan role dan matriks izin dari Figma`.

### Task 10: Verifikasi menyeluruh dan review impeccable

- [ ] **Step 1:** Jalankan semua pemeriksaan FE dan BE; semua hijau.
- [ ] **Step 2:** Walkthrough end-to-end di browser (login → beranda → pengguna (filter, menu) → tambah → berhasil → detail → ubah → role (tambah/ubah/hapus)) pada desktop 1440 dan mobile 390; bandingkan dengan Figma; catat dan perbaiki selisih visual.
- [ ] **Step 3:** Jalankan skill `impeccable` (critique + audit) atas seluruh perubahan FE; jalankan `ponytail:ponytail-review`; perbaiki temuan valid dalam satu batch; konfirmasi dengan satu putaran pemeriksaan.
- [ ] **Step 4:** Perbarui `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`: tandai Track FE Fase 1 untuk halaman yang selesai; daftar yang belum didesain (Aktivasi, Lupa/Reset password, Sekolah, Profil, error) dan Beranda berisi data sebagai pekerjaan lanjutan.
- [ ] **Step 5:** Commit perbaikan review.

---

## Self-Review

**Cakupan:** setiap node Figma di peta dipetakan ke task; halaman di luar Figma dicatat eksplisit sebagai di luar cakupan; kontrak BE (nama halaman, props, `filter[...]`) dirujuk. **Placeholder:** markup halaman sengaja diturunkan dari `get_design_context` saat eksekusi (tidak dapat dituliskan di muka), sedangkan seluruh logika murni (format, WhatsApp, salin, matriks, query) memuat kode dan test lengkap. **Konsistensi tipe:** `CatalogModule/CatalogAction` (Task 2) cocok dengan `PermissionCatalog::forActor` BE; `UserRow`/`RoleRow` cocok dengan `row()`/`index()` BE setelah Task 1; `mode` dan `can_manage` ditambahkan di Task 1 sebelum dipakai Task 6 dan 8.
