# Fase 1 BE — Akses dan Autentikasi: Desain

Tanggal: 2026-10-07. Status: menunggu review.
Induk: `2026-10-07-sijebol-v1-design.md` (spec sistem), `2026-10-07-sijebol-ui-guide.md`, sprint plan Fase 1. Dokumen ini memperinci keputusan Fase 1 **Track BE**; bila bertentangan dengan spec induk, dokumen ini menang untuk Fase 1.

## 1. Tujuan dan cakupan

**Tujuan.** Admin kecamatan dapat membuat sekolah, role (dengan matriks izin), dan user. Operator menerima link aktivasi, membuat password, lalu login. Seluruh akses dikendalikan RBAC dinamis, dan data operator terkunci ke sekolahnya.

**Dalam cakupan:** migrasi, model, seeder role/izin awal, aktivasi akun, penyesuaian login, profil, CRUD Sekolah/User/Role, scope sekolah, menu server-side, audit log (pencatatan), test Pest.

**Di luar cakupan:** halaman FE (Track FE Fase 1), notifikasi dan email aktivasi otomatis, viewer log dan retensi (Fase 2), reset password lewat admin, modul siswa dan seterusnya.

**Kriteria selesai:** seluruh test Fase 1 hijau (termasuk `composer analyse` dan `composer lint`); matriks rute × izin teruji; tidak ada jalur akses lintas sekolah; super-admin pertama dapat dibuat lewat command dan login setelah aktivasi.

## 2. Keputusan

| Topik | Keputusan |
|---|---|
| Role terikat sekolah | Kolom `roles.is_school_scoped`. User dengan role itu wajib `school_id`; role lain wajib kosong. |
| Role per user | Satu user satu role. |
| Status user | Dihitung, tidak disimpan: `nonaktif` (is_active=false) > `aktif` (activated_at terisi) > `menunggu` (ada aktivasi belum dipakai dan belum kedaluwarsa) > `kedaluwarsa`. |
| Password | Minimal 8 karakter, mengandung huruf dan angka. Kolom `password` nullable (akun belum aktif). |
| Login | Email + password. Akun nonaktif atau belum aktif ditolak dengan pesan umum "Email atau password salah." |
| Super-admin | Gate `before` meloloskan semua. Tidak dapat diubah, dihapus, atau dinonaktifkan lewat UI; super-admin aktif terakhir tidak dapat dinonaktifkan atau diganti rolenya. |
| Email | Wajib unik; tidak dapat diubah setelah akun aktif. `email_verified_at` tidak dipakai. |
| Dev mail | Mailer `log`. |

## 3. Data

**Migrasi baru/ubah**
- `schools`: `id`, `name`, `npsn` (unik), `level` (`SMA`/`SMK`/`MA`), `address` nullable, timestamp.
- `users` (ubah): `phone` nullable, `school_id` FK nullable → `schools`, `is_active` boolean default true, `activated_at` nullable, `last_login_at` nullable, `created_by` FK nullable → `users`; `password` menjadi nullable.
- `roles` (ubah): `description` nullable, `is_school_scoped` boolean default false. Spatie diarahkan ke model `App\Domains\Access\Models\Role` lewat `config/permission.php`.
- `account_activations`: `id`, `user_id`, `token_hash` (unik, SHA-256), `expires_at`, `used_at` nullable, timestamp.

**Struktur kode (`app/Domains`)**
```
Access/   Models/Role, Services/{UserService,RoleService,ActivationService,MenuService},
          Repositories/{Contracts,Eloquent*},
          Http/{Controllers,Requests}, Listeners/LogAuthEvents, Exceptions/
Schools/  Models/School, Services/SchoolService, Repositories, Http/...
```
`app/Models/User.php` tetap. Dibuat sekarang karena sudah ada konsumennya: `RepositoryServiceProvider` (binding Contracts → Eloquent) dan trait `ScopedBySchool`.

**Seeder:** semua permission dari enum `Permission`; role `super-admin` (semua), `admin-kecamatan` (user, role, sekolah, plus izin modul lain yang sudah ada di enum), `operator-sekolah` (`is_school_scoped=true`; siswa, dokumen, pendaftaran, `jadwal.lihat`). Command `sijebol:create-super-admin {email} {name?}` membuat super-admin pertama dan mencetak link aktivasi; tidak ada password di seeder.

## 4. Scope sekolah
Trait `ScopedBySchool` memasang global scope berdasarkan `auth()->user()->school_id` bila terisi; pengguna tanpa `school_id` melihat semua. Query tunggal ke data sekolah lain mengembalikan **404** (bukan 403). Fase 1 memasangnya pada `User`; model lain memasangnya di fasenya sendiri.

## 5. Rute dan izin

| Modul | Rute | Izin |
|---|---|---|
| Sekolah | `GET /sekolah`, `/sekolah/buat`, `POST /sekolah`, `GET /sekolah/{id}/ubah`, `PUT /sekolah/{id}` | `sekolah.lihat` / `.buat` / `.ubah` |
| Pengguna | `GET /pengguna` (filter cari, role, sekolah, status; paginasi 20), `/pengguna/buat`, `POST /pengguna`, `GET /pengguna/{id}`, `/{id}/ubah`, `PUT /{id}`, `POST /{id}/nonaktifkan`, `/{id}/aktifkan`, `/{id}/kirim-ulang-aktivasi`, `GET /{id}/berhasil` | `user.lihat/buat/ubah/nonaktifkan/kirim-ulang-aktivasi` |
| Role | `GET /role`, `/role/buat`, `POST /role`, `GET /role/{id}/ubah`, `PUT /role/{id}`, `DELETE /role/{id}` | `role.lihat/buat/ubah/hapus` |
| Aktivasi | `GET /aktivasi/{token}`, `POST /aktivasi/{token}` (tamu, `throttle:10,1`) | — |
| Profil | ubah nama dan HP; ubah password | login |

