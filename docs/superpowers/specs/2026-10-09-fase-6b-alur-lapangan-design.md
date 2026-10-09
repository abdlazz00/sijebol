# Fase 6b — Alur lapangan: desain

Tanggal: 2026-10-09. Bagian kedua Fase 6 (6a beranda dan laporan sudah selesai; 6c hardening menyusul; bagian rilis ditahan). Item berasal dari daftar tertunda Fase 4 dan 5 (memory `deferred-items`) yang disetujui user masuk 6b.

**Tujuan:** menutup celah operasional yang pasti muncul di lapangan: salah tandai rekaman, jadwal salah buat atau salah tempat, kabar penandaan yang tidak sampai ke sekolah, dan antrean verifikasi yang lambat bila sekolah mengajukan puluhan siswa sekaligus.

**Sukses:** setiap koreksi tercatat di riwayat status dan log aktivitas tanpa menghapus jejak; siswa tidak pernah berada di dua jadwal atau memegang dua entri aktif; operator sekolah menerima notifikasi untuk setiap penandaan dan pembatalannya; persetujuan massal bersifat atomik (satu entri bermasalah membatalkan seluruhnya dengan pesan yang menyebut siswanya).

**Di luar cakupan:** koreksi langsung antar-status akhir (cukup batalkan lalu tandai ulang), hapus jadwal yang masih punya entri, pindah massal antar-jadwal, penggabungan notifikasi yang sudah terkirim, tolak atau minta-perbaikan massal, kapasitas dan jam kegiatan (kelompok D, tetap tertunda).

**Keputusan (dikonfirmasi user, 2026-10-09):** tujuh item sesuai usulan: (1) batalkan penandaan, (2) hapus jadwal kosong, (3) pindah antar-jadwal, (4) notifikasi penandaan, (5) notifikasi massal verifikasi, (6) jadwal terdekat-dulu, (7) setujui massal.

**Keputusan saya (diambil saat merancang):**
- **K1:** Pembatalan penandaan dilakukan satu entri per aksi (bukan massal); koreksi jarang terjadi dan satu entri memudahkan catatan dan pesan galat. Layanannya menerima satu entri.
- **K2:** Hapus jadwal memakai izin `jadwal.ubah` (tidak menambah permission `jadwal.hapus`; seeder, matriks role, dan UI izin tidak perlu berubah).
- **K3:** Pindah antar-jadwal dibatasi ke jadwal sekolah yang sama dan tanggal hari ini atau setelahnya; dari jadwal yang sudah lewat tetapi belum ditandai tetap boleh dipindahkan (sejalan dengan "keluarkan" kapan pun selama Terjadwal).
- **K4:** Notifikasi massal verifikasi memakai kelas `EntryDecidedNotification` yang ada; ringkasan siswa dibentuk di service ("3 siswa: A, B, C." atau "5 siswa: A, B, dan 3 lainnya.") tanpa kelas baru.
- **K5:** Persetujuan massal memeriksa ulang kelayakan (umur pada tanggal pengajuan, dokumen KK dan akta, tidak ada entri aktif lain) untuk setiap entri; penyetujuan satuan di halaman detail tidak diubah.

## 1. Backend

### 1.1 Mesin status

`RegistrationStatus::next()` ditambah: `SudahRekaman`, `TidakHadir`, dan `BelumRekaman` masing-masing `→ [Terjadwal]` ("batalkan penandaan"). Peta lain tidak berubah. Tes peta transisi Fase 5 (`ScheduleModelTest`) diperbarui. Semua perubahan status tetap lewat `StatusTransitioner::move`.

### 1.2 Batalkan penandaan (item 1)

`RecordingService::undo(RegistrationStudent $entry, string $note, User $actor): void`:
- Entri harus berstatus akhir (`SudahRekaman`, `TidakHadir`, `BelumRekaman`) dan `schedule_id` tidak kosong; selain itu `AccessRuleException`.
- Di dalam transaksi: periksa siswa tidak punya entri aktif lain (`RegistrationStatus::active()`, id berbeda) karena `Tidak hadir` dan `Belum rekaman` melepas slot aktif dan siswa mungkin sudah didaftarkan ulang. Bila ada: `AccessRuleException("Siswa sudah memiliki pendaftaran aktif lain; penandaan tidak dapat dibatalkan.")`. Pelanggaran indeks unik `active_student_id` akibat balapan (`UniqueConstraintViolationException`) dipetakan ke pesan yang sama.
- `StatusTransitioner::move($entry, Terjadwal, $actor, $note)`; `schedule_id` dipertahankan. Catatan wajib (maks 500).
- Log aktivitas `rekaman.dibatalkan` (subjek jadwal, properti `status_lama`, `siswa_id`). Setelah transaksi: `ScheduleNotification::undone(...)` ke operator sekolah.
- Rute `POST jadwal/{schedule}/rekaman/{entry}/batalkan` (`can:rekaman.tandai`, `scopeBindings`, throttle 60/menit; tanpa permission baru), `UndoMarkingRequest` (`note` required string max 500), controller `RecordingController::undo`, nama rute `jadwal.rekaman.batalkan`.

