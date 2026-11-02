# Daftar Rekaman & KTP-el — Desain

Tanggal: 2026-11-01. Status: menunggu persetujuan.

## Latar belakang dan tujuan

Client (Kecamatan Sungai Beduk) meminta laporan yang memuat **nama siswa-siswi** yang sudah rekaman, yang KTP-el-nya sudah bisa terbit, dan yang belum. Laporan yang ada (`/reports`) hanya berupa angka rekap per sekolah dan per sesi, tanpa nama.

Tujuan: petugas kecamatan dapat melihat dan mengunduh daftar per siswa, lengkap dengan kelompok dan alasannya, sehingga tahu siapa yang perlu ditindaklanjuti.

## Yang sudah dipastikan dari percakapan

- **Arti "bisa terbit"** dihitung dari umur, bukan dari pencatatan cetak. Aturannya sama dengan penanda `ktp_langsung` yang sudah ada (`EligibilityService::directKtp`): lewat ulang tahun ke-17 pada tanggal sesi rekaman. Sistem **tidak** mencatat KTP-el yang benar-benar sudah dicetak Disdukcapil.
- **Bentuk:** halaman daftar di aplikasi dengan filter, plus unduh Excel (bukan Excel saja, bukan PDF).
- **Susunan halaman:** satu tabel, tiga kotak angka di atas yang bisa diklik sebagai filter (pilihan A pada mockup).
- **Cakupan "belum":** semua siswa terdata yang belum rekaman, dengan kolom alasan.
- **NIK:** tidak tampil di halaman. NIK penuh hanya di Excel, hanya untuk pemegang izin khusus.
- **Akses:** diatur lewat hak akses (RBAC). Default hanya admin kecamatan dan super-admin.
- **Rentang tanggal rekaman:** ada. Siswa yang belum punya sesi tetap tampil di "Belum rekaman" walau rentang diisi.
- **Kolom Excel:** semua kolom tabel, ditambah NIK penuh, tanggal lahir, jenis kelamin, dan tanggal genap 17 tahun.

## Tujuan, bukan-tujuan, anti-tujuan

**Tujuan:** daftar per siswa yang dapat disaring dan diunduh; jumlah per kelompok tampak sekilas.

**Bukan tujuan:** mencatat status cetak KTP-el; mengubah laporan rekap yang ada; PDF atau cetak; menampilkan NIK di layar; kelas atau nomor HP siswa (datanya memang tidak ada di `students`).

**Anti-tujuan (dianggap gagal walau secara teknis jalan):**
- Siswa terdata yang tidak muncul di kelompok mana pun. Jumlah tiga kelompok harus selalu sama dengan jumlah siswa dalam cakupan filter.
- Teks yang mengesankan KTP-el sudah dicetak. Pakai teks penanda `ktp_langsung` yang sudah ada.
- NIK penuh bocor ke pemegang izin Laporan biasa, atau unduhan NIK tanpa jejak di log.

## Perilaku

### Kelompok (satu siswa tepat satu kelompok)

Dasarnya pendaftaran terkini siswa (`Student::currentRegistration()`: yang aktif bila ada, selain itu yang terbaru).

1. **Langsung terbit:** status pendaftaran terkini `sudah_rekaman` dan `directKtp(siswa, tanggal sesi)` benar.
2. **Menunggu genap 17:** `sudah_rekaman` tetapi `directKtp` salah. Kolom KTP-el menampilkan perkiraan tanggal mulai terbit.
3. **Belum rekaman:** semua yang lain. Kolom alasan memakai label status pendaftaran terkini (menunggu verifikasi, perlu perbaikan, terdaftar, tidak hadir, belum rekaman/hadir tapi belum sempat direkam, ditolak, dibatalkan, kedaluwarsa). Siswa tanpa pendaftaran sama sekali: "Belum mendaftar sesi".

### Halaman

