# SIJEBOL v1 — Desain Sistem

Sistem Jemput Bola Perekaman KTP-el Pelajar, Kecamatan Sungai Beduk.
Tanggal: 2026-10-07. Status: menunggu review.

## 1. Tujuan dan batasan

**Tujuan.** Kecamatan mendata pelajar usia 16–17 tahun yang akan direkam KTP-el lewat jemput bola. Operator sekolah mendaftarkan siswa secara digital, petugas kecamatan memverifikasi dan menjadwalkan, lalu menandai status kehadiran rekaman.

**Di luar sistem (manual di kecamatan).** Perekaman biometrik, hasil rekaman, validasi hasil, penerbitan KTP-el, notifikasi pengambilan KTP-el, sosialisasi. SIJEBOL tidak menyimpan biometrik atau status penerbitan.

**Di luar v1 (sub-proyek berikutnya).** Notifikasi WhatsApp, penjadwalan otomatis, dashboard monitoring lengkap, form evaluasi/feedback.

**Kriteria sukses v1.** Satu sekolah dapat dari aktivasi akun sampai pendaftaran terverifikasi dan terjadwal tanpa bantuan developer; admin dapat membuat role baru dan mengatur permission lewat UI; data sekolah tidak bocor antar operator; semua perubahan penting tercatat.

## 2. Aktor dan role awal

| Role | Hak utama |
|---|---|
| `super-admin` | Semua permission. Dilindungi: tidak dapat diubah atau dihapus lewat UI. |
| `admin-kecamatan` | Kelola user, role, sekolah; verifikasi; jadwal; penandaan rekaman; laporan. |
| `operator-sekolah` | Data siswa, dokumen, pendaftaran, lihat jadwal (hanya sekolahnya). |

Role lain dapat dibuat lewat UI (misalnya verifikator terpisah).

## 3. Alur

### 3.1 Aktivasi akun
1. Admin membuat user (nama, email/HP, role, sekolah). Sistem membuat token acak; hanya hash token disimpan.
2. Link `/aktivasi/{token}` berlaku 3×24 jam, sekali pakai. Admin menyalin dan mengirim link secara manual.
3. Operator mengisi password; akun aktif; login ke beranda.
4. Link kedaluwarsa/terpakai: halaman menampilkan pesan; admin memakai `user.kirim-ulang-aktivasi` (token lama tidak berlaku). Percobaan token dibatasi (rate limit).

### 3.2 Data siswa
Operator menginput siswa (NIK, NISN, nama, tanggal lahir, jenis kelamin), satu per satu atau impor Excel, dan mengunggah dokumen KK dan akta lahir. Sistem memvalidasi format dan keunikan NIK.

### 3.3 Pendaftaran rekaman
1. Operator membuat pendaftaran dan mengisi detail (keterangan, perkiraan jumlah peserta).
2. Daftar pilihan hanya memuat siswa **eligible**; operator memilih beberapa sekaligus (multi-select). Siswa yang dokumennya belum lengkap tampil nonaktif dengan tombol "Lengkapi dokumen".
3. Submit: server memvalidasi ulang kelayakan; setiap siswa masuk dengan status `Diajukan`.

**Eligible** (dihitung di server saat submit):
- umur 16 atau 17 tahun pada **tanggal submit** (dihitung dari tanggal lahir)
- data wajib lengkap, KK dan akta lahir terunggah
- tidak memiliki entri aktif di pendaftaran lain. Entri aktif = status `Diajukan`, `Perlu perbaikan`, `Terverifikasi`, `Terjadwal`, atau `Sudah rekaman`. Siswa dengan status akhir `Ditolak`, `Tidak hadir`, atau `Belum rekaman` boleh didaftarkan lagi.

### 3.4 Verifikasi (satu keputusan per siswa)
Petugas memeriksa data dan dokumen siswa, lalu memutuskan: **Setujui**, **Minta perbaikan** (catatan wajib), atau **Tolak** (catatan wajib). Satu pengajuan dapat berisi keputusan berbeda per siswa. Siswa `Perlu perbaikan` diperbaiki operator lalu diajukan ulang (kembali `Diajukan`).

### 3.5 Penjadwalan manual
Admin membuat jadwal per sekolah (tanggal, lokasi), lalu memasukkan siswa `Terverifikasi` sekolah itu ke jadwal; status menjadi `Terjadwal`. Operator sekolah melihat jadwal di beranda.

### 3.6 Penandaan rekaman
Setelah hari kegiatan, admin menandai tiap siswa (ceklis massal): `Sudah rekaman`, `Tidak hadir`, atau `Belum rekaman`. Ini status kehadiran, bukan hasil biometrik.

### 3.7 State machine (per siswa dalam pendaftaran)

```
Diajukan -> Terverifikasi -> Terjadwal -> Sudah rekaman | Tidak hadir | Belum rekaman
Diajukan -> Perlu perbaikan -> (operator perbaiki) -> Diajukan
Diajukan -> Ditolak
```

Status batch tidak disimpan; dihitung dari status siswa. Pendaftaran yang sudah diajukan terkunci kecuali siswa berstatus `Perlu perbaikan`.

