# Profil Irisan A (Halaman dan Keamanan Dasar) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman `/profile` memuat kartu identitas per role (baca saja), login terakhir, dan aksi "Keluar dari perangkat lain" dengan konfirmasi password.

**Architecture:** `ProfileController::edit` menambah prop `identity` lewat metode privat `identity(User)` (data mentah, NIK sudah dimasker); `Edit.vue` memecah baris tampil di klien lewat komponen kartu baru. Aksi keluar perangkat lain = satu controller baru: hapus baris `sessions` milik user selain sesi saat ini + putar remember token. Form kontak dan ubah password tidak berubah.

**Tech Stack:** Laravel, Inertia, Vue 3 + TypeScript, shadcn-vue (`Dialog`, `Avatar`, `Button`), Pest, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-28-halaman-profil-design.md` (irisan A di §6). Irisan B (saklar email) dan C (ganti email) bukan bagian rencana ini: jangan menambah kartu Notifikasi atau tombol "Ubah email".

## Global Constraints

- Bahasa antarmuka Indonesia; tanggal lewat `lib/date.ts` (`formatDate`, `formatDateTime`), inisial lewat `initials()`, label role lewat `humanize()` dari `lib/format.ts`.
- NIK tidak pernah dikirim utuh ke klien: pakai `App\Support\Nik::mask()`.
- Dua kolom hanya di ≥ 1280 px (`xl:`); di bawahnya satu kolom. Kolom kanan maks 720 px, kolom kartu identitas 320 px, kartu identitas `xl:sticky xl:top-24`.
- Tanpa dependensi baru. Komponen baru ditaruh di `resources/js/Components/profile/`, tipe di `resources/js/types/profile.ts`.
- Rute baru bernama bahasa Inggris RESTful, di grup `auth` tanpa `can:`, ber-`throttle`. Daftarkan namanya di `auditOwnAccountNames()` pada `tests/Feature/Security/RouteGuardAuditTest.php`.
- Pesan sukses lewat `->with('success', ...)` (otomatis menjadi toast di `AppLayout`).
- Prop halaman tidak boleh bernama `auth`, `flash`, `menu`, `notifications`.
- Tes backend butuh MySQL hidup (`sijebol_test`) dan tidak boleh dijalankan dua proses sekaligus; `SESSION_DRIVER=array` di tes.
- Relasi siswa dibaca lewat `$user->student()->first()` (relasi ter-cache antar-request di tes).
- Tanpa commit kecuali diminta user; bila diminta, pesan commit detail tanpa baris `Co-Authored-By` atau atribusi AI (CLAUDE.md).

## Review Focus

1. Siswa yang belum menyimpan data diri (tak ada baris `Student`): halaman tidak error, kartu menampilkan baris sekolah, tautan "lengkapi data saya" tetap ada. (Task 1, Task 3)
2. `last_login_at` null (akun baru atau hasil seeder): tampil "—", bukan "Invalid Date". (Task 3)
3. Role tanpa deskripsi: baris "Akses" hilang, bukan kosong. (Task 3)
4. "Keluar dari perangkat lain" tidak boleh menghapus sesi saat ini maupun sesi pengguna lain. (Task 2)
5. Perangkat dengan cookie "Ingat saya" tidak boleh masuk lagi otomatis sesudahnya: remember token diputar. (Task 2)
6. Dialog yang ditutup lalu dibuka lagi tidak menyisakan password atau galat sebelumnya. (Task 4)

## File Structure

- Create `app/Http/Controllers/ProfileSessionController.php`: `destroy` untuk keluar dari perangkat lain.
- Modify `app/Http/Controllers/ProfileController.php`, `routes/web.php`, `tests/Feature/ProfileTest.php`, `tests/Feature/Security/RouteGuardAuditTest.php`; create `tests/Feature/ProfileSessionTest.php`.
- Create `resources/js/types/profile.ts`, `resources/js/Components/profile/ProfileIdentityCard.vue` (+ `.test.ts`), `resources/js/Components/profile/LogoutOtherDevicesDialog.vue` (+ `.test.ts`).
- Modify `resources/js/Pages/Profile/Edit.vue`; create `resources/js/Pages/Profile/Edit.test.ts`.
- Modify `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (§5.15). Delete `resources/js/Layouts/AuthenticatedLayout.vue` bila tak ada pemakai.

