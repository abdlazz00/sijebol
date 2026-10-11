# SIJEBOL — Panduan UI (v1: Autentikasi sampai User Management)

Tanggal: 2026-10-07. Status: menunggu review. Untuk dipakai agent Figma sebagai acuan UI/UX.
Terkait: `2026-10-07-sijebol-v1-design.md` (spec sistem).

## 1. Cakupan dan arah

**Dalam panduan ini:** layout shell, autentikasi (login, aktivasi akun, lupa dan reset password), beranda, pengguna, role & permission, sekolah, notifikasi, profil, halaman error.
**Belum dicakup (putaran berikutnya):** siswa, dokumen, pendaftaran, verifikasi, jadwal, penandaan rekaman, laporan, log aktivitas.

**Karakter visual:** administratif, bersih, resmi, seperti layanan pemerintah. Canvas abu-putih; biru hanya untuk sidebar dan aksen. Tanpa gradien, tanpa mode gelap, tanpa ilustrasi berlebihan. Bahasa antarmuka: Indonesia.

## 2. Design token

### Warna

| Token | Hex | Pakai untuk |
|---|---|---|
| `canvas` | `#F4F6F9` | Latar halaman |
| `surface` | `#FFFFFF` | Kartu, tabel, form, modal, dropdown |
| `surface-subtle` | `#F8FAFC` | Header tabel, area sekunder |
| `border` | `#E2E8F0` | Garis kartu, tabel, input |
| `text` | `#0F172A` | Teks utama |
| `text-muted` | `#5B6B80` | Label, teks bantu, metadata |
| `sidebar` | `#0B2A5B` | Latar sidebar dan panel kiri halaman auth |
| `sidebar-hover` | `#14397A` | Hover item sidebar |
| `sidebar-text` | `#FFFFFF` | Teks di atas sidebar |
| `sidebar-text-muted` | `#B6C4DD` | Judul grup, ikon tidak aktif |
| `primary` | `#1D4ED8` | Tombol utama, link, fokus, item aktif |
| `primary-hover` | `#1E40AF` | Hover tombol utama |
| `primary-soft` | `#E8EFFD` | Hover baris tabel, badge info, latar item terpilih |
| `success` / `success-soft` | `#15803D` / `#DCFCE7` | Status aktif, sukses |
| `warning` / `warning-soft` | `#B45309` / `#FEF3C7` | Menunggu, peringatan |
| `danger` / `danger-soft` | `#B91C1C` / `#FEE2E2` | Error, kedaluwarsa, destruktif |
| `neutral-soft` | `#E2E8F0` | Badge nonaktif |

Aturan: biru hanya di sidebar, tombol primer, link, fokus, dan item aktif. Area konten tetap putih dan abu. Rasio kontras teks minimal 4.5:1 (teks di sidebar di atas 7:1). Cincin fokus: 2 px `primary` dengan offset 2 px.

### Tipografi (Inter, fallback sistem)

| Peran | Ukuran/tinggi baris | Berat |
|---|---|---|
| Judul halaman | 24/32 | 600 |
| Judul kartu | 16/24 | 600 |
| Isi | 14/20 | 400 |
| Label dan tombol | 14/20 | 500 |
| Caption, teks bantu | 12/16 | 400 |
| Angka ringkasan | 28/36 | 600 |

Angka di tabel memakai tabular figures.

### Bentuk dan ruang
- Grid 4 px. Jarak umum: 8, 12, 16, 24, 32.
- Radius: 8 px (tombol, input, kartu), 12 px (modal), pil penuh untuk badge.
- Kartu: latar `surface`, border 1 px `border`, tanpa bayangan. Bayangan tipis hanya untuk modal dan dropdown.
- Ikon: Lucide (garis tipis); 20 px di sidebar, 16 px di tombol dan tabel.

## 3. Komponen

