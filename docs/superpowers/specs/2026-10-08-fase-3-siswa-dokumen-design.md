# Fase 3 — Siswa dan Dokumen: Desain

Tanggal: 2026-10-08. Turunan dari `2026-10-07-sijebol-v1-design.md` (bagian 3.2, 3.3 eligibility, 5, 6, 9) dan rencana sprint Fase 3. Mencakup Track BE dan Track FE. Flow tambahan yang muncul kemudian ditambahkan setelah fase ini selesai (keputusan user).

## 1. Tujuan dan batasan

Operator sekolah memiliki data siswa yang bersih dan dokumen persyaratannya (KK dan akta lahir): input satu per satu atau impor Excel, unggah dokumen, dan sistem menunjukkan siswa yang layak (eligible) untuk didaftarkan di Fase 4. Sukses: operator hanya melihat siswa sekolahnya; NIK dimasker di tampilan dan log; dokumen tidak pernah punya URL publik; impor sebagian-gagal melaporkan baris salah tanpa membatalkan baris benar.

**Di luar cakupan:** pendaftaran dan multi-select (Fase 4), unduh massal/zip, OCR atau verifikasi isi dokumen, foto siswa, kelas/jurusan, pembaruan data lewat impor (impor hanya menambah).

**Asumsi (dikonfirmasi user, 2026-10-08):**
- Eligibility Fase 3 = umur 16–17 pada hari ini + data wajib lengkap + KK dan akta terunggah. Syarat "tidak punya entri aktif di pendaftaran lain" ditambahkan di Fase 4.
- Satu berkas per jenis dokumen; unggah ulang menggantikan yang lama. Tipe PDF/JPG/PNG, maksimal 5 MB (satu konstanta).
- Hapus siswa = hapus permanen beserta berkas, dicatat di log. Fase 4 menambah larangan bila siswa punya pendaftaran.
- NIK unik se-kecamatan; pesan duplikat tidak menyebut sekolah pemiliknya.
- **NISN wajib** (10 digit angka) bersama NIK, nama, tanggal lahir, jenis kelamin.
- Dokumen diunggah di halaman detail siswa, bukan di form tambah.
- Admin kecamatan dapat memilih sekolah; operator sekolah dikunci ke sekolahnya.

## 2. Keputusan arsitektur

- **Disk privat:** dokumen di disk `local` (`storage/app/private`, sudah ada), nama berkas acak (`students/{student_id}/{uuid}.{ext}`), tanpa URL publik. Unduh lewat controller yang memeriksa permission dan scope sekolah.
- **Impor:** `maatwebsite/excel` v4 (sudah terpasang) membaca baris; tiap baris divalidasi dengan aturan yang sama dengan form (`StudentRules`), baris benar disimpan, baris salah dikumpulkan `{row, reason}`. Bukan CSV mentah karena pengguna terbiasa dengan .xlsx.
- **Domain baru `app/Domains/Students`** dengan pola yang ada (Controller → FormRequest/Service → Repository interface + Eloquent), memakai trait `ScopedBySchool` yang ada.
- **Eligibility** dipisah sebagai layanan murni (`EligibilityService`) agar Fase 4 cukup menambah syarat dan memanggilnya saat submit.

## 3. Backend

### 3.1 Data

**`students`:** `id`, `school_id` (FK `schools`, indeks), `nik` (string 16, unik), `nisn` (string 10), `name`, `birth_date` (date), `gender` (`L`|`P`), timestamps. Unik `nik`; indeks `(school_id, name)`.

**`student_documents`:** `id`, `student_id` (FK cascade on delete), `type` (`kk`|`akta_lahir`), `path`, `mime`, `size` (unsigned int), timestamps; unik `(student_id, type)`.

**`Student`:** `ScopedBySchool`, `LogsActivity` (log `siswa`, atribut `nik` ikut tetapi dimasker saat dibaca oleh `ActivityPropertyMasker`), relasi `school`, `documents`. Accessor `age(?CarbonInterface $on)`.

### 3.2 Aturan validasi (`StudentRules`, dipakai FormRequest dan impor)

- `nik`: wajib, `digits:16`, unik (pesan: "NIK sudah terdaftar.").
- `nisn`: wajib, `digits:10`.
- `name`: wajib, string, maks 150.
- `birth_date`: wajib, tanggal, sebelum atau sama dengan hari ini; impor menerima `YYYY-MM-DD` dan `DD/MM/YYYY`.
- `gender`: wajib, `L` atau `P` (impor menerima juga "Laki-laki"/"Perempuan", tak peka huruf besar).
- `school_id`: wajib untuk staf tanpa sekolah, ditiadakan untuk operator sekolah (dipaksa ke `school_id`-nya); sekolah harus ada.

### 3.3 Service dan endpoint