### 1.3 Notifikasi penandaan (item 4)

`ScheduleNotification` mendapat dua pabrik: `marked(scheduleId, count, statusLabel, date)` (judul "Rekaman ditandai", isi "{n} siswa ditandai {Status} pada {tanggal jadwal}.") dan `undone(scheduleId, statusLabel)` (judul "Penandaan dibatalkan", isi "Penandaan {Status} dibatalkan; siswa kembali Terjadwal."). `RecordingService::mark` mengirim satu notifikasi `marked` ke operator sekolah (`toSchool('jadwal.lihat', ..., $schedule->school_id)`) setelah transaksi; `undo` mengirim `undone`. `RecordingService` mendapat `NotificationService` lewat konstruktor.

### 1.4 Hapus jadwal (item 2)

`ScheduleService::delete(Schedule $schedule, User $actor): void`: bila `$schedule->entries()->exists()` lempar `AccessRuleException("Jadwal masih memiliki siswa; keluarkan atau batalkan penandaan mereka lebih dulu.")` (entri yang sudah ditandai tetap memegang `schedule_id`, jadi jadwal dengan riwayat tidak dapat dihapus). Selain itu catat log `jadwal.dihapus` lalu hapus. Rute `DELETE jadwal/{schedule}` (`jadwal.ubah`, throttle 60/menit), `ScheduleController::destroy`, repository mendapat `delete(Schedule)`; sukses mengarahkan ke `jadwal.index`. Log subjek yang terhapus ditampilkan `#id` (perilaku `ActivityLogService` yang ada).

### 1.5 Pindah antar-jadwal (item 3)

`ScheduleService::move(RegistrationStudent $entry, Schedule $target, User $actor): void`:
- Syarat: entri `Terjadwal`; `$target->id !== $entry->schedule_id`; `$target->school_id === $entry->school_id`; `! $target->isPast()`. Pelanggaran: `AccessRuleException` dengan pesan jelas.
- Di dalam satu transaksi dan satu kunci baris: `move` ke `Terverifikasi` (`schedule_id` null, catatan "Dipindahkan dari jadwal {tanggal lama}"), lalu `move` ke `Terjadwal` (`schedule_id` target). Riwayat memuat dua baris. Log `jadwal.dipindahkan` (subjek jadwal tujuan, properti `dari` dan `siswa_id`).
- Setelah transaksi: `ScheduleNotification::assigned(target, 1, ...)` ke operator sekolah.
- Rute `POST jadwal/{schedule}/siswa/{entry}/pindah` (`jadwal.ubah`, `scopeBindings`, throttle 60/menit), `MoveRequest` (`target_schedule_id` required integer exists), `ScheduleStudentController::move`. Target diselesaikan lewat `ScheduleService`/repository dengan scope sekolah (operator tidak dapat memilih jadwal sekolah lain: 404 atau galat validasi).

### 1.6 Urutan jadwal (item 6)

`EloquentScheduleRepository::paginate`: urutan `date >= hari_ini` dulu, lalu yang akan datang naik (terdekat), lalu yang lewat menurun (terbaru), tie-break `id`. Memakai `Schedule::today()`; filter Periode tidak berubah.

### 1.7 Setujui massal dan notifikasi massal (item 5 dan 7)

`VerificationService::approveMany(array $entryIds, User $actor): int`:
- Dalam satu transaksi: kunci entri (`lockForUpdate`, urut id); semua harus ada, berstatus `Diajukan`, dan satu per id unik; selain itu `ValidationException` (`entry_ids`: "Sebagian entri sudah diputuskan; muat ulang halaman lalu coba lagi.").
- Untuk setiap entri periksa kelayakan dengan `EligibilityService::check($student, $registration->submitted_at, $registration->id)` (siswa dimuat bersama `documents` dan `registrationEntries`). Bila ada yang tidak layak: `ValidationException` (`entry_ids`) berisi nama siswa dan alasan pertamanya, seluruh batch dibatalkan (K5).
- `StatusTransitioner::move($entry, Terverifikasi, $actor)` untuk tiap entri; log `pendaftaran.diputuskan` per entri dengan properti `massal: true`.
- Setelah transaksi: satu `EntryDecidedNotification` per pendaftaran (dikelompokkan `registration_id`) dengan ringkasan siswa (K4). Mengembalikan jumlah entri yang disetujui.
- Rute `POST verifikasi/setujui` (`verifikasi.putuskan`, throttle 30/menit), `ApproveManyRequest` (`entry_ids` required array min 1 max 50, `entry_ids.*` integer distinct), `VerificationController::approveMany`; nama rute `verifikasi.setujui`; sukses kembali ke `verifikasi.index` dengan pesan "N siswa disetujui."