Controller tipis: `FormRequest` (validasi) → `Gate`/middleware `can:` (akses; hak mengelola akun lain lewat Gate `manage-user`) → `Service` (aturan) → `Repository` (query). Query daftar memakai `spatie/laravel-query-builder`. Halaman Inertia belum ada di FE; test memakai `inertia.testing.ensure_pages_exist=false` sampai Track FE.

## 6. Aturan bisnis

**Aktivasi.** Token acak 64 karakter; hanya hash disimpan; berlaku 3×24 jam; sekali pakai; token baru mencabut yang lama. Setelah user dibuat atau link dikirim ulang, link ditampilkan **sekali** di `/pengguna/{id}/berhasil` lewat flash session; muat ulang menghilangkannya. Kirim ulang hanya untuk akun yang belum aktif. Token kedaluwarsa, terpakai, dan tidak dikenal menghasilkan satu state yang sama. Penyelesaian aktivasi dalam transaksi dengan `lockForUpdate` pada baris token (dua submit bersamaan: hanya satu berhasil); sukses mengisi password dan `activated_at`, menandai token terpakai, lalu login otomatis.

**Pengguna.** Sekolah wajib bila role `is_school_scoped`, terlarang bila tidak. Email terkunci setelah aktif. Nonaktifkan: `is_active=false`; middleware `EnsureAccountIsActive` men-logout user itu pada request berikutnya (termasuk lewat remember-cookie). Tidak dapat menonaktifkan atau mengubah role diri sendiri. Login berhasil mengisi `last_login_at`.

**Anti eskalasi.** Pembuat hanya boleh memberi role yang seluruh izinnya ada di izin miliknya; hanya super-admin yang boleh memberi role `super-admin`. Pengguna non-super-admin boleh melihat akun lain di daftar dan detail (sesuai `user.lihat`), tetapi tidak dapat mengubah, menonaktifkan, atau mengirim ulang aktivasi akun yang rolenya memuat izin di luar izin miliknya (403).

**Role.** Nama unik. Izin harus anggota enum `Permission`. Penyunting hanya menambah atau mencabut izin yang ia miliki; izin lain pada role itu tidak disentuh. Hapus ditolak bila masih dipakai pengguna. `is_school_scoped` terkunci bila role sudah dipakai. Perubahan izin mengosongkan cache izin spatie. Role `super-admin` tidak dapat diubah atau dihapus.

**Menu.** `MenuService` menyaring daftar menu (grup, label, href, kunci ikon, izin) berdasarkan izin pengguna dan membagikannya sebagai shared prop `menu`; FE memetakan kunci ikon ke komponen.

## 7. Audit log
`spatie/laravel-activitylog`, log name `akses`. Otomatis (`LogsActivity`, hanya atribut berubah, tanpa entri kosong) pada `User`, `Role`, `School`; `password`, token, dan `remember_token` dikecualikan. Manual dengan pelaku pengguna yang login: perubahan izin role (daftar ditambah dan dicabut), nonaktifkan/aktifkan, buat dan kirim ulang aktivasi (tanpa token), aktivasi berhasil (pelaku pengguna itu sendiri), login, logout, dan penguncian login. Login dan logout ditangkap lewat event `Login`/`Logout` di satu listener. Viewer dan retensi: Fase 2.

## 8. Penanganan error
- Validasi: `FormRequest`, pesan bahasa Indonesia per field.
- Pelanggaran aturan bisnis dilempar service sebagai `DomainException` bertipe khusus; satu handler memetakannya ke redirect kembali dengan `flash.error`.
- 403 akses ditolak; 404 data sekolah lain atau token/ID tidak dikenal; 429 untuk throttle aktivasi.
- Operasi multi-tabel dalam transaksi.

## 9. Pengujian (Pest, MySQL `sijebol_test`)
- Tabel rute × izin: tanpa izin 403, dengan izin lolos.
- Aktivasi: valid, kedaluwarsa, terpakai, tidak dikenal, token lama tidak berlaku setelah kirim ulang, submit ganda bersamaan, password lemah.
- Login: nonaktif, belum aktif, throttle, `last_login_at`, pesan tidak membedakan alasan.
- Nonaktifkan: sesi terhapus; tidak dapat menonaktifkan diri sendiri.
- Scope sekolah: operator A tidak dapat melihat atau mengubah data sekolah B (404).
- Anti eskalasi: role atau izin di luar milik pembuat ditolak; akun berhak lebih tinggi tidak dapat diubah.
- Super-admin: tidak dapat diubah atau dihapus; yang terakhir tidak dapat dinonaktifkan.
- Role: hapus terpakai ditolak; `is_school_scoped` terkunci bila terpakai; izin di luar enum ditolak; cache izin dikosongkan.
- Pengguna: sekolah wajib atau terlarang sesuai flag role; email terkunci setelah aktif.
- Menu hanya memuat item berizin.
- Audit log: entri untuk pembuatan user, perubahan izin, dan login; password tidak pernah muncul.
- Arch test ringan: service tidak bergantung pada kelas `Http`.

## 10. Asumsi
- Halaman FE dibangun setelah BE selesai; kontrak data ke FE (props Inertia) mengikuti nama field model, dan final-nya ditetapkan saat rencana tugas.
- Satu user satu role cukup untuk v1.
- Link aktivasi dikirim manual oleh admin (tanpa email otomatis).
