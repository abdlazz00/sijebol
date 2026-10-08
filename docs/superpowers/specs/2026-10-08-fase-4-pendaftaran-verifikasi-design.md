# Fase 4 — Pendaftaran dan Verifikasi: Desain

Tanggal: 2026-10-08. Turunan dari `2026-10-07-sijebol-v1-design.md` (bagian 3.3, 3.4, 3.7, 5, 6, 7, 8) dan rencana sprint Fase 4. Mencakup Track BE dan Track FE. Item tertunda dari Fase 3 (syarat eligibility "tanpa entri aktif", larangan hapus siswa yang punya pendaftaran, notifikasi, riwayat status) tercakup di sini.

## 1. Tujuan dan batasan

Operator sekolah mengelompokkan siswa eligible ke satu pendaftaran rekaman dan mengajukannya; petugas kecamatan memutuskan per siswa (setujui, minta perbaikan, atau tolak); siswa yang diminta perbaikan dibetulkan operator lalu diajukan ulang. Sukses: satu siswa tidak pernah memiliki dua entri aktif (dijaga basis data, termasuk pada pengajuan bersamaan); semua transisi status tervalidasi dan tercatat sebagai riwayat; pihak yang tepat diberi tahu di tiap langkah.

**Di luar cakupan:** penjadwalan dan penandaan rekaman (Fase 5; kolom `schedule_id` dan status lanjutan disiapkan di enum saja), aksi massal verifikasi, komentar dua arah, unggah dokumen dari halaman verifikasi, penggabungan notifikasi keputusan (satu notifikasi per keputusan siswa pada v1).

**Asumsi (dikonfirmasi user, 2026-10-08):**
- Draf dipertahankan sesuai rencana sprint: pendaftaran dibuat sebagai draf (dapat diubah, siswanya ditambah atau dikurangi, atau dihapus) lalu diajukan. Entri siswa pada draf berstatus `draf` dan tidak dianggap aktif.
- Data siswa terkunci saat sedang diproses: siswa dengan entri `diajukan`, `terverifikasi`, `terjadwal`, atau `sudah_rekaman` tidak dapat diubah datanya, dihapus, atau dokumennya diganti/dihapus; `perlu_perbaikan` dan `draf` membuka kunci. Siswa dengan entri apa pun (termasuk draf dan ditolak) tidak dapat dihapus.
- Keputusan per siswa saja.
- Status batch pendaftaran tidak disimpan; ringkasan dihitung dari status entri.

## 2. Keputusan arsitektur

- **Mesin status:** enum `RegistrationStatus` dengan peta transisi eksplisit dan satu titik perubahan (`StatusTransitioner`) yang menolak transisi ilegal, menulis riwayat, dan memicu log. Tanpa paket baru.
- **Satu entri aktif per siswa:** kolom generated `active_student_id` (= `student_id` bila status aktif, selain itu `NULL`) dengan indeks unik, seperti spec §5; MySQL 8.0 mendukung `STORED` generated column dan indeks unik mengabaikan `NULL`.
- **Domain baru `app/Domains/Registrations`** dengan pola yang ada (Controller → FormRequest → Service → Repository interface + Eloquent) dan trait `ScopedBySchool`. `registration_students` menyimpan `school_id` terdenormalisasi agar scope sekolah berlaku langsung pada antrean verifikasi.
- **Eligibility** tetap di `EligibilityService` (Fase 3) dan ditambah satu syarat; picker, daftar siswa, dan submit memakai layanan yang sama.

## 3. Backend

### 3.1 Skema

**`registrations`:** `id`, `school_id` (FK), `title` (string 150), `expected_count` (unsignedSmallInteger nullable), `created_by` (FK users), `submitted_at` (timestamp nullable), timestamps; indeks `(school_id, submitted_at)`.

