# Spesifikasi Desain: Halaman Beranda Siswa & Standarisasi Rute Bahasa Inggris

- **Tanggal:** 2026-10-27
- **Status:** Proposed
- **Tipe Perubahan:** Architectural
- **Branch Terkait:** `feature/alur-mandiri-siswa`

---

## 1. Latar Belakang & Tujuan

Pada implementasi Fase 7a–7d, alur mandiri siswa berhasil dibangun sehingga siswa dapat melakukan registrasi via link sekolah, melengkapi data diri, mengunggah berkas, memilih sesi rekaman, dan mendapatkan e-tiket QR. Namun, ditemukan dua celah arsitektural dan pengalaman pengguna (UX) yang perlu dibenahi:

1. **Ketiadaan Beranda Khusus Siswa (Student Command Center):**
   Saat siswa mengakses rute `/dashboard` (atau mengklik menu "Beranda"), `DashboardController` saat ini langsung me-redirect siswa ke halaman profil (`/saya`). Siswa tidak memiliki beranda terpadu yang memandu posisi alur mereka saat ini, jadwal sesi yang dibuka di sekolah mereka, sisa waktu perbaikan berkas, dan akses cepat menuju e-tiket QR.
2. **Inkonsistensi Penamaan Rute (Route Naming Convention):**
   Hampir seluruh rute aplikasi saat ini menggunakan URI dan *named route* berbahasa Indonesia (seperti `/saya`, `/siswa`, `/jadwal`, `/daftar`, `/laporan`, `/pengguna`, `/sekolah`, `/verifikasi`, `/log`, `/notifikasi`). Hal ini menyimpang dari konvensi standar RESTful dan praktik terbaik Laravel. Seluruh rute perlu distandarisasi ke dalam Bahasa Inggris baku secara menyeluruh (*Full Application Restructure*).

Tujuan dari desain ini adalah:
- Merancang dan membangun **Halaman Beranda Siswa (Student Dashboard)** dengan pola *Action-Oriented Journey Tracker*.
- Melakukan migrasi penuh seluruh rute web aplikasi ke Bahasa Inggris standar (URI dan *named route*), baik di sisi backend, frontend, maupun test suite.

---

## 2. Keputusan Arsitektur Utama

### 2.1 Pola Tampilan Dashboard Terisolasi (Approach 1)
- `DashboardController` berperan sebagai *role-based dispatcher*:
  - Jika pengguna adalah akun siswa (`$request->user()->isStudent()`), controller memanggil `StudentDashboardService` dan me-render Inertia page khusus: `Pages/Student/Dashboard.vue`.
  - Jika pengguna adalah staf/operator/admin/disdukcapil, controller tetap me-render dashboard staf yang sudah ada: `Pages/Dashboard.vue`.
- Keuntungan: Menjaga `Dashboard.vue` staf tetap ramping dan terisolasi dari logika/props alur mandiri siswa, mencegah kondisional `v-if` yang rumit, dan memudahkan pengujian Vitest secara independen.

### 2.2 Konvensi RESTful Bahasa Inggris Penuh
- Seluruh URI rute diubah ke kata benda jamak (*plural nouns*) dalam Bahasa Inggris sesuai standar REST.
- Rute mandiri siswa menggunakan prefix `/me` (`/me`, `/me/sessions`, `/me/tickets/{entry}`, `/me/registrations/{entry}/...`) untuk membedakan kepemilikan data pribadi dari rute manajemen operator (`/students`, `/students/{student}`).
- Seluruh *named route* Laravel menggunakan pola `resource.action` dalam Bahasa Inggris (misal: `schedules.index`, `schedules.create`, `me.show`, `me.sessions.index`, `verifications.approve-batch`).

