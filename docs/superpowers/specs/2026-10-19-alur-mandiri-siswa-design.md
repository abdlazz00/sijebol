# SIJEBOL — Alur Mandiri Siswa (Desain)

Sistem Jemput Bola Perekaman KTP-el Pelajar, Kecamatan Sungai Beduk.
Tanggal: 2026-10-19. Status: menunggu review.

Dokumen ini mengubah dan menambah alur di `2026-10-07-sijebol-v1-design.md`. Bagian v1 yang tidak disebut di sini tetap berlaku.

Penanda: **[Putusan]** = keputusan user. **[Pilihan saya]** = keputusan perancang yang belum disetujui eksplisit; user dapat mengubahnya.

## 1. Maksud dan alasan

Di v1, operator sekolah yang menginput siswa dan dokumen, membuat pendaftaran massal, lalu admin kecamatan menjadwalkan siswa satu per satu dan menandai kehadiran dengan ceklis. Client meminta alur yang berpusat pada siswa:

1. Siswa membuat akun SIJEBOL sendiri, melengkapi data, dan mengunggah dokumen sendiri.
2. Siswa mendaftar ke sesi rekaman sendiri.
3. Admin kecamatan membuat sesi rekaman per sekolah dengan kuota.
4. Siswa yang disetujui mendapat tiket berisi detail sesi dan QR.
5. Saat sesi berlangsung, admin kecamatan memindai QR tiket; siswa yang terpindai dianggap hadir dan sudah rekaman.
6. Siswa yang sudah berumur 17 tahun lebih 1 hari diberi penanda bahwa KTP-el dapat langsung terbit.

Tujuannya mengurangi beban input operator sekolah dan penjadwalan manual admin, serta membuat kehadiran tercatat tanpa ceklis manual.

## 2. Tujuan, bukan-tujuan, anti-tujuan

**Tujuan**
- Siswa dari akun sampai tiket tanpa bantuan developer atau operator.
- Kuota sesi tidak pernah terlampaui, termasuk saat banyak siswa mendaftar bersamaan.
- Pemeriksaan data tetap oleh admin kecamatan, satu keputusan per siswa.
- Hak akses seluruhnya lewat RBAC yang ada; tidak ada hak akses yang ditanam di kode.

**Bukan-tujuan**
- WhatsApp (tetap channel masa depan). Notifikasi hanya email dan lonceng.
- Daftar tunggu cadangan saat kuota penuh: kuota penuh berarti sesi tidak bisa dipilih.
- Fitur pemindahan siswa antar sekolah sebagai alur tersendiri (hanya koreksi sekolah oleh admin, bagian 4.9).
- Pemindaian offline, penyimpanan biometrik, pelacakan penerbitan KTP-el, formulir evaluasi.
- Pemilihan SMTP produksi (di luar spec ini; lihat bagian 11).

**Anti-tujuan (gagal walau tampak berfungsi)**
- Siswa dapat melihat atau mengubah data siswa lain, termasuk satu sekolah.
- QR dapat ditebak, dipakai ulang, atau dipakai di sesi lain.
- Sistem menahan kursi selamanya (kursi bocor) atau menjual kursi melebihi kuota.
- Admin kebanjiran notifikasi per pendaftar.
- Perubahan hak akses dilakukan dengan `if` di kode, bukan lewat role dan permission.

## 3. Aktor dan role

| Role | Setelah perubahan |
|---|---|
| `super-admin` | Tetap, semua permission. |
| `admin-kecamatan` | Kelola sesi, verifikasi, scan QR, laporan, pengguna, sekolah, role; mereset link pendaftaran sekolah. |
| `operator-sekolah` | **[Putusan]** hanya memantau: melihat siswa dan sesi sekolahnya, serta menyalin link pendaftaran sekolahnya. Tidak boleh menambah, mengubah, menghapus, atau mengimpor siswa. **[Pilihan saya]** tidak melihat dokumen KK/akta (data sensitif, tidak diperlukan untuk memantau). |
| `siswa` (baru) | Mengelola data dan dokumen miliknya sendiri, mendaftar sesi, melihat tiket. |