| Method | URL | Izin | Aksi |
|---|---|---|---|
| GET | `/siswa` | `siswa.lihat` | Daftar (cari nama/NIK/NISN, `filter[docs]=lengkap\|kurang`, `filter[eligible]=1`, `filter[school_id]` untuk staf), 20 per halaman |
| GET | `/siswa/buat` | `siswa.buat` | Form tambah |
| POST | `/siswa` | `siswa.buat` | Simpan, redirect ke detail |
| GET | `/siswa/{student}` | `siswa.lihat` | Detail + dokumen + eligibility |
| GET | `/siswa/{student}/ubah` | `siswa.ubah` | Form ubah |
| PUT | `/siswa/{student}` | `siswa.ubah` | Perbarui |
| DELETE | `/siswa/{student}` | `siswa.hapus` | Hapus permanen |
| POST | `/siswa/{student}/dokumen/{type}` | `dokumen.unggah` | Unggah/ganti (multipart `file`) |
| GET | `/siswa/{student}/dokumen/{type}` | `dokumen.lihat` | Unduh (stream), catat `dokumen.diunduh` |
| DELETE | `/siswa/{student}/dokumen/{type}` | `dokumen.hapus` | Hapus dokumen |
| GET | `/siswa/impor/template` | `siswa.impor` | Unduh template .xlsx |
| POST | `/siswa/impor` | `siswa.impor` | Impor; JSON/flash hasil `{imported, failed:[{row,reason}]}` |

Rute `impor/*` didefinisikan sebelum `{student}` agar tidak tertangkap parameter. Siswa sekolah lain → 404 (global scope).

**Dokumen:** validasi `mimes:pdf,jpg,jpeg,png` + `max:5120` (KB); `type` di luar `kk|akta_lahir` → 404. Alur unggah: simpan berkas baru → dalam transaksi upsert baris → hapus berkas lama; bila transaksi gagal, berkas baru dihapus (tidak ada berkas yatim dan tidak ada baris tanpa berkas). Hapus siswa/dokumen menghapus berkas dari disk. Unduh memakai `Storage::disk('local')->download` dengan nama berkas aman (`{type}-{nisn}.{ext}`) dan `Content-Disposition: attachment`; berkas hilang di disk → 404.

**Impor:** `StudentImportService::import(UploadedFile, User): array{imported:int, failed:list<array{row:int, reason:string}>}`. Batas 500 baris data dan 2 MB; kolom dikenali dari baris judul (tak peka huruf besar, urutan bebas): `NIK`, `NISN`, `Nama`, `Tanggal lahir`, `Jenis kelamin`; kolom wajib hilang → error tunggal ("Kolom X tidak ditemukan"). Nomor baris = nomor baris di berkas (judul = baris 1). NIK ganda di dalam berkas yang sama: baris pertama masuk, berikutnya gagal ("NIK ganda di berkas"). Sekolah = sekolah operator; staf kecamatan harus memilih sekolah (`school_id` parameter wajib). Satu log ringkas `siswa.diimpor` (jumlah berhasil/gagal) per impor, bukan satu log per baris dari model.

**Eligibility:** `EligibilityService::check(Student, ?CarbonInterface $on = null): array{eligible: bool, reasons: list<string>}`; alasan: "Umur harus 16 atau 17 tahun (umur sekarang N).", "Data wajib belum lengkap.", "Kartu Keluarga belum diunggah.", "Akta kelahiran belum diunggah." Umur dihitung dari tanggal lahir pada tanggal acuan (default hari ini, zona Asia/Jakarta).

**Log dan masker:** model `Student`/`StudentDocument` memakai log `siswa`; `lang/id/activity.php` mendapat jenis `siswa`, subjek `Student`/`StudentDocument`, dan aksi `dokumen.diunduh`, `dokumen.diunggah`, `dokumen.dihapus`, `siswa.diimpor`. NIK dimasker pada tampilan daftar dan detail (kecuali pengguna dengan `siswa.ubah` melihatnya penuh di form ubah) dan di log (sudah oleh masker).

**Menu:** "Siswa" (`/siswa`, ikon `graduation-cap`, izin `siswa.lihat`) di grup baru "Data Siswa" tepat setelah "Master Data".

### 3.4 Pengujian (Pest)

- Scope: operator A tidak bisa melihat, mengubah, menghapus, atau mengunduh dokumen siswa sekolah B (404); staf tanpa sekolah melihat semua.
- Validasi: NIK 15/17 digit ditolak, NIK duplikat ditolak (lintas sekolah, pesan tanpa nama sekolah), tanggal lahir masa depan ditolak, gender tak valid ditolak.
- Dokumen: tipe/ukuran ditolak, unggah ulang mengganti berkas lama (hanya satu berkas di disk), kegagalan simpan tidak meninggalkan berkas, hapus siswa menghapus berkas, unduh memeriksa izin dan scope, unduh tercatat di log, `type` tak dikenal → 404.
- Impor: sebagian gagal (baris benar masuk, baris salah dilaporkan dengan nomor), NIK ganda dalam berkas, kolom wajib hilang, melebihi 500 baris, format tanggal ganda, staf wajib memilih sekolah, operator tidak bisa menyuntikkan `school_id` lain.
- Eligibility: batas umur (15 th 364 hari ✗; tepat ulang tahun ke-16 ✓; sehari sebelum ulang tahun ke-18 ✓; tepat ulang tahun ke-18 ✗), tanpa KK/akta ✗ dengan alasan tepat, data lengkap + dokumen lengkap ✓.
- Matriks izin: semua rute baru ditambahkan ke `RoutePermissionMatrixTest`.
- Log: perubahan siswa tercatat dengan NIK termasking di respons `/log`.

