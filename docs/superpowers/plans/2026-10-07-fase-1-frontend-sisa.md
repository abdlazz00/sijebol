# FE Fase 1 — Halaman Tanpa Figma Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, tanpa subagent, pilihan user). Steps memakai checkbox (`- [ ]`).

**Goal:** Menyelesaikan halaman FE Fase 1 yang belum punya desain Figma: Aktivasi akun, Lupa/Reset password, Sekolah (daftar + form), Profil, dan halaman error 403/404/500, dengan tampilan identik dengan halaman Figma yang sudah ada.

**Architecture:** Semua halaman memakai komponen shared yang ada (`AuthLayout`, `AppLayout`, `FormField`, `PasswordInput`, `InlineAlert`, `DataTable`, `PageHeader`, `SearchInput`, `SelectField`, `RowActions`). Satu komponen baru `PasswordRules` (+ fungsi murni `passwordRules` di `lib/`) dipakai tiga halaman. Backend hanya berubah di dua titik: props `Profile/Edit` dan render halaman error Inertia.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3 + TS, Tailwind v4, shadcn-vue, Vitest, Pest.

**Spec:** `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` bagian 5.3, 5.4, 5.5, 5.13, 5.15, 5.16; desain singkat yang disetujui di chat (2026-10-07).

## Global Constraints

- Bahasa UI: Indonesia; salin teks persis dari spec 5.3–5.5, 5.13, 5.15, 5.16.
- URL Breeze tetap: `/forgot-password`, `/reset-password/{token}`, `/profile` (nama rute `password.request`, `password.reset`, `password.store`, `profile.edit`). Jangan ganti nama rute.
- Pola form: `FormField` + `Input` `h-10`, tombol `h-10`, `InlineAlert` untuk banner, `focusFirstInvalid` saat validasi gagal.
- Tanpa dependensi baru. Warna hanya lewat token (`text-success`, `text-muted-foreground`, dst).
- Kode ESLint/Prettier (semicolon, 4 spasi); jalankan `npm run format` sebelum cek.
- Commit: pesan detail, **tanpa** baris `Co-Authored-By` atau atribusi AI (aturan `CLAUDE.md`).
- Setelah semua task: review `impeccable` + `ponytail:ponytail-review`, perbaiki temuan valid, lalu verifikasi.

## Review Focus

- Token aktivasi/reset tidak valid atau kedaluwarsa: halaman tanpa form, tidak membocorkan data pengguna.
- Lupa password dengan email tak terdaftar: pesan sukses identik (tidak membocorkan keberadaan akun).
- Password tidak memenuhi aturan: tombol tetap bisa dikirim ke server dan error server tampil; aturan klien hanya panduan, server penentu.
- Pengguna operator sekolah membuka `/sekolah` tanpa izin: halaman 403 dalam shell, bukan layar putih.
- Profil: email tidak bisa diubah meski request dimanipulasi (sudah ditolak `ProfileUpdateRequest`); nomor HP kosong menyimpan `null`.
- 404/500 untuk tamu (belum login) memakai `AuthLayout`, bukan shell yang butuh `auth.user`.

## File Structure

| File | Tanggung jawab |
|---|---|
| `resources/js/lib/passwordRules.ts` (+ `.test.ts`) | Fungsi murni: daftar aturan terpenuhi + skor 0–3 |
| `resources/js/Components/shared/PasswordRules.vue` | Tampilan daftar aturan bercentang + indikator 3 segmen |
| `resources/js/Pages/Auth/Activate.vue` | Aktivasi akun + state token tidak berlaku |
| `resources/js/Pages/Auth/ForgotPassword.vue` | Tulis ulang di `AuthLayout` |
| `resources/js/Pages/Auth/ResetPassword.vue` | Tulis ulang di `AuthLayout` |
| `resources/js/Pages/Sekolah/Index.vue`, `Form.vue` | Daftar + form sekolah |
| `resources/js/Pages/Profile/Edit.vue` (+ hapus `Partials/*`) | Dua kartu: informasi profil, ubah password |
| `resources/js/Pages/Error.vue` | 403/404/500 |
| `app/Http/Controllers/ProfileController.php` | Kirim props `profile` ke `Profile/Edit` |
| `bootstrap/app.php` | Render Inertia untuk 403/404/500 |
| `tests/Feature/ProfileTest.php`, `tests/Feature/ErrorPagesTest.php` | Tes backend |