**`registration_students`:** `id`, `registration_id` (FK cascade), `school_id` (FK), `student_id` (FK **restrict**), `status` (string 20), `note` (text nullable), `decided_by` (FK users nullable), `decided_at` (timestamp nullable), `schedule_id` (unsignedBigInteger nullable, belum dipakai), `active_student_id` (generated stored, unik), timestamps; unik `(registration_id, student_id)`; indeks `(school_id, status)`.

```
active_student_id = CASE WHEN status IN ('diajukan','perlu_perbaikan','terverifikasi','terjadwal','sudah_rekaman') THEN student_id END
```

**`registration_status_histories`:** `id`, `registration_student_id` (FK cascade), `from_status` (nullable), `to_status`, `by_user_id` (FK), `note` (text nullable), `created_at`.

### 3.2 Status dan transisi

Enum `RegistrationStatus` (label Indonesia): `draf` "Draf", `diajukan` "Diajukan", `perlu_perbaikan` "Perlu perbaikan", `terverifikasi` "Terverifikasi", `terjadwal` "Terjadwal", `sudah_rekaman` "Sudah rekaman", `tidak_hadir` "Tidak hadir", `belum_rekaman` "Belum rekaman", `ditolak` "Ditolak". `isActive()` = lima status aktif di atas; `locksStudent()` = `diajukan`, `terverifikasi`, `terjadwal`, `sudah_rekaman`.

Transisi sah di Fase 4: `draf → diajukan`, `diajukan → terverifikasi | perlu_perbaikan | ditolak`, `perlu_perbaikan → diajukan`. Transisi lain di enum (`terverifikasi → terjadwal`, `terjadwal → sudah_rekaman | tidak_hadir | belum_rekaman`) didefinisikan tetapi hanya dipakai Fase 5. Catatan wajib untuk `perlu_perbaikan` dan `ditolak`.

### 3.3 Eligibility dan kunci data

- `EligibilityService::check` menambah alasan "Siswa sudah terdaftar di pendaftaran lain." bila ada entri aktif pada pendaftaran lain dari pendaftaran yang sedang disunting (parameter opsional `?int $exceptRegistrationId`). `birthDateBounds` dipakai filter SQL; filter `eligible` di daftar siswa menambah `whereDoesntHave('registrationEntries', aktif)`.
- Relasi `Student::registrationEntries()`. `StudentService::update/delete` dan `StudentDocumentService::store/delete` menolak bila siswa terkunci (`AccessRuleException` "Data siswa terkunci karena pendaftarannya sedang diproses."); `delete` menolak bila ada entri apa pun ("Siswa sudah memiliki pendaftaran; keluarkan dari draf atau hubungi admin.").

### 3.4 Service

**`RegistrationService`:**
- `createDraft(array $data, User $actor): Registration` (`title`, `expected_count`, `school_id` hanya untuk staf, `student_ids`), `updateDraft(Registration, array)` (hanya draf milik sekolahnya; menyinkronkan entri `draf`), `deleteDraft(Registration)`.
- `submit(Registration $r, User $actor): void` — transaksi: kunci baris siswa (`lockForUpdate`), validasi ulang `EligibilityService::check` (pada waktu submit, kecuali entri pendaftaran ini sendiri) untuk **semua** siswa, minimal satu siswa; gagal → `ValidationException` pada `students` dengan daftar `{name, reasons}`; berhasil → set `submitted_at`, ubah semua entri `draf → diajukan` lewat `StatusTransitioner`, tulis log `pendaftaran.diajukan`, antre notifikasi ke pemilik `verifikasi.putuskan`. Pelanggaran indeks unik (pengajuan bersamaan) → rollback dan `AccessRuleException` "Sebagian siswa sudah terdaftar di pendaftaran lain."
- `resubmit(RegistrationStudent $entry, User $actor)` — hanya `perlu_perbaikan`; validasi eligibility; `→ diajukan`; log `pendaftaran.diajukan-ulang`; notifikasi ke petugas.
- Pendaftaran yang sudah diajukan tidak dapat diubah atau dihapus (hanya `resubmit` untuk entri `perlu_perbaikan`).

