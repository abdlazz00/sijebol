# Data dummy Implementation Plan

> **For agentic workers:** executing-plans (inline). Steps use checkbox syntax.

**Goal:** Perintah `sijebol:isi-data-dummy` yang mengisi siswa, sesi, pendaftaran, dokumen, dan akun dummy ke sekolah yang sudah ada, plus pembersihan.

**Architecture:** State `dummy()` di `StudentFactory`, state `cancelled()` di `ScheduleFactory`; logika di `Database\Seeders\DummyDataSeeder` (`generate(int $students): array`, `clean(): array`, pengaman env di dalamnya); `App\Console\Commands\SeedDummyData` hanya membungkus.

**Tech Stack:** Laravel 12, factory + faker `id_ID`, Pest (MySQL `sijebol_test`).

**Spec:** docs/superpowers/specs/2026-11-02-data-dummy-design.md

## Global Constraints
- Tidak memakai `Co-Authored-By`; commit hanya bila diminta. Tidak menyentuh data sekolah.
- Penanda: NIK `9999…`, NISN `99…`, email `@dummy.sijebol.test`.

## Review Focus
- Dijalankan di env bukan local/testing → menolak tanpa menulis.
- Tanpa sekolah / tanpa role (AccessSeeder belum jalan) → pesan jelas, tidak setengah jadi.
- Dijalankan dua kali → tidak menggandakan, tidak bentrok unik.
- `--bersihkan` tidak menghapus siswa/sesi/pendaftaran asli.
- Tidak ada siswa dengan dua pendaftaran aktif; kuota tidak terlampaui; Sudah rekaman hanya di sesi lampau/hari ini.

### Task 1: Factory state dan aset dokumen
**Files:** Modify `database/factories/StudentFactory.php`, `ScheduleFactory.php`; Create `database/seeders/assets/dokumen-contoh.png`.
**Interfaces:** Produces `StudentFactory::dummy(int $age = 16)` (nik `9999`+12 digit, nisn `99`+8 digit, nama `id_ID` selaras gender, birth_date menurut umur), `ScheduleFactory::cancelled()`.
- [ ] Tes `tests/Feature/Dummy/DummyFactoryTest.php`: `dummy(17)` → NIK 16 digit berawalan 9999, NISN 10 digit berawalan 99, umur 17 hari ini, gender L/P; `cancelled()` mengisi `cancelled_at`. Jalankan: gagal.
- [ ] Implementasi state. Jalankan: lulus.

### Task 2: Generator dan pembersihan
**Files:** Create `database/seeders/DummyDataSeeder.php`; Test `tests/Feature/Dummy/DummyDataSeederTest.php`.
**Interfaces:** `generate(int $students): array{schedules,students,registrations,accounts}`, `clean(): array`, `run()` memanggil `generate(1500)`; melempar `RuntimeException` bila env salah, sekolah kosong, atau role belum ada.
- [ ] Tes (jumlah kecil, mis. 60 siswa, sekolah dari `School::factory()` + role dari AccessSeeder): env `production` ditolak tanpa menulis; tanpa sekolah ditolak; semua siswa dummy bertanda; tidak ada siswa dengan >1 pendaftaran aktif; `seatsTaken() <= quota`; Sudah rekaman hanya pada sesi ≤ hari ini; tiket terisi untuk Terdaftar/Sudah rekaman; riwayat berakhir di status akhir; siswa ≥16 pada tanggal sesi; dokumen ada untuk siswa berpendaftaran; akun siswa berperan siswa; dua kali jalan tidak menggandakan akun/operator; log aktivitas tidak bertambah; sekolah tidak berubah.
- [ ] Tes `clean()`: data asli (siswa, sesi, pendaftaran, user biasa) utuh; semua dummy hilang, berkas dokumen ikut.
- [ ] Implementasi generator: urutan = pengaman → bersihkan → admin/operator → sesi → alokasi siswa → siswa+dokumen+akun → pendaftaran+riwayat; dalam satu transaksi; `activity()->disableLogging()` selama jalan.
- [ ] Jalankan tes sampai lulus.

### Task 3: Perintah artisan dan verifikasi lokal
**Files:** Create `app/Console/Commands/SeedDummyData.php`; Test di `DummyDataSeederTest.php` (perintah memanggil seeder, `--bersihkan`); Modify `docs/deployment-guide.md` (bagian singkat data dummy).
- [ ] Perintah `sijebol:isi-data-dummy {--siswa=1500} {--bersihkan}`; cetak ringkasan jumlah; pesan error ramah.
- [ ] Jalankan di DB lokal dengan 1500 siswa, cek halaman Laporan KTP-el pelajar menunjukkan ketiga kelompok; jalankan `--bersihkan` lalu isi ulang.
- [ ] Pint, PHPStan, seluruh Pest.
