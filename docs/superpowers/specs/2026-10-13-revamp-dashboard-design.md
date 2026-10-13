# Desain Spesifikasi: Revamp Beranda / Dashboard SIJEBOL

Tanggal: 2026-10-13  
Branch: `feature/revamp-dashboard`  
Status: Draf Disetujui (Hasil Brainstorming)

---

## 1. Latar Belakang & Tujuan

Mengubah tampilan Beranda (`Dashboard.vue`) SIJEBOL dari yang semula kartu sederhana menjadi **Pusat Kendali Operasional Terpadu (Command Center)** yang kaya informasi, interaktif, dan modern sesuai alur program Jemput Bola (pelayanan perekaman KTP-el pemula bagi pelajar di Kecamatan Sungai Beduk).

Spesifikasi ini mengadopsi referensi mockup dengan penyempurnaan:
- **Visualisasi data** menggunakan pustaka grafik modern **ApexCharts**.
- **Diferensiasi Multi-Role**: Penyajian data global se-Kecamatan untuk Staf/Admin Kecamatan vs data terisolasi untuk Operator Sekolah.
- **Bilah Aksi Cepat di bawah KPI Cards** (Akses instan) dengan pembedaan styling visual yang tegas agar tidak tertukar dengan kartu informasi metrik.
- **Mini Kalender Interaktif**: Menandai tanggal kegiatan perekaman, tooltip saat di-hover, dan dapat diklik untuk membuka rincian agenda kegiatan.

---

## 2. Struktur Tata Letak (Layout Hierarchy)

Tata letak disusun secara vertikal dari atas ke bawah:

```text
1. Header Salam & Info Pengguna (Nama, Role Badge, Tanggal Hari Ini)
2. Baris KPI (5 Stat Cards Berjejer)
3. Bilah Aksi Cepat / Quick Actions (4 Tombol Interaktif Terbuka Cepat)
4. Baris Visualisasi Grafik ApexCharts (3 Kartu: Bar Sekolah, Donut Status, Line Tren Tahunan)
5. Baris Operasional 2 Kolom:
   - Kolom Kiri (~60%): Tabel Jadwal Perekaman Terdekat (+ Link "Lihat Semua")
   - Kolom Kanan (~40%):
     a. Card Notifikasi Terbaru (+ Link "Lihat Semua")
     b. Card Kalender Mini Jadwal Interaktif (Navigasi Bulan, Highlight, Hover & Klik)
```

---

## 3. Spesifikasi Rinci Tiap Komponen

### A. Baris KPI (5 Stat Cards)
- **Desain Visual**: Permukaan kartu berlatar warna pastel lembut (*soft tinted background*) dengan border tipis, ikon di dalam lingkaran berwarna, angka nilai tebal, dan label penjelasan.
- **Diferensiasi Nilai**:
  - **Staf / Admin Kecamatan** (Data se-Kecamatan):
    1. *Total Pelajar Terdaftar* (Biru)
    2. *Sudah Dijadwalkan* (Hijau + % dari total)
    3. *Sudah Melakukan Perekaman* (Kuning Emas + % dari total)
    4. *Dalam Proses / Siap Dijadwalkan* (Merah Muda / Pink + % dari total)
    5. *Belum Dijadwalkan* (Slate / Abu-abu + % dari total)
  - **Operator Sekolah** (Khusus Sekolah Sendiri):
    1. *Total Siswa Terdaftar*
    2. *Memenuhi Syarat* (Berkas lengkap & umur ≥ 16)
    3. *Belum Memenuhi Syarat* (Berkas kurang / umur < 16)
    4. *Sudah Masuk Jadwal Perekaman*
    5. *Sudah Selesai Rekam KTP-el*

### B. Bilah Aksi Cepat (Quick Actions) — Opsi B (Bawah KPI Cards)
- **Pembedaan Visual dari KPI Cards**:
  - Diberi judul penanda tegas: `⚡ Aksi Cepat`.
  - Menggunakan format tombol aksi interaktif dengan warna solid/kontras tinggi, ikon aksi, dan efek hover yang jelas menandakan elemen dapat diklik (*clickable*).
  - Berlatar belakang panel kartu yang menyatu dengan border rapi.
- **Aksi Staf Kecamatan**:
  1. `Tambah Pendaftaran` (`pendaftaran.buat`)
  2. `Buat Jadwal` (`jadwal.buat`)
  3. `Input Hasil Perekaman` (`rekaman.tambah`)
  4. `Unduh Laporan` (`laporan.ekspor`)
- **Aksi Operator Sekolah**:
  1. `Tambah Siswa` (`siswa.buat`)
  2. `Ajukan Pendaftaran` (`pendaftaran.buat`)
  3. `Cek Siswa Memenuhi Syarat` (`siswa.index`)
  4. `Unduh Rekap Sekolah` (`laporan.ekspor`)