---

### Task 1: Aturan password + komponen `PasswordRules`

**Files:**
- Create: `resources/js/lib/passwordRules.ts`, `resources/js/lib/passwordRules.test.ts`, `resources/js/Components/shared/PasswordRules.vue`

**Interfaces:**
- Produces: `passwordRules(password: string): { label: string; met: boolean }[]` (urutan: minimal 8 karakter, mengandung huruf, mengandung angka) dan `passwordScore(password: string): 0 | 1 | 2 | 3` (jumlah aturan terpenuhi). `<PasswordRules :password="string" />`.

- [ ] **Step 1: Tulis tes yang gagal** (`passwordRules.test.ts`)

```ts
import { describe, expect, it } from 'vitest';
import { passwordRules, passwordScore } from './passwordRules';

describe('passwordRules', () => {
    it('kosong tidak memenuhi aturan apa pun', () => {
        expect(passwordRules('').every((r) => !r.met)).toBe(true);
        expect(passwordScore('')).toBe(0);
    });

    it('menilai tiap aturan terpisah', () => {
        expect(passwordRules('abcdefgh').map((r) => r.met)).toEqual([true, true, false]);
        expect(passwordRules('12345678').map((r) => r.met)).toEqual([true, false, true]);
        expect(passwordRules('a1').map((r) => r.met)).toEqual([false, true, true]);
    });

    it('skor penuh untuk password kuat', () => {
        expect(passwordScore('abcd1234')).toBe(3);
    });
});
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `npm test -- passwordRules` → FAIL (modul belum ada).

- [ ] **Step 3: Implementasi**

```ts
// resources/js/lib/passwordRules.ts
const RULES = [
    { label: 'Minimal 8 karakter', test: (p: string) => p.length >= 8 },
    { label: 'Mengandung huruf', test: (p: string) => /[A-Za-z]/.test(p) },
    { label: 'Mengandung angka', test: (p: string) => /\d/.test(p) },
];

/** Panduan tampilan; validasi sebenarnya ada di server. */
export function passwordRules(password: string) {
    return RULES.map((r) => ({ label: r.label, met: r.test(password) }));
}

export function passwordScore(password: string): 0 | 1 | 2 | 3 {
    return passwordRules(password).filter((r) => r.met).length as 0 | 1 | 2 | 3;
}
```

```vue
<!-- resources/js/Components/shared/PasswordRules.vue -->
<script setup lang="ts">
import { passwordRules, passwordScore } from '@/lib/passwordRules';
import { Check, Circle } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ password: string }>();
const rules = computed(() => passwordRules(props.password));
const score = computed(() => passwordScore(props.password));
const bar = ['bg-danger', 'bg-warning', 'bg-success'];
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex gap-1" aria-hidden="true">
            <span
                v-for="n in 3"
                :key="n"
                class="h-1 flex-1 rounded-full"
                :class="n <= score ? bar[score - 1] : 'bg-neutral-soft'"
            />
        </div>
        <ul class="flex flex-col gap-1 text-xs leading-4">
            <li
                v-for="rule in rules"
                :key="rule.label"
                class="flex items-center gap-2"
                :class="rule.met ? 'text-success' : 'text-muted-foreground'"
            >
                <Check v-if="rule.met" class="size-3.5" aria-hidden="true" />
                <Circle v-else class="size-3.5" aria-hidden="true" />
                {{ rule.label }}
                <span class="sr-only">{{ rule.met ? '(terpenuhi)' : '(belum terpenuhi)' }}</span>
            </li>
        </ul>
    </div>