**`VerificationService`:** `approve(entry, actor)`, `requestFix(entry, actor, note)`, `reject(entry, actor, note)` — hanya dari `diajukan`; mengisi `decided_by/decided_at/note`; riwayat; log `pendaftaran.diputuskan`; notifikasi ke operator sekolah terkait (`NotificationService::toSchool(permission, notification, schoolId)` baru: hanya pengguna terikat sekolah itu pemilik izin `pendaftaran.lihat`).

**`StatusTransitioner::move(RegistrationStudent, RegistrationStatus $to, User $actor, ?string $note)`:** memeriksa peta transisi (ilegal → `AccessRuleException`), memperbarui status, menulis satu baris riwayat.

### 3.5 Endpoint

| Method | URL | Izin | Aksi |
|---|---|---|---|
| GET | `/pendaftaran` | `pendaftaran.lihat` | Daftar (cari judul, `filter[status]`, `filter[school_id]` staf) |
| GET | `/pendaftaran/buat` | `pendaftaran.buat` | Form draf (staf: `?school_id=` memuat kandidat sekolah itu) |
| POST | `/pendaftaran` | `pendaftaran.buat` | Simpan draf |
| GET | `/pendaftaran/{registration}` | `pendaftaran.lihat` | Detail + entri + riwayat |
| GET | `/pendaftaran/{registration}/ubah` | `pendaftaran.buat` | Form ubah draf |
| PUT | `/pendaftaran/{registration}` | `pendaftaran.buat` | Perbarui draf |
| DELETE | `/pendaftaran/{registration}` | `pendaftaran.buat` | Hapus draf |
| POST | `/pendaftaran/{registration}/ajukan` | `pendaftaran.ajukan` | Ajukan |
| POST | `/pendaftaran/{registration}/entri/{entry}/ajukan-ulang` | `pendaftaran.ajukan` | Ajukan ulang satu siswa |
| GET | `/verifikasi` | `verifikasi.lihat` | Antrean (entri `diajukan`; `filter[school_id]`, `filter[search]`) |
| GET | `/verifikasi/{entry}` | `verifikasi.lihat` | Halaman keputusan |
| POST | `/verifikasi/{entry}/putuskan` | `verifikasi.putuskan` | `decision` = `approve`\|`fix`\|`reject`, `note` |

Rute dengan throttle `60,1` pada POST. Entri/pendaftaran sekolah lain → 404 (global scope). Nama prop halaman: `registrations`, `registration`, `entries`, `candidates`, `queue`, `entry` (tidak bentrok dengan shared props).

**Props kunci:**
- `Pendaftaran/Form`: `registration` (`{id,title,expected_count,school_id}` | null), `candidates: CandidateRow[]` (`{id,name,nisn,age,eligible,reasons,missing_docs: bool, selected: bool}`), `schools: Option[] | null`.
- `Pendaftaran/Show`: `registration` (`{id,title,expected_count,school,created_by,submitted_at,status_counts,can:{...}}`), `entries: EntryRow[]` (`{id,student:{id,name,nisn},status,status_label,note,decided_at,history:[{from,to,by,note,at}]}`).
- `Verifikasi/Index`: `queue: Paginator<QueueRow>`; `Verifikasi/Show`: `entry` (detail siswa: NIK dimasker, umur, tanggal lahir, gender, sekolah, `documents`, `eligibility`, `history`).

### 3.6 Notifikasi, log, menu