**[Putusan]** Perubahan hak operator dilakukan lewat RBAC yang ada (permission di role), bukan kode khusus. Role `siswa` dibuat sebagai role biasa yang tampil di layar Role & Permission, dan akun yang mendaftar mandiri otomatis mendapatkannya.

Permission baru mengikuti konvensi `modul.aksi` dan didaftarkan di daftar permission di kode (beserta cerminan TypeScript), lalu diberikan ke role lewat UI/seeder. Perlu ada setidaknya: mendaftar/membatalkan sesi sendiri, melihat tiket sendiri, memindai tiket, menyalin link pendaftaran sekolah, dan mereset link. Nama pastinya ditentukan di rencana. **[Pilihan saya]** identifier `jadwal.*` dan `rekaman.*` dipertahankan untuk menghindari churn; hanya label UI yang menjadi "Sesi rekaman".

**Kepemilikan data siswa (aturan di luar RBAC).** Scope sekolah yang ada (`ScopedBySchool`) membatasi per sekolah. Untuk akun siswa itu tidak cukup: siswa harus dibatasi ke datanya sendiri. Ini aturan kepemilikan (Policy dan scope pemilik), bukan permission, karena "milik sendiri" tidak dapat dinyatakan sebagai izin. Berlaku untuk data siswa, dokumen, pendaftaran sesi, dan tiket. Data milik siswa lain mengembalikan 404.

Aturan v1 tetap: role terikat sekolah tidak boleh memegang `log.lihat` dan `laporan.lihat`.

## 4. Alur

```
Admin buat sesi (sekolah, tanggal, jam, lokasi, kuota)
Admin/Operator salin link pendaftaran sekolah  ->  dibagikan ke siswa
   |
Siswa buka link -> buat akun (nama, email, password) -> verifikasi email
   |
Siswa lengkapi data (NIK, NISN, tgl lahir, jenis kelamin) + unggah KK & akta
   |
Siswa pilih sesi sekolahnya  -> "Menunggu verifikasi" (kursi ditahan)
   |
Admin periksa, satu keputusan per siswa:
   |- Setujui          -> "Terdaftar", tiket + QR terbit
   |- Minta perbaikan  -> siswa ganti data/dokumen, ajukan ulang (kursi tetap ditahan sampai batas waktu)
   |- Tolak            -> kursi dilepas
   |
Hari H: admin buka halaman scan sesi, pindai QR -> "Sudah rekaman"
Sesi selesai: yang belum terpindai -> "Tidak hadir"
```

### 4.1 Link pendaftaran per sekolah
- **[Putusan]** Setiap sekolah punya satu link pendaftaran, dibuat otomatis oleh sistem. Operator hanya menyalin dan membagikan. Siswa yang mendaftar lewat link itu otomatis tercatat di sekolah tersebut (tanpa memilih sekolah).
- **[Putusan]** Tidak ada halaman daftar umum tanpa link. Pemeriksaan admin saat verifikasi adalah pintu terakhir kalau siswa jelas bukan dari sekolah itu.
- Admin dapat mereset link (token baru, token lama tidak berlaku). Akun siswa yang sudah ada tidak terpengaruh.
- Token acak panjang, hanya hash yang dipakai untuk pencarian bila memungkinkan; rate limit pada rute daftar; token tidak dikenal atau sudah direset menampilkan pesan netral.
- Rute daftar mandiri membalik keputusan v1 yang menghapus registrasi publik; `NoPublicRegistrationTest` disesuaikan: tidak ada registrasi tanpa token sekolah yang sah.