---

### Task 1: Prop `identity` di halaman Profil

**Files:**
- Modify: `app/Http/Controllers/ProfileController.php` (`edit` + metode privat `identity`)
- Test: `tests/Feature/ProfileTest.php`

**Interfaces:**
- Produces: prop Inertia `identity` dengan bentuk
  `{ school: ?string, student: ?{nik: string(masked), nisn: string, birth_date: 'Y-m-d', age: int, gender: 'L'|'P'}, access: ?string, created_at: string(ISO 8601), last_login_at: ?string(ISO 8601), can_open_student_data: bool }`.
- `ProfileController::identity(User $user): array` (privat; bentuk di atas).

- [ ] **Step 1: Tambah tes di `tests/Feature/ProfileTest.php`** (pakai `siswaLengkap(School)` dan `makeUserWith` dari `tests/Pest.php`):
  - `identitas siswa memuat data siswa dengan NIK dimasker dan umur`: login sebagai akun dari `siswaLengkap($school)` (beri role `siswa` izin `profil.lihat`); assert `identity.student.nik` = `Nik::mask($siswa->nik)`, `identity.student.age` = `$siswa->birth_date->age`, `identity.school` = nama sekolah, `identity.can_open_student_data` = true.
  - `identitas siswa tanpa baris Student tetap valid`: akun role `siswa` + sekolah tanpa `Student`; assert `identity.student` null, `identity.school` terisi, `identity.can_open_student_data` true.
  - `identitas operator memuat sekolah dan deskripsi role`: `makeUserWith([], $school, true, 'operator-sekolah')` lalu set `description` role; assert `identity.student` null, `identity.access` = deskripsi, `identity.can_open_student_data` false.
  - `identitas admin tanpa sekolah`: `makeUserWith([], null, false, 'admin-kecamatan')`; assert `identity.school` null, `identity.access` null, `identity.last_login_at` null, `identity.created_at` terisi.
- [ ] **Step 2:** Run `php artisan test --filter=ProfileTest`. Expected: empat tes baru FAIL (prop `identity` belum ada).
- [ ] **Step 3: Implement `ProfileController::identity(User $user): array`** (privat). `school` = `$user->school?->name`; `student` dari `$user->student()->first()` (NIK lewat `Nik::mask`, `age` dari `birth_date->age`, `birth_date->toDateString()`); `access` = `$user->currentRole()?->description`; tanggal `toIso8601String()` (null aman); `can_open_student_data` = `$user->isStudent() && $user->can('profil.lihat')`.
- [ ] **Step 4: `ProfileController::edit`** menambah `'identity' => $this->identity($user)` ke props; prop `profile` tidak berubah.
- [ ] **Step 5:** Run `php artisan test --filter=ProfileTest`. Expected: semua PASS (termasuk tes lama).

### Task 2: Keluar dari perangkat lain

**Files:**
- Create: `app/Http/Controllers/ProfileSessionController.php`
- Modify: `routes/web.php` (grup `auth`, setelah rute `profile.update`), `tests/Feature/Security/RouteGuardAuditTest.php` (`auditOwnAccountNames()`)
- Test: `tests/Feature/ProfileSessionTest.php`

**Interfaces:**
- Produces: rute `DELETE /profile/sessions` bernama `profile.sessions.destroy`, `throttle:6,1`; body `current_password`. Sukses: redirect back + flash `success` = "Perangkat lain sudah dikeluarkan.". Gagal: galat sesi `current_password`.
- Consumes: tabel `sessions` (`SESSION_DRIVER=database`).