### 2.3 Pola Antarmuka Beranda Siswa: *Action-Oriented Journey*
- **Visual Stepper (4 Langkah):** Memberikan kejelasan progres (1. Akun &rarr; 2. Data Diri & Dokumen &rarr; 3. Pendaftaran Sesi &rarr; 4. Selesai Perekaman).
- **Smart Hero Action Banner:** Kartu dinamis berbobot visual tinggi yang langsung memberikan 1 tombol aksi utama (*Single Primary CTA*) paling mendesak sesuai kondisi real-time siswa saat itu (misal: isi data, unggah berkas, pilih jadwal, tunggu antrean, perbaiki berkas dengan *deadline countdown*, atau unduh tiket QR).
- **Active Ticket / Session Card:** Ringkasan jadwal sesi terdaftar, lokasi, hitung mundur hari-H, dan lencana *KTP Langsung Cetak* (&ge; 17 tahun + 1 hari).
- **School Upcoming Schedules & Guidelines:** Informasi sesi rekaman yang tersedia di sekolah siswa dan panduan tata tertib hari-H.

---

## 3. Matriks Pemetaan Rute Baru (Full English RESTful Routing)

Berikut adalah daftar lengkap pemetaan rute dari Bahasa Indonesia ke Bahasa Inggris:

### 3.1 Alur Siswa Mandiri (`routes/students.php` & `routes/registrations.php`)
| HTTP Method | URI Lama | URI Baru | Named Route Baru | Handler |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/saya` | `/me` | `me.show` | `SelfStudentController@show` |
| `PUT` | `/saya` | `/me` | `me.update` | `SelfStudentController@save` |
| `GET` | `/saya/sesi` | `/me/sessions` | `me.sessions.index` | `SelfRegistrationController@index` |
| `POST` | `/saya/sesi/{schedule}/daftar` | `/me/sessions/{schedule}/register` | `me.sessions.register` | `SelfRegistrationController@register` |
| `GET` | `/saya/tiket/{entry}` | `/me/tickets/{entry}` | `me.tickets.show` | `SelfRegistrationController@ticket` |
| `POST` | `/saya/pendaftaran/{entry}/batal` | `/me/registrations/{entry}/cancel` | `me.registrations.cancel` | `SelfRegistrationController@cancel` |
| `POST` | `/saya/pendaftaran/{entry}/ajukan-ulang` | `/me/registrations/{entry}/resubmit` | `me.registrations.resubmit` | `SelfRegistrationController@resubmit` |

### 3.2 Registrasi Publik & Aktivasi Akun
| HTTP Method | URI Lama | URI Baru | Named Route Baru | Handler |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/daftar/{token}` | `/register/{token}` | `register.show` | `StudentSignupController@show` |
| `POST` | `/daftar/{token}` | `/register/{token}` | `register.store` | `StudentSignupController@store` |
| `GET` | `/aktivasi/{token}` | `/activation/{token}` | `activation.show` | `ActivationController@show` |
| `POST` | `/aktivasi/{token}` | `/activation/{token}` | `activation.store` | `ActivationController@store` |