</template>
```

- [ ] **Step 4: Jalankan tes** — `npm test -- passwordRules` → PASS.
- [ ] **Step 5: Commit** — `git add resources/js/lib/passwordRules* resources/js/Components/shared/PasswordRules.vue && git commit -m "feat: tambah aturan dan indikator kekuatan password"` (isi pesan menjelaskan fungsi murni dan bahwa server tetap penentu; tanpa Co-Authored-By).

---

### Task 2: Aktivasi, Lupa password, Reset password

**Files:**
- Create: `resources/js/Pages/Auth/Activate.vue`
- Modify (tulis ulang): `resources/js/Pages/Auth/ForgotPassword.vue`, `resources/js/Pages/Auth/ResetPassword.vue`

**Interfaces:**
- Consumes: `PasswordRules` (Task 1), `AuthLayout` (slot default, prop `title`), pola `Login.vue` (header `h1` `text-2xl` + sub-judul `text-sm text-muted-foreground`, `form.flex.flex-col.gap-8`, `InlineAlert`, tombol `h-10 w-full` dengan `LoaderCircle`).
- Props `Activate`: `{ valid: false } | { valid: true; name: string; email: string; school: string | null }`. Form: `useForm({ password: '', password_confirmation: '' })`, `post(route('aktivasi.store', token))` — token diambil dari `usePage().url` segmen terakhir (backend tidak mengirim token sebagai prop); gunakan `window.location.pathname.split('/').pop()`.
- Props `ResetPassword`: `{ email: string; token: string }` (tetap). `ForgotPassword`: `{ status?: string }` (tetap).

- [ ] **Step 1: Activate.vue.** Dua cabang `v-if="valid"`:
  - Valid: `AuthLayout title="Aktifkan akun"`; `h1` "Aktifkan akun Anda"; sub "Halo, {name}. Buat password untuk mengaktifkan akun."; kartu info kecil `bg-secondary rounded-lg p-3 text-sm` berisi email (ikon `Lock` seperti `Pengguna/Form`) dan sekolah bila ada; `FormField` "Password baru" + `PasswordInput` (`autocomplete="new-password"`), `PasswordRules :password="form.password"`; `FormField` "Konfirmasi password" + `PasswordInput`; tombol "Aktifkan akun". Klien: bila `password !== password_confirmation` set error "Konfirmasi password tidak sama." lalu `focusFirstInvalid()`; selain itu kirim ke server dan tampilkan `form.errors.password`. `onError: focusFirstInvalid`, `onFinish: form.reset('password','password_confirmation')`.
  - Tidak valid: ikon `Clock` (size-10 `text-muted-foreground`), `h1` "Link aktivasi tidak berlaku", paragraf "Link ini sudah kedaluwarsa atau sudah dipakai.", paragraf "Hubungi admin kecamatan untuk meminta link baru." Tanpa form; tautan `Link` "Ke halaman masuk" ke `route('login')`.
- [ ] **Step 2: ForgotPassword.vue.** `AuthLayout title="Lupa password"`: `h1` "Lupa password?", sub "Masukkan email akun Anda. Kami akan mengirim link untuk mengatur ulang password."; `FormField` Email; tombol "Kirim link reset" (`form.post(route('password.email'))`); tautan "Kembali ke login". Bila `status` ada (setelah kirim): ganti isi dengan ikon `Mail`, `h1` "Periksa email Anda", "Jika email terdaftar, link reset password sudah dikirim." (teks tetap, tidak memakai `status` dari server agar sama untuk email tak terdaftar), dan tombol/ tautan "Kembali ke login".
- [ ] **Step 3: ResetPassword.vue.** Seperti Activate-valid: email terkunci (kartu info), password baru + `PasswordRules` + konfirmasi, tombol "Simpan password", `form.post(route('password.store'))` dengan field `token`, `email`, `password`, `password_confirmation`. Error `form.errors.email` (token tidak valid dari broker) tampil sebagai `InlineAlert` + tautan "Minta link baru" ke `route('password.request')`.
- [ ] **Step 4: Cek** — `npm run format && npm run typecheck && npm run lint && npm run build` → bersih.
- [ ] **Step 5: Verifikasi browser (Playwright).** Buat token via `php artisan tinker` (`app(ActivationService::class)` atau `sijebol:create-super-admin x@y.test` yang mencetak link) lalu: buka link valid (desktop 1440 + mobile 390; indikator kekuatan berubah saat mengetik), buka token ngawur (state "tidak berlaku"), kirim lupa-password dengan email acak (pesan sama), buka `/reset-password/ngawur?email=a@b.c` (error + tautan minta link baru). Tidak ada overflow horizontal.
- [ ] **Step 6: Commit** — "feat: terapkan aktivasi, lupa dan reset password di layout auth".

---

### Task 3: Sekolah (daftar + form)

**Files:**
- Create: `resources/js/Pages/Sekolah/Index.vue`, `resources/js/Pages/Sekolah/Form.vue`
- Modify: `resources/js/types/access.ts` (tambah `SchoolRow`)

**Interfaces:**
- Backend sudah ada: `Sekolah/Index` props `{ schools: Paginator<SchoolRow>, filters: Record<string,string|undefined> }`, filter `filter[search]` (nama/NPSN) dan `filter[level]`; `Sekolah/Form` props `{ school: { id, name, npsn, level, address } | null }`. Route: `sekolah.index|create|store|edit|update`. Izin: `sekolah.lihat|buat|ubah` (cek `lib/permissions.ts` untuk nama konstanta `SekolahLihat/Buat/Ubah`; tambahkan bila belum ada).
- Produces: `type SchoolRow = { id: number; name: string; npsn: string; level: 'SMA'|'SMK'|'MA'; address: string | null; users_count: number }`.

- [ ] **Step 1: Tipe + izin.** Tambah `SchoolRow` ke `types/access.ts`; pastikan konstanta izin sekolah ada di `lib/permissions.ts`.
- [ ] **Step 2: Index.vue** — salin struktur `Pengguna/Index.vue`: `PageHeader title="Sekolah" description="Kelola data sekolah di Kecamatan Sungai Beduk."` dengan tombol "Tambah sekolah" (`v-if can(SekolahBuat)`); filter row (`SearchInput` "Cari nama atau NPSN" + `SelectField all-label="Semua jenjang"` opsi SMA/SMK/MA) memakai `useDataTable('/sekolah', { search, level })` (watch `level` langsung `apply`, `search` debounce 300 ms, persis Index pengguna); `DataTable` kolom Nama sekolah (alamat kecil di bawahnya `text-xs text-muted-foreground`), NPSN, Jenjang, Jumlah operator (rata kanan), Aksi (tautan "Ubah" bila `can(SekolahUbah)`); `Pagination`; `EmptyState` ("Belum ada sekolah" + tombol tambah) dan state hasil filter kosong (dengan "Reset filter" → `reset()`), loading skeleton dari `DataTable`. Petunjuk "↔ Geser tabel" di mobile seperti Pengguna.
- [ ] **Step 3: Form.vue** — salin `Pengguna/Form.vue`: judul "Tambah sekolah"/"Ubah sekolah", kartu "Data sekolah" lebar maks 720: Nama sekolah (wajib), NPSN (wajib, `inputmode="numeric"`, `maxlength=8`, hint "8 digit angka."), Jenjang (`SelectField`, SMA/SMK/MA, wajib), Alamat (opsional, `Textarea`). Validasi klien: nama kosong "Nama sekolah wajib diisi.", NPSN bukan 8 digit "NPSN harus 8 digit angka.", jenjang kosong "Pilih jenjang."; `focusFirstInvalid`; server error (NPSN sudah dipakai) tampil lewat `form.errors`. Submit `post(route('sekolah.store'))` / `put(route('sekolah.update', id))`; Batal ke `sekolah.index`. Breadcrumb: Sekolah > Tambah/Ubah sekolah.
- [ ] **Step 4: Cek** format/typecheck/lint/build.
- [ ] **Step 5: Verifikasi browser.** Daftar tampil (data demo `seed-demo.php` di scratchpad bila perlu), filter cari → `?filter[search]=`, filter jenjang, buat sekolah baru (NPSN unik) → redirect + toast, ubah sekolah, NPSN duplikat → error di kolom, mobile 390 tanpa overflow.
- [ ] **Step 6: Commit** — "feat: terapkan daftar dan form sekolah".

---

### Task 4: Profil

**Files:**
- Modify: `app/Http/Controllers/ProfileController.php`, `resources/js/Pages/Profile/Edit.vue`
- Delete: `resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue`, `.../UpdateProfileInformationForm.vue`
- Test: `tests/Feature/ProfileTest.php` (tambah/ubah)

**Interfaces:**
- Consumes: `PasswordRules`, rute `profile.update` (PATCH, field `name`, `phone`), `password.update` (PUT, field `current_password`, `password`, `password_confirmation`).
- Produces: props `Profile/Edit`: `profile: { name: string; email: string; phone: string | null; role: string | null }`.

- [ ] **Step 1: Tes backend yang gagal** (`tests/Feature/ProfileTest.php`, Pest; gunakan helper `makeUserWith` dari `tests/Pest.php` — baca signature-nya dulu):

```php
it('mengirim data profil ke halaman profil', function () {
    $user = makeUserWith('admin-kecamatan', ['phone' => '081234567890']);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->component('Profile/Edit')
            ->where('profile.email', $user->email)
            ->where('profile.phone', '081234567890')
            ->where('profile.role', 'admin-kecamatan'));
});

