# Fase 5 — Jadwal dan Penandaan Rekaman: Desain

Tanggal: 2026-10-08. Turunan dari `2026-10-07-sijebol-v1-design.md` (bagian 3.5, 3.6, 3.7, 5, 6, 7, 8, 12) dan rencana sprint Fase 5. Mencakup Track BE dan Track FE. Item tertunda dari Fase 4 yang tercakup di sini: kolom `schedule_id`, transisi penjadwalan dan penandaan lewat `StatusTransitioner`, notifikasi jadwal, label log baru, dan blok "Jadwal terdekat" di Beranda.

## 1. Tujuan dan batasan

Admin kecamatan membuat jadwal per sekolah (tanggal dan lokasi), memasukkan siswa `Terverifikasi` sekolah itu ke jadwal (status `Terjadwal`), dan setelah hari kegiatan menandai siswa lewat ceklis massal: `Sudah rekaman`, `Tidak hadir`, atau `Belum rekaman`. Operator sekolah melihat jadwal sekolahnya dan diberi notifikasi saat jadwal ditetapkan atau diubah.

**Sukses:** satu siswa tidak pernah berada di dua jadwal; semua perubahan status tercatat sebagai riwayat; operator tidak dapat melihat atau mengubah jadwal sekolah lain (404); siswa `Tidak hadir` atau `Belum rekaman` dapat didaftarkan lagi (indeks unik Fase 4 sudah melepas slot entri aktif untuk status itu; Fase 5 mengunci perilakunya dengan tes).

**Di luar cakupan:** laporan, kartu ringkasan dan "Perlu tindakan" di Beranda (Fase 6), penjadwalan otomatis, notifikasi WhatsApp, hapus jadwal, pindah siswa antar-jadwal (cukup keluarkan lalu masukkan lagi), kapasitas dan jam kegiatan, koreksi status akhir, notifikasi atas penandaan rekaman (spec §7 tidak memuatnya).

**Asumsi (dikonfirmasi user, 2026-10-08):**
1. Transisi tambahan `Terjadwal → Terverifikasi` ("keluarkan dari jadwal") selama siswa belum ditandai; ini di luar state machine spec v1 agar salah penempatan dapat dibatalkan.
2. Penandaan rekaman hanya pada atau setelah tanggal jadwal (zona Asia/Jakarta); sebelum itu ditolak.
3. Status akhir (`Sudah rekaman`, `Tidak hadir`, `Belum rekaman`) final, tanpa koreksi.
4. Jadwal hanya memuat tanggal dan lokasi; tanpa hapus, pindah, kapasitas, atau jam.
5. Tambahan dari perancangan: jadwal baru dan perubahan tanggal harus pada hari ini atau setelahnya, dan siswa hanya dapat ditempatkan ke jadwal yang tanggalnya hari ini atau setelahnya (pencegah salah input).

## 2. Keputusan arsitektur

- **Domain baru `app/Domains/Schedules`** dengan pola yang ada (Controller → FormRequest → Service → Repository interface + Eloquent) dan trait `ScopedBySchool` pada `Schedule`. `RegistrationStudent` sudah memakai `ScopedBySchool`, jadi entri sekolah lain tidak terlihat oleh operator.
- **Mesin status tetap satu pintu.** `RegistrationStatus::next()` ditambah transisi baru; `StatusTransitioner::move` mendapat parameter `array $attributes = []` yang digabung ke update (dipakai untuk mengisi atau mengosongkan `schedule_id` dalam transaksi yang sama dengan perubahan status dan riwayat). `active()` dan `locking()` tidak berubah (sudah memuat `Terjadwal` dan `SudahRekaman`).
- **Dua layanan dengan satu tanggung jawab masing-masing:** `ScheduleService` (jadwal dan penempatan) dan `RecordingService` (penandaan massal). Keduanya memakai `ScheduleRepositoryInterface`.
- **Satu kelas notifikasi** `ScheduleNotification` untuk "ditetapkan" dan "diubah" (pola `again` pada notifikasi pengajuan).

## 3. Backend

### 3.1 Skema

**`schedules`:** `id`, `school_id` (FK), `date` (date), `location` (string 150), `created_by` (FK users), timestamps; indeks `(school_id, date)`.

