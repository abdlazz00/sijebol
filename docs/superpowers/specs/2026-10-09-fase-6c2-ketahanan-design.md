# Fase 6c-2 — Ketahanan: desain

Tanggal: 2026-10-09. Bagian kedua dari tiga sub-fase 6c (6c-1 hardening selesai; 6c-3 polesan UI dan aksesibilitas menyusul). Rilis ditahan sampai 6c selesai. Item berasal dari daftar tertunda Fase 2, 4, dan 5 (memory `deferred-items`): state "gagal memuat", pemilih siswa yang memuat semua siswa sekolah sekaligus, dan impor Excel yang berjalan sinkron.

**Tujuan:** aplikasi tetap dapat dipakai dan jelas bagi pengguna saat jaringan atau proxy bermasalah, form pendaftaran tidak melambat untuk sekolah dengan ratusan siswa, dan impor Excel tidak menahan request.

**Sukses:** kegagalan jaringan atau respons non-Inertia menampilkan banner dengan "Coba lagi" yang mengulang kunjungan yang gagal (tidak ada modal HTML mentah); pemilih siswa hanya memuat 20 siswa per halaman dengan pencarian server dan pilihan lintas halaman tidak hilang; impor diproses di queue, hasilnya (jumlah dan baris gagal) tersimpan dan dapat dibuka lagi lewat notifikasi, dan berkas unggahan berisi NIK dihapus setelah diproses.

**Di luar cakupan:** WebSocket atau progres per baris, retry otomatis impor, pemangkasan riwayat impor, penanganan kegagalan di layout autentikasi (login), unggah dokumen lewat queue.

**Keputusan (dikonfirmasi user, 2026-10-09):** (1) banner global di `AppLayout`, bukan blok per halaman daftar; (2) impor lewat queue dengan hasil disimpan di tabel dan dibuka lewat notifikasi; (3) pemecahan 6c-1/6c-2/6c-3 dan impor queue dikerjakan di 6c-2.

**Keputusan saya:**
- **K1:** "Coba lagi" mengulang kunjungan terakhir yang dimulai (dilacak lewat peristiwa `before`), bukan `router.reload()`, karena reload meminta URL halaman saat ini yang bisa berbeda dari kunjungan yang gagal (mis. filter).
- **K2:** Hanya `exception` (kegagalan jaringan) dan `invalid` (respons non-Inertia) yang ditangani; respons error aplikasi (403/404/429/500) sudah berupa halaman Inertia `Error` dan tidak berubah.
- **K3:** Impor dilarang diulang otomatis (`$tries = 1`); NIK unik membuat percobaan ulang melaporkan semua baris sebagai ganda. Kegagalan job menandai impor `gagal` dengan pesan.
- **K4:** Hasil impor dapat dilihat oleh pembuatnya dan staf kecamatan (`school_id` kosong) pemegang `siswa.impor`; operator sekolah lain mendapat 404.

## 1. Banner "gagal memuat" global

- **Composable `resources/js/composables/useLoadFailure.ts`** (satu instansi bersama): state `failed: boolean` dan `lastVisit` (url, method, data, opsi). Pemasangan sekali dari `AppLayout`: `router.on('before', ...)` menyimpan kunjungan (mengabaikan prefetch), `router.on('start' | 'success', ...)` mengosongkan `failed`, `router.on('exception', ...)` dan `router.on('invalid', ...)` menyetel `failed = true` (`invalid` memanggil `preventDefault()` agar modal HTML Inertia tidak muncul). `retry()` mengunjungi ulang `lastVisit`; `dismiss()` mengosongkan.
- **Komponen `Components/shared/LoadFailedBanner.vue`:** `InlineAlert` variant danger (`role="alert"`), teks "Gagal memuat data. Periksa koneksi Anda lalu coba lagi.", tombol **Coba lagi** dan **Tutup**.
- **`AppLayout`:** memasang composable dan menaruh banner di atas slot konten. Layout autentikasi tidak dicakup.
- **Vitest:** logika composable diuji dengan memanggil penangan peristiwa yang diekspor (kegagalan menyetel `failed`, kunjungan baru mengosongkan, retry memakai kunjungan terakhir, prefetch diabaikan).