it('tidak mengubah email lewat profil', function () {
    $user = makeUserWith('admin-kecamatan');

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Nama Baru', 'phone' => '', 'email' => 'lain@example.com',
    ])->assertRedirect();

    expect($user->fresh()->email)->toBe($user->email)
        ->and($user->fresh()->phone)->toBeNull();
});
```

- [ ] **Step 2: Jalankan** `php artisan test --filter=ProfileTest` → tes pertama FAIL (props belum ada). Jika `makeUserWith` tidak menerima atribut, sesuaikan pembuatan user di tes dengan factory + `assignRole`.
- [ ] **Step 3: Implementasi controller**

```php
public function edit(Request $request): Response
{
    $user = $request->user();

    return Inertia::render('Profile/Edit', ['profile' => [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'role' => $user->currentRole()?->name,
    ]]);
}
```
(tambah `use Illuminate\Http\Request;`). Pastikan `ProfileUpdateRequest` mengubah string kosong menjadi `null` — Laravel `ConvertEmptyStringsToNull` sudah melakukannya.
- [ ] **Step 4: Jalankan tes** → PASS; `vendor/bin/pint` dan `vendor/bin/phpstan analyse --memory-limit=1G` bersih.
- [ ] **Step 5: Edit.vue.** `AppLayout title="Profil"` + `PageHeader title="Profil" description="Kelola informasi akun dan password Anda."`; dua `section.bg-card.rounded-lg.border.p-6` lebar maks 720:
  - **Informasi profil:** Nama (wajib), Nomor HP (opsional, hint "Gunakan nomor yang diawali 08."), Email (disabled + ikon `Lock`, hint "Email tidak dapat diubah."), Role (disabled, `humanize`); tombol "Simpan" (`form.patch(route('profile.update'))`); validasi klien sama dengan `Pengguna/Form` (nama wajib, HP `^08\d{8,12}$`).
  - **Ubah password:** Password saat ini, Password baru + `PasswordRules`, Konfirmasi; tombol "Perbarui password" (`put(route('password.update'))`, `onSuccess`/`onFinish` reset field, `onError: focusFirstInvalid`). Konfirmasi tidak sama → error klien.
- [ ] **Step 6: Hapus Partials** dan pastikan tidak ada impor tersisa (`grep -rn "Partials/Update" resources/js`).
- [ ] **Step 7: Cek + browser.** format/typecheck/lint/build; ubah nama/HP (toast "Profil diperbarui."), HP salah → error, ganti password dengan password saat ini salah → error di kolom; mobile 390.
- [ ] **Step 8: Commit** — "feat: terapkan halaman profil dengan dua kartu".

---

### Task 5: Halaman error 403/404/500

**Files:**
- Create: `resources/js/Pages/Error.vue`, `tests/Feature/ErrorPagesTest.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Produces: Inertia page `Error` dengan props `{ status: 403 | 404 | 500 | 503 }`. Render hanya untuk permintaan Inertia/HTML yang bukan JSON (`shouldRenderJsonWhen` yang ada tetap berlaku).