**`registration_students`:** tambah `schedule_id` (unsignedBigInteger nullable, FK `schedules` restrict), indeks `schedule_id`. Entri tetap menyimpan `schedule_id` setelah ditandai (jejak jadwal yang diikuti); hanya "keluarkan dari jadwal" yang mengosongkannya.

### 3.2 Transisi

Ditambahkan ke `RegistrationStatus::next()`: `Terverifikasi → [Terjadwal]`, `Terjadwal → [Terverifikasi, SudahRekaman, TidakHadir, BelumRekaman]`. Status akhir tanpa transisi lanjutan.

### 3.3 Service

**`ScheduleService`:**
- `paginate()`, `create(array $data, User $actor): Schedule` (`school_id` hanya untuk staf; operator terkunci pada sekolahnya, tetapi operator tidak memegang `jadwal.buat` pada role bawaan), `update(Schedule, array $data, User $actor): Schedule`.
- `update` mencatat log `jadwal.diubah` dan, bila tanggal atau lokasi berubah dan jadwal sudah memiliki siswa, memberi satu notifikasi ke operator sekolah (`toSchool('jadwal.lihat', …)`).
- `assign(Schedule $schedule, list<int> $entryIds, User $actor): void` — transaksi: kunci baris entri (urut id), pastikan semuanya milik sekolah jadwal dan berstatus `Terverifikasi` dan jadwal belum lewat (tanggal ≥ hari ini), lalu `StatusTransitioner::move(..., Terjadwal, attributes: ['schedule_id' => id])` untuk tiap entri; log `jadwal.penempatan` (jumlah); satu notifikasi "ditetapkan" berisi jumlah siswa. Entri yang bukan `Terverifikasi` (mis. sudah diputuskan lain) menggagalkan seluruh operasi dengan `ValidationException` pada `entry_ids`.
- `unassign(RegistrationStudent $entry, User $actor): void` — hanya `Terjadwal`; `move(..., Terverifikasi, attributes: ['schedule_id' => null])`; log `jadwal.penempatan` (jumlah −1 pada properti `dikeluarkan`).

**`RecordingService::mark(Schedule $schedule, list<int> $entryIds, RegistrationStatus $to, ?string $note, User $actor): void`** — `$to` ∈ `SudahRekaman|TidakHadir|BelumRekaman`. Menolak bila tanggal jadwal masih di masa depan (`AccessRuleException` "Penandaan baru dapat dilakukan pada atau setelah tanggal jadwal."). Transaksi atomik: setiap entri harus milik jadwal itu dan berstatus `Terjadwal`; pelanggaran menggagalkan seluruhnya. `move()` per entri, satu log `rekaman.ditandai` (status, jumlah). Tanpa notifikasi.

### 3.4 Endpoint

| Method | URL | Izin | Aksi |
|---|---|---|---|
| GET | `/jadwal` | `jadwal.lihat` | Daftar (cari lokasi, `filter[school_id]` staf, `filter[period]` = `akan-datang`\|`lewat`) |
| GET | `/jadwal/buat` | `jadwal.buat` | Form |
| POST | `/jadwal` | `jadwal.buat` | Simpan |
| GET | `/jadwal/{schedule}` | `jadwal.lihat` | Detail + peserta (+ kandidat bila `jadwal.ubah`) |
| GET | `/jadwal/{schedule}/ubah` | `jadwal.ubah` | Form ubah |
| PUT | `/jadwal/{schedule}` | `jadwal.ubah` | Perbarui |
| POST | `/jadwal/{schedule}/siswa` | `jadwal.ubah` | Masukkan siswa (`entry_ids[]`) |
| DELETE | `/jadwal/{schedule}/siswa/{entry}` | `jadwal.ubah` | Keluarkan dari jadwal |
| GET | `/jadwal/{schedule}/rekaman` | `rekaman.lihat` | Layar penandaan |
| POST | `/jadwal/{schedule}/rekaman` | `rekaman.tandai` | Tandai massal (`status`, `entry_ids[]`, `note`) |

POST/DELETE memakai `throttle:60,1`; rute bertingkat dengan `scopeBindings()` (entri harus milik jadwal pada URL). Jadwal atau entri sekolah lain → 404. Nama prop halaman: `schedules`, `schedule`, `participants`, `candidates`, `entries`.