| Komponen | Spesifikasi |
|---|---|
| Tombol primer | Tinggi 36 (40 di form besar), latar `primary`, teks putih; hover `primary-hover`; disabled opacity 50% |
| Tombol sekunder | Putih, border `border`, teks `text` |
| Tombol ghost | Tanpa latar, teks `text-muted` (hover `primary-soft`) |
| Tombol destruktif | Latar `danger`, teks putih |
| Input teks | Tinggi 40, radius 8, border `border`, fokus border `primary` dan cincin fokus; label 14/500 di atas, teks bantu 12 di bawah, error 12 `danger` dengan ikon |
| Input password | Input teks + tombol tampil/sembunyi di kanan |
| Dropdown pilih | Seperti input; dengan kotak cari untuk daftar panjang (sekolah) |
| Checkbox | 16 px, radius 4; centang `primary` |
| Badge status | Pil, tinggi 24, teks 12/500, titik berwarna 6 px di kiri; varian: Aktif (success), Menunggu aktivasi (warning), Kedaluwarsa (danger), Nonaktif (neutral) |
| Tabel | Header `surface-subtle` teks 12/500 `text-muted` huruf kapital kecil; baris 48 px; garis pemisah `border`; hover baris `primary-soft`; kolom aksi rata kanan; header sticky |
| Kartu angka | Label 12 `text-muted` di atas, angka 28/36, ikon 20 di kanan atas dalam lingkaran `primary-soft` |
| Toast | Kanan atas, lebar 360, ikon status, hilang 4 detik, tombol tutup |
| Banner | Lebar penuh form, latar `*-soft`, border 1 px warna status, ikon dan teks |
| Dialog konfirmasi | Radius 12, judul, deskripsi menyebut nama objek, tombol Batal (sekunder) dan aksi (destruktif untuk aksi merusak) |
| Empty state | Ikon 40 px `text-muted`, satu kalimat, satu tombol aksi (jika berizin) |
| Skeleton | Blok `neutral-soft` dengan animasi denyut halus, bentuk mengikuti konten |
| Avatar inisial | Lingkaran 32/40/64, latar `primary-soft`, teks `primary` dua huruf |
| Breadcrumb | 12/16 `text-muted`, segmen terakhir `text` |
| Paginasi | Ringkasan "Menampilkan 1–20 dari 134" di kiri; tombol sebelumnya/berikutnya dan nomor di kanan |

## 4. Layout shell (semua halaman setelah login)

- **Sidebar** 264 px, latar `sidebar`. Atas (tinggi 64): logo SIJEBOL dan "Kec. Sungai Beduk". Tengah: menu dikelompokkan dalam 4 kategori ("Utama", "Pelayanan", "Laporan", "Sistem & Akses") dengan judul grup 11/16 huruf kapital `sidebar-text-muted`. Item: tinggi 40, ikon 20 + teks 14/500, radius 8. Aktif: latar `primary`, teks putih. Hover: `sidebar-hover`. Dapat dilipat ke 72 px (ikon saja, tooltip nama). Area navigasi vertikal menggunakan seamless invisible scrollbar tanpa bilah native abu-abu. Item tanpa izin tidak ditampilkan.
- **Topbar** 64 px, putih (latar card), sticky di bagian atas (tidak ikut tergulung saat konten di-scroll), z-index aman di bawah modal (z-20), garis bawah `border`. Kiri: tombol lipat sidebar (mobile: hamburger) dan breadcrumb. Kanan: lonceng notifikasi (badge angka merah) dan menu pengguna (avatar, nama, role; berisi Profil dan Keluar).
- **Konten:** latar `canvas`, padding 24 (mobile: 16), lebar penuh rata-kiri (fluid) mengikuti sidebar tanpa terpusat, dengan batas maksimal longgar 1600 px pada monitor lebar. Halaman formulir input tetap dibatasi lebar maks 720 px agar ergonomis diisi, sedangkan tabel data, matriks permission, dan kartu metrik memanfaatkan lebar area secara optimal. Header halaman: judul (24/32) dan sub-judul opsional di kiri, tombol aksi utama di kanan.
- **Mobile (<768 px):** sidebar menjadi drawer dari kiri dengan overlay; padding konten 16; tabel menjadi scroll horizontal; tombol aksi utama penuh lebar di bawah judul.
- **Kelompok menu:** Utama (Beranda), Pelayanan (Siswa, Pendaftaran, Antrean verifikasi, Jadwal), Laporan (Rekap pendaftaran), Sistem & Akses (Sekolah, Pengguna, Role & Permission, Log Aktivitas). Item disaring otomatis menurut permission pengguna; grup kosong dibuang.

## 5. Halaman

### 5.1 Pola halaman autentikasi
Layout split screen. Panel kiri (45% lebar, latar `sidebar`): logo, "SIJEBOL", "Sistem Jemput Bola Perekaman KTP-El Pelajar", "Kecamatan Sungai Beduk", tagline pendek di bagian bawah. Panel kanan putih: form di tengah vertikal dan horizontal, lebar maks 400. Mobile: panel kiri menjadi header biru tipis (logo saja), form di bawahnya.

### 5.2 Login `/login`
- Judul "Masuk", sub-judul "Gunakan akun yang diberikan admin kecamatan."
- Field: Email, Password (tampil/sembunyi). Checkbox "Ingat saya". Tautan "Lupa password?" rata kanan di atas atau bawah field password. Tombol **Masuk** lebar penuh.
- State: kredensial salah (banner danger "Email atau password salah."); terlalu banyak percobaan (banner warning "Terlalu banyak percobaan. Coba lagi dalam {n} detik.", tombol disabled); akun nonaktif (banner danger "Akun Anda dinonaktifkan. Hubungi admin kecamatan."); loading (spinner di tombol).
- Tanpa tautan daftar.

