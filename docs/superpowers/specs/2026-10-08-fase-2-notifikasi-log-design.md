# Fase 2 — Notifikasi dan Log Aktivitas: Desain

Tanggal: 2026-10-08. Turunan dari `2026-10-07-sijebol-v1-design.md` bagian 6, 7, 8 dan `2026-10-07-sijebol-ui-guide.md` bagian 5.14. Mencakup Track BE dan Track FE Fase 2.

## 1. Tujuan dan batasan

Menyediakan infrastruktur yang dipakai fase 3–5: (a) notifikasi in-app dan email (queued) dengan penerima ditentukan permission, dan (b) penampil audit log baca-saja di balik `log.lihat`. Sukses: satu notifikasi nyata mengalir ujung ke ujung; log terbaca dengan filter; fase berikutnya cukup menambah satu kelas notifikasi.

**Di luar cakupan:** notifikasi pendaftaran/verifikasi/jadwal (Fase 4–5), channel WhatsApp, preferensi notifikasi per pengguna, ekspor log, pembatasan log per sekolah (hanya pemilik `log.lihat`, yaitu admin kecamatan).

**Asumsi:** SMTP belum ditentukan (`MAIL_MAILER=log` lokal); login/logout tampil di log dan dapat disaring lewat filter jenis; antrean memakai driver `database` (sudah dikonfigurasi, tabel `jobs` dan `failed_jobs` ada).

## 2. Keputusan arsitektur

- **Notifikasi bawaan Laravel** (`database` + `mail`), bukan tabel buatan sendiri. WhatsApp kelak menjadi channel tambahan pada kelas dasar yang sama.
- **Penghitung belum dibaca lewat shared prop lazy + partial reload** (`router.reload({ only: ['notifications'] })` tiap 60 detik), bukan endpoint JSON polling: satu jalur data, mengikuti pola Inertia.
- **Dua domain baru** mengikuti pola yang ada (Controller → FormRequest/Service → Repository → Model): `app/Domains/Notifications` dan `app/Domains/Audit`.
- Log dibaca dari model activitylog yang sudah ada lewat query-builder; tidak ada read-model terpisah.

## 3. Backend

### 3.1 Notifikasi

**Tabel:** `notifications` standar Laravel (uuid, `type`, morph `notifiable`, `data` json, `read_at`, timestamps) lewat migrasi baru.

**`App\Domains\Notifications\Notifications\BaseNotification`** (abstract, `implements ShouldQueue`, `use Queueable`):

- `via()` → `['database', 'mail']`; `public int $tries = 3`; `backoff()` → `[60, 300]`.
- Kontrak subclass: `abstract title(): string`, `abstract body(): string`, `abstract url(): string`.
- `toArray()` → `['title' => ..., 'body' => ..., 'url' => ...]` (satu-satunya bentuk data, dipakai bell, halaman, dan email).
- `toMail()` → `MailMessage` berbahasa Indonesia: subjek = `title()`, baris = `body()`, tombol aksi "Buka di SIJEBOL" ke `url()`, salam dan salam penutup Indonesia.

**`NotificationService`:**

- `toPermission(string $permission, BaseNotification $notification, ?int $schoolId = null): int` — memilih pengguna aktif yang memegang permission (termasuk lewat role dan `super-admin`), bila `$schoolId` diberikan hanya pengguna dengan `school_id` sama atau tanpa sekolah; mengirim lewat `Notification::send`; mengembalikan jumlah penerima.
- `list(User $user, bool $unreadOnly): LengthAwarePaginator` (15 per halaman, terbaru dulu), `markRead(User $user, string $id): void`, `markAllRead(User $user): void`, `summary(User $user): array{unread_count: int, latest: list<array>}` (8 terbaru).
- Semua operasi dibatasi `$user->notifications()` (tidak bisa menyentuh milik orang lain; id milik orang lain → 404).

**Endpoint** (middleware `auth`, tanpa permission karena milik sendiri):

| Method | URL | Aksi |
|---|---|---|
| GET | `/notifikasi` | Halaman `Notifikasi/Index`; `filter[status]=unread` opsional |
| POST | `/notifikasi/{id}/baca` | Tandai satu dibaca; redirect back |
| POST | `/notifikasi/baca-semua` | Tandai semua dibaca; redirect back |