- Rute dan izin: lihat bagian Izin. Judul "Laporan KTP-el pelajar", di bawah Laporan.
- Tiga kotak angka (jumlah per kelompok) yang bisa diklik untuk menyaring; klik lagi untuk melepas. Jumlah mengikuti filter lain (sekolah, sesi, rentang, pencarian) kecuali filter kelompok itu sendiri.
- Filter: sekolah (hanya untuk pengguna tanpa sekolah), sesi, rentang tanggal rekaman, pencarian nama atau NISN.
- Tabel server-side berpaginasi, kolom: Nama, NISN, Sekolah, Tanggal rekaman, KTP-el (kelompok atau perkiraan tanggal terbit), Alasan (hanya terisi untuk Belum rekaman). Tanpa NIK.
- Tombol **Unduh Excel** mengikuti filter yang sedang aktif.

### Rentang tanggal rekaman

Menyaring menurut **tanggal sesi** pendaftaran terkini siswa. Siswa tanpa pendaftaran (tanpa tanggal sesi) selalu tetap tampil di Belum rekaman.

### Excel

- Kolom: Nama, NIK, NISN, Tanggal lahir, Jenis kelamin, Sekolah, Tanggal rekaman, Kelompok KTP-el, Tanggal genap 17 tahun, Alasan.
- Isinya mengikuti filter aktif (semua halaman, bukan hanya halaman yang tampil).
- NIK dan NISN sebagai teks (jangan berubah jadi notasi ilmiah). Nama dan sekolah lewat `RecapExport::safe` (anti formula Excel).
- Tiap unduhan dicatat: `activity('laporan')->log('laporan.diekspor')` dengan `jenis: ktp-el-pelajar`, filter yang dipakai, dan `nik_penuh: true|false`.
- Throttle sama dengan ekspor lain (`throttle:20,1`).

### Izin

- `laporan.rekaman`: membuka halaman dan mengunduh Excel (NIK tampil termasking).
- `laporan.unduh-nik`: Excel berisi NIK penuh. Bergantung pada `laporan.rekaman`.
- Keduanya otomatis dipegang `super-admin` dan `admin-kecamatan` (seeder memberi semua izin). Role lain tidak mendapat default.
- Pengguna terikat sekolah yang diberi izin ini hanya melihat dan mengunduh siswa sekolahnya sendiri (pembatasan sekolah yang sama dengan daftar Siswa), sehingga izin ini **tidak** dimasukkan ke daftar izin kecamatan-saja di `RoleService::assertScopeAllows`.

## Kendala

- Stack dan pola yang ada: service-repository, `ReportService` / `ReportRepositoryInterface`, ekspor `maatwebsite/excel`, halaman Inertia `Laporan/`.
- Tidak boleh ada N+1; jumlah siswa kecamatan ratusan sampai ribuan, jadi pengelompokan boleh di SQL atau di PHP selama satu query besar.
- Tanggal memakai zona Asia/Jakarta (`lib/date.ts` di frontend).
- Teks Indonesia, status tidak hanya dibedakan lewat warna.
- Pengguna yang hanya memegang `laporan.rekaman` harus tetap punya jalan menuju halaman ini (periksa pembentuk menu).
- Docs wajib diperbarui: panduan UI, `docs/deployment-guide.md` bagian langkah seeder izin baru (`AccessSeeder` harus dijalankan di UAT dan produksi).

## Keputusan saya (mohon dikoreksi bila salah)

1. Perkiraan tanggal terbit = tanggal ulang tahun ke-17 ditambah satu hari, mengikuti aturan `directKtp` (lebih dari 17 tahun, bukan tepat 17).
2. Pengguna tanpa izin NIK penuh tetap bisa mengunduh Excel, dengan NIK termasking, bukan tanpa kolom NIK.
3. Pencarian hanya nama dan NISN (sebagian), tidak NIK, selaras dengan keputusan lama bahwa NIK hanya cocok persis supaya masker tidak bisa dibongkar.
4. Urutan bawaan tabel: kelompok, lalu nama. Excel: sekolah, lalu nama.
5. Pengguna terikat sekolah boleh memegang kedua izin baru karena datanya otomatis dibatasi ke sekolahnya.
6. Nama izin: `laporan.rekaman` dan `laporan.unduh-nik`.

## Diserahkan ke pelaksana

Penamaan kelas, struktur query, rute persis, tata letak di bawah Laporan (tab atau tautan), dan tes. Ikuti rencana yang akan ditulis.