### 4.2 Akun dan kelengkapan data
- Dua langkah: (1) akun lewat link (nama, email, password) dan verifikasi email; (2) data siswa (NIK, NISN, tanggal lahir, jenis kelamin) dan dokumen KK dan akta lahir. **[Pilihan saya]** verifikasi email wajib karena seluruh notifikasi lewat email dan siswa mendaftar sendiri.
- Siswa baru mendaftar sesi hanya bila data dan dokumen lengkap.
- Aturan data tetap seperti v1: NIK 16 digit unik se-kecamatan, NISN 10 digit, format dokumen PDF/JPG/PNG maksimal 5 MB, dokumen di disk privat. NIK dimasker di tampilan dan log.
- Kunci data v1 tetap: data dan dokumen terkunci selama pendaftaran berstatus Menunggu verifikasi, Terdaftar, atau Sudah rekaman; terbuka saat Perlu perbaikan.
- Pesan "NIK sudah terdaftar" tetap trade-off yang disetujui; throttle ditegaskan karena kini ada formulir publik (lewat link).

### 4.3 Sesi rekaman
- Dibuat admin: sekolah, tanggal, jam mulai dan selesai, lokasi, kuota.
- Siswa hanya melihat sesi sekolahnya yang masih terbuka dan belum penuh.
- **Kuota** dihitung dari pendaftaran yang memegang kursi: Menunggu verifikasi, Perlu perbaikan, Terdaftar, Sudah rekaman, dan Belum rekaman. Ditolak, Dibatalkan, dan Kedaluwarsa tidak menghitung. **[Pilihan saya]**
- Pendaftaran ke sesi dilakukan di bawah kunci baris sesi (`lockForUpdate`) agar tidak ada kelebihan kuota saat serentak. Kuota tidak boleh diubah di bawah jumlah kursi yang sedang ditahan.
- **[Pilihan saya]** Satu siswa hanya punya satu pendaftaran aktif (aturan v1, indeks unik `active_student_id`, daftar status aktif diperbarui).
- **[Pilihan saya]** Umur minimal 16 tahun dihitung pada **tanggal sesi** (bukan tanggal daftar), di satu tempat (`EligibilityService`).
- **[Pilihan saya]** Pendaftaran ditutup 2 hari sebelum tanggal sesi; verifikasi admin tetap boleh sampai hari sesi. Nilai awal ini berada di satu tempat konfigurasi.
- Sesi yang sudah memiliki pendaftar tidak dapat dihapus. **[Pilihan saya]** Perubahan pada sesi yang sudah punya pendaftar:
  - Jam dan lokasi boleh diubah; siswa terdaftar diberi notifikasi.
  - Tanggal **tidak** boleh diubah (umur, batas tutup, dan tiket bergantung pada tanggal). Bila tanggal harus berubah, admin membatalkan sesi lalu membuat sesi baru.
  - Kuota tidak boleh diturunkan di bawah jumlah kursi yang sedang ditahan.
  - Admin dapat **membatalkan sesi**: semua pendaftaran yang memegang kursi menjadi Dibatalkan, tiket ditarik, dan siswa diberi notifikasi. Siswa kemudian dapat mendaftar sesi lain.

### 4.4 Verifikasi
- Memakai ulang antrean verifikasi dan keputusan per siswa dari v1: Setujui, Minta perbaikan (catatan wajib), Tolak (catatan wajib). Setujui massal tetap ada dan tetap memeriksa ulang kelayakan.
- Antrean dapat disaring per sesi dan per sekolah.
- **[Putusan]** Perbaikan menahan kursi sampai batas waktu perbaikan. **[Pilihan saya]** Batas waktu = 2×24 jam sejak diminta, tetapi tidak melewati hari sebelum sesi. Satu tempat konfigurasi. **[Pilihan saya]** "Minta perbaikan" hanya tersedia selama tanggal sesi masih paling cepat besok; pada hari sesi admin hanya dapat Setujui atau Tolak, supaya tidak ada perbaikan yang langsung kedaluwarsa.
- Siswa yang lewat batas perbaikan menjadi Kedaluwarsa dan kursinya dilepas. Pendaftaran yang masih Menunggu verifikasi atau Perlu perbaikan saat sesi berakhir juga menjadi Kedaluwarsa.