### 3.3 Manajemen Siswa oleh Operator/Staf (`routes/students.php`)
| HTTP Method | URI Lama | URI Baru | Named Route Baru | Handler |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/siswa` | `/students` | `students.index` | `StudentController@index` |
| `GET` | `/siswa/{student}` | `/students/{student}` | `students.show` | `StudentController@show` |
| `GET` | `/siswa/{student}/ubah` | `/students/{student}/edit` | `students.edit` | `StudentController@edit` |
| `PUT` | `/siswa/{student}` | `/students/{student}` | `students.update` | `StudentController@update` |
| `DELETE` | `/siswa/{student}` | `/students/{student}` | `students.destroy` | `StudentController@destroy` |
| `POST` | `/siswa/{student}/dokumen/{type}` | `/students/{student}/documents/{type}` | `students.documents.store` | `StudentDocumentController@store` |
| `GET` | `/siswa/{student}/dokumen/{type}` | `/students/{student}/documents/{type}` | `students.documents.download` | `StudentDocumentController@download` |
| `GET` | `/siswa/{student}/dokumen/{type}/preview` | `/students/{student}/documents/{type}/preview` | `students.documents.preview` | `StudentDocumentController@preview` |
| `DELETE` | `/siswa/{student}/dokumen/{type}` | `/students/{student}/documents/{type}` | `students.documents.destroy` | `StudentDocumentController@destroy` |

### 3.4 Verifikasi & Jadwal Sesi (`routes/registrations.php` & `routes/schedules.php`)
| HTTP Method | URI Lama | URI Baru | Named Route Baru | Handler |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/verifikasi` | `/verifications` | `verifications.index` | `VerificationController@index` |
| `POST` | `/verifikasi/setujui` | `/verifications/approve-batch` | `verifications.approve-batch` | `VerificationController@approveBatch` |
| `GET` | `/verifikasi/{entry}` | `/verifications/{entry}` | `verifications.show` | `VerificationController@show` |
| `POST` | `/verifikasi/{entry}/putuskan` | `/verifications/{entry}/decide` | `verifications.decide` | `VerificationController@decide` |
| `GET` | `/jadwal` | `/schedules` | `schedules.index` | `ScheduleController@index` |
| `POST` | `/jadwal` | `/schedules` | `schedules.store` | `ScheduleController@store` |
| `GET` | `/jadwal/buat` | `/schedules/create` | `schedules.create` | `ScheduleController@create` |
| `GET` | `/jadwal/{schedule}` | `/schedules/{schedule}` | `schedules.show` | `ScheduleController@show` |
| `PUT` | `/jadwal/{schedule}` | `/schedules/{schedule}` | `schedules.update` | `ScheduleController@update` |
| `DELETE` | `/jadwal/{schedule}` | `/schedules/{schedule}` | `schedules.destroy` | `ScheduleController@destroy` |
| `GET` | `/jadwal/{schedule}/ubah` | `/schedules/{schedule}/edit` | `schedules.edit` | `ScheduleController@edit` |
| `POST` | `/jadwal/{schedule}/batalkan` | `/schedules/{schedule}/cancel` | `schedules.cancel` | `ScheduleController@cancel` |
| `POST` | `/jadwal/{schedule}/peserta/{entry}/batalkan` | `/schedules/{schedule}/entries/{entry}/cancel` | `schedules.entries.cancel` | `ScheduleController@cancelEntry` |
| `GET` | `/jadwal/{schedule}/pindai` | `/schedules/{schedule}/scan` | `schedules.scan.show` | `ScanController@show` |
| `POST` | `/jadwal/{schedule}/pindai` | `/schedules/{schedule}/scan` | `schedules.scan.store` | `ScanController@store` |
| `GET` | `/jadwal/{schedule}/rekaman` | `/schedules/{schedule}/recording` | `schedules.recording.show` | `RecordingController@show` |
| `POST` | `/jadwal/{schedule}/rekaman` | `/schedules/{schedule}/recording` | `schedules.recording.store` | `RecordingController@store` |
| `POST` | `/jadwal/{schedule}/rekaman/{entry}/batalkan` | `/schedules/{schedule}/recording/{entry}/cancel` | `schedules.recording.cancel` | `RecordingController@cancel` |