- `RegistrationSubmittedNotification` (judul "Pendaftaran baru diajukan"; isi "{sekolah}: {n} siswa menunggu verifikasi."; url `/verifikasi`) → `toPermission('verifikasi.putuskan', …)`. `EntryDecidedNotification` (judul "Pendaftaran diverifikasi" / "Perlu perbaikan" / "Pendaftaran ditolak"; isi "{nama siswa}" + catatan ringkas; url `/pendaftaran/{id}`) → `toSchool`.
- Log `pendaftaran`: `pendaftaran.diajukan`, `pendaftaran.diajukan-ulang`, `pendaftaran.diputuskan` (subjek = `Registration`; properti jumlah/keputusan), label di `lang/id/activity.php` (jenis `pendaftaran`, subjek `Registration` "Pendaftaran").
- Menu: "Pendaftaran" (`/pendaftaran`, ikon `clipboard-list`, `pendaftaran.lihat`) di grup "Data Siswa"; grup baru "Verifikasi" berisi "Antrean verifikasi" (`/verifikasi`, ikon `badge-check`, `verifikasi.lihat`).
- Detail siswa (`Siswa/Show`) menampilkan kartu "Pendaftaran" berisi entri siswa itu (status, pendaftaran, tautan).

### 3.7 Pengujian (Pest)

- Transisi: semua yang sah; ilegal (mis. `draf → terverifikasi`, `terverifikasi → diajukan`, putuskan dua kali) ditolak; catatan wajib pada fix dan reject.
- Submit: semua eligible → `diajukan` dengan riwayat dan notifikasi; satu siswa tidak eligible → semua gagal (atomik) dengan alasan; siswa dengan entri aktif di pendaftaran lain ditolak; minimal satu siswa.
- Pendaftaran ganda bersamaan: dua pendaftaran draf berisi siswa yang sama diajukan berurutan → yang kedua gagal; indeks unik terbukti menolak penyisipan langsung entri aktif kedua.
- Scope: operator A tidak melihat/mengubah/mengajukan pendaftaran sekolah B (404); antrean verifikasi menampilkan semua sekolah bagi petugas.
- Kunci data siswa: edit, hapus, dan unggah/hapus dokumen ditolak saat `diajukan`, `terverifikasi`; diizinkan saat `perlu_perbaikan` dan `draf`; hapus siswa dengan entri apa pun ditolak.
- Eligibility: siswa dengan entri aktif tidak eligible; filter `eligible` daftar siswa konsisten.
- Notifikasi: submit → pemilik `verifikasi.putuskan` (bukan operator); keputusan → hanya operator sekolah terkait (staf kecamatan dan operator sekolah lain tidak).
- Pengajuan ulang hanya untuk `perlu_perbaikan`; draf hanya dapat diubah/dihapus selama belum diajukan; matriks izin untuk semua rute baru.

**Exit criteria BE:** semua tes lulus (Pest + Larastan + Pint); tidak ada akses lintas sekolah; indeks unik terbukti menjaga satu entri aktif per siswa.

## 4. Frontend

**Prasyarat desain:** tambahkan ke panduan UI bagian **5.21 Pendaftaran (daftar, form, detail)**, **5.22 Pemilih siswa (`StudentMultiSelect`)**, **5.23 Antrean verifikasi**, dan **5.24 Keputusan verifikasi** sebelum implementasi FE.

### 4.1 Daftar dan form pendaftaran
- **`/pendaftaran`** (pola daftar yang ada): judul "Pendaftaran", tombol "Buat pendaftaran" (`pendaftaran.buat`); filter cari judul, Status (Semua/Draf/Diajukan/…), Sekolah (staf). Kolom Pendaftaran (judul, tanggal dibuat), Sekolah (staf), Peserta (jumlah), Ringkasan status (chip berteks per status, mis. "Diajukan 12 · Terverifikasi 3"), Aksi (Lihat, Ubah dan Hapus bila draf). State loading, kosong, hasil filter kosong.
- **Form** (`/pendaftaran/buat`, `/{id}/ubah`): kartu "Detail pendaftaran" (Judul/keterangan wajib, Perkiraan jumlah peserta opsional, Sekolah hanya staf saat buat; mengganti sekolah memuat ulang kandidat lewat partial reload) dan kartu **Pilih siswa** (`StudentMultiSelect`). Tombol "Simpan draf" dan "Ajukan" (dialog konfirmasi "Ajukan N siswa untuk diverifikasi? Setelah diajukan pendaftaran tidak dapat diubah."); galat submit menampilkan daftar siswa bermasalah beserta alasannya.