### 4.5 Tiket dan QR
- Tiket terbit saat disetujui: detail sesi (sekolah, tanggal, jam, lokasi), nama siswa, dan QR. Siswa membukanya di aplikasi (tombol simpan sebagai gambar).
- QR berisi kode acak panjang yang disimpan pada pendaftaran; tidak memuat NIK atau data pribadi. Kode tidak dapat ditebak dan hanya sah untuk pendaftaran dan sesi itu.
- Tiket tidak dilampirkan di email; email hanya berisi link ke halaman tiket. **[Pilihan saya]**
- Tiket ditarik otomatis (tidak sah) bila pendaftaran dibatalkan, ditolak, atau kedaluwarsa.

### 4.6 Absensi lewat scan
- Admin membuka halaman scan untuk satu sesi (kamera perangkat; membutuhkan HTTPS) dan memindai QR. Siswa langsung menjadi **Sudah rekaman**.
- Penolakan dengan pesan jelas: kode tidak dikenal, tiket sesi lain, tiket tidak berstatus Terdaftar, sudah terpindai (tampilkan waktu scan sebelumnya), di luar jendela sesi. **[Pilihan saya]** Jendela sesi = tanggal sesi, mulai dari jam mulai dikurangi toleransi hingga jam selesai ditambah toleransi (nilai awal di satu tempat konfigurasi).
- Ceklis manual v1 dipertahankan sebagai cadangan (HP mati, tiket hilang), termasuk status Tidak hadir dan Belum rekaman dan pembatalan penandaan. **[Pilihan saya]**
- Setelah sesi berakhir, pendaftaran Terdaftar yang belum terpindai otomatis menjadi **Tidak hadir** lewat scheduled command. Siswa Tidak hadir dapat mendaftar sesi lain (aturan v1).
- Setiap scan dan perubahan status masuk riwayat status dan audit log.

### 4.7 Pembatalan
**[Pilihan saya]**
- Siswa dapat membatalkan pendaftarannya sendiri sampai hari sebelum sesi (bukan pada hari sesi); kursi dilepas dan tiket ditarik.
- Admin kecamatan dapat membatalkan pendaftaran siswa yang berstatus Menunggu verifikasi, Perlu perbaikan, atau Terdaftar, dengan catatan wajib dan notifikasi ke siswa. Pada hari sesi, pendaftaran yang ingin ditiadakan admin ditangani lewat penandaan manual, bukan pembatalan.

### 4.8 Penanda KTP-el langsung (poin 6)
- **[Putusan]** Hanya label informasi; tidak mengubah alur atau status.
- Tafsir "17 tahun lebih 1 hari" **[Pilihan saya]**: label muncul bila tanggal acuan paling cepat sehari setelah ulang tahun ke-17 (tanggal acuan > tanggal lahir + 17 tahun). Tanggal acuan adalah **tanggal sesi** pendaftaran siswa itu; bila siswa belum punya pendaftaran, tanggal hari ini.
- Tampil di data siswa, antrean verifikasi, layar scan, dan tiket. Dihitung di satu tempat (service umur), bukan disimpan.

### 4.9 Koreksi oleh admin
- **[Putusan]** Alur utama tetap siswa mengubah datanya sendiri. Admin kecamatan hanya jalur koreksi untuk kasus yang buntu (salah ketik NIK, salah sekolah, data terkunci).
- Admin boleh mengubah data siswa (termasuk NIK dan sekolah) dan membuka kunci data. **[Pilihan saya]** Catatan alasan wajib, tercatat di audit log (nilai sebelum dan sesudah, NIK dimasker), dan siswa diberi notifikasi.
- Semuanya lewat permission baru di RBAC (terpisah dari permission siswa), sehingga dapat dicabut atau dibatasi dari UI. Permission itu tidak diberikan ke operator sekolah atau siswa.
- Mengganti sekolah membatalkan pendaftaran siswa yang masih memegang kursi di sesi sekolah lama (kursi dilepas, tiket ditarik, siswa diberi tahu), karena sesi hanya berlaku untuk sekolahnya.
- Menjaga aturan v1: perubahan NIK tetap tunduk pada keunikan se-kecamatan.