### 3.5 Administrasi, Laporan, Log & Notifikasi
| HTTP Method | URI Lama | URI Baru | Named Route Baru | Handler |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/laporan` | `/reports` | `reports.index` | `ReportController@index` |
| `GET` | `/laporan/ekspor` | `/reports/export` | `reports.export` | `ReportController@export` |
| `GET` | `/laporan/ekspor-sesi` | `/reports/export-sessions` | `reports.export-sessions` | `ReportController@exportSessions` |
| `GET` | `/sekolah` | `/schools` | `schools.index` | `SchoolController@index` |
| `POST` | `/sekolah` | `/schools` | `schools.store` | `SchoolController@store` |
| `GET` | `/sekolah/buat` | `/schools/create` | `schools.create` | `SchoolController@create` |
| `PUT` | `/sekolah/{school}` | `/schools/{school}` | `schools.update` | `SchoolController@update` |
| `GET` | `/sekolah/{school}/ubah` | `/schools/{school}/edit` | `schools.edit` | `SchoolController@edit` |
| `GET` | `/pengguna` | `/users` | `users.index` | `UserController@index` |
| `POST` | `/pengguna` | `/users` | `users.store` | `UserController@store` |
| `GET` | `/pengguna/buat` | `/users/create` | `users.create` | `UserController@create` |
| `GET` | `/pengguna/{user}` | `/users/{user}` | `users.show` | `UserController@show` |
| `PUT` | `/pengguna/{user}` | `/users/{user}` | `users.update` | `UserController@update` |
| `GET` | `/pengguna/{user}/ubah` | `/users/{user}/edit` | `users.edit` | `UserController@edit` |
| `POST` | `/pengguna/{user}/aktifkan` | `/users/{user}/activate` | `users.activate` | `UserController@activate` |
| `POST` | `/pengguna/{user}/nonaktifkan` | `/users/{user}/deactivate` | `users.deactivate` | `UserController@deactivate` |
| `POST` | `/pengguna/{user}/kirim-ulang-aktivasi` | `/users/{user}/resend-activation` | `users.resend-activation` | `UserController@resendActivation` |
| `GET` | `/pengguna/{user}/berhasil` | `/users/{user}/done` | `users.done` | `UserController@done` |
| `GET` | `/role` | `/roles` | `roles.index` | `RoleController@index` |
| `POST` | `/role` | `/roles` | `roles.store` | `RoleController@store` |
| `GET` | `/role/buat` | `/roles/create` | `roles.create` | `RoleController@create` |
| `PUT` | `/role/{role}` | `/roles/{role}` | `roles.update` | `RoleController@update` |
| `DELETE` | `/role/{role}` | `/roles/{role}` | `roles.destroy` | `RoleController@destroy` |
| `GET` | `/role/{role}/ubah` | `/roles/{role}/edit` | `roles.edit` | `RoleController@edit` |
| `GET` | `/tautan-pendaftaran` | `/registration-links` | `registration-links.index` | `SchoolLinkController@index` |
| `POST` | `/tautan-pendaftaran/{school}/reset` | `/registration-links/{school}/reset` | `registration-links.reset` | `SchoolLinkController@reset` |
| `GET` | `/log` | `/audit-logs` | `audit-logs.index` | `ActivityLogController@index` |
| `GET` | `/notifikasi` | `/notifications` | `notifications.index` | `NotificationController@index` |
| `POST` | `/notifikasi/baca-semua` | `/notifications/mark-all-read` | `notifications.mark-all-read` | `NotificationController@readAll` |
| `POST` | `/notifikasi/{id}/baca` | `/notifications/{id}/mark-read` | `notifications.mark-read` | `NotificationController@read` |

---

## 4. Arsitektur Backend: Beranda Siswa

### 4.1 Dispatcher pada `DashboardController`
Lokasi: `app/Http/Controllers/DashboardController.php`

```php
namespace App\Http\Controllers;

