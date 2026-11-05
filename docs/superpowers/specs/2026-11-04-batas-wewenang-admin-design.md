# Batas wewenang admin: sekolah, role, dan akun siswa (F-04, F-05, F-06)

Sumber: temuan F-04, F-05, F-06 di `docs/reviews/2026-11-02-full-code-review.md`. Ketiganya tentang aksi yang lolos dari pemeriksaan pelaku (actor) di service.

## F-04: tambah dan ubah sekolah tanpa pemeriksaan pelaku

**Masalah.** Perbaikan IDOR sebelumnya menolak izin `sekolah.buat/ubah/hapus` hanya pada role yang dibuat atau diubah sesudahnya (`RoleService::assertScopeAllows`), dan menambah guard pada `SchoolService::delete`. Role terikat sekolah yang sudah lama memegang izin itu masih lolos untuk tambah dan ubah sekolah mana pun.

**Rancangan.** `SchoolService::create` dan `update` menerima `User $actor` dan memakai satu pemeriksaan privat `assertDistrictStaff` (sama dengan guard `delete`): pelaku dengan `school_id` tidak null ditolak dengan `AccessRuleException` ("Hanya staf kecamatan yang dapat ..."). Pelaku terikat sekolah ditolak juga untuk sekolahnya sendiri, karena data sekolah mencakup seluruh kecamatan. Controller meneruskan `$request->user()`. Halaman form (GET) tidak diubah: datanya (nama, NPSN, alamat) sudah terlihat di daftar sekolah.

## F-05: ubah role tanpa memeriksa hak atas role itu

**Keputusan pemilik (4 Nov 2026): kunci identitas role, pertahankan edit izin parsial.** Desain Fase 1 sengaja membolehkan penyunting mengubah hanya izin yang ia miliki (sisanya dibiarkan; panduan UI menonaktifkan checkbox lainnya). Itu dipertahankan.

**Rancangan.** Bila `AccessGuard::canGrantRole($actor, $role)` salah (izin role melebihi milik penyunting), `RoleService::update` menolak perubahan **nama, deskripsi, atau `is_school_scoped`** dengan `AccessRuleException`. Deskripsi kosong dan null dianggap sama. Super-admin tidak terkena (selalu `canGrantRole`). `RoleController::edit` mengirim `role.identity_locked`; `Role/Form.vue` menonaktifkan kolom nama, deskripsi, dan kotak "terikat sekolah" serta menampilkan peringatan. Yang tetap diizinkan: mengubah izin yang dimiliki penyunting (termasuk mencabutnya dari role itu), seperti sebelumnya.

## F-06: akun siswa di menu Pengguna

**Keputusan pemilik: blokir ubah dan role siswa; aktif/nonaktif tetap.**

**Rancangan.**
- `UserService::update` menolak akun yang `isStudent()` ("dikelola dari menu Siswa"). Metode publik `assertEditable` dipakai juga oleh `UserController::edit`, sehingga halaman ubah ditolak dengan flash error.
- `UserService::create` dan `update` menolak pemberian role `siswa` (akun siswa lahir dari pendaftaran lewat link sekolah yang sekaligus membuat baris `students`). Form Pengguna tidak lagi menawarkan role `siswa`.
- Nonaktifkan, aktifkan, dan kirim ulang aktivasi pada akun siswa tidak diubah.
- Baris daftar membawa `is_student`; tombol Ubah disembunyikan untuk akun siswa di `Pengguna/Index.vue` dan `Pengguna/Show.vue`.

## Di luar cakupan
- Memigrasi role lama yang terlanjur memegang izin sekolah (guard sudah menetralkan dampaknya; kueri pengecekan ada di panduan deployment).
- Akun siswa lama yang sudah terlanjur dibuat tanpa data siswa lewat menu Pengguna (kueri pengecekan di panduan).

## Pengujian
Pest: guard sekolah (tambah, ubah sekolah sendiri dan lain), identitas role terkunci untuk tiga bidang, edit izin parsial tetap jalan, null/kosong setara, super-admin bebas, `identity_locked` di prop, akun siswa tidak bisa diubah/dibuat, role siswa tidak ada di form, aktif/nonaktif/kirim ulang tetap berfungsi.