### 5.3 Aktivasi akun `/aktivasi/{token}`
- Judul "Aktifkan akun Anda", sapaan "Halo, {nama}. Buat password untuk mengaktifkan akun." Email (baca saja, terkunci) dan sekolah (untuk operator) ditampilkan di kartu info kecil.
- Field: Password baru, Konfirmasi password. Daftar aturan dengan centang hijau: minimal 8 karakter, mengandung huruf, mengandung angka. Indikator kekuatan (3 segmen).
- Tombol **Aktifkan akun**. Sukses: masuk ke beranda dan toast "Akun aktif. Selamat datang."
- State token tidak berlaku: ikon jam, judul "Link aktivasi tidak berlaku", satu kalimat penyebab (kedaluwarsa atau sudah dipakai), "Hubungi admin kecamatan untuk meminta link baru." Tanpa form.

### 5.4 Lupa password `/lupa-password`
- Judul "Lupa password?", sub-judul satu kalimat. Field Email, tombol **Kirim link reset**, tautan "Kembali ke login".
- Setelah kirim: ikon amplop, "Periksa email Anda", "Jika email terdaftar, link reset password sudah dikirim." (sama untuk email tak terdaftar). Tombol "Kembali ke login".

### 5.5 Reset password `/reset-password/{token}`
- Seperti form aktivasi (password baru, konfirmasi, aturan, indikator), tombol **Simpan password**. Token tidak valid memakai state seperti 5.3 dengan tautan ke "Lupa password".

### 5.6 Beranda `/`
- Header: "Selamat datang, {nama}"; sub-judul role (dan nama sekolah untuk operator).
- Blok tampil sesuai permission, urutan tetap; blok tanpa izin tidak menyisakan ruang kosong:
  1. **Kartu ringkasan** (3–4 kartu angka dalam satu baris; 2 kolom di mobile). Data nyata sejak Fase 6a: staf kecamatan melihat Menunggu verifikasi, Terjadwal, dan Sudah rekaman (se-kecamatan); operator sekolah melihat Draf, Perlu perbaikan, dan Sudah rekaman (sekolahnya). Tiap kartu butuh permission modulnya.
  2. **Perlu tindakan**: kartu daftar pendek, tiap baris berupa tautan (contoh: "12 entri menunggu verifikasi"). Data nyata sejak Fase 6a; aksi dengan hitungan 0 tidak ditampilkan.
  3. **Jadwal terdekat**: kartu dengan tanggal besar di kiri, lalu sekolah dan lokasi; berisi maksimal 3 jadwal hari ini atau setelahnya, dibatasi sekolah pengguna (data nyata sejak Fase 5; hanya untuk pemegang `jadwal.lihat`).
  4. **Notifikasi terbaru**: 5 item dengan tautan "Lihat semua".
- Empty state per blok (satu kalimat). Modul pemicu blok belum dirancang; gambar dengan data contoh dan beri catatan permission pemicu di anotasi.

### 5.7 Pengguna: daftar `/pengguna` (`user.lihat`)
- Header: judul "Pengguna"; tombol **+ Tambah pengguna** (`user.buat`).
- Baris filter di atas tabel: kotak cari (nama/email), filter Role, Sekolah, Status.
- Kolom tabel: Pengguna (avatar inisial, nama tebal, email kecil), Role, Sekolah (strip untuk staf kecamatan), Status (badge), Login terakhir (tanggal relatif), Aksi (menu tiga titik).
- Menu aksi: Lihat detail, Ubah, Kirim ulang aktivasi (hanya untuk Menunggu aktivasi/Kedaluwarsa), Nonaktifkan atau Aktifkan. Tidak ada hapus.
- State: loading (skeleton 8 baris), kosong ("Belum ada pengguna" + tombol tambah), hasil filter kosong ("Tidak ada hasil" + tombol reset filter).
- Paginasi 20 per halaman.

### 5.8 Pengguna: tambah/ubah `/pengguna/buat`, `/pengguna/{id}/ubah`
- Breadcrumb "Pengguna > Tambah pengguna" (atau "Ubah pengguna"). Satu kartu "Data akun".
- Field: Nama lengkap (wajib), Email (wajib), Nomor HP (opsional, format 08xx), Role (dropdown, wajib), Sekolah (dropdown cari-ketik; tampil dan wajib hanya jika role bersifat operator sekolah).
- Tombol **Batal** (sekunder) dan **Simpan** (primer) rata kanan di bawah kartu.
- Ubah: Email terkunci untuk akun yang sudah aktif, dengan teks bantu "Email tidak dapat diubah setelah akun aktif."
- Error validasi per field (merah di bawah field), loading di tombol Simpan.