**Exit criteria BE:** semua tes lulus (Pest + Larastan + Pint); tidak ada akses lintas sekolah di tes; tidak ada berkas yatim pada skenario gagal.

## 4. Frontend

**Prasyarat desain:** tambahkan bagian **5.18 Siswa (daftar, form, detail)**, **5.19 Dokumen siswa**, dan **5.20 Impor Excel** ke panduan UI sebelum implementasi FE (isi mengikuti bagian di bawah).

### 4.1 Daftar siswa (`/siswa`, `siswa.lihat`)
Pola `Pengguna/Index`: judul "Siswa", tombol "Tambah siswa" (`siswa.buat`) dan "Impor Excel" (`siswa.impor`); filter cari (nama/NIK/NISN), Kelengkapan dokumen (Semua/Lengkap/Belum lengkap), Eligible (Semua/Ya), dan Sekolah (hanya staf tanpa sekolah). Tabel: Siswa (nama, NISN kecil), NIK (dimasker), Umur, Sekolah (hanya staf), Dokumen ("KK ✓ · Akta ✗" sebagai dua indikator berteks), Status (badge "Eligible" hijau atau "Belum eligible" netral dengan alasan sebagai `title`/teks sr-only), Aksi (menu: Lihat detail, Ubah, Hapus dengan konfirmasi). State: loading, kosong ("Belum ada siswa" + tombol tambah/impor), hasil filter kosong.

### 4.2 Form tambah/ubah (`/siswa/buat`, `/siswa/{id}/ubah`)
Pola `Pengguna/Form`: NIK (numerik, 16 digit), NISN (10 digit), Nama lengkap, Tanggal lahir (`<input type="date">`), Jenis kelamin (`SelectField` Laki-laki/Perempuan), Sekolah (`SelectField`, hanya staf; operator tidak melihat kolom). Validasi klien dengan pesan sama dengan server, fokus ke kolom pertama yang salah. Setelah simpan → detail siswa (di sana unggah dokumen).

### 4.3 Detail siswa (`/siswa/{id}`)
Kartu profil (inisial, nama, NISN, badge eligible dan alasan bila belum), tombol Ubah dan menu "…" (Hapus), `InfoList` (NIK dimasker, tanggal lahir + umur, jenis kelamin, sekolah), kartu **Dokumen** dengan dua `FileUpload` (Kartu Keluarga, Akta Kelahiran). Kartu aktivitas per siswa tidak dibuat di fase ini (log siswa tersedia lewat halaman Log Aktivitas).

### 4.4 `FileUpload` (komponen bersama)
Props: `label`, `accept`, `maxBytes`, `existing` (`{name, size, downloadUrl}` atau null), `canUpload`, `canDelete`. Status: kosong (area "Pilih berkas atau seret ke sini", teks tipe dan batas), memilih (nama, ukuran, tombol Unggah/Batal), mengunggah (progress), terunggah (nama, ukuran, Unduh, Ganti, Hapus dengan konfirmasi), galat (pesan di bawah). Validasi klien: tipe dan ukuran sebelum kirim, pesan sama dengan server. Dapat dioperasikan keyboard; progress diumumkan lewat `role="status"`.

### 4.5 Dialog impor Excel
Tombol "Impor Excel" membuka dialog: langkah 1 "Unduh template" (tautan), langkah 2 pilih berkas .xlsx (maks 2 MB), tombol "Impor". Hasil: ringkasan "N siswa berhasil ditambahkan, M baris gagal" dan bila ada yang gagal tabel Baris / Alasan (maks tinggi dengan scroll) serta tombol "Tutup". Staf kecamatan memilih sekolah di dialog sebelum mengimpor.

### 4.6 Utilitas
`calculateAge(birthDate, on?)` dan `maskNik(nik)` dikembalikan di `lib/date.ts` dan `lib/format.ts` dengan tes (umur tepat di hari ulang tahun; NIK 16 digit → `3201********1234`; NIK pendek termasking penuh).

**Exit criteria FE:** panduan UI 5.18–5.20 terimplementasi; alur tambah → unggah dokumen → eligible berjalan; impor sebagian gagal tampil benar; desktop dan mobile tanpa overflow; review `impeccable` dan `ponytail` selesai.

## 5. Risiko dan catatan

- Berkas besar/serangan unggah: batas 5 MB dan whitelist mime; validasi mime dilakukan dari isi berkas (bukan hanya ekstensi) oleh `mimes`.
- Impor 500 baris dijalankan sinkron di request (cukup untuk v1); pindah ke queue bila batas dinaikkan.
- NIK unik se-kecamatan berarti operator dapat mengetahui bahwa sebuah NIK sudah terdaftar di tempat lain; pesan tidak menyebut sekolahnya, dan ini perlu untuk mencegah data ganda.
- Item memory `deferred-items` untuk Fase 3 (util FE, label log, menu) sudah tercakup di spec ini.