## 4. Arsitektur

**Pola:** modular monolith Laravel; Inertia + Vue 3 (tanpa API terpisah). Kode per domain.

### 4.1 Layering backend
`Controller -> FormRequest -> Service -> Repository -> Model`

- **FormRequest** terpisah per aksi: validasi dan `authorize()`.
- **Controller** tipis: panggil service, kembalikan respons Inertia. Tanpa logika bisnis.
- **Service** per domain (`StudentService`, `RegistrationService`, `VerificationService`, `ScheduleService`, dll.): aturan bisnis, transisi status (validasi status asal), transaksi database, pemicu event.
- **Repository** per model utama dengan interface (Contracts) dan implementasi Eloquent, di-bind di service provider. Berisi query (filter, paginasi, eager loading). Dijaga tipis; perpindahan status tetap di service.
- **Policy** untuk aturan kepemilikan data. **Event/Listener** untuk efek samping (notifikasi, log).

Struktur folder (di `app/Domains/`): `Access`, `Schools`, `Students`, `Registrations`, `Schedules`, `Notifications`. Tiap domain berisi `Http/Controllers`, `Http/Requests`, `Services`, `Repositories` (+ `Contracts`), `Models`, `Policies`, `Events`, `Listeners`, `Notifications`.

### 4.2 Frontend
- Tiga tingkat komponen: `components/ui/` (dasar), `components/shared/` (`DataTable` server-side, `StatusBadge`, `FileUpload`, `StudentMultiSelect`, `ConfirmDialog`, `NotificationBell`), `pages/` (hanya merakit).
- Utilitas satu tempat di `resources/js/lib/`: `date.ts` (format tanggal/waktu/umur, locale `id-ID` via `Intl`), `format.ts` (NIK masker, angka), `status.ts` (label dan warna status). Tidak ada formatter yang diduplikasi di halaman atau komponen.
- Composable: `usePermission()`, `useDataTable()`, `useFlash()`.
- Konstanta status dan permission satu sumber (TypeScript mirror enum PHP).

### 4.3 Package

| Kebutuhan | Pilihan |
|---|---|
| Framework | Laravel stabil terbaru, PHP 8.3+ (mesin dev: PHP 8.5) |
| Database | MySQL/MariaDB; zona waktu `Asia/Jakarta`, locale `id` |
| Auth | Laravel Breeze (Inertia + Vue) |
| UI | Vue 3, TypeScript, Vite, Tailwind, shadcn-vue |
| RBAC | `spatie/laravel-permission` |
| Audit log | `spatie/laravel-activitylog` |
| Excel | `maatwebsite/excel` |
| Filter/sort server-side | `spatie/laravel-query-builder` |
| Queue | Database queue (naik ke Redis bila perlu) |
| Test | Pest |
| Kualitas | Larastan, Pint |

### 4.4 Penyesuaian Breeze
Hapus registrasi publik dan verifikasi email bawaan (akun hanya dibuat admin; aktivasi lewat link adalah verifikasinya). Tambahan: halaman aktivasi, rate limit login, pencatatan login/logout ke audit log.

## 5. Model data

- `schools`
- `users` (email unik wajib, `phone` opsional untuk tautan WhatsApp manual, `school_id` nullable: terisi = operator sekolah; kosong = staf kecamatan)
- `roles`, `permissions`, `role_has_permissions`, `model_has_roles` (spatie)
- `account_activations` (user_id, token_hash, expires_at, used_at)
- `students` (school_id, nik unik, nisn, nama, tanggal_lahir, jenis_kelamin)
- `student_documents` (student_id, jenis: kk | akta_lahir, path privat, mime, ukuran)
- `registrations` (school_id, detail, dibuat_oleh, submitted_at)
- `registration_students` (registration_id, student_id, status, catatan, diputuskan_oleh, diputuskan_pada, schedule_id nullable)
- `schedules` (school_id, tanggal, lokasi, dibuat_oleh)
- `registration_status_histories` (registration_student_id, dari_status, ke_status, oleh, catatan, waktu)
- `notifications` (bawaan Laravel), `activity_log` (spatie)

**Integritas:**
- Database: MySQL/MariaDB. MySQL tidak punya unique index parsial, jadi `registration_students` memakai kolom generated `active_student_id` (`student_id` jika status termasuk entri aktif, selain itu `NULL`) dengan unique index di kolom itu. Nilai `NULL` boleh berulang, sehingga satu siswa hanya punya satu entri aktif (mencegah pendaftaran ganda saat submit bersamaan).
- NIK disimpan biasa (tidak dienkripsi); dilindungi akses ketat dan dimasker di tampilan dan log.
- Dokumen di disk privat, tidak ada URL publik.

## 6. RBAC dan menu

Permission didefinisikan di kode (daftar tetap, konvensi `modul.aksi`); role dan penetapan permission ke role dikelola lewat UI.