### 5.9 Pengguna: layar sukses (setelah tambah atau kirim ulang aktivasi)
- Breadcrumb "Pengguna > Tambah pengguna > Berhasil". Kartu sukses dengan ikon centang hijau, judul "Akun dibuat untuk {nama}", sub-judul "{role} · {sekolah}".
- Kolom link (baca saja, font monospace kecil) dengan tombol **Salin link**; setelah klik tombol berubah menjadi "Tersalin" 2 detik.
- Tombol **Kirim via WhatsApp** (sekunder, ikon; nonaktif dengan tooltip jika tidak ada nomor HP): membuka chat dengan pesan siap kirim berisi link.
- Teks catatan: "Berlaku 3 hari. Link tidak dapat ditampilkan lagi setelah halaman ini ditutup."
- Tombol **Kembali ke daftar**. Untuk kirim ulang, judul "Link aktivasi baru dibuat" dengan komposisi yang sama.

### 5.10 Pengguna: detail `/pengguna/{id}` (`user.lihat`)
- Header kartu: avatar 64, nama, email, badge status; di kanan tombol Ubah dan menu aksi (sama dengan 5.7).
- Kartu **Informasi akun**: Role, Sekolah, Nomor HP, Dibuat oleh, Tanggal dibuat, Login terakhir (daftar label-nilai dua kolom).
- Kartu **Aktivitas terbaru** (hanya jika pemirsa punya `log.lihat`): 10 baris (waktu, aksi, ringkasan), tautan "Lihat semua".

### 5.11 Role & permission: daftar `/role` (`role.lihat`)
- Header "Role & Permission"; tombol **+ Tambah role** (`role.buat`).
- Tabel: Nama role, Deskripsi, Jumlah pengguna, Jumlah permission, Aksi (Ubah, Hapus). `super-admin` memiliki ikon gembok, tanpa aksi.

### 5.12 Role & permission: tambah/ubah `/role/buat`, `/role/{id}/ubah`
- Kartu atas: Nama role (wajib), Deskripsi.
- **Matriks permission** (kartu terpisah): baris = modul (User, Role, Sekolah, Siswa, Dokumen, Pendaftaran, Verifikasi, Jadwal, Rekaman, Laporan, Log); kolom = Lihat, Buat, Ubah, Hapus, Lainnya. Cell yang tidak berlaku diisi tanda strip abu. Kolom Lainnya berisi checkbox dengan label teks kecil per aksi (contoh: "kirim ulang aktivasi", "impor", "putuskan"). Checkbox "Pilih semua" di awal tiap baris. Header tabel sticky.
- Bar aksi sticky di bawah layar: Batal dan **Simpan**, serta penanda "Ada perubahan belum disimpan".
- Checkbox untuk permission yang tidak dimiliki penyunting dinonaktifkan dengan tooltip "Anda tidak memiliki izin ini".
- Hapus role: dialog konfirmasi; jika masih ada pengguna, tampil pesan "Role masih dipakai {n} pengguna" dan tombol hapus nonaktif.

### 5.13 Sekolah `/sekolah` (`sekolah.lihat`)
- Daftar: Nama sekolah, NPSN, Jenjang (SMA/SMK/MA), Jumlah operator, Aksi (Ubah). Tombol **+ Tambah sekolah**. Filter cari dan jenjang.
- Form tambah/ubah (halaman terpisah, pola 5.8): Nama sekolah, NPSN, Jenjang, Alamat (opsional).

### 5.14 Notifikasi
- **Dropdown lonceng** (lebar ±380): header "Notifikasi" dan tautan **Tandai semua dibaca**; daftar maks 8 item terbaru; footer tautan **Lihat semua**.
- **Item notifikasi** (dropdown dan halaman): titik biru 8 px untuk belum dibaca (item belum dibaca juga berjudul tebal dan berlatar `primary-soft` tipis), judul satu baris, deskripsi satu baris, waktu relatif. Tombol ikon centang **tandai dibaca** per item: muncul saat hover di desktop, selalu tampil di mobile; item sudah dibaca tidak memiliki tombol itu. Klik badan item membuka halaman terkait dan menandainya dibaca.
- **Halaman penuh** `/notifikasi`: header "Notifikasi" dan tombol **Tandai semua dibaca**; segmen **Semua / Belum dibaca**; daftar dengan item yang sama; paginasi. Empty state "Tidak ada notifikasi".

### 5.15 Profil `/profil`
- Kartu **Informasi profil**: Nama dan Nomor HP dapat diubah; Email dan Role hanya baca. Tombol Simpan.
- Kartu **Ubah password**: Password saat ini, Password baru, Konfirmasi, aturan password (seperti 5.3), tombol Perbarui password.

### 5.16 Halaman error
- 403 ("Anda tidak memiliki akses ke halaman ini"), 404 ("Halaman tidak ditemukan"), 500 ("Terjadi kesalahan"). Dalam layout shell (jika sudah login) atau pola auth (jika belum): ikon besar sederhana, judul, satu kalimat, tombol "Kembali ke beranda".