## 5. Notifikasi

Channel email dan lonceng (sistem Notification dan queue yang ada). **[Putusan]**

| Kejadian | Penerima | Isi | Tujuan klik |
|---|---|---|---|
| Disetujui | Siswa | Detail sesi, ajakan membawa tiket | Halaman tiket |
| Perlu perbaikan | Siswa | Catatan admin, batas waktu perbaikan | Form perbaikan |
| Ditolak | Siswa | Alasan, kursi dilepas | Halaman status |
| Kursi dilepas karena lewat batas | Siswa | Penjelasan | Halaman status |
| Sesi diubah | Siswa terdaftar | Detail baru | Halaman tiket |
| Pengingat | Siswa | H-1 batas perbaikan; H-1 sesi | Perbaikan / tiket |

- Admin tidak menerima notifikasi per pendaftar; cukup hitungan "menunggu verifikasi" di antrean dan beranda. **[Pilihan saya]**
- Penerima ditentukan lewat penerima langsung (siswa) atau permission (staf), mengikuti pola `NotificationService` yang ada. Operator sekolah tidak lagi menerima notifikasi keputusan.
- Pengingat dan pelepasan kursi berjalan lewat scheduled command (cron sudah ada di infrastruktur).

## 6. State machine pendaftaran sesi (per siswa)

```
Menunggu verifikasi -> Terdaftar | Perlu perbaikan | Ditolak | Dibatalkan | Kedaluwarsa
Perlu perbaikan     -> Menunggu verifikasi (ajukan ulang) | Dibatalkan | Kedaluwarsa
Terdaftar           -> Sudah rekaman | Tidak hadir | Belum rekaman | Dibatalkan | Kedaluwarsa*
Sudah rekaman / Tidak hadir / Belum rekaman -> Terdaftar (batalkan penandaan, aturan v1)
```

\* Terdaftar yang sesinya lewat tanpa scan menjadi Tidak hadir, bukan Kedaluwarsa; Kedaluwarsa hanya untuk yang tidak pernah disetujui. Status Draf dan Terjadwal v1 dihapus (sesi sudah dipilih saat mendaftar).

Semua perubahan lewat `StatusTransitioner` dengan riwayat status; aktor otomatis (scheduled command) dicatat sebagai sistem.

## 7. Dampak ke kode yang ada

Arah yang saya sarankan; builder boleh mengubah bentuk selama perilaku di atas terpenuhi.

- **Dipertahankan dan diperluas:** `StatusTransitioner`, riwayat status, `EligibilityService`, antrean verifikasi, penguncian data, alur aktivasi/login, dokumen privat, notifikasi, audit log, RBAC.
- **Diubah:** `schedules` menjadi sesi (jam mulai/selesai, kuota); `registration_students` menjadi pendaftaran sesi per siswa (sesi wajib terisi, kode tiket, waktu scan, batas perbaikan, enum status baru) dan indeks unik `active_student_id` mengikuti status aktif baru; `students` terhubung ke akun user; `schools` mendapat token link pendaftaran; role dan seeder; menu; beranda dan laporan (KPI, grafik, dan ekspor yang bergantung pada status lama). **[Pilihan saya]** Struktur beranda dan rekap yang ada dipertahankan dan dipetakan ke status baru; ditambah kehadiran per sesi dan per sekolah, kuota terisi, dan jumlah menunggu verifikasi. Metrik yang bergantung pada Draf, Terjadwal, atau pendaftaran batch dibuang. Rinciannya ditetapkan di tahap 4 rencana.
- **Dihapus:** pendaftaran batch dan halamannya (`Pendaftaran/*`, `Registration`), pemilih kandidat siswa, impor siswa Excel dan antreannya, penempatan/pindah/keluarkan siswa di jadwal oleh admin, tambah/ubah/hapus siswa oleh operator, notifikasi keputusan ke operator.
- **Baru:** registrasi lewat link sekolah, halaman siswa (data, dokumen, pilih sesi, status, tiket), halaman scan, scheduled command (pelepasan kursi, auto Tidak hadir, pengingat), penanda KTP-el langsung.
- **Pembersihan data (satu kali):** **[Putusan]** data siswa saat ini dibersihkan dan mulai dari kosong: siswa, dokumen (termasuk berkas di disk), pendaftaran, entri, dan riwayatnya. **[Pilihan saya]** jadwal lama ikut dibersihkan karena tidak punya jam dan kuota. Akun admin dan operator tetap. Dijalankan eksplisit dengan konfirmasi (bukan di seeder); rilis belum terjadi sehingga tidak ada data produksi.