**Props kunci:**
- `Jadwal/Index`: `schedules` (paginator `{id,date,location,school,participants_count,counts}`), `filters`, `schools`.
- `Jadwal/Form`: `schedule` (`{id,date,location}` | null), `schools`.
- `Jadwal/Show`: `schedule` (`{id,date,location,school,created_by,is_past}`), `participants` (`[{id,student:{id,name,nisn},status}]`), `candidates` (`[{id,student:{id,name,nisn},registration}]`, hanya untuk pemegang `jadwal.ubah`, selain itu `[]`).
- `Jadwal/Rekaman`: `schedule`, `entries` (peserta `Terjadwal` untuk ditandai) dan `marked` (peserta berstatus akhir, hanya baca), `can_mark` (tanggal sudah tiba).
- `Dashboard`: prop `schedules` (`{date,school,location,participants}`, maksimal 3 jadwal akan datang) hanya untuk pemegang `jadwal.lihat`, dibatasi sekolah pengguna.

### 3.5 Notifikasi, log, menu, Beranda

- `ScheduleNotification(string $school?, …)`: judul "Jadwal ditetapkan" atau "Jadwal diubah"; isi "{n} siswa dijadwalkan pada {tanggal} di {lokasi}." atau "Jadwal {tanggal} diubah ke {tanggal baru} di {lokasi}."; url `/jadwal/{id}`; ke `toSchool('jadwal.lihat', …, schoolId)`.
- Log `jadwal` (subjek `Schedule`): `jadwal.dibuat`, `jadwal.diubah`, `jadwal.penempatan`, `rekaman.ditandai`; label di `lang/id/activity.php` (jenis `jadwal` "Jadwal", subjek `Schedule` "Jadwal").
- Menu: grup baru "Jadwal" berisi "Jadwal" (`/jadwal`, ikon `calendar-days`, `jadwal.lihat`); layar penandaan dijangkau dari daftar dan detail jadwal.
- Beranda: route `/dashboard` dialihkan ke `DashboardController` minimal yang hanya menyuplai `schedules`.
- Detail siswa dan pendaftaran tidak berubah; entri `Terjadwal` terlihat lewat status yang sudah ada.

### 3.6 Pengujian (Pest)

- Transisi: semua yang sah (`Terverifikasi → Terjadwal`, `Terjadwal → Terverifikasi/SudahRekaman/TidakHadir/BelumRekaman`) dan ilegal (mis. `Diajukan → Terjadwal`, `SudahRekaman → Terjadwal`, `TidakHadir → SudahRekaman`); `move` dengan `attributes` mengisi dan mengosongkan `schedule_id`.
- Penempatan: entri `Terverifikasi` sekolah itu masuk, status dan riwayat benar, satu notifikasi; entri bukan `Terverifikasi`, sekolah lain, atau jadwal lewat tanggal menggagalkan semuanya (atomik); entri tidak dapat masuk dua jadwal; keluarkan hanya untuk `Terjadwal`.
- Penandaan: tiga status akhir; sebelum tanggal ditolak; entri bukan milik jadwal atau bukan `Terjadwal` menggagalkan seluruhnya; status akhir tidak dapat ditandai ulang.
- Pendaftaran ulang: siswa `TidakHadir`/`BelumRekaman` lolos eligibility dan dapat masuk draf baru lalu diajukan; `SudahRekaman` tidak.
- Kunci data: siswa `Terjadwal` terkunci; `TidakHadir`/`BelumRekaman` terbuka.
- Jadwal: buat, ubah (notifikasi hanya bila tanggal/lokasi berubah dan ada siswa), validasi tanggal; scope sekolah 404; operator hanya melihat jadwal sekolahnya; matriks izin semua rute; Beranda hanya memuat jadwal akan datang milik sekolah pengguna.

**Exit criteria BE:** semua tes lulus (Pest, Larastan, Pint); tidak ada akses lintas sekolah; satu entri tidak pernah berada di dua jadwal; semua perubahan status melewati `StatusTransitioner`.

## 4. Frontend