### 5.17 Log Aktivitas `/log` (`log.lihat`)
- Judul "Log Aktivitas", deskripsi "Rekam jejak aktivitas di SIJEBOL." Menu berada di grup **Sistem**, hanya untuk pemilik `log.lihat`.
- Filter (pola 5.7): **Pengguna** (semua pengguna yang pernah melakukan aktivitas), **Jenis** (Akses / Data), **Dari tanggal** dan **Sampai tanggal** (input tanggal bawaan). Setiap perubahan langsung menerapkan filter; tombol "Reset filter" muncul bila ada filter aktif.
- Tabel: **Waktu** (kurang dari 24 jam memakai waktu relatif, selebihnya tanggal dan jam "6 Okt 2026 · 08.00"; waktu lengkap sebagai tooltip), **Pengguna** ("Sistem" bila tanpa pelaku), **Aksi** (label bahasa Indonesia), **Subjek** (jenis dan nama; "#id" bila sudah dihapus), **Ringkasan** (hingga dua perubahan "kolom: lama → baru", dipotong), tombol teks **Detail**.
- **Dialog Detail:** judul = aksi; deskripsi = pelaku dan waktu lengkap; subjek; tabel Kolom / Sebelum / Sesudah (nilai kosong "—"; password dan token tidak pernah tampil, NIK dimasker); tombol Tutup. Tanpa perubahan data: "Tidak ada perubahan data."
- State: loading (skeleton baris), kosong ("Belum ada aktivitas"), hasil filter kosong ("Tidak ada hasil" + Reset filter). Mobile: tabel geser horizontal dengan petunjuk "↔ Geser tabel untuk melihat kolom lainnya".
- Log hanya baca: tidak ada tombol ubah atau hapus.

### 5.18 Siswa: daftar, form, detail `/siswa` (`siswa.lihat`)
- **Daftar:** judul "Siswa", deskripsi "Kelola data siswa dan kelengkapan dokumennya." Tombol **+ Tambah siswa** (`siswa.buat`) dan **Impor Excel** (`siswa.impor`). Filter (pola 5.7): cari nama atau NISN (NIK hanya cocok persis bila diketik lengkap), Dokumen (Semua / Lengkap / Belum lengkap), Status (Semua / Eligible), dan Sekolah (hanya staf kecamatan).
- Tabel: **Siswa** (nama, NISN kecil), **NIK** (dimasker, `3201********1234`), **Umur**, **Sekolah** (hanya staf), **Dokumen** (dua indikator berteks "KK" dan "Akta" dengan ikon centang atau silang; tidak mengandalkan warna), **Status** (badge hijau "Eligible" atau abu-abu "Belum eligible" dengan alasan sebagai teks pembaca layar), **Aksi** (menu: Lihat detail, Ubah, Hapus dengan konfirmasi "Data siswa dan dokumennya akan dihapus permanen."). State: loading, kosong ("Belum ada siswa" + tombol tambah dan impor), hasil filter kosong, dan petunjuk geser di mobile.
- **Form tambah/ubah** (halaman terpisah, pola 5.8): NIK (16 digit), NISN (10 digit), Nama lengkap, Tanggal lahir, Jenis kelamin (Laki-laki / Perempuan), dan Sekolah (hanya staf saat tambah). Pada ubah, NIK tampil penuh dan sekolah tidak dapat diubah. Setelah simpan menuju detail agar dokumen dapat diunggah.
- **Detail:** kartu profil (inisial, nama, NISN, badge eligible; bila belum eligible tampil daftar alasannya), Ubah dan menu "…" (Hapus), kartu **Informasi siswa** (NIK dimasker, tanggal lahir dan umur, jenis kelamin, sekolah), dan kartu **Dokumen** (5.19).

### 5.19 Dokumen siswa (komponen unggah)
- Dua kolom dokumen: **Kartu Keluarga** dan **Akta Kelahiran**. Tiap kolom berstatus: *kosong* (area "Pilih berkas atau seret ke sini", catatan "PDF, JPG, atau PNG, maks 5 MB"), *dipilih* (nama, ukuran, tombol Unggah dan Batal), *mengunggah* (progress dengan `role="status"`), *terunggah* (nama berkas, ukuran, tautan Unduh, tombol Ganti dan Hapus dengan konfirmasi), dan *galat* (pesan di bawah area).
- Validasi klien sebelum kirim memakai pesan yang sama dengan server: "Berkas harus PDF, JPG, atau PNG." dan "Ukuran berkas maksimal 5 MB." Tanpa izin unggah tampil "Belum diunggah." atau hanya tautan unduh.