**Shared prop** `notifications` (lazy closure di `HandleInertiaRequests`): `{ unread_count, latest: [{ id, title, body, url, read_at, created_at }] }`; `null` untuk tamu.

**Notifikasi bukti pola:** `AccountActivatedNotification` (`title` "Akun diaktifkan", `body` "{nama} telah mengaktifkan akunnya.", `url` ke detail pengguna) dikirim ke pembuat akun (`created_by`) saat `ActivationService::complete` selesai, bila pembuatnya masih ada dan aktif.

**Antrean dan email:** `QUEUE_CONNECTION=database`; worker `php artisan queue:work` (produksi: supervisor, didokumentasikan di `docs/`); retry sesuai `tries/backoff`; kegagalan permanen masuk `failed_jobs`.

### 3.2 Log aktivitas

**`ActivityLogController@index`** (`GET /log`, `can:log.lihat`) merender `Log/Index` dengan props `{ activities: Paginator<ActivityRow>, filters, types: string[], users: Option[] }`.

**Query** (spatie query-builder atas `Spatie\Activitylog\Models\Activity` dengan `causer`, `subject` eager loaded, urut terbaru, 25 per halaman, `withQueryString`):

- `filter[user]` → `causer_id` (causer bertipe `User`).
- `filter[type]` → `log_name` (nilai dari daftar `types` = `log_name` distinct).
- `filter[from]`, `filter[to]` → rentang `created_at` (tanggal, zona Asia/Jakarta, `to` inklusif sampai akhir hari).

**`ActivityRow`:** `id`, `created_at`, `causer` (`{ id, name }` atau null untuk sistem), `type` (`log_name`), `action` (`description`), `subject` (`{ type, label }`: nama model dalam bahasa Indonesia + nama/judul bila ada), `changes` (daftar `{ field, old, new }` dari `properties.attributes` dan `properties.old`).

**`ActivityPropertyMasker`:** dijalankan saat membangun `ActivityRow`: kunci `password`, `password_confirmation`, `token`, `remember_token` dibuang; kunci `nik` dimasker (4 digit awal dan akhir terlihat). Berlaku pula pada data historis karena dilakukan saat baca.

**Aktivitas per pengguna:** `UserController::show` menambah prop `activities` (10 terbaru di mana pengguna itu `causer` atau `subject`, bentuk `ActivityRow` yang sama) bila pengguna saat ini memegang `log.lihat`; bila tidak, prop `null` (kartu disembunyikan).

**Retensi:** command `logs:prune {--days=365}` menghapus baris `activity_log` yang lebih lama dari batas; dijadwalkan harian di `routes/console.php`; mengembalikan jumlah baris terhapus.

**Log hanya baca:** tidak ada route tulis/hapus untuk log.

### 3.3 Pengujian (Pest)

- Penerima notifikasi sesuai permission; pengguna nonaktif dan tanpa permission tidak menerima; pembatasan `schoolId` (operator sekolah lain tidak menerima, pengguna tanpa sekolah menerima).
- Notifikasi di-queue (`Queue::fake`/`Notification::fake`) dengan channel `database` dan `mail`; isi `toArray` dan email.
- Tandai dibaca satu/semua; id milik orang lain → 404; tamu → redirect login.
- `AccountActivatedNotification` terkirim ke pembuat saat aktivasi selesai dan tidak error bila pembuat tidak ada.
- `GET /log` 403 tanpa `log.lihat`; filter user/type/tanggal; masker membuang password/token dan memasker NIK; log tidak punya route tulis.
- `logs:prune` menghapus hanya baris lebih tua dari batas.
- `show` pengguna: `activities` ada bila `log.lihat`, `null` bila tidak.

**Exit criteria BE:** semua tes lulus (Pest + Larastan + Pint); worker memproses email di lingkungan lokal; log hanya terbaca pemilik `log.lihat`.

## 4. Frontend

**Prasyarat desain:** tambahkan bagian **5.17 Log Aktivitas** ke panduan UI sebelum implementasi FE (isi mengikuti bagian 4.2 di bawah).