- [ ] **Step 1: Tulis `tests/Feature/ProfileSessionTest.php`.** Helper kecil menyisipkan baris `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload` kosong, `last_activity`). Tes:
  - `sesi lain milik pengguna dihapus, sesi saat ini dan sesi pengguna lain tetap`: `$this->startSession()`; sisipkan dua baris lain milik user, satu baris dengan id `session()->getId()` milik user, satu baris milik user lain; `actingAs($user)->delete('/profile/sessions', ['current_password' => 'password'])` → `assertSessionHasNoErrors()`; sisa baris = sesi saat ini + milik user lain. Jika id sesi tes tidak stabil, bandingkan dengan `$this->app['session.store']->getId()` setelah panggilan.
  - `remember token diputar`: set `remember_token` 'lama' lalu panggil; `fresh()->remember_token` ≠ 'lama'.
  - `password salah ditolak dan tidak ada yang dihapus`: kirim `current_password` salah → `assertSessionHasErrors('current_password')`; baris sesi lain masih ada.
  Akses tamu sudah dijaga `RouteGuardAuditTest`; tak perlu tes sendiri. Password bawaan factory: periksa `UserFactory` (kemungkinan `password`).
- [ ] **Step 2:** Run `php artisan test --filter=ProfileSessionTest`. Expected: FAIL (rute belum ada).
- [ ] **Step 3: Implement `ProfileSessionController::destroy(Request $request): RedirectResponse`.** Validasi `['current_password' => ['required', 'current_password']]` (aturan yang sama dengan `Auth\PasswordController`); panggil `Auth::logoutOtherDevices($request->input('current_password'))` (memutar remember token dan memasang ulang cookie perangkat ini); lalu `DB::table(config('session.table'))` hapus baris `user_id` = user ini dan `id` ≠ `$request->session()->getId()`. Tambah komentar `// ponytail: hanya driver sesi database; driver lain butuh mekanisme berbeda`.
- [ ] **Step 4: Daftarkan rute** di `routes/web.php` dengan `->middleware('throttle:6,1')->name('profile.sessions.destroy')` dan tambahkan `'profile.sessions.destroy'` ke `auditOwnAccountNames()`.
- [ ] **Step 5:** Run `php artisan test --filter="ProfileSessionTest|RouteGuardAuditTest"`. Expected: PASS.

### Task 3: Kartu identitas

**Files:**
- Create: `resources/js/types/profile.ts`, `resources/js/Components/profile/ProfileIdentityCard.vue`
- Test: `resources/js/Components/profile/ProfileIdentityCard.test.ts`

**Interfaces:**
- Produces: `export type ProfileIdentity` (bentuk sama dengan prop `identity` di Task 1; `gender: 'L' | 'P'`).
- Produces: `ProfileIdentityCard.vue` dengan props `{ name: string; role: string | null; identity: ProfileIdentity }`.

- [ ] **Step 1: Tulis `ProfileIdentityCard.test.ts`** (Vitest; stub global `route` seperti `Saya.test.ts`, mock `Link` Inertia sebagai `<a :href>`). Tes:
  - siswa: tampil "3201••••" (nilai NIK masker apa adanya), NISN, "Jenis kelamin" "Perempuan" untuk `P`, tanggal lahir berformat `formatDate(...)` diikuti " · 17 tahun"; tautan "Lihat dan lengkapi data saya" ber-href `route('me.show')` dan catatan "Data siswa tidak diubah di sini" hanya bila `can_open_student_data` true.
  - siswa tanpa `student`: baris "Sekolah" tampil, tautan tetap tampil.
  - operator: baris "Sekolah" dan "Akses" (nilai deskripsi), "Akun dibuat"; baris "Akses" tidak ada saat `access` null.
  - admin (`school` null, `student` null): baris "Wilayah" berisi "Seluruh sekolah di Kec. Sungai Beduk".
  - "Login terakhir" = `formatDateTime(iso)`; bernilai "—" saat `last_login_at` null.
  - nama dan inisial: `initials(name)` di avatar, `humanize(role)` di lencana.
  - tombol "Data diri" (`aria-expanded="false"` awal) berubah `true` setelah diklik dan `aria-controls` menunjuk id blok rincian.
  - tautan "Lihat dan lengkapi data saya" **bukan** keturunan blok rincian yang dilipat (selalu terjangkau di HP, penting bagi siswa yang datanya belum lengkap); catatan "Data siswa tidak diubah di sini" boleh di dalam blok.
  - nama pengguna berupa `<h2>` dan `<section>` ber-`aria-labelledby` menunjuk ke nama itu (kartu punya judul bagi pembaca layar seperti kartu di kolom kanan).