- [ ] **Step 1: Tes gagal** (`ErrorPagesTest.php`):

```php
it('menampilkan halaman 404 lewat Inertia', function () {
    $this->actingAs(makeUserWith('admin-kecamatan'))
        ->get('/tidak-ada')
        ->assertStatus(404)
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

it('menampilkan halaman 403 bagi pengguna tanpa izin', function () {
    $this->actingAs(makeUserWith('operator-sekolah'))
        ->get(route('role.index'))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
});

it('tamu mendapat halaman 404 tanpa memuat shell', function () {
    $this->get('/tidak-ada')->assertStatus(404)
        ->assertInertia(fn ($page) => $page->component('Error'));
});
```
- [ ] **Step 2: Jalankan** → FAIL (masih halaman Blade bawaan).
- [ ] **Step 3: Implementasi** di `bootstrap/app.php`, di dalam `withExceptions`, setelah baris `AccessRuleException`:

```php
$exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
    $status = $response->getStatusCode();

    if (! in_array($status, [403, 404, 500, 503], true) || $request->expectsJson() || $request->is('api/*')) {
        return $response;
    }

    // 500 hanya diganti di produksi agar halaman debug tetap muncul saat pengembangan.
    if ($status === 500 && config('app.debug')) {
        return $response;
    }

    return \Inertia\Inertia::render('Error', ['status' => $status])
        ->toResponse($request)
        ->setStatusCode($status);
});
```
Catatan: `HandleInertiaRequests` perlu berjalan pada respons error; jika `auth.user` tidak ada untuk tamu, `Error.vue` memakai `AuthLayout`. Bila 404 route-tidak-ada tidak melewati middleware web, tambahkan `->withMiddleware(... )` fallback `Route::fallback` di `routes/web.php` yang `abort(404)` agar grup web (session, Inertia) aktif.
- [ ] **Step 4: Jalankan tes** → PASS (atur fallback bila perlu).
- [ ] **Step 5: Error.vue.** Props `{ status: number }`; salinan teks dari spec 5.16: 403 "Anda tidak memiliki akses ke halaman ini", 404 "Halaman tidak ditemukan", 500/503 "Terjadi kesalahan" + satu kalimat penjelas ("Hubungi admin kecamatan bila masalah berlanjut." untuk 403; "Periksa kembali alamat atau kembali ke beranda." untuk 404; "Silakan coba lagi beberapa saat lagi." untuk 500/503). Ikon besar (`ShieldAlert`, `SearchX`, `TriangleAlert`, `size-16 text-muted-foreground`), kode status kecil, judul `text-2xl font-semibold`, tombol "Kembali ke beranda" (`route('dashboard')`). Layout: `usePage().props.auth?.user` ada → `AppLayout`; tidak → `AuthLayout`. `Head title`.
- [ ] **Step 6: Cek + browser.** format/typecheck/lint/build; kunjungi `/tidak-ada` (login dan logout), `/role` sebagai operator sekolah (403); mobile 390.
- [ ] **Step 7: Commit** — "feat: tambah halaman error 403, 404, dan 500".

---

### Task 6: Verifikasi akhir, review, dan penutupan

- [ ] **Step 1:** `npm test && npm run typecheck && npm run lint && npm run build`; `vendor/bin/pint --test`; `php artisan test --compact`; `vendor/bin/phpstan analyse --memory-limit=1G` → semua hijau.
- [ ] **Step 2:** Jalan end-to-end di browser (desktop 1440 + mobile 390): buat pengguna → salin link → aktivasi → masuk → ubah profil → logout → lupa password. 
- [ ] **Step 3:** Jalankan skill `impeccable` (critique + audit, hanya laporan) dan `ponytail:ponytail-review` pada perubahan; perbaiki temuan valid dalam satu batch; konfirmasi sekali.
- [ ] **Step 4:** Perbarui `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (Track FE Fase 1: tandai halaman selesai; sisa tertunda hanya kartu aktivitas, state "gagal memuat", banner akun nonaktif, Notifikasi).
- [ ] **Step 5:** Commit akhir (tanpa Co-Authored-By) dan ringkasan ke user.
