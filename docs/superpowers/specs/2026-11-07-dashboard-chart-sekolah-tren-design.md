# Beranda: bar chart semua sekolah dan perbaikan hitungan tren

## Permintaan (pemilik, 7 Nov 2026)
1. Bar chart "Rekap Pendaftar per Sekolah" (Staf Kecamatan) tidak lagi Top 5 + "Lainnya"; tampilkan **semua sekolah**.
2. Periksa chart tren: ada hitungan yang meleset.
Pemilik memilih mengerjakan butir 1 (definisi "Terdaftar") dan butir 2 (rentang bulan) dari temuan; donut tidak diubah.

## Temuan (data lokal, 2026)

| Ukuran | Nilai |
|---|---|
| Garis "Terdaftar" tren = jumlah baris `registration_students` dibuat per bulan | 1030 |
| Siswa unik yang pernah mendaftar | 932 |
| Siswa unik dengan ≥1 pendaftaran non-gugur | 775 |
| KPI "Jumlah Pelajar Terdaftar" (baris pendaftaran tanpa ditolak/dibatalkan/kedaluwarsa) | 803 |
| Donut "Terdaftar" (status Terdaftar saja) | 142 |

Penyebab garis "Terdaftar" membengkak:
- Pendaftaran gugur (ditolak 58, dibatalkan 90, kedaluwarsa 79) ikut terhitung.
- Siswa yang mendaftar ulang dihitung berkali-kali (98 siswa punya >1 pendaftaran).
- Kata "Terdaftar" bermakna tiga hal berbeda di dasbor (tren, KPI, donut).

Garis "Sudah Rekam" (menurut bulan tanggal sesi) benar dan cocok dengan KPI/donut; tidak diubah.

Bar chart sekolah memakai dasar hitung yang sama (semua baris pendaftaran, total 1030), jadi ikut meleset dari KPI.

Catatan terpisah: KPI `total_students` menghitung baris pendaftaran, bukan siswa (803 vs 775 siswa unik; 28 siswa punya dua pendaftaran non-gugur). Tidak diubah di sini; angka tren (775) karena itu tidak persis sama dengan KPI. Diserahkan ke pemilik.

## Keputusan

Definisi tunggal untuk tren dan bar sekolah: **jumlah siswa unik yang punya setidaknya satu pendaftaran non-gugur** (bukan Ditolak/Dibatalkan/Kedaluwarsa). Sejalan dengan rancangan awal Beranda ("Pelajar Terdaftar", `2026-10-13-revamp-dashboard-design.md`).

- **Tren, garis "Pendaftar"** (sebelumnya "Terdaftar"; diganti nama agar tidak bertabrakan dengan status Terdaftar di KPI/donut): tiap siswa dihitung **sekali**, pada bulan `created_at` pendaftaran non-gugurnya yang paling awal, bila bulan itu jatuh di tahun berjalan.
- **Tren, garis "Sudah Rekam"**: tidak berubah.
- **Rentang bulan** (pilihan saya, belum dipilih pemilik; mudah dibalik): dari bulan pertama yang punya data di tahun berjalan sampai bulan berjalan (atau bulan terakhir yang berisi data bila lebih akhir). Tanpa data sama sekali: hanya bulan berjalan. Alasan: bulan sebelum data pertama hanya garis datar nol, dan bulan setelah hari ini belum terjadi.
- **Bar sekolah**: semua sekolah (termasuk 0 pendaftar), diurutkan dari terbanyak lalu nama; jumlahnya siswa unik non-gugur per sekolah. Tanpa "Lainnya". Bila belum ada sekolah: tetap "Belum ada sekolah".
- **Tampilan bar sekolah**: bar horizontal satu warna dengan label angka; kartu tetap setinggi kartu lain dan daftar bisa digulir (area gulir dapat difokus keyboard), tinggi grafik mengikuti jumlah sekolah. Operator sekolah ("Kesiapan Berkas", 4 kategori tetap) tidak berubah dan tetap bar vertikal.
- Judul tetap "Rekap Pendaftar per Sekolah"; subjudul: "Siswa terdaftar, semua sekolah".

## Di luar cakupan
- Donut (total 622 vs KPI 803), KPI `total_students` (baris vs siswa).
- Perubahan lain di halaman Beranda.

## Pengujian
- Pest (`DashboardSummaryTest`): siswa unik per bulan pendaftaran non-gugur paling awal; pendaftaran gugur dan tahun lain tidak dihitung; rentang bulan; tanpa data; semua sekolah, urutan, tanpa "Lainnya", siswa ganda dihitung sekali.
- Vitest (`DashboardComponents.test.ts`): bar sekolah horizontal untuk staf, area gulir berlabel; operator tetap bar vertikal.
- Verifikasi data lokal: total garis "Pendaftar" = 775.