- [ ] **Step 2:** Run `npx vitest run resources/js/Components/profile/ProfileIdentityCard.test.ts`. Expected: FAIL (komponen belum ada).
- [ ] **Step 3: Implement `types/profile.ts` dan `ProfileIdentityCard.vue`.** Struktur: `<section>` kartu (`bg-card rounded-lg border`, `xl:sticky xl:top-24`); kepala = `Avatar` (`size-12 xl:size-16`, isi `initials(name)`), nama (`text-lg font-semibold`), lencana role (kelas sama dengan varian `info` di `StatusBadge`: `bg-primary-soft text-primary h-6 rounded-full px-2.5 text-xs font-medium` dengan titik 6 px), nama sekolah (`text-muted-foreground text-xs`, bila ada). Rincian = `<dl>` satu kolom (`grid grid-cols-[96px_1fr]`, label `text-muted-foreground text-xs`). Baris disusun di klien: ada `identity.student` → NIK, NISN, Tanggal lahir, Jenis kelamin, Login terakhir; else ada `identity.school` → Sekolah, Akses (jika ada), Akun dibuat, Login terakhir; else → Wilayah, Akun dibuat, Login terakhir. Di bawah `xl` rincian dilipat: tombol "Data diri" (`xl:hidden`, ikon `ChevronDown` memutar, `aria-expanded`, `aria-controls`) dan blok rincian berkelas `block` bila terbuka, selain itu `hidden xl:block`. Tautan siswa ditaruh di bawah blok rincian (di luar bagian yang dilipat); catatan boleh di dalam blok. Nama pengguna = `<h2>`, `<section>` memakai `aria-labelledby`.
- [ ] **Step 4:** Run `npx vitest run resources/js/Components/profile/ProfileIdentityCard.test.ts`. Expected: PASS.

### Task 4: Dialog "Keluar dari perangkat lain"

**Files:**
- Create: `resources/js/Components/profile/LogoutOtherDevicesDialog.vue`
- Test: `resources/js/Components/profile/LogoutOtherDevicesDialog.test.ts`

**Interfaces:**
- Produces: `LogoutOtherDevicesDialog.vue` dengan `v-model:open` (boolean, wajib) tanpa props lain.
- Consumes: rute `profile.sessions.destroy` (Task 2); `PasswordInput`, `FormField`, `focusFirstInvalid`.

- [ ] **Step 1: Tulis `LogoutOtherDevicesDialog.test.ts`** (mock `useForm` seperti `Saya.test.ts` dengan `delete` mock; stub `route`; mount `open: true` dengan teleport dinonaktifkan atau stub `Dialog*` bila perlu). Tes:
  - judul "Keluar dari perangkat lain?" dan deskripsi "Semua perangkat lain akan keluar dari akun ini. Perangkat ini tetap masuk." tampil.
  - submit dengan password kosong: tidak memanggil `delete`, tampil "Password saat ini wajib diisi.".
  - submit dengan password: `delete` dipanggil dengan `route('profile.sessions.destroy')` dan opsi `preserveScroll: true`.
  - saat `onSuccess` dipanggil: event `update:open` dengan `false`.
  - galat server `errors.current_password` tampil di bawah field.
  - menutup dialog (`open` jadi false) mengosongkan `current_password` dan galat (`reset` dan `clearErrors` terpanggil).
- [ ] **Step 2:** Run `npx vitest run resources/js/Components/profile/LogoutOtherDevicesDialog.test.ts`. Expected: FAIL.
- [ ] **Step 3: Implement komponen** dengan primitif `Dialog*` (`@/Components/ui/dialog`, `DialogContent` `rounded-xl` seperti `ConfirmDialog`), `<form novalidate @submit.prevent>`, `FormField` "Password saat ini" + `PasswordInput` (`autocomplete="current-password"`), footer `Button variant="outline"` "Batal" dan `Button variant="destructive"` "Keluar dari perangkat lain" (spinner `LoaderCircle` + dinonaktifkan saat `processing`). Validasi klien lalu `form.delete(route('profile.sessions.destroy'), { preserveScroll: true, onSuccess: tutup, onError: focusFirstInvalid })`. `watch` pada `open`: saat false, `form.reset()` dan `form.clearErrors()` serta hapus galat klien.
- [ ] **Step 4:** Run tes yang sama. Expected: PASS.