## 2. Frontend

- **`Pages/Jadwal/Rekaman.vue`:** tabel "Sudah ditandai" mendapat kolom Aksi dengan tombol **Batalkan** per baris (hanya `can(RekamanTandai)`), membuka dialog berisi bidang **Catatan** (wajib) dan tombol "Batalkan penandaan". Teks "Status akhir tidak dapat diubah" diganti: "Status dapat dibatalkan oleh petugas dengan catatan." Konfirmasi simpan penandaan tidak lagi menyebut final. Petunjuk geser tabel tetap.
- **`Pages/Jadwal/Show.vue`:** tombol **Hapus jadwal** di header (hanya bila `can(JadwalUbah)` dan tanpa peserta; `ConfirmDialog`), aksi baris **Pindahkan** (dialog dengan `SelectField` jadwal tujuan dari prop `targets`; hanya `can(JadwalUbah)` dan status Terjadwal). Prop baru `targets: {id, label}[]` (jadwal sekolah sama yang belum lewat, tanpa jadwal ini).
- **`Pages/Jadwal/Index.vue`:** tidak berubah selain urutan dari server.
- **`Pages/Verifikasi/Index.vue`:** kolom ceklis (centang semua di halaman ini), bilah aksi "Setujui terpilih (N)" dengan `ConfirmDialog` ("Setujui N siswa? Kelayakan diperiksa ulang"), hanya untuk `can(VerifikasiPutuskan)`; galat `entry_ids` ditampilkan di `InlineAlert`; pilihan dikosongkan saat halaman atau filter berubah.
- **Panduan UI:** perbarui 5.24 (antrean verifikasi) dan 5.25–5.27 (jadwal, detail, penandaan), tambahkan pola dialog koreksi.

## 3. Pengujian

- **Mesin status:** peta transisi baru; tes `ScheduleModelTest` dan yang setara diperbarui.
- **Batalkan:** sukses mengembalikan Terjadwal dengan `schedule_id` utuh, riwayat dan log tercatat, notifikasi `undone`; ditolak untuk status non-akhir, tanpa catatan, dan saat siswa punya entri aktif lain; balapan indeks unik dipetakan ke pesan; operator tidak punya akses (403) dan entri sekolah lain 404.
- **Notifikasi penandaan:** satu notifikasi per aksi tandai, hanya operator sekolah itu; tes membuat permission terkait.
- **Hapus jadwal:** kosong terhapus dan tercatat; berisi (termasuk yang sudah ditandai) ditolak; `/log` tetap menampilkan baris subjek terhapus.
- **Pindah:** sukses atomik dengan dua baris riwayat dan satu notifikasi ke tujuan; ditolak untuk jadwal lain sekolah, jadwal lewat, jadwal yang sama, status bukan Terjadwal; satu entri tidak pernah berada di dua jadwal.
- **Urutan:** daftar memuat yang akan datang terdekat-dulu lalu yang lewat terbaru-dulu, dengan dan tanpa filter Periode.
- **Setujui massal:** sukses atomik dengan satu notifikasi per pendaftaran dan ringkasan benar; gagal total bila ada entri sudah diputuskan, tidak layak (umur, dokumen, entri aktif lain), id ganda, atau lebih dari 50; matriks izin dan throttle.
- **Matriks izin dan frontend:** baris rute baru di `RoutePermissionMatrixTest`; Vitest untuk logika pilihan massal bila diekstrak; verifikasi browser 1440 dan 390 px (tanpa overflow, konsol bersih) untuk batalkan, hapus, pindah, setujui massal.
- **Review:** `ponytail:ponytail-review` untuk semua perubahan dan `impeccable` untuk frontend, sebelum klaim selesai.

## 4. Catatan teknis

- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; dataset Pest tanpa closure; tes yang memicu notifikasi membuat permission (`Permission::findOrCreate`); suite penuh BE lebih dari 120 detik (jalankan di background).
- Binding bertingkat (`scopeBindings`) dievaluasi sebelum middleware `can`; entri di matriks izin harus konsisten dengan induknya (helper `makeScheduledEntry`). Entri berstatus akhir untuk tes rute batalkan dapat memakai status Terjadwal karena matriks hanya memeriksa bukan 403.
- Perilaku "Status akhir final" dari spec Fase 5 sengaja dilonggarkan oleh dokumen ini; dokumentasi UI dan catatan sprint ikut diperbarui.