### C. Baris Visualisasi Grafik ApexCharts (3 Kartu)
- **Teknologi**: Menggunakan paket `apexcharts` dan `vue3-apexcharts`.
- **Komponen**: Dibuat komponen pembungkus yang aman dari SSR Inertia (hanya merender di client dengan dynamic import/onMounted).
- **Grafik 1 (Bar Chart) — Rekap Pendaftaran per Sekolah**:
  - *Staf Kecamatan*: Bar vertikal warna-warni Top 5 sekolah pendaftar terbanyak + kategori "Lainnya".
  - *Operator Sekolah*: Bar perbandingan kesiapan berkas siswa (KK lengkap, Akta lengkap, Kedua dokumen lengkap).
- **Grafik 2 (Donut Chart) — Proporsi Status Perekaman**:
  - Donut ring dengan angka total pelajar di tengah lingkaran.
  - Tiga segmen warna:
    - Hijau: Sudah Rekam
    - Biru: Dijadwalkan
    - Kuning: Belum Dijadwalkan
  - Legenda di samping/bawah kartu dengan angka dan persentase.
- **Grafik 3 (Line / Area Chart) — Tren Perekaman Tahunan**:
  - Rentang waktu: Januari hingga Desember tahun berjalan saat ini (Jan – Des).
  - Dua kurva area halus:
    - Garis Biru: Pelajar Terdaftar
    - Garis Hijau: Sudah Rekaman

### D. Tabel Jadwal Perekaman Terdekat
- Letak: Kolom kiri baris bawah (lebar ~60%).
- Menampilkan 5–8 jadwal kegiatan jemput bola terdekat.
- Kolom tabel: No, Tanggal, Sekolah, Jumlah Peserta, Lokasi, Status.
- Status Badge:
  - `Terlaksana`: Hijau lembut (*emerald*)
  - `Hari Ini`: Biru terang berdenyut (*pulse dot*)
  - `Dijadwalkan`: Kuning/Amber
- Tautan *"Lihat Semua"* di pojok kanan atas menuju halaman penuh `/jadwal`.
- Setiap baris tabel dapat diklik untuk melihat rincian kegiatan.

### E. Notifikasi Terbaru
- Letak: Kolom kanan atas (lebar ~40%).
- Menampilkan 4–5 notifikasi terbaru dengan penanda titik warna, judul, ringkasan isi, dan penunjuk waktu (misal: "10:24", "Kemarin").
- Tautan *"Lihat Semua"* menuju halaman `/notifikasi`.

### F. Kalender Mini Jadwal Interaktif
- Letak: Kolom kanan bawah (lebar ~40%).
- Navigasi bulan (`<` dan `>` serta penunjuk bulan/tahun, default bulan berjalan).
- Grid 7 hari (Min, Sen, Sel, Rab, Kam, Jum, Sab).
- **Penandaan Tanggal**:
  - Lingkaran warna pada tanggal yang memiliki agenda kegiatan jemput bola (Hijau = Selesai, Biru = Hari Ini, Kuning = Dijadwalkan).
- **Interaksi**:
  - **Hover**: Tooltip native/popover menampilkan nama sekolah & lokasi kegiatan.
  - **Klik Tanggal**: Membuka dialog ringkas agenda kegiatan pada tanggal tersebut, berisi daftar sekolah, kuota peserta, dan tombol langsung ke rincian jadwal.

---

## 4. Arsitektur Data & Backend (DashboardService)

Memperluas kontrak payload `DashboardService` dan `DashboardController`:

```php
[
    'kpi' => [
        'total_students' => int,
        'scheduled_count' => int,
        'scheduled_pct' => float,
        'recorded_count' => int,
        'recorded_pct' => float,
        'in_process_count' => int,
        'in_process_pct' => float,
        'unscheduled_count' => int,
        'unscheduled_pct' => float,
    ],
    'quick_actions' => list<array{label: string, href: string, icon: string, variant: string}>,
    'charts' => [
        'school_recap' => array{categories: list<string>, series: list<int>, colors: list<string>},
        'recording_status' => array{labels: list<string>, series: list<int>, total: int},
        'monthly_trend' => array{months: list<string>, registered: list<int>, recorded: list<int>},
    ],
    'schedules_table' => list<array{id: int, date: string, school_name: string, participants: int, location: string, status: string}>,
    'calendar_events' => list<array{date: string, school_name: string, location: string, status: string, participants: int}>,
    'notifications' => list<...>,
]
```

- Query dilakukan secara efisien dengan agregasi SQL terindeks (`registration_students` dan `schedules`), tanpa N+1.
- Scope `user->school_id` tetap otomatis menyaring data jika pengguna adalah Operator Sekolah.

---

## 5. Rencana Pengujian & Quality Gates

1. **Unit & Feature Test Backend**:
   - Uji payload `DashboardService` untuk Staf Kecamatan (data lengkap se-kecamatan).
   - Uji payload `DashboardService` untuk Operator Sekolah (data terbatas sekolahnya).
   - Uji akurasi agregasi bulanan tahun berjalan dan jadwal kalender.
2. **Frontend Test (Vitest)**:
   - Pengujian rendering komponen dashboard.
   - Pengujian pembagian hak akses tombol Aksi Cepat.
   - Pengujian interaksi kalender mini (hover tooltip & click popover).
3. **Quality Gates Lengkap**:
   - `vendor/bin/pint --test`
   - `vendor/bin/phpstan analyse`
   - `php artisan test --parallel`
   - `npx vitest run`
   - `npm run build`