## 2. Pemilih siswa berbasis pencarian server

**Backend**
- `StudentRepositoryInterface::searchForSchool(int $schoolId, ?string $q, int $perPage = 20): LengthAwarePaginator`: siswa sekolah itu, `where(name like %q% atau nisn like %q%)` bila `q` terisi, urut nama lalu id, memuat `documents` dan `registrationEntries`. `StudentService::candidatesPage(...)` meneruskannya.
- `RegistrationController::candidates()` (dipakai `create` dan `edit`): membaca `q` dan `page` dari query; `candidates` kini bentuk paginator (`data`, `links`, `from`, `to`, `total`, `per_page`) berisi `CandidateRow` hanya untuk halaman itu (kelayakan dihitung per siswa halaman itu). Prop baru `selected: CandidateRow[]` berisi siswa dari entri pendaftaran yang sedang diubah (kosong saat buat), dengan kelayakan dan alasan seperti baris biasa.
- Validasi saat pengajuan di server tidak berubah (kelayakan diperiksa ulang untuk semua entri).

**Frontend**
- `StudentMultiSelect.vue`: kotak cari dengan debounce memicu partial reload `only: ['candidates']` dengan `q` dan `page` (pola yang sudah dipakai form saat ganti sekolah); tombol halaman sebelumnya/berikutnya memicu reload yang sama. Pilihan disimpan sebagai peta id → `CandidateRow` di klien sehingga pilihan dari halaman lain tetap tampil sebagai daftar "Terpilih (N)" dengan tombol hapus per siswa. "Pilih semua yang eligible" menjadi "Pilih semua yang eligible di halaman ini".
- `Pendaftaran/Form.vue`: nilai awal `form.student_ids` dari `selected`; pergantian sekolah (staf) mengosongkan pilihan seperti sekarang.
- Tipe `CandidateRow` tetap; ditambah tipe `CandidatePage` untuk bentuk paginator.

## 3. Impor Excel lewat queue

**Data:** migrasi `student_imports`: `id`, `user_id` (FK users), `school_id` (FK schools), `status` (`antri`|`berjalan`|`selesai`|`gagal`, default `antri`), `original_name`, `path` (nullable setelah berkas dihapus), `imported` (unsigned int default 0), `failed` (json nullable: daftar `{row, reason}`), `error` (text nullable), `started_at`, `finished_at`, timestamps; indeks `(user_id, id)`. Model `App\Domains\Students\Models\StudentImport`.

**Alur**
1. `POST /siswa/impor` (`siswa.impor.store`, tetap `can:siswa.impor`, throttle 6/menit): validasi berkas seperti sekarang (`mimes:xlsx`, maks 2 MB, sekolah) lalu **pra-periksa sinkron** (baca terbatas: kolom wajib dan maksimal 500 baris; galat tetap berupa galat validasi `file` seketika). Berkas disimpan di disk privat `imports/{uuid}.xlsx`, baris `student_imports` dibuat (`antri`), `ImportStudentsJob` didispatch, lalu redirect kembali dengan flash `import` berisi `{id}` (menggantikan flash `import_result`).
2. `ImportStudentsJob(int $importId)` (`ShouldQueue`, `$tries = 1`, `$timeout = 120`): status `berjalan`, memproses baris dengan logika `importRow` yang ada (satu log ringkas `siswa.diimpor` dengan pembuat impor sebagai penyebab), menulis `imported`, `failed`, status `selesai`, **menghapus berkas dan mengosongkan `path` di blok `finally`**, lalu mengirim `StudentImportFinishedNotification` ke pembuatnya. `failed(Throwable)` menandai `gagal` dengan pesan umum (bukan isi berkas) dan tetap menghapus berkas dan memberi tahu.
3. `GET /siswa/impor/{import}` (`siswa.impor.show`, `can:siswa.impor`, didefinisikan sebelum rute `siswa/{student}`): JSON `{id, status, imported, failed, error}`; hanya pembuat atau staf kecamatan (K4), selain itu 404.
4. `StudentImportFinishedNotification` (judul "Impor siswa selesai" atau "Impor siswa gagal", isi "{n} siswa ditambahkan, {m} baris gagal." atau pesan gagal, tautan `/siswa?impor={id}`), via `database` dan `mail` seperti notifikasi lain.
5. `GET /siswa` menerima `?impor={id}`: bila impor milik pengguna (aturan K4) memuat prop `recent_import` (bentuk sama dengan JSON di atas) sehingga dialog terbuka otomatis dengan hasilnya.