### 5.20 Impor Excel
- Tombol "Impor Excel" membuka dialog: langkah 1 **Unduh template (.xlsx)** dengan catatan "Kolom NIK, NISN, dan Tanggal lahir berformat teks; tanggal diisi YYYY-MM-DD atau DD/MM/YYYY."; langkah 2 pilih berkas .xlsx (maks 2 MB; staf memilih sekolah lebih dulu) lalu **Impor**.
- Impor diproses di latar belakang (queue): setelah **Impor** dialog menampilkan "Impor sedang diproses" dengan spinner dan boleh ditutup; polling tiap 2 detik, dan bila masih antri lebih dari 20 detik tampil petunjuk "pastikan queue worker berjalan". Pembuat menerima notifikasi "Impor siswa selesai" (atau "gagal") dengan tautan `/siswa?impor={id}` yang membuka dialog berisi hasil tersimpan. Berkas unggahan dihapus setelah diproses.
- Hasil di dalam dialog: "N siswa berhasil ditambahkan, M baris gagal." dan bila ada yang gagal tabel **Baris / Alasan** (maks tinggi, scroll), tombol Tutup dan "Impor lagi". Galat berkas (kolom wajib hilang, lebih dari 500 baris, bukan .xlsx) tampil sebagai banner di dialog.

### 5.21 Pendaftaran: daftar dan form `/pendaftaran` (`pendaftaran.lihat`)
- **Daftar:** judul "Pendaftaran", deskripsi "Kelompokkan siswa eligible untuk diajukan ke verifikasi." Tombol **+ Buat pendaftaran** (`pendaftaran.buat`). Filter (pola 5.7): cari judul, Status (Semua / Draf / Diajukan / Perlu perbaikan / Terverifikasi / Ditolak), Sekolah (hanya staf). Tabel: **Pendaftaran** (judul, tanggal dibuat), **Sekolah** (hanya staf), **Peserta** (jumlah), **Ringkasan status** (daftar berteks mis. "Diajukan 12 · Terverifikasi 3"), **Aksi** (Lihat; Ubah dan Hapus hanya untuk draf). Hapus draf memakai konfirmasi "Draf dan daftar siswanya akan dihapus." State: loading, kosong ("Belum ada pendaftaran" + tombol buat), hasil filter kosong.
- **Form** (`/pendaftaran/buat`, `/pendaftaran/{id}/ubah`, pola 5.8): kartu **Detail pendaftaran** (Judul wajib, Perkiraan jumlah peserta opsional, Sekolah hanya staf saat buat; mengganti sekolah memuat ulang daftar siswa) dan kartu **Pilih siswa** (5.22). Bar aksi bawah: Batal dan **Simpan draf** (menuju detail, tempat **Ajukan** berada).