use App\Domains\Reports\Services\DashboardService;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schedules\Services\ScheduleService;
use App\Domains\Students\Services\StudentDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ScheduleService $schedules,
        DashboardService $dashboard,
        StudentDashboardService $studentDashboard
    ): Response {
        if ($request->user()->isStudent()) {
            return Inertia::render('Student/Dashboard', $studentDashboard->forUser($request->user()));
        }

        $canJadwal = $request->user()->can('jadwal.lihat');

        return Inertia::render('Dashboard', [
            'schedules' => $canJadwal
                ? $schedules->upcoming(3)->map(fn (Schedule $s) => [
                    'date' => $s->date->toDateString(),
                    'school' => $s->school?->name,
                    'location' => $s->location,
                    'participants' => $s->taken_count,
                ])->values()
                : [],
            'schedules_table' => $canJadwal ? $schedules->upcomingTable(8) : [],
            'calendar_events' => $canJadwal ? $schedules->calendarEvents((int) now()->year) : [],
            ...$dashboard->forUser($request->user()),
        ]);
    }
}
```

### 4.2 Servis Khusus: `StudentDashboardService`
Lokasi: `app/Domains/Students/Services/StudentDashboardService.php`

Metode utama: `forUser(User $user): array`

#### Payload Struktur Data:
```php
[
    'student' => [
        'id' => 10,
        'name' => 'Budi Santoso',
        'nik' => '2171012345670001',
        'nisn' => '0061234567',
        'school_name' => 'MAN 2 Batam',
        'has_profile' => true,
    ],
    'stepper' => [
        'current_step' => 3, // 1: Akun, 2: Data & Dokumen, 3: Sesi Rekaman, 4: Selesai Rekam
        'steps' => [
            ['id' => 1, 'title' => 'Akun Terdaftar', 'status' => 'completed', 'description' => 'Akun aktif'],
            ['id' => 2, 'title' => 'Data Diri & Dokumen', 'status' => 'completed', 'description' => 'Profil & 2 dokumen terunggah'],
            ['id' => 3, 'title' => 'Pendaftaran Sesi', 'status' => 'current', 'description' => 'Menunggu verifikasi petugas'],
            ['id' => 4, 'title' => 'Perekaman KTP-el', 'status' => 'upcoming', 'description' => 'Hari-H pelaksanaan'],
        ],
    ],
    'smart_hero' => [
        'state' => 'MENUNGGU_VERIFIKASI', // NO_PROFILE | MISSING_DOCS | NO_REGISTRATION | MENUNGGU_VERIFIKASI | PERBAIKAN | TERDAFTAR | SUDAH_REKAMAN | DITOLAK
        'variant' => 'indigo', // amber | blue | indigo | rose | emerald | zinc
        'title' => 'Berkas Sedang Diverifikasi',
        'subtitle' => 'Petugas sedang memeriksa data dan dokumen Anda. Anda akan menerima notifikasi setelah diperiksa.',
        'badge' => 'Dalam Antrean',
        'cta_label' => 'Lihat Sesi Rekaman',
        'cta_url' => '/me/sessions',
        'deadline_at' => null, // ISO string jika status perbaikan
        'notes' => null, // Catatan perbaikan dari verifikator jika ada
    ],
    'active_entry' => [ // Null jika belum pernah daftar
        'id' => 45,
        'status' => 'terdaftar',
        'status_label' => 'Terdaftar',
        'ticket_code' => 'TKT-202610-0045',
        'schedule_date' => '2026-11-05',
        'time_window' => '08:30 - 11:30 WIB',
        'location' => 'Aula Serbaguna MAN 2 Batam',
        'direct_ktp' => true, // Usia >= 17 tahun + 1 hari
        'days_remaining' => 9,
    ],
    'school_schedules' => [
        [
            'id' => 12,
            'date' => '2026-11-05',
            'quota' => 50,
            'taken' => 32,
            'remaining_quota' => 18,
            'location' => 'Aula Serbaguna MAN 2 Batam',
            'is_open' => true,
        ],
    ],
    'guidelines' => [
        'Bawa dokumen fisik asli (Kartu Keluarga dan Akta Kelahiran/Ijazah).',
        'Kenakan seragam sekolah rapi berkerah (bukan kaos oblong).',
        'Hadir di lokasi perekaman minimal 15 menit sebelum sesi dimulai.',
        'Tunjukkan QR Code e-Tiket pada smartphone Anda kepada petugas piket.',
    ],
]
```

### 4.3 Logika Penentuan State Smart Hero
Urutan evaluasi kondisi pada `StudentDashboardService`:
1. Jika belum ada profil `Student` (`$user->student === null`):
   - `state = NO_PROFILE`, `variant = amber`, CTA &rarr; `/me`.
2. Jika dokumen wajib (`kk`, `akta`) belum lengkap:
   - `state = MISSING_DOCS`, `variant = amber`, CTA &rarr; `/me`.
3. Jika belum memiliki entri pendaftaran aktif (`$activeEntry === null`):
   - `state = NO_REGISTRATION`, `variant = blue`, CTA &rarr; `/me/sessions`.
4. Jika entri aktif berstatus `menunggu_verifikasi`:
   - `state = MENUNGGU_VERIFIKASI`, `variant = indigo`, CTA &rarr; `/me/sessions`.
5. Jika entri aktif berstatus `perbaikan`:
   - `state = PERBAIKAN`, `variant = rose`, catatan verifikator, deadline timer, CTA &rarr; `/me`.
6. Jika entri aktif berstatus `terdaftar`:
   - `state = TERDAFTAR`, `variant = emerald`, CTA &rarr; `/me/tickets/{entry}`.
7. Jika entri aktif berstatus `sudah_rekaman`:
   - `state = SUDAH_REKAMAN`, `variant = emerald`, CTA &rarr; `/me/tickets/{entry}`.
8. Jika ditolak permanen:
   - `state = DITOLAK`, `variant = zinc`, CTA &rarr; `/me/sessions`.

---

## 5. Arsitektur Frontend: Halaman & Komponen Beranda Siswa

### 5.1 Struktur Berkas Frontend
- `resources/js/Pages/Student/Dashboard.vue`: Halaman utama terintegrasi dengan `AuthenticatedLayout`.
- `resources/js/Components/student-dashboard/StudentStepper.vue`: Komponen progress bar 4 langkah interaktif.
- `resources/js/Components/student-dashboard/StudentSmartHero.vue`: Banner hero dinamis dengan tombol aksi primer dan timer perbaikan.
- `resources/js/Components/student-dashboard/StudentActiveTicketCard.vue`: Kartu ringkasan e-Tiket, status cetak KTP langsung, dan jadwal sesi.
- `resources/js/Components/student-dashboard/StudentUpcomingSchedules.vue`: Kartu sesi sekolah yang akan datang beserta sisa kuota.
- `resources/js/Components/student-dashboard/StudentGuidelines.vue`: Kartu petunjuk persiapan hari-H perekaman.
- `resources/js/types/student-dashboard.d.ts`: Definisi tipe TypeScript yang lengkap dan aman.

### 5.2 Desain Aksesibilitas & Responsivitas (Antislop & Human Guidelines)
- Kompatibel penuh dengan kontras rasio WCAG AA pada seluruh badge status dan tombol.
- Mendukung navigasi keyboard penuh (`Tab`, `Enter`, `Space`) pada link dan tombol CTA.
- Tampilan responsif murni dengan grid Tailwind: 1 kolom pada mobile (`< 768px`) dan 2/3 kolom fleksibel pada desktop (`>= 1024px`).
- Indikator status tidak hanya mengandalkan warna, melainkan disertai ikon (*Lucide icons*) dan teks penjelasan eksplisit untuk pengguna dengan gangguan penglihatan warna.

### 5.3 Pembaruan Navigasi Menu (`MenuService.php`)
Menu item pada sidebar disesuaikan:
```php
private const ITEMS = [
    ['group' => 'Utama', 'label' => 'Beranda', 'href' => '/dashboard', 'icon' => 'home', 'permission' => null],
    ['group' => 'Pelayanan', 'label' => 'Siswa', 'href' => '/students', 'icon' => 'graduation-cap', 'permission' => 'siswa.lihat'],
    ['group' => 'Pelayanan', 'label' => 'Data saya', 'href' => '/me', 'icon' => 'graduation-cap', 'permission' => 'profil.lihat'],
    ['group' => 'Pelayanan', 'label' => 'Link pendaftaran', 'href' => '/registration-links', 'icon' => 'link', 'permission' => 'sekolah.tautan'],
    ['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/me/sessions', 'icon' => 'calendar-days', 'permission' => 'pendaftaran.lihat'],
    ['group' => 'Pelayanan', 'label' => 'Antrean verifikasi', 'href' => '/verifications', 'icon' => 'badge-check', 'permission' => 'verifikasi.lihat'],
    ['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/schedules', 'icon' => 'calendar-days', 'permission' => 'jadwal.lihat'],
    ['group' => 'Laporan', 'label' => 'Rekap pendaftaran', 'href' => '/reports', 'icon' => 'file-spreadsheet', 'permission' => 'laporan.lihat'],
    ['group' => 'Sistem & Akses', 'label' => 'Sekolah', 'href' => '/schools', 'icon' => 'school', 'permission' => 'sekolah.lihat'],
    ['group' => 'Sistem & Akses', 'label' => 'Pengguna', 'href' => '/users', 'icon' => 'users', 'permission' => 'user.lihat'],
    ['group' => 'Sistem & Akses', 'label' => 'Role & Permission', 'href' => '/roles', 'icon' => 'shield-check', 'permission' => 'role.lihat'],
    ['group' => 'Sistem & Akses', 'label' => 'Log Aktivitas', 'href' => '/audit-logs', 'icon' => 'scroll-text', 'permission' => 'log.lihat'],
];
```
Pengecekan hak akses di `forUser`:
```php
if (str_starts_with($item['href'], '/me') && ! $user->isStudent()) {
    continue;
}
```

---

## 6. Penanganan Edge Cases & Keamanan

1. **Siswa Belum Mengisi Profil:**
   - Tidak menghasilkan error PHP null-pointer; `StudentDashboardService` dengan anggun mendeteksi `$user->student === null` dan menghasilkan data default aman dengan smart hero berstatus `NO_PROFILE`.
2. **Isolasi Rute Siswa Mandiri (`/me/*`):**
   - Staf atau operator yang mencoba mengakses `/me`, `/me/sessions`, atau `/me/tickets/{entry}` langsung mendapatkan respons `404 Not Found` melalui pengecekan `abort_unless($request->user()->isStudent(), 404);`.
3. **Isolasi Tiket Siswa Antar-Siswa:**
   - Siswa A tidak dapat membuka tiket milik Siswa B; controller melakukan verifikasi kepemilikan data sebelum menampilkan tiket.
4. **Perubahan Rute pada Link Notifikasi & Email:**
   - Seluruh URL yang dibuat pada notifikasi database, email, atau redirect sesudah login diperbarui menggunakan helper `route('...')` baru agar tidak menghasilkan `RouteNotFoundException` atau rute usang.

---

## 7. Rencana Pengujian & Quality Gates

### 7.1 Tes Fitur Backend (Pest PHP)
- `tests/Feature/Student/StudentDashboardTest.php`:
  - Siswa yang belum login dialihkan ke `/login`.
  - Siswa login mengakses `/dashboard` mendapatkan halaman Inertia `Student/Dashboard`.
  - Staf/operator login mengakses `/dashboard` mendapatkan halaman Inertia `Dashboard` (bukan halaman siswa).
  - Verifikasi payload `StudentDashboardService` untuk setiap state: `NO_PROFILE`, `MISSING_DOCS`, `NO_REGISTRATION`, `MENUNGGU_VERIFIKASI`, `PERBAIKAN` (lengkap dengan deadline & catatan), `TERDAFTAR` (lengkap dengan tiket & flag KTP langsung), `SUDAH_REKAMAN`.
- Pembaruan nama rute di seluruh suite tes Pest yang ada (`tests/Feature/**/*.php`).

### 7.2 Tes Frontend (Vitest)
- `resources/js/Pages/Student/Dashboard.test.ts`: Memastikan seluruh seksi beranda siswa me-render data props dengan benar.
- `resources/js/Components/student-dashboard/StudentStepper.test.ts`: Memastikan status langkah (completed, current, upcoming) ter-render tepat.
- `resources/js/Components/student-dashboard/StudentSmartHero.test.ts`: Memastikan teks judul, badge, deadline countdown, dan link CTA berfungsi presisi.
- `resources/js/Components/student-dashboard/StudentActiveTicketCard.test.ts`: Memastikan lencana "KTP Langsung" tampil jika `direct_ktp = true`.

### 7.3 Kriteria Kelulusan (Quality Gates)
1. **PHPStan Level 8:** `vendor/bin/phpstan analyse --memory-limit=512M` &rarr; 0 error.
2. **Pest PHP Test Suite:** `php artisan test --parallel` &rarr; 100% lolos (650+ tes).
3. **Vitest Test Suite:** `npx vitest run --pool=threads` &rarr; 100% lolos.
4. **Laravel Pint:** `vendor/bin/pint --test` &rarr; 0 formatting violations.
5. **Vite Production Build:** `npm run build` &rarr; Berhasil tanpa error.

---

## 8. Dokumen & Referensi
- Dokumen Alur Siswa: `docs/superpowers/specs/2026-10-19-alur-mandiri-siswa-design.md`
- Panduan UI Sistem: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`
