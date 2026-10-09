# Fase 6a — Beranda riil dan laporan rekap: desain

Tanggal: 2026-10-09. Bagian pertama Fase 6 (sprint plan). Fase 6 dipecah tiga sub-fase: **6a** Beranda dan laporan (dokumen ini), **6b** alur lapangan (koreksi status akhir, hapus jadwal, pindah siswa antar-jadwal, notifikasi penandaan, penggabungan notifikasi, jadwal terdekat-dulu, verifikasi massal), **6c** hardening, ketahanan, dan polesan. Bagian rilis ditahan sampai 6a–6c selesai dan item tertunda dibahas ulang.

**Tujuan:** Beranda menampilkan angka dan tugas nyata sesuai permission, dan admin kecamatan mendapat satu laporan rekap per sekolah × status yang dapat difilter dan diekspor ke Excel.

**Sukses:** angka Beranda sama dengan data sebenarnya dan operator hanya melihat angka sekolahnya; setiap kartu dan aksi hilang bila pengguna tidak punya permission modulnya; isi file Excel identik dengan tabel di halaman; pengguna tanpa `laporan.lihat` mendapat 403.

**Di luar cakupan:** grafik, laporan daftar rinci siswa, laporan per jadwal, cache hasil hitung, filter berdasarkan tanggal perubahan status.

**Keputusan (dikonfirmasi user, 2026-10-09):**
1. Laporan v1 hanya rekap sekolah × status.
2. Isi Beranda per peran sesuai usulan (bagian 2).
3. Filter periode memakai tanggal pengajuan pendaftaran (`registrations.submitted_at`); sel berisi jumlah entri dengan status saat ini.

## 1. Backend

Domain baru `app/Domains/Reports` dengan pola Controller → FormRequest → Service → Repository (interface di `Repositories/Contracts` + `Eloquent*`, dibind di `RepositoryServiceProvider`).

**Repository (`ReportRepositoryInterface`)**
- `statusCounts(): array<string,int>`: jumlah entri per status. Memakai scope `ScopedBySchool`, sehingga operator hanya menghitung sekolahnya.
- `schoolStatusMatrix(?string $from, ?string $to): Collection`: satu baris per sekolah (semua sekolah, termasuk yang 0, urut nama) berisi hitungan per status. Entri berstatus `draf` tidak dihitung. Filter `from`/`to` bersifat inklusif pada `submitted_at` dalam zona Asia/Jakarta; tanpa filter berarti semua.

**Service**
- `ReportService::recap(?from, ?to)` membentuk baris dan baris total (kolom: Diajukan, Perlu perbaikan, Terverifikasi, Terjadwal, Sudah rekaman, Tidak hadir, Belum rekaman, Ditolak, Total). Halaman dan ekspor memakai method ini.
- `DashboardService::summary(User)` dan `::actions(User)` memakai `statusCounts()`.

**Kartu ringkasan** (muncul hanya bila pengguna punya permission modulnya)
- Menunggu verifikasi (`verifikasi.lihat`), Draf dan Perlu perbaikan (`pendaftaran.lihat`), Terjadwal dan Sudah rekaman (`jadwal.lihat`).
- Admin kecamatan: Menunggu verifikasi, Terjadwal, Sudah rekaman. Operator sekolah: Draf, Perlu perbaikan, Sudah rekaman. Kelompok kartu ditentukan lewat permission, bukan nama role; batas tiga atau empat kartu dijaga di service.

**Perlu tindakan** (aksi dengan hitungan 0 tidak ditampilkan)
- "N entri menunggu verifikasi" → `verifikasi.index` (`verifikasi.lihat`).
- "N siswa terverifikasi belum dijadwalkan" → `jadwal.index` (`jadwal.buat`).
- "N entri perlu diperbaiki" → `pendaftaran.index` (`pendaftaran.lihat`).
- "N siswa terjadwal" → `jadwal.index` (`jadwal.lihat`), hanya bagi pemegang permission yang tidak memegang `jadwal.buat`.

**Laporan** (`routes/reports.php`)
- `GET /laporan` (`laporan.index`) dan `GET /laporan/ekspor` (`laporan.ekspor`), keduanya `can:laporan.lihat`.
- `ReportRequest`: `dari` dan `sampai` opsional, format `Y-m-d`, `sampai >= dari`.
- Ekspor: export class `maatwebsite/excel` (`FromArray` dengan heading) yang membaca `ReportService::recap`. Rute ekspor memakai `throttle`; setiap ekspor dicatat di log aktivitas (`laporan.diekspor`, subjek berupa filter).
- Menu: grup "Laporan", item "Rekap pendaftaran" (ikon sesuai set `menuIcons`), permission `laporan.lihat`.
- Label log: tambah aksi `laporan.diekspor` di `lang/id/activity.php`.

## 2. Frontend

- **`Pages/Laporan/Index.vue`:** header, filter Dari/Sampai (input tanggal native) dan tombol "Terapkan"/"Reset", tombol "Ekspor Excel" (tautan unduh yang membawa filter aktif), tabel rekap dengan baris total tebal, petunjuk "Geser tabel untuk melihat kolom lainnya" di mobile (`md:hidden`), empty state bila tidak ada sekolah. Angka rata kanan; kolom Sekolah tidak sticky (cukup petunjuk geser).
- **`Dashboard.vue`:** tanpa perubahan struktur; hanya `types/dashboard.ts` disesuaikan (union ikon kartu dan aksi).
- **Panduan UI:** bagian 5.28 (Laporan) dan pembaruan 5.6 (kartu dan aksi riil).

## 3. Pengujian

- **Tes layanan:** hitungan benar; draf tidak dihitung; filter tanggal inklusif pada batas hari Jakarta; sekolah tanpa entri tampil 0; baris total benar.
- **Isolasi:** operator tidak mendapat angka sekolah lain di Beranda; kartu dan aksi hilang bila permission modulnya tidak ada.
- **HTTP:** matriks izin dan menu mendapat baris laporan; 403 tanpa `laporan.lihat`; validasi `dari`/`sampai`; isi file ekspor sama dengan data halaman; ekspor tercatat di log.
- **Frontend:** Vitest untuk tabel rekap dan total; verifikasi browser pada 1440 dan 390 px (tanpa overflow horizontal, konsol bersih).
- **Review:** `ponytail:ponytail-review` untuk semua perubahan dan `impeccable` untuk frontend, sebelum klaim selesai.

## 4. Catatan teknis

- Zona waktu: `from`/`to` dikonversi ke awal dan akhir hari Asia/Jakarta sebelum dibandingkan dengan `submitted_at`; jangan menduplikasi logika "hari ini" (gunakan pola `Schedule::today()` bila perlu "hari ini").
- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`.
- Pest dataset tidak boleh berisi closure; tes yang memicu notifikasi harus membuat permission terkait.