### 5.22 Pemilih siswa (`StudentMultiSelect`)
- Daftar dipaginasi 20 per halaman dan dicari di server (cari nama atau NISN dengan debounce; tombol **Sebelumnya**/**Berikutnya** dan "Halaman X dari Y · N siswa"). Pilihan lintas halaman dan hasil pencarian tampil sebagai daftar **Terpilih (N)** berupa chip dengan tombol hapus per siswa. Kolom cari, tombol **Pilih semua yang eligible di halaman ini**, dan penghitung "N dipilih" (`role="status"`, diumumkan). Tiap baris: kotak centang, nama, NISN dan umur. Baris belum eligible dinonaktifkan dan menampilkan alasannya sebagai teks (bukan hanya warna), disertai tautan "Lengkapi dokumen" ke detail siswa bila dokumen kurang. Siswa yang sudah terpilih tetapi menjadi tidak eligible tetap dapat dibatalkan pilihannya. Galat per siswa (dari server) tampil di bawah barisnya. Dapat dioperasikan dengan keyboard; mobile: baris menumpuk.

### 5.23 Pendaftaran: detail `/pendaftaran/{id}`
- Header: judul, badge ringkasan, tombol sesuai keadaan. **Draf:** Ubah, **Ajukan** (dialog "Ajukan N siswa untuk diverifikasi? Setelah diajukan pendaftaran tidak dapat diubah.") dan menu Hapus. Kartu **Informasi** (sekolah, dibuat oleh, perkiraan peserta, waktu diajukan). Tabel entri: **Siswa**, **Status** (badge berteks), **Catatan petugas** (untuk perlu perbaikan/ditolak), **Aksi** (Riwayat membuka dialog dengan timeline; untuk perlu perbaikan: Perbaiki data ke detail siswa dan **Ajukan ulang**). Galat pengajuan tampil sebagai banner dan alasan per siswa di baris terkait.
- **Timeline riwayat:** daftar vertikal berpenanda titik: "Status lama → baru", lalu waktu · pelaku, lalu catatan bila ada.

### 5.24 Verifikasi: antrean dan keputusan `/verifikasi` (`verifikasi.lihat`)
- **Antrean:** filter cari nama/NISN dan Sekolah (staf). Tabel **Siswa** (nama, NISN), **Sekolah**, **Pendaftaran**, **Diajukan** (waktu relatif), **Aksi** (tombol "Periksa"). Bagi pemegang `verifikasi.putuskan`: kolom ceklis **Pilih**, kotak "Pilih semua di halaman ini", penghitung `role="status"`, dan tombol **Setujui terpilih** (konfirmasi "Setujui N siswa? Kelayakan setiap siswa diperiksa ulang. Bila ada yang tidak layak, tidak ada yang disetujui."; maksimal 50, atomik, galat di atas tabel; pilihan dikosongkan saat halaman atau filter berubah; kolom Pilih tidak tampil saat antrean kosong). Operator menerima satu notifikasi per pendaftaran. State: loading, kosong ("Tidak ada antrean verifikasi"), hasil filter kosong.
- **Keputusan** (`/verifikasi/{id}`): kartu **Data siswa** (NIK dimasker, tanggal lahir dan umur, jenis kelamin, sekolah, pendaftaran), kartu **Dokumen** (tautan unduh KK dan akta), banner eligibility bila belum memenuhi syarat, kartu **Riwayat**, dan kartu **Keputusan** (hanya `verifikasi.putuskan` dan status Diajukan): pilihan radio berteks **Setujui / Minta perbaikan / Tolak** dengan keterangan singkat, kolom Catatan (wajib untuk dua yang terakhir), tombol **Simpan keputusan** (Tolak meminta konfirmasi). Setelah simpan kembali ke antrean dengan toast. Entri yang sudah diputuskan menampilkan keputusan terakhir tanpa formulir.

### 5.25 Jadwal: daftar dan form `/jadwal` (`jadwal.lihat`)
- **Daftar:** judul "Jadwal", deskripsi "Jadwal perekaman per sekolah." Tombol **+ Buat jadwal** (`jadwal.buat`). Filter (pola 5.7): cari lokasi, Periode (Semua / Akan datang / Sudah lewat), Sekolah (hanya staf). Tabel: **Jadwal** (tanggal dengan hari, lokasi), **Sekolah** (hanya staf), **Peserta** (jumlah), **Ringkasan status** (teks, mis. "Terjadwal 12 · Sudah rekaman 3"), **Aksi** (Lihat; Ubah bila `jadwal.ubah`; Penandaan rekaman bila `rekaman.lihat`). Urutan: yang akan datang dulu (terdekat), lalu yang sudah lewat (terbaru). Jadwal lewat diberi teks "Sudah lewat". State: loading, kosong ("Belum ada jadwal"), hasil filter kosong, petunjuk geser di mobile.
- **Form** (`/jadwal/buat`, `/jadwal/{id}/ubah`, pola 5.8): Sekolah (hanya staf saat buat), Tanggal (input tanggal), Lokasi. Pada ubah dengan peserta tampil catatan "Operator sekolah akan diberi tahu bila tanggal atau lokasi berubah." Bar aksi bawah: Batal dan Simpan.

### 5.26 Jadwal: detail dan penempatan siswa `/jadwal/{id}`
- Header: lokasi, tanggal dengan hari, sekolah, dibuat oleh; tombol **Ubah** (`jadwal.ubah`), **Hapus** (`jadwal.ubah`, hanya jadwal tanpa peserta; konfirmasi destruktif) dan **Penandaan rekaman** (`rekaman.lihat`). Tabel **Peserta**: Siswa (nama, NISN), Status (badge berteks), Aksi **Pindahkan** (dialog "Jadwal tujuan" berisi jadwal sekolah yang sama yang belum lewat; tombol Pindahkan nonaktif sampai dipilih) dan **Keluarkan** untuk status Terjadwal bila `jadwal.ubah` (konfirmasi "Siswa kembali menjadi Terverifikasi dan dapat dijadwalkan ulang."), dengan petunjuk geser di mobile.
- Tombol **Tambah siswa** (`jadwal.ubah`, jadwal belum lewat) membuka dialog: daftar siswa Terverifikasi sekolah itu yang belum dijadwalkan (cari, kotak centang, "Pilih semua", penghitung `role="status"`), tombol Batal dan **Jadwalkan N siswa** (dialog ini sekaligus konfirmasi; operator sekolah diberi tahu). Galat penempatan tampil di dalam dialog. Operator sekolah melihat detail tanpa tombol ubah, tambah, atau keluarkan.

### 5.27 Penandaan rekaman `/jadwal/{id}/rekaman` (`rekaman.lihat`)
- Header jadwal. Sebelum hari kegiatan tampil banner "Penandaan dibuka pada {tanggal}." dan kontrol dinonaktifkan. Daftar peserta Terjadwal memakai ceklis massal (5.26), lalu pilihan status radio berteks **Sudah rekaman / Tidak hadir / Belum rekaman** dengan keterangan singkat, kolom Catatan opsional, dan tombol **Simpan penandaan** (konfirmasi menyebut jumlah dan status; hanya `rekaman.tandai`). Bagian **Sudah ditandai** menampilkan status; pemegang `rekaman.tandai` mendapat tombol **Batalkan** per siswa yang membuka dialog dengan kolom Catatan wajib (tombol "Batalkan penandaan" nonaktif tanpa catatan); siswa kembali Terjadwal pada jadwal yang sama dan operator diberi tahu. Pembatalan ditolak bila siswa sudah punya pendaftaran aktif lain.

### 5.28 Laporan: rekap pendaftaran `/laporan` (`laporan.lihat`)
- Header "Rekap pendaftaran" dengan sub-judul "Jumlah entri per sekolah dan status, menurut tanggal pengajuan pendaftaran." dan tombol **Ekspor Excel** (tautan unduh; mengikuti filter yang sedang diterapkan, bukan isian yang belum disimpan).
- Filter: **Dari tanggal** dan **Sampai tanggal** (input tanggal native, salah satu boleh kosong), tombol **Terapkan**, dan **Reset** (hanya bila ada isian). Galat validasi tampil di bawah field ("Tanggal akhir tidak boleh sebelum tanggal awal.").
- Tabel: kolom Sekolah, delapan status (Diajukan sampai Ditolak), dan Total; angka rata kanan; satu baris per sekolah (sekolah tanpa entri tampil 0) dan baris **Total** bercetak tebal. Draf tidak dihitung. Petunjuk "Geser tabel untuk melihat kolom lainnya" di mobile. Empty state "Belum ada sekolah" bila tidak ada sekolah.

## 6. Pola lintas halaman

- **Umpan balik:** sukses = toast hijau; gagal form = banner dan pesan per field; aksi merusak = dialog konfirmasi menyebut nama objek.
- **Izin:** tombol, menu, dan aksi tanpa izin tidak ditampilkan; halaman tanpa izin = 403.
- **Gagal memuat:** kegagalan jaringan atau respons non-Inertia (mis. 502 dari proxy) menampilkan banner danger di atas konten (`role="alert"`): "Gagal memuat data. Periksa koneksi Anda lalu coba lagi." dengan tombol **Coba lagi** (mengulang kunjungan yang gagal) dan **Tutup**; berlaku untuk semua halaman di dalam layout aplikasi.
- **Loading:** skeleton untuk tabel dan kartu; tombol submit menampilkan spinner dan nonaktif saat proses.
- **Tanggal dan waktu:** format `7 Okt 2026`, `14.30`; relatif untuk waktu dekat ("2 menit lalu"); satu formatter di kode (`lib/date.ts`), jadi tidak ada format berbeda antarkomponen.
- **Aksesibilitas:** semua kontrol dapat dioperasikan dengan keyboard; urutan fokus logis; label terhubung ke input; status tidak hanya dibedakan lewat warna (badge selalu bertuliskan teks); area sentuh minimal 40 px.
- **Navigasi keyboard (6c-3):** tautan "Lewati ke konten" adalah fokus pertama di `AppLayout` dan menuju `main#konten`; saat pindah ke halaman lain (komponen berbeda) fokus dipindah ke `#konten` dan wilayah `role=status` mengumumkan "Halaman: {judul}" (tidak pada muat awal, filter, atau partial reload). Tautan teks memakai cincin fokus 2 px `--ring`; tautan di baris tabel dan breadcrumb bertinggi minimal 24 px (`py-1`). Setiap `DataTable` wajib berprop `label` (nama aksesibel tabel; dijaga tes), header memakai `scope="col"`, dan `SearchInput` meneruskan `aria-label` ke input. Sistem tidak terindeks (`noindex`).
- **Responsif:** breakpoint 768 dan 1280; desain kerja dibuat untuk lebar 1440 (desktop) dan 390 (mobile).

## 7. Catatan serah terima ke Figma

- Buat halaman Figma: **Tokens** (warna, tipografi, ruang), **Components** (tabel di bagian 3 beserta varian state: default, hover, fokus, disabled, error), **Auth**, **Shell dan Beranda**, **Pengguna**, **Role & Sekolah**, **Notifikasi dan Profil**, **Error dan State**.
- Gunakan variabel Figma untuk semua token warna dan ruang; hindari nilai tetap di komponen.
- Setiap halaman: frame desktop 1440 dan mobile 390, termasuk state kosong, loading, dan error.
- Data contoh: pengguna "Budi Santoso" (operator, SMAN 1 Sungai Beduk), "Sari Wulandari" (admin kecamatan); sekolah SMAN 1, SMKN 2, MAN 1 Sungai Beduk. Semua fiktif; tidak memakai NIK nyata.
- Catatan penambahan terhadap spec sistem: field **Nomor HP** (opsional) pada user dan fitur **Kirim via WhatsApp** hanyalah tautan `wa.me`, bukan integrasi WhatsApp.