### 4.2 `StudentMultiSelect`
Daftar kandidat dengan pencarian (nama/NISN), kotak centang per baris, "Pilih semua yang eligible", penghitung "N dipilih", baris tidak eligible dinonaktifkan dengan alasan tampil sebagai teks (bukan hanya warna) dan tautan "Lengkapi dokumen" ke detail siswa bila dokumen kurang. Dapat dioperasikan keyboard; penghitung diumumkan lewat `role="status"` (debounce seperti matriks izin). Mobile: baris menumpuk dan tombol aksi menempel di bawah (pola bar aksi Role).

### 4.3 Detail pendaftaran
Header (judul, sekolah, pembuat, waktu diajukan), ringkasan status, tabel entri (Siswa, Status badge berteks, Catatan petugas untuk `perlu_perbaikan`/`ditolak`, Aksi: "Perbaiki data" ke form ubah siswa dan "Ajukan ulang" bila `perlu_perbaikan`; tombol "Riwayat" membuka dialog timeline per siswa). Draf: tombol Ubah, Ajukan, Hapus.

### 4.3.1 Timeline riwayat
Komponen `StatusTimeline` (dialog atau kartu): daftar kronologis `waktu · status lama → baru · oleh · catatan`, tersusun vertikal dengan penanda titik; dipakai di detail pendaftaran, halaman keputusan, dan detail siswa.

### 4.4 Verifikasi
- **`/verifikasi`** antrean: filter cari nama dan Sekolah; tabel Siswa, Sekolah, Pendaftaran, Diajukan (waktu relatif), Aksi "Periksa" (ke halaman keputusan).
- **`/verifikasi/{entry}`**: kartu data siswa (NIK dimasker, tanggal lahir, umur, gender, sekolah), kartu Dokumen (tautan unduh KK dan akta), badge dan alasan eligibility, riwayat, dan kartu **Keputusan**: pilihan Setujui / Minta perbaikan / Tolak (radio berteks), kolom Catatan (wajib untuk dua yang terakhir), tombol "Simpan keputusan" (dengan konfirmasi untuk Tolak); setelah simpan kembali ke antrean dengan toast. Entri yang sudah diputuskan menampilkan keputusan dan tidak dapat diubah.

### 4.5 Kartu pendaftaran di detail siswa
Daftar entri siswa (status badge, judul pendaftaran, tautan) pada `Siswa/Show`; kosong → "Belum pernah didaftarkan."

**Exit criteria FE:** panduan UI 5.21–5.24 terimplementasi; alur buat draf → pilih siswa → ajukan → keputusan → perbaikan → ajukan ulang berjalan di desktop dan mobile; `impeccable` dan `ponytail` selesai.

## 5. Risiko dan catatan

- Pengajuan bersamaan diselesaikan di basis data (indeks unik), bukan hanya validasi aplikasi; aplikasi menangkap pelanggaran dan memberi pesan jelas.
- Satu notifikasi per keputusan siswa dapat banyak bila petugas memutuskan banyak siswa; penggabungan ditunda (v1).
- Daftar kandidat memuat semua siswa sekolah di satu respons (cukup untuk ribuan baris); pindah ke pencarian server bila terbukti lambat.
- Kunci data siswa adalah tambahan di luar spec v1; dapat dilonggarkan bila terlalu ketat di lapangan.
- Penjadwalan (Fase 5) memakai kolom `schedule_id` dan status `terjadwal` yang sudah tersedia; Fase 5 hanya menambah transisi dan layanan.
