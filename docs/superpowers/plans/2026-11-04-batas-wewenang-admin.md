# Batas Wewenang Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Menutup F-04, F-05, F-06 dengan pemeriksaan pelaku di service dan penyesuaian form.

**Architecture:** Guard di lapisan service (`SchoolService`, `RoleService`, `UserService`) yang melempar `AccessRuleException` (diubah handler menjadi flash error); controller meneruskan pelaku dan menyediakan prop `identity_locked` / `is_student` untuk frontend.

**Tech Stack:** Laravel 13, Pest, Vue 3 + Inertia.

**Spec:** `docs/superpowers/specs/2026-11-04-batas-wewenang-admin-design.md`

## Global Constraints
- Satu temuan satu commit; urutan F-04, F-05, F-06.
- Pesan galat berbahasa Indonesia; pola `AccessRuleException`.
- Tanpa dependensi baru.

## Review Focus
- Operator terikat sekolah menambah/mengubah sekolahnya sendiri tetap ditolak.
- Deskripsi `''` vs `null` tidak boleh dianggap perubahan identitas role.
- Edit izin parsial yang sudah ada tetap lulus (tes lama tidak diubah).
- Akun siswa tetap bisa dinonaktifkan/diaktifkan/dikirim ulang aktivasinya.
- Mengubah staf menjadi siswa lewat ubah role ditolak.

### Task 1: F-04
**Files:** Modify `SchoolService.php`, `SchoolController.php`; Test `tests/Feature/Schools/SchoolTest.php`
- [ ] Tes gagal dulu: operator terikat sekolah (role memegang `sekolah.buat`/`sekolah.ubah`) ditolak tambah dan ubah (sekolah sendiri dan lain) dengan flash `error`.
- [ ] `create(array, User)`, `update(School, array, User)` + `assertDistrictStaff` privat (dipakai juga `delete`); controller meneruskan pelaku.

### Task 2: F-05
**Files:** Modify `RoleService.php`, `RoleController.php`, `Role/Form.vue`; Test `RoleServiceTest.php`, `RoleHttpTest.php`
- [ ] Tes gagal dulu: tiga kasus (nama, deskripsi, jenis) ditolak pada role di luar milik penyunting; izin parsial tetap boleh; `''` setara `null`; super-admin bebas; role dalam jangkauan boleh diganti nama; `identity_locked` pada prop edit.
- [ ] Guard di `update`, prop `identity_locked`, kolom form dinonaktifkan + peringatan.

### Task 3: F-06
**Files:** Modify `UserService.php`, `UserController.php`, `types/access.ts`, `Pengguna/Index.vue`, `Pengguna/Show.vue`; Test `UserServiceTest.php`, `UserHttpTest.php`
- [ ] Tes gagal dulu: update akun siswa ditolak (sekolah tetap), create dengan role siswa ditolak, staf menjadi siswa ditolak, halaman ubah akun siswa ditolak, form tanpa role siswa, `is_student` di baris, nonaktif/aktif/kirim ulang tetap jalan.
- [ ] `assertEditable` (publik) + `assertNotStudentRole`; filter form; `is_student`; tombol Ubah disembunyikan.

### Task 4: Dokumen dan verifikasi
- [ ] Panduan deployment: kueri pengecekan role/akun lama. Berkas review: status F-04 sampai F-06.
- [ ] Pint, PHPStan, Pest penuh, Vitest, vue-tsc, ESLint; review ponytail + impeccable.