## 8. Keamanan dan privasi
- Kepemilikan data siswa berlaku di setiap rute siswa (bagian 3), dengan tes yang membuktikan siswa A tidak dapat membaca atau mengubah data, dokumen, atau tiket siswa B (termasuk satu sekolah).
- QR: kode acak, bukan data pribadi; pencarian kode dibatasi rate limit.
- Semua aksi tulis baru memakai `throttle`; rute daftar publik dibatasi lebih ketat.
- Role siswa tidak boleh memegang permission staf; anti-eskalasi v1 tetap berlaku.
- Dokumen tetap tidak punya URL publik; unduh dan lihat tercatat di audit log.

## 9. Pengujian (Pest)
- Registrasi lewat token sah, kedaluwarsa/di-reset, dan tanpa token.
- Kuota: pendaftaran serentak tidak melebihi kuota; pelepasan kursi saat ditolak, dibatalkan, kedaluwarsa.
- State machine: transisi sah dan ilegal, perbaikan dan batas waktu.
- Kepemilikan data antar siswa; permission matrix route diperbarui (`RoutePermissionMatrixTest`, `RouteGuardAuditTest`).
- Tiket: terbit hanya saat Terdaftar, ditarik saat batal.
- Scan: sukses, kode tak dikenal, sesi lain, scan ganda, di luar jendela, status tidak sah.
- Label KTP-el langsung: sehari sebelum, tepat pada ulang tahun ke-17, sehari sesudah.
- Notifikasi ke siswa; admin tidak menerima notifikasi per pendaftar.
- Scheduled command: auto Tidak hadir, pelepasan kursi, pengingat.
- End-to-end: link -> akun -> data -> daftar sesi -> setujui -> tiket -> scan.

## 10. Tahapan rencana
Rencana pelaksanaan dipecah (via `writing-plans`), urutan awal:
1. Akun siswa, link sekolah, kepemilikan data, role/permission, pembersihan data.
2. Sesi dengan kuota dan pendaftaran siswa ke sesi.
3. Verifikasi (perbaikan dan batas waktu), tiket, notifikasi, scheduled command.
4. Scan QR, ceklis manual cadangan, label KTP-el langsung, beranda dan laporan.

## 11. Asumsi dan hal tertunda
- Email: Resend free tier hanya untuk pengembangan/UAT. Pengirim `onboarding@resend.dev` hanya dapat mengirim ke pemilik akun, dan kuota gratis 100 email/hari, 3.000/bulan. Sebelum rilis perlu domain terverifikasi dengan paket yang cukup atau SMTP instansi; kode tidak berubah, hanya konfigurasi. Dicatat sebagai syarat rilis.
- Siswa mendaftar ke sekolah yang salah: admin mengoreksi sekolahnya lewat jalur koreksi (bagian 4.9). Fitur pemindahan siswa antar sekolah sebagai alur tersendiri tetap di luar lingkup. Siswa menghubungi admin di luar sistem.
- Jumlah pelajar sasaran dan beban pendaftaran hari pertama belum diketahui; memengaruhi kuota email dan batas rate limit.
- Kamera pemindai membutuhkan HTTPS pada produksi (sudah menjadi bagian konfigurasi rilis).