### 4.1 Notifikasi (panduan UI 5.14)

- **`NotificationBell`** menggantikan empty-state di Topbar: ikon lonceng dengan badge jumlah belum dibaca (maks "9+", disembunyikan bila 0; `aria-label` memuat jumlah), dropdown lebar ±380 dengan header "Notifikasi" + tautan "Tandai semua dibaca", maks 8 item, footer "Lihat semua". Empty state "Tidak ada notifikasi". Polling: `router.reload({ only: ['notifications'] })` tiap 60 detik, dihentikan saat tab tidak terlihat (`document.visibilityState`).
- **Item notifikasi** (komponen `NotificationItem`, dipakai dropdown dan halaman): titik biru 8 px untuk belum dibaca, judul tebal dan latar `primary-soft` tipis bila belum dibaca, judul satu baris, deskripsi satu baris, waktu relatif (`relativeTime`), tombol ikon centang "tandai dibaca" (hover di desktop, selalu tampil di mobile, hanya untuk yang belum dibaca). Klik badan item membuka `url` dan menandainya dibaca.
- **`/notifikasi`** (`Notifikasi/Index`): header "Notifikasi" + tombol "Tandai semua dibaca", segmen Semua/Belum dibaca (`filter[status]`), daftar item sama, paginasi, empty state "Tidak ada notifikasi".

### 4.2 Log Aktivitas (`/log`, `log.lihat`)

- Header "Log Aktivitas" dengan deskripsi "Rekam jejak aktivitas di SIJEBOL." Menu "Log Aktivitas" sudah/akan muncul dari menu server bila permission dimiliki (tambahkan ke `MenuService` bila belum).
- Filter: Pengguna (`SelectField`, semua pengguna), Jenis (`SelectField`, `types` dengan label bahasa Indonesia), Dari tanggal, Sampai tanggal (`<input type="date">`), tombol "Reset filter" bila ada filter aktif; pola `useDataTable`.
- Tabel (`DataTable`): Waktu (kurang dari 24 jam: `relativeTime`, lebih lama: tanggal dan jam, mis. "6 Okt 2026 · 08.00"; menambah kembali `formatDateTime` ke `lib/date.ts` beserta tesnya karena sebelumnya dihapus saat belum terpakai; waktu lengkap sebagai `title`), Pengguna (nama atau "Sistem"), Aksi (deskripsi diterjemahkan ke label Indonesia), Subjek, Ringkasan (hingga 2 perubahan, "lama → baru", dipotong) dan tombol "Detail". State: loading skeleton, kosong ("Belum ada aktivitas"), hasil filter kosong.
- **Dialog Detail:** pelaku, waktu lengkap, aksi, subjek, tabel Kolom / Sebelum / Sesudah (nilai sudah termasking dari server), tombol Tutup.
- Mobile: tabel geser horizontal dengan petunjuk "↔ Geser tabel", seperti daftar lain.

### 4.3 Kartu "Aktivitas terbaru" di detail pengguna

Muncul bila prop `activities` tidak null: baris Waktu / Aksi / Ringkasan (desain Figma 19:2443), maks 10, tautan "Lihat semua" ke `/log?filter[user]={id}` (hanya bila `log.lihat`). Di mobile, baris menumpuk (waktu + aksi di atas, ringkasan di bawah) seperti desain mobile Figma.

**Exit criteria FE:** panduan UI 5.14 dan 5.17 terimplementasi; bell, `/notifikasi`, `/log`, dan kartu aktivitas berfungsi desktop dan mobile; review `impeccable` dan `ponytail` selesai.

## 5. Risiko dan catatan

- Volume log (login/logout) bisa besar; paginasi 25 dan indeks bawaan `created_at` cukup untuk v1. Tambahkan indeks `(log_name, created_at)` bila lambat.
- Polling 60 detik per tab: ringan karena hanya satu prop lazy; dihentikan saat tab tersembunyi.
- Email butuh SMTP nyata di produksi (keputusan terbuka v1 §11); di lokal terbaca di `storage/logs`.
- Terjemahan label aksi/jenis/subjek log berada di satu file bahasa (`lang/id/activity.php`) agar mudah dilengkapi saat modul baru menambah event.