**Layanan:** `StudentImportService::import()` dipecah menjadi `prepare(UploadedFile, User, ?int $schoolId): StudentImport` (validasi sekolah, pra-periksa, simpan berkas, buat baris, dispatch) dan `run(StudentImport): void` (isi job). Pembacaan berkas menerima path di disk privat. Logika baris, aturan, dan batas 500 tidak berubah.

**Frontend (`ImportStudentsDialog.vue`):** setelah kirim berhasil dan flash `import` ada: layar "Impor diproses" dengan indikator dan polling `fetch` JSON tiap 2 detik ke `siswa.impor.show` sampai `selesai`/`gagal`, lalu menampilkan hasil yang sama seperti sekarang (jumlah berhasil dan tabel baris gagal) atau pesan gagal; bila status masih `antri` lebih dari 20 detik tampil petunjuk "Menunggu proses latar belakang; pastikan queue worker berjalan." Dialog boleh ditutup saat menunggu (notifikasi menyusul). Dialog menerima prop `recentImport` untuk membuka hasil dari `?impor={id}`. Tipe `ImportResult` diganti `ImportStatus`.

**Operasional:** impor kini butuh queue worker (`php artisan queue:work`) di lokal; di produksi worker sudah dibutuhkan oleh notifikasi dan email. `docs/operasional-queue.md` diperbarui.

## 4. Pengujian

- **Backend:** `prepare` menolak berkas bermasalah seketika (kolom, >500 baris, sekolah) tanpa membuat baris; job (queue `sync` pada tes) memproses baris benar dan melaporkan baris salah dengan nomor baris, menyimpan hasil, menghapus berkas, mencatat satu log, dan memberi tahu pembuat; kegagalan job menandai `gagal`, menghapus berkas, dan memberi tahu; `show` hanya untuk pembuat atau staf (404 untuk operator lain); `?impor=` memuat `recent_import` hanya bila berhak; pemilih siswa: `searchForSchool` mencari nama dan NISN, memaginasi 20, tidak membocorkan siswa sekolah lain, `selected` memuat siswa entri yang diubah; tes impor lama diadaptasi ke alur baru (flash `import`, hasil dibaca dari tabel).
- **Frontend (Vitest):** composable `useLoadFailure`; logika peta pilihan pemilih siswa bila diekstrak.
- **Browser (1440 dan 390 px):** matikan jaringan (`context.setOffline(true)`) lalu ganti filter/halaman untuk memunculkan banner dan "Coba lagi" setelah jaringan kembali; pemilih siswa dengan data sekolah yang lebih dari 20 siswa (data demo sementara, dipulihkan); impor dengan queue worker berjalan dan tanpa worker (petunjuk 20 detik); tanpa overflow horizontal, konsol bersih.
- **Review:** `ponytail:ponytail-review` untuk semua perubahan dan `impeccable` untuk frontend, sebelum klaim selesai.

## 5. Catatan teknis

- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; dataset Pest tanpa closure; tes yang memicu notifikasi memakai `Notification::fake()` dan membuat permission yang dicari; helper tes global bernama unik; Larastan menolak `->pluck()->isNotEmpty()`; `UploadedFile::fake()` menebak MIME dari nama (pakai berkas sungguhan untuk uji isi); jangan matikan Vite dev server milik user; bila klik asli Playwright tidak menghasilkan event jalankan `browser_close`.
- Panduan UI diperbarui: 5.20 (Impor Excel: proses latar belakang), 5.22 (pemilih siswa berpaginasi dan "Terpilih"), dan pola lintas halaman (banner gagal memuat).