**Prasyarat desain:** tambahkan ke panduan UI **5.25 Jadwal: daftar dan form**, **5.26 Jadwal: detail dan penempatan siswa**, **5.27 Penandaan rekaman**, dan catatan pada **5.6 Beranda** (blok "Jadwal terdekat" kini memakai data nyata).

### 4.1 Daftar dan form jadwal
- **`/jadwal`** (pola daftar yang ada): judul "Jadwal", tombol **+ Buat jadwal** (`jadwal.buat`); filter cari lokasi, Periode (Semua / Akan datang / Sudah lewat), Sekolah (staf). Kolom **Jadwal** (tanggal dengan hari dan lokasi), **Sekolah** (staf), **Peserta** (jumlah), **Ringkasan status** (teks, mis. "Terjadwal 12 · Sudah rekaman 3"), **Aksi** (Lihat, Ubah bila `jadwal.ubah`, Tandai rekaman bila `rekaman.tandai`). State loading, kosong, hasil filter kosong; petunjuk "Geser tabel" di mobile.
- **Form** (`/jadwal/buat`, `/jadwal/{id}/ubah`, pola 5.8): Sekolah (hanya staf saat buat), Tanggal, Lokasi; bar aksi bawah Batal dan Simpan. Pada ubah dengan peserta, tampil catatan "Operator sekolah akan diberi tahu bila tanggal atau lokasi berubah."

### 4.2 Detail jadwal dan penempatan
Header (tanggal, lokasi, sekolah) dengan tombol Ubah (`jadwal.ubah`) dan Penandaan rekaman (`rekaman.lihat`); tabel **Peserta** (siswa, status badge berteks, aksi **Keluarkan** untuk `Terjadwal` bila `jadwal.ubah`, dengan konfirmasi). Tombol **Tambah siswa** (`jadwal.ubah`, jadwal belum lewat) membuka dialog dengan daftar kandidat `Terverifikasi` sekolah itu (cari nama/NISN, kotak centang, penghitung `role="status"`, pilih semua), konfirmasi "Jadwalkan N siswa? Operator sekolah akan diberi tahu." Galat penempatan tampil di dialog.

### 4.3 Penandaan rekaman
Halaman `/jadwal/{id}/rekaman`: ringkasan jadwal; bila tanggal belum tiba tampil banner "Penandaan dibuka pada {tanggal}." dan kontrol dinonaktifkan. Daftar peserta `Terjadwal` dengan kotak centang, "Pilih semua", penghitung terpilih, pilihan status (radio berteks: Sudah rekaman / Tidak hadir / Belum rekaman), catatan opsional, dan tombol **Simpan penandaan** (konfirmasi menyebut jumlah dan status). Bagian **Sudah ditandai** menampilkan status akhir (hanya baca). Mobile: baris menumpuk, bar aksi menempel di bawah.

### 4.4 Beranda dan operator
Blok **Jadwal terdekat** di Beranda memakai prop `schedules` yang sudah dirancang (tanggal besar di kiri, sekolah dan lokasi). Operator melihat `/jadwal` dan detail jadwal sekolahnya tanpa tombol ubah, tambah, atau tandai.

**Exit criteria FE:** panduan UI 5.25–5.27 terimplementasi; alur buat jadwal → masukkan siswa → ubah jadwal (notifikasi) → tandai massal berjalan di desktop dan mobile, dan operator hanya melihat jadwal sekolahnya; `impeccable` dan `ponytail` selesai.

## 5. Risiko dan catatan

- Penempatan dan penandaan massal atomik: satu entri tidak sah menggagalkan seluruhnya; pesan galat menyebut entri yang bermasalah.
- Perubahan jadwal setelah ada siswa hanya menotifikasi satu kali per perubahan, bukan per siswa.
- Tanpa koreksi status akhir, salah tandai `Sudah rekaman` tidak dapat dibatalkan lewat UI; bila sering terjadi di lapangan, tambahkan transisi koreksi terbatas di fase berikutnya.
- Kapasitas dan jam kegiatan belum ada; bila kecamatan membutuhkannya, tambahkan kolom pada `schedules` tanpa mengubah mesin status.
- Zona waktu penentu "hari ini" adalah Asia/Jakarta (sama dengan perhitungan umur di `EligibilityService`).
