# Data dummy untuk pengembangan, UAT, dan simulasi — desain

## Tujuan
Mengisi database dengan data siswa, sesi, pendaftaran, dokumen, dan akun yang banyak dan bervariasi, memakai **sekolah yang sudah ada**, supaya halaman daftar, filter, paginasi, laporan, dan Excel bisa diuji dengan beban nyata. Tahap berikutnya (di luar pekerjaan ini): dijalankan di UAT sebagai bahan pengenalan sistem ke tiap sekolah dan simulasi oleh client.

## Bentuk (keputusan pengguna)
- Perintah artisan `php artisan sijebol:isi-data-dummy {--siswa=1500} {--bersihkan}`, dikerjakan lewat factory yang ada ditambah state baru.
- Sekitar 1.500 siswa; umur di sekitar batas 17 tahun; pendaftaran dengan kesembilan status beserta riwayat; sesi lampau/hari ini/mendatang; akun siswa (±30%), satu operator sekolah per sekolah.
- Penanda dummy supaya bisa dibersihkan dan dijalankan ulang tanpa menyentuh data asli.

## Keputusan builder (tebakan yang disetujui pengguna saat playback)
- Pengaman: hanya jalan di env `local` dan `testing`. Menyalakannya di UAT = pekerjaan terpisah (lihat "Menuju UAT").
- Penanda: NIK berawalan `9999`, NISN berawalan `99`, email `@dummy.sijebol.test`. Sesi dummy dikenali dari pembuatnya (admin dummy).
- Jumlah siswa per sekolah tidak merata (bobot acak 1–4).

## Isi data
- **Sesi:** 8 per sekolah, tersebar dari -60 sampai +25 hari (lampau, hari ini, mendatang); sekitar separuh sekolah punya satu sesi dibatalkan (bukan hari ini); kuota sebanding dengan perkiraan pendaftar × 0,8–1,6 (minimal 20) sehingga ada yang penuh dan ada yang longgar; batas pendaftaran `registration_close_days` hari sebelum sesi.
- **Siswa:** umur 14–18 (bobot 14:5%, 15:15%, 16:30%, 17:30%, 18:20%), nama gaya Indonesia (`id_ID`), gender selaras nama.
- **Pendaftaran:** hanya siswa berumur ≥16 pada tanggal sesi dan ±75% dari mereka. Status ditarik menurut waktu sesi:
  - lampau: Sudah rekaman 55, Tidak hadir 15, Belum rekaman 10, Kedaluwarsa 10, Ditolak 5, Dibatalkan 5;
  - hari ini: Sudah rekaman 50, Terdaftar 35, Belum rekaman 15;
  - mendatang: Terdaftar 45, Menunggu verifikasi 30, Perlu perbaikan 15, Ditolak 5, Dibatalkan 5;
  - sesi dibatalkan: hanya Dibatalkan.
  Status yang menahan kursi dialihkan ke Dibatalkan bila kuota penuh. ±12% siswa punya satu pendaftaran lama yang tidak aktif (Tidak hadir/Ditolak/Dibatalkan/Kedaluwarsa) di sesi lebih awal. Satu pendaftaran aktif per siswa (indeks unik).
- **Riwayat status** mengikuti jalur sah state machine; kode tiket untuk status setelah Terdaftar; `scanned_at` untuk Sudah rekaman; batas perbaikan untuk Perlu perbaikan; catatan untuk Ditolak/Perlu perbaikan.
- **Dokumen:** siswa yang punya pendaftaran mendapat KK dan akta lahir berupa gambar contoh, satu salinan per siswa di `students/{id}/`.
- **Akun:** ±30% siswa punya akun (role siswa, kata sandi `password`); satu operator sekolah per sekolah (`operator.{npsn}@dummy.sijebol.test`); satu admin kecamatan dummy (pembuat sesi). Dibuat langsung, tidak mengirim email.

## Pembersihan
`--bersihkan` menghapus hanya yang bertanda: riwayat, pendaftaran, dan dokumen siswa dummy (berkas ikut), siswa dummy, sesi dummy yang tidak lagi punya pendaftaran, dan akun `@dummy.sijebol.test`. Menjalankan generator ulang membersihkan dulu, lalu mengisi.

## Bukan tujuan
Tidak menambah atau mengubah sekolah. Tidak memakai `fake()` di luar factory. Tidak membuat log aktivitas (dimatikan selama pengisian). Tidak ada UI.

## Tahap UAT (dikerjakan)
- `fakerphp/faker` dipindah ke `require` (image dibangun `--no-dev`).
- Pengaman env diganti saklar: jalan di `local`/`testing`, atau bila `SIJEBOL_ALLOW_DUMMY_DATA=true` (hanya UAT). Di luar lokal perintah meminta konfirmasi (`--force` melewati) dan menolak kata sandi bawaan: `--sandi` wajib, minimal 8 karakter.
- Semua akun dummy memakai satu kata sandi dari `--sandi` (hash dihitung sekali). Admin dummy pembuat sesi dibuat nonaktif.
- `BaseNotification::via` tidak memakai kanal email untuk alamat `@dummy.sijebol.test` (pengingat otomatis dan notifikasi verifikasi tidak boleh bounce di Resend). Akhiran dummy dan saklar ada di `config/sijebol.php`.
- Dummy ikut tampil di daftar sekolah asli di UAT.