### Task 5: Susun ulang `Edit.vue`

**Files:**
- Modify: `resources/js/Pages/Profile/Edit.vue`
- Test: `resources/js/Pages/Profile/Edit.test.ts`

**Interfaces:**
- Consumes: prop `identity: ProfileIdentity` (Task 1), `ProfileIdentityCard` (Task 3), `LogoutOtherDevicesDialog` (Task 4).

- [ ] **Step 1: Tulis `Edit.test.ts`** (mock `AppLayout`, `@inertiajs/vue3`, `route`, seperti `Saya.test.ts`; stub `LogoutOtherDevicesDialog` dengan prop `open`). Tes:
  - halaman memuat kartu identitas (stub atau nyata) dan kartu "Keamanan" dengan tombol "Keluar dari perangkat lain".
  - klik tombol itu membuka dialog (`open` jadi true).
  Perilaku form lama tidak diuji ulang.
- [ ] **Step 2:** Run `npx vitest run resources/js/Pages/Profile/Edit.test.ts`. Expected: FAIL.
- [ ] **Step 3: Ubah `Edit.vue`.** Tambah prop `identity`. Pembungkus konten menjadi `grid items-start gap-6 xl:grid-cols-[320px_minmax(0,720px)]` (ganti `max-w-[720px]` lama) dengan `ProfileIdentityCard` di kolom pertama dan `flex flex-col gap-6` berisi kartu kontak (isi dan logika tidak berubah) lalu satu kartu "Keamanan". Kartu Keamanan: `<h2>Keamanan</h2>`, form ubah password yang ada (isi dan logika tidak berubah; tombol "Perbarui password"), pemisah (`border-t`), lalu baris: judul "Keluar dari perangkat lain", penjelasan "Akhiri sesi di semua perangkat selain yang sedang dipakai. Cocok jika HP hilang atau dipinjam.", `Button variant="outline"` yang membuka `LogoutOtherDevicesDialog` (`v-model:open`). Di HP (< `sm`) tombol Simpan, Perbarui password, dan Keluar dari perangkat lain berlebar penuh (`w-full sm:w-auto`); baris "Keluar dari perangkat lain" bertumpuk. Pasang `ref` `devicesOpen`.
- [ ] **Step 4:** Run `npx vitest run resources/js/Pages/Profile/Edit.test.ts`. Expected: PASS.

### Task 6: Dokumen, sisa Breeze, verifikasi, review

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (§5.15)
- Delete: `resources/js/Layouts/AuthenticatedLayout.vue` (bersyarat)

- [ ] **Step 1: Perbarui §5.15** panduan UI: rute `/profile`; susunan dua kolom ≥ 1280 px; isi kartu identitas per role; kartu Informasi kontak, Keamanan (ubah password + keluar dari perangkat lain); catat bahwa Notifikasi (irisan B) dan Ubah email (irisan C) menyusul. Rujuk spec profil.
- [ ] **Step 2: Hapus `AuthenticatedLayout.vue`** hanya bila `grep -rn "AuthenticatedLayout" resources tests app vite.config.*` tidak menemukan pemakai selain berkasnya sendiri.
- [ ] **Step 3: Verifikasi penuh** (skill `superpowers:verification-before-completion`): `php artisan test` (satu proses), `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --memory-limit=1G`, `npx vitest run`, `npm run typecheck`, `npm run build`. Semua harus hijau; laporkan keluaran nyata.
- [ ] **Step 4: Review** (CLAUDE.md §3): `ponytail:ponytail-review` untuk seluruh perubahan, lalu `impeccable` untuk Components/profile dan `Edit.vue` (kualitas UI, aksesibilitas, kesesuaian dengan panduan UI dan mockup yang disetujui). Perbaiki temuan valid, ulangi verifikasi Step 3.
- [ ] **Step 5: Penelusuran manual** (gerbang rilis; dijalankan user): siswa, operator, dan admin membuka `/profile` di lebar 1440 dan 390; "Keluar dari perangkat lain" dari dua browser (satu dengan "Ingat saya") menghentikan sesi browser kedua.