| Modul | Permission |
|---|---|
| `user` | `lihat`, `buat`, `ubah`, `nonaktifkan`, `kirim-ulang-aktivasi` |
| `role` | `lihat`, `buat`, `ubah`, `hapus` (termasuk atur permission) |
| `sekolah` | `lihat`, `buat`, `ubah` |
| `siswa` | `lihat`, `buat`, `ubah`, `hapus`, `impor` |
| `dokumen` | `lihat`, `unggah`, `hapus` |
| `pendaftaran` | `lihat`, `buat`, `ajukan` |
| `verifikasi` | `lihat`, `putuskan` |
| `jadwal` | `lihat`, `buat`, `ubah` |
| `rekaman` | `lihat`, `tandai` |
| `laporan` | `lihat` |
| `log` | `lihat` |

**Tiga lapisan pemeriksaan:** middleware `can:` di route; Policy untuk kepemilikan data dan kondisi (misalnya pendaftaran terkunci); UI menyembunyikan menu/tombol berdasarkan permission (hanya kenyamanan).

**Scope sekolah** adalah atribut user, bukan permission: user dengan `school_id` dibatasi ke sekolahnya lewat global scope (dan di repository); user tanpa `school_id` melihat semua sekolah. Data sekolah lain mengembalikan 404.

**Menu:** satu daftar di kode, tiap item punya permission pemicu, disaring di server lalu dikirim ke sidebar.

**Pengaman:** perubahan role/permission mengosongkan cache dan masuk audit log; user tidak dapat memberi permission yang tidak ia miliki (anti eskalasi); `super-admin` tidak dapat diubah/dihapus.

## 7. Notifikasi

Sistem Notification bawaan Laravel: channel `database` (in-app) dan `mail`, dikirim lewat queue. WhatsApp kelak menjadi channel tambahan di class yang sama.

- In-app: lonceng di header, jumlah belum dibaca lewat Inertia shared props, daftar notifikasi, tandai dibaca; polling ringan 60 detik, tanpa WebSocket.
- Email: queued dengan retry; kegagalan masuk `failed_jobs`.
- Penerima ditentukan oleh permission, bukan nama role.
- Tidak ada preferensi per pengguna di v1.

| Kejadian | Penerima |
|---|---|
| Pendaftaran diajukan | Pemilik `verifikasi.putuskan` |
| Siswa diverifikasi / ditolak / perlu perbaikan | Operator sekolah terkait |
| Jadwal ditetapkan atau diubah | Operator sekolah terkait |

## 8. Log aktivitas

1. **Riwayat status** (`registration_status_histories`): data domain, tampil sebagai timeline per siswa dan dipakai laporan.
2. **Audit log** (activitylog): siapa, kapan, apa, nilai sebelum/sesudah. Mencakup user, role/permission, siswa, upload dan unduh dokumen, login/logout, aksi verifikasi dan jadwal.

Aturan: password dan token dikecualikan; NIK dimasker; log hanya baca; viewer di balik `log.lihat` dengan filter pengguna, tanggal, jenis; retensi minimal 1 tahun, pembersihan lewat scheduled command.

## 9. Penanganan error

- Validasi form: pesan per field (bahasa Indonesia) lewat Inertia.
- Impor Excel: baris benar dimasukkan, baris salah dilaporkan beserta nomor baris dan alasan.
- Upload dokumen: batas tipe (PDF/JPG/PNG) dan ukuran; kegagalan tidak meninggalkan baris database yatim.
- Akses ditolak: 403 dengan halaman jelas; data sekolah lain: 404.

## 10. Pengujian (Pest)

- Kelayakan umur (batas 16/17, hari ulang tahun di tanggal submit).
- Transisi status sah dan ilegal.
- Scope sekolah: operator A tidak dapat melihat/mengubah data atau dokumen sekolah B.
- Permission route sensitif; anti eskalasi hak; perlindungan super-admin.
- Aktivasi: token valid, kedaluwarsa, dipakai dua kali.
- Pendaftaran ganda bersamaan.
- Notifikasi terkirim ke penerima yang benar (fake channel).
- End-to-end alur utama: aktivasi -> submit; verifikasi -> jadwal -> penandaan.

## 11. Infrastruktur

Hosting mendukung queue worker (supervisor) dan cron (sudah dikonfirmasi). SMTP: ditentukan kemudian (email instansi atau layanan seperti SES/Mailgun).

## 12. Asumsi dan keputusan terbuka

- "NIP" pada permintaan awal dibaca sebagai **NISN**.
- Umur dihitung pada **tanggal submit**. Jika kecamatan butuh umur pada tanggal rekaman, aturan diubah di satu tempat (service eligibility).
- Impor Excel: baris benar masuk, baris salah dilaporkan (bukan batal total).
- Link aktivasi disalin admin dan dikirim manual; kirim otomatis lewat email dapat ditambah.
- Retensi audit log: 1 tahun (dapat disesuaikan).
- Dokumen KK/akta tidak punya status verifikasi sendiri; diperiksa sebagai bagian keputusan per siswa.
- Siswa `Tidak hadir`/`Belum rekaman` dapat didaftarkan lagi di pendaftaran baru (tanpa fitur jadwal ulang khusus).
