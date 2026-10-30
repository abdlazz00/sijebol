# Implementasi Beranda Siswa & Standarisasi Rute Bahasa Inggris Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun Halaman Beranda Khusus Siswa (*Student Command Center*) dengan pola *Action-Oriented Journey Tracker* dan menstandarisasi seluruh rute web aplikasi dari Bahasa Indonesia ke Bahasa Inggris baku (RESTful URI & *named routes*).

**Architecture:** Menggunakan *role-based dispatcher* pada `DashboardController` untuk memanggil `StudentDashboardService` dan me-render `Pages/Student/Dashboard.vue` bagi role `siswa` (menghilangkan redirect ke `/saya`), memisahkan komponen visual ke dalam subkomponen modular di `resources/js/Components/student-dashboard/`, dan memigrasikan seluruh route di `routes/*.php` ke konvensi RESTful Bahasa Inggris dengan rute siswa mandiri menggunakan prefix `/me`.

**Tech Stack:** Laravel 11, PHP 8.3+, Inertia.js v2, Vue 3 (Composition API & `<script setup lang="ts">`), Tailwind CSS, Lucide Icons, Pest PHP, Vitest, PHPStan Level 8.

**Spec:** [`docs/superpowers/specs/2026-10-27-beranda-siswa-dan-rute-inggris-design.md`](file:///C:/Users/abdulaziz/Documents/pribadi/SIJEBOL/docs/superpowers/specs/2026-10-27-beranda-siswa-dan-rute-inggris-design.md)

## Global Constraints
- Seluruh URI rute dan *named routes* wajib dalam Bahasa Inggris standar (RESTful plural nouns; siswa mandiri ber-prefix `/me`).
- Dilarang menyertakan `Co-Authored-By` atau atribusi AI apapun pada commit git (sesuai CLAUDE.md).
- Quality Gates wajib 100% hijau: PHPStan level 8 (0 error), Pest PHP (>650 tests passing), Vitest (>210 tests passing), Laravel Pint (clean), Vite build (clean).
- Komponen Vue wajib TypeScript strictly typed tanpa `any`.
- Aksesibilitas WCAG AA: Rasio kontras teks/elemen valid, keyboard navigable (`Tab`/`Enter`), dan indikator status tidak hanya berbasis warna.

---

### Task 1: Standarisasi Rute Backend ke Bahasa Inggris (`routes/*.php` & Controllers)

**Files:**
- Modify: `routes/students.php`
- Modify: `routes/registrations.php`
- Modify: `routes/schedules.php`
- Modify: `routes/reports.php`
- Modify: `routes/access.php`
- Modify: `routes/audit.php`
- Modify: `routes/notifications.php`
- Modify: `app/Domains/Students/Http/Controllers/SelfStudentController.php`
- Modify: `app/Domains/Registrations/Http/Controllers/SelfRegistrationController.php`
- Modify: `app/Domains/Students/Http/Controllers/StudentSignupController.php`
- Modify: `app/Domains/Access/Http/Controllers/ActivationController.php`
- Modify: `app/Domains/Access/Http/Controllers/UserController.php`
- Modify: `app/Domains/Schools/Http/Controllers/SchoolLinkController.php`
- Test: `tests/Feature/Routes/EnglishRouteSmokeTest.php`

**Interfaces:**
- Consumes: HTTP request pipeline, Laravel route registrar.
- Produces: Rute baru berbahasa Inggris:
  - `me.show` (`GET /me`), `me.update` (`PUT /me`)
  - `me.sessions.index` (`GET /me/sessions`), `me.sessions.register` (`POST /me/sessions/{schedule}/register`)
  - `me.tickets.show` (`GET /me/tickets/{entry}`)
  - `me.registrations.cancel` (`POST /me/registrations/{entry}/cancel`), `me.registrations.resubmit` (`POST /me/registrations/{entry}/resubmit`)
  - `register.show` / `store` (`/register/{token}`)
  - `activation.show` / `store` (`/activation/{token}`)
  - `students.*`, `schedules.*`, `verifications.*`, `reports.*`, `schools.*`, `users.*`, `roles.*`, `registration-links.*`, `audit-logs.*`, `notifications.*`.

- [ ] **Step 1: Buat smoke test rute baru yang memverifikasi eksistensi named routes bahasa Inggris**

```php
// tests/Feature/Routes/EnglishRouteSmokeTest.php
<?php

use App\Models\User;

test('semua named route bahasa Inggris terdaftar di router', function () {
    $expectedRoutes = [
        'me.show',
        'me.update',
        'me.sessions.index',
        'me.sessions.register',
        'me.tickets.show',
        'me.registrations.cancel',
        'me.registrations.resubmit',
        'register.show',
        'register.store',
        'activation.show',
        'activation.store',
        'students.index',
        'schedules.index',
        'verifications.index',
        'reports.index',
        'schools.index',
        'users.index',
        'roles.index',
        'registration-links.index',
        'audit-logs.index',
        'notifications.index',
    ];

    foreach ($expectedRoutes as $name) {
        expect(Route::has($name))->toBeTrue("Route [{$name}] belum terdaftar.");
    }
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (Red)**

Run: `php artisan test --filter=EnglishRouteSmokeTest`
Expected: FAIL karena rute-rute tersebut masih bernama bahasa Indonesia.

- [ ] **Step 3: Perbarui seluruh berkas rute di `routes/*.php` dan controller redirects**

Perbarui `routes/students.php`:
- `/saya` &rarr; `/me` (`name('me.show')`, `name('me.update')`)
- `/daftar/{token}` &rarr; `/register/{token}` (`name('register.show')`, `name('register.store')`)
- `/siswa` &rarr; `/students` (`name('students.*')`)
- `/siswa/{student}/dokumen/{type}` &rarr; `/students/{student}/documents/{type}` (`name('students.documents.*')`)

Perbarui `routes/registrations.php`:
- `/saya/sesi` &rarr; `/me/sessions` (`name('me.sessions.index')`)
- `/saya/sesi/{schedule}/daftar` &rarr; `/me/sessions/{schedule}/register` (`name('me.sessions.register')`)
- `/saya/tiket/{entry}` &rarr; `/me/tickets/{entry}` (`name('me.tickets.show')`)
- `/saya/pendaftaran/{entry}/batal` &rarr; `/me/registrations/{entry}/cancel` (`name('me.registrations.cancel')`)
- `/saya/pendaftaran/{entry}/ajukan-ulang` &rarr; `/me/registrations/{entry}/resubmit` (`name('me.registrations.resubmit')`)
- `/verifikasi` &rarr; `/verifications` (`name('verifications.*')`)

Perbarui `routes/schedules.php`:
- `/jadwal` &rarr; `/schedules` (`name('schedules.*')`)
- `/jadwal/{schedule}/pindai` &rarr; `/schedules/{schedule}/scan` (`name('schedules.scan.*')`)
- `/jadwal/{schedule}/rekaman` &rarr; `/schedules/{schedule}/recording` (`name('schedules.recording.*')`)

Perbarui `routes/reports.php`:
- `/laporan` &rarr; `/reports` (`name('reports.*')`)

Perbarui `routes/access.php`:
- `/aktivasi/{token}` &rarr; `/activation/{token}` (`name('activation.*')`)
- `/sekolah` &rarr; `/schools` (`name('schools.*')`)
- `/pengguna` &rarr; `/users` (`name('users.*')`)
- `/role` &rarr; `/roles` (`name('roles.*')`)
- `/tautan-pendaftaran` &rarr; `/registration-links` (`name('registration-links.*')`)

Perbarui `routes/audit.php`:
- `/log` &rarr; `/audit-logs` (`name('audit-logs.index')`)

Perbarui `routes/notifications.php`:
- `/notifikasi` &rarr; `/notifications` (`name('notifications.*')`)

Perbarui controller redirect target:
- `SelfStudentController`: redirect ke `me.show`.
- `SelfRegistrationController`: redirect ke `me.sessions.index`.
- `StudentSignupController`: redirect ke `me.show`.
- `ActivationController`: redirect ke `login` / target yang sesuai.
- `UserController`: redirect ke `users.index`, `users.show`, dll.
- `SchoolLinkController`: redirect ke `registration-links.index`.

- [ ] **Step 4: Jalankan smoke test untuk memverifikasi kelulusan (Green)**

Run: `php artisan test --filter=EnglishRouteSmokeTest`
Expected: PASS (1 test, 21 assertions passed).

- [ ] **Step 5: Commit perubahan rute backend**

```bash
git add routes/ app/Domains/ tests/Feature/Routes/EnglishRouteSmokeTest.php
git commit -m "refactor(routes): standarisasi seluruh rute web ke bahasa inggris RESTful"
```

---

### Task 2: Perbaruan `MenuService` dan Navigasi Akses Backend

**Files:**
- Modify: `app/Domains/Access/Services/MenuService.php:9-50`
- Test: `tests/Unit/Domains/Access/MenuServiceTest.php`

**Interfaces:**
- Consumes: User instance with role & permissions.
- Produces: Navigation menu groups with English href paths (`/dashboard`, `/me`, `/me/sessions`, `/students`, `/schedules`, dll.) and proper student `/me` gating.

- [ ] **Step 1: Tulis test untuk memverifikasi MenuService menghasilkan rute Bahasa Inggris yang tepat bagi role siswa dan staf**

```php
// Tambahkan / perbarui test di tests/Unit/Domains/Access/MenuServiceTest.php
test('menu service menghasilkan tautan bahasa inggris yang tepat untuk siswa', function () {
    $student = User::factory()->siswa()->create();
    $menu = app(MenuService::class)->forUser($student);

    $allHrefs = collect($menu)->pluck('items')->flatten(1)->pluck('href')->all();
    expect($allHrefs)->toContain('/dashboard')
        ->toContain('/me')
        ->toContain('/me/sessions')
        ->not->toContain('/saya')
        ->not->toContain('/siswa')
        ->not->toContain('/jadwal');
});

test('menu service menghasilkan tautan bahasa inggris yang tepat untuk staf', function () {
    $operator = User::factory()->operator()->create();
    $menu = app(MenuService::class)->forUser($operator);

    $allHrefs = collect($menu)->pluck('items')->flatten(1)->pluck('href')->all();
    expect($allHrefs)->toContain('/dashboard')
        ->toContain('/students')
        ->toContain('/schedules')
        ->toContain('/verifications')
        ->not->toContain('/me')
        ->not->toContain('/me/sessions');
});
```

- [ ] **Step 2: Jalankan test untuk memastikan kegagalan (Red)**

Run: `php artisan test --filter=MenuServiceTest`
Expected: FAIL karena `MenuService::ITEMS` masih menggunakan URL bahasa Indonesia (`/saya`, `/siswa`, dll.).

- [ ] **Step 3: Perbarui `MenuService::ITEMS` dan filter `/me`**

Ubah array `ITEMS` pada `app/Domains/Access/Services/MenuService.php`:
- `['group' => 'Utama', 'label' => 'Beranda', 'href' => '/dashboard', 'icon' => 'home', 'permission' => null]`
- `['group' => 'Pelayanan', 'label' => 'Siswa', 'href' => '/students', 'icon' => 'graduation-cap', 'permission' => 'siswa.lihat']`
- `['group' => 'Pelayanan', 'label' => 'Data saya', 'href' => '/me', 'icon' => 'graduation-cap', 'permission' => 'profil.lihat']`
- `['group' => 'Pelayanan', 'label' => 'Link pendaftaran', 'href' => '/registration-links', 'icon' => 'link', 'permission' => 'sekolah.tautan']`
- `['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/me/sessions', 'icon' => 'calendar-days', 'permission' => 'pendaftaran.lihat']`
- `['group' => 'Pelayanan', 'label' => 'Antrean verifikasi', 'href' => '/verifications', 'icon' => 'badge-check', 'permission' => 'verifikasi.lihat']`
- `['group' => 'Pelayanan', 'label' => 'Sesi rekaman', 'href' => '/schedules', 'icon' => 'calendar-days', 'permission' => 'jadwal.lihat']`
- `['group' => 'Laporan', 'label' => 'Rekap pendaftaran', 'href' => '/reports', 'icon' => 'file-spreadsheet', 'permission' => 'laporan.lihat']`
- `['group' => 'Sistem & Akses', 'label' => 'Sekolah', 'href' => '/schools', 'icon' => 'school', 'permission' => 'sekolah.lihat']`
- `['group' => 'Sistem & Akses', 'label' => 'Pengguna', 'href' => '/users', 'icon' => 'users', 'permission' => 'user.lihat']`
- `['group' => 'Sistem & Akses', 'label' => 'Role & Permission', 'href' => '/roles', 'icon' => 'shield-check', 'permission' => 'role.lihat']`
- `['group' => 'Sistem & Akses', 'label' => 'Log Aktivitas', 'href' => '/audit-logs', 'icon' => 'scroll-text', 'permission' => 'log.lihat']`

Perbarui filter pengecualian:
```php
if (str_starts_with($item['href'], '/me') && ! $user->isStudent()) {
    continue;
}
```

- [ ] **Step 4: Jalankan test MenuService (Green)**

Run: `php artisan test --filter=MenuServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit perubahan MenuService**

```bash
git add app/Domains/Access/Services/MenuService.php tests/Unit/Domains/Access/MenuServiceTest.php
git commit -m "refactor(menu): perbarui href sidebar ke bahasa inggris dan filter rute /me"
```

---

### Task 3: Perbaruan Frontend: Refactor Pemanggilan `route(...)` di Vue & Layouts

**Files:**
- Modify: `resources/js/Pages/Saya/Index.vue` & `resources/js/Pages/Saya/Sesi.vue` & `resources/js/Pages/Saya/Tiket.vue`
- Modify: `resources/js/Pages/Auth/Daftar.vue`
- Modify: `resources/js/Pages/Siswa/**/*.vue`
- Modify: `resources/js/Pages/Jadwal/**/*.vue`
- Modify: `resources/js/Pages/Verifikasi/**/*.vue`
- Modify: `resources/js/Pages/Laporan/**/*.vue`
- Modify: `resources/js/Pages/Sekolah/**/*.vue`
- Modify: `resources/js/Pages/Pengguna/**/*.vue`
- Modify: `resources/js/Pages/Role/**/*.vue`
- Modify: `resources/js/Pages/Log/**/*.vue`
- Modify: `resources/js/Pages/Notifikasi/**/*.vue`
- Modify: `resources/js/Components/**/*.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Test: `npx vitest run --pool=threads`

**Interfaces:**
- Consumes: Ziggy `route(...)` global helper.
- Produces: Seluruh komponen Vue memanggil named routes bahasa Inggris (`me.show`, `me.update`, `me.sessions.index`, `me.tickets.show`, `schedules.*`, `students.*`, `verifications.*`, dll.).

- [ ] **Step 1: Identifikasi seluruh pemanggilan route bahasa Indonesia di frontend**

Run: `git grep -n "route('" resources/js/` untuk mendata seluruh pemanggilan `route('saya.*')`, `route('siswa.*')`, `route('jadwal.*')`, `route('daftar.*')`, dll.

- [ ] **Step 2: Lakukan refactor pemanggilan `route(...)` ke nama rute baru**

Lakukan pembaruan terstruktur:
1. Ganti `route('saya.show')` &rarr; `route('me.show')`
2. Ganti `route('saya.save')` &rarr; `route('me.update')`
3. Ganti `route('saya.sesi')` &rarr; `route('me.sessions.index')`
4. Ganti `route('saya.sesi.daftar', ...)` &rarr; `route('me.sessions.register', ...)`
5. Ganti `route('saya.tiket', ...)` &rarr; `route('me.tickets.show', ...)`
6. Ganti `route('saya.pendaftaran.batal', ...)` &rarr; `route('me.registrations.cancel', ...)`
7. Ganti `route('saya.pendaftaran.ajukan-ulang', ...)` &rarr; `route('me.registrations.resubmit', ...)`
8. Ganti `route('daftar.*')` &rarr; `route('register.*')`
9. Ganti `route('siswa.*')` &rarr; `route('students.*')`
10. Ganti `route('jadwal.*')` &rarr; `route('schedules.*')`
11. Ganti `route('verifikasi.*')` &rarr; `route('verifications.*')`
12. Ganti `route('laporan.*')` &rarr; `route('reports.*')`
13. Ganti `route('sekolah.*')` &rarr; `route('schools.*')`
14. Ganti `route('pengguna.*')` &rarr; `route('users.*')`
15. Ganti `route('role.*')` &rarr; `route('roles.*')`
16. Ganti `route('tautan.*')` &rarr; `route('registration-links.*')`
17. Ganti `route('log.*')` &rarr; `route('audit-logs.*')`
18. Ganti `route('notifikasi.*')` &rarr; `route('notifications.*')`
19. Ganti `route('aktivasi.*')` &rarr; `route('activation.*')`

- [ ] **Step 3: Jalankan Vitest untuk memverifikasi komponen frontend tetap sehat**

Run: `npx vitest run --pool=threads`
Expected: All existing test files pass.

- [ ] **Step 4: Commit pembaruan route di frontend**

```bash
git add resources/js/
git commit -m "refactor(frontend): perbarui pemanggilan named route ke bahasa inggris di seluruh halaman dan komponen"
```

---

### Task 4: Pembuatan `StudentDashboardService` (Backend Service & Unit Test)

**Files:**
- Create: `app/Domains/Students/Services/StudentDashboardService.php`
- Create: `tests/Unit/Domains/Students/StudentDashboardServiceTest.php`

**Interfaces:**
- Consumes: `User` model with relation to `Student`, `RegistrationStudent`, `Schedule`, `StudentDocumentService`, `EligibilityService`.
- Produces: `forUser(User $user): array{student: array, stepper: array, smart_hero: array, active_entry: ?array, school_schedules: array, guidelines: array}`.

- [ ] **Step 1: Tulis unit test untuk `StudentDashboardService` mencakup seluruh variasi state**

```php
// tests/Unit/Domains/Students/StudentDashboardServiceTest.php
<?php

use App\Domains\Registrations\Enums\RegistrationStatus;
use App\Domains\Registrations\Models\RegistrationStudent;
use App\Domains\Schedules\Models\Schedule;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentDashboardService;
use App\Models\User;

test('menghasilkan state NO_PROFILE jika siswa belum memiliki record student', function () {
    $user = User::factory()->siswa()->create();
    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['student']['has_profile'])->toBeFalse()
        ->and($data['smart_hero']['state'])->toBe('NO_PROFILE')
        ->and($data['smart_hero']['cta_url'])->toBe(route('me.show', absolute: false))
        ->and($data['stepper']['current_step'])->toBe(2);
});

test('menghasilkan state MISSING_DOCS jika dokumen wajib belum lengkap', function () {
    $school = School::factory()->create();
    $user = User::factory()->siswa()->create(['school_id' => $school->id]);
    Student::factory()->create(['user_id' => $user->id, 'school_id' => $school->id]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['student']['has_profile'])->toBeTrue()
        ->and($data['smart_hero']['state'])->toBe('MISSING_DOCS')
        ->and($data['stepper']['current_step'])->toBe(2);
});

test('menghasilkan state TERDAFTAR jika pendaftaran disetujui', function () {
    $school = School::factory()->create();
    $user = User::factory()->siswa()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['user_id' => $user->id, 'school_id' => $school->id]);
    // Mock dokumen lengkap...
    $schedule = Schedule::factory()->create(['school_id' => $school->id, 'date' => now()->addDays(5)]);
    $entry = RegistrationStudent::factory()->create([
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => RegistrationStatus::Terdaftar,
        'ticket_code' => 'TKT-TEST-001',
    ]);

    $service = app(StudentDashboardService::class);
    $data = $service->forUser($user);

    expect($data['smart_hero']['state'])->toBe('TERDAFTAR')
        ->and($data['active_entry']['ticket_code'])->toBe('TKT-TEST-001')
        ->and($data['stepper']['current_step'])->toBe(3);
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan (Red)**

Run: `php artisan test --filter=StudentDashboardServiceTest`
Expected: FAIL (Class `StudentDashboardService` does not exist).

- [ ] **Step 3: Implementasikan `StudentDashboardService`**

Buat berkas `app/Domains/Students/Services/StudentDashboardService.php`:
- Injeksi dependensi: `StudentDocumentService`, `EligibilityService`, `ScheduleRepositoryInterface` / model `Schedule`.
- Metode `forUser(User $user): array`:
  1. Ambil `$student = $user->student()->with(['documents', 'registrationEntries.schedule.school'])->first();`
  2. Hitung kelengkapan dokumen wajib (`kk`, `akta`).
  3. Ambil `$activeEntry = $student?->registrationEntries->first(fn ($e) => $e->status->isActive());`
  4. Bangun array `student`, `stepper`, `smart_hero`, `active_entry`, `school_schedules`, `guidelines`.
  5. Pastikan pengecekan usia KTP Langsung menggunakan `$this->eligibility->directKtp($student, $activeEntry->schedule->date)`.
  6. Kembalikan array payload sesuai spesifikasi.

- [ ] **Step 4: Jalankan unit test (Green)**

Run: `php artisan test --filter=StudentDashboardServiceTest`
Expected: PASS.

- [ ] **Step 5: Jalankan PHPStan**

Run: `vendor/bin/phpstan analyse app/Domains/Students/Services/StudentDashboardService.php --memory-limit=512M`
Expected: 0 errors.

- [ ] **Step 6: Commit `StudentDashboardService`**

```bash
git add app/Domains/Students/Services/StudentDashboardService.php tests/Unit/Domains/Students/StudentDashboardServiceTest.php
git commit -m "feat(student): implementasikan StudentDashboardService untuk kalkulasi alur dan state beranda siswa"
```

---

### Task 5: Dispatcher `DashboardController` & Feature Tests Beranda Siswa

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Create: `tests/Feature/Student/StudentDashboardTest.php`

**Interfaces:**
- Consumes: Authenticated request, `ScheduleService`, `DashboardService`, `StudentDashboardService`.
- Produces: Inertia response `Student/Dashboard` untuk role siswa, atau `Dashboard` untuk role staf.

- [ ] **Step 1: Tulis feature test untuk `DashboardController` role siswa vs staf**

```php
// tests/Feature/Student/StudentDashboardTest.php
<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('tamu dialihkan ke halaman login saat membuka /dashboard', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('siswa mengakses /dashboard mendapatkan halaman Student/Dashboard tanpa redirect', function () {
    $student = User::factory()->siswa()->create();

    $this->actingAs($student)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Dashboard')
            ->has('student')
            ->has('stepper')
            ->has('smart_hero')
            ->has('school_schedules')
            ->has('guidelines')
        );
});

test('staf operator mengakses /dashboard mendapatkan halaman staf Dashboard', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('kpis')
        );
});
```

- [ ] **Step 2: Jalankan feature test untuk memverifikasi kegagalan (Red)**

Run: `php artisan test --filter=StudentDashboardTest`
Expected: FAIL karena `DashboardController` saat ini me-redirect siswa ke `saya.show`.

- [ ] **Step 3: Perbarui `DashboardController`**

Ubah `app/Http/Controllers/DashboardController.php`:
```php
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
```

- [ ] **Step 4: Jalankan feature test (Green)**

Run: `php artisan test --filter=StudentDashboardTest`
Expected: PASS.

- [ ] **Step 5: Commit perubahan `DashboardController`**

```bash
git add app/Http/Controllers/DashboardController.php tests/Feature/Student/StudentDashboardTest.php
git commit -m "feat(dashboard): dispatch role siswa ke halaman Student/Dashboard via StudentDashboardService"
```

---

### Task 6: Pembuatan Subkomponen UI Beranda Siswa

**Files:**
- Create: `resources/js/types/student-dashboard.d.ts`
- Create: `resources/js/Components/student-dashboard/StudentStepper.vue`
- Create: `resources/js/Components/student-dashboard/StudentSmartHero.vue`
- Create: `resources/js/Components/student-dashboard/StudentActiveTicketCard.vue`
- Create: `resources/js/Components/student-dashboard/StudentUpcomingSchedules.vue`
- Create: `resources/js/Components/student-dashboard/StudentGuidelines.vue`
- Create: `resources/js/Components/student-dashboard/StudentComponents.test.ts`

**Interfaces:**
- Consumes: Data props sesuai `student-dashboard.d.ts`.
- Produces: Komponen presentasional yang modular, beraksesibilitas tinggi, dan teruji.

- [ ] **Step 1: Definisikan interface TypeScript di `resources/js/types/student-dashboard.d.ts`**

```typescript
export interface StudentInfo {
    id: number | null;
    name: string;
    nik: string | null;
    nisn: string | null;
    school_name: string;
    has_profile: boolean;
}

export interface StepItem {
    id: number;
    title: string;
    status: 'completed' | 'current' | 'upcoming' | 'error';
    description: string;
}

export interface SmartHeroData {
    state: 'NO_PROFILE' | 'MISSING_DOCS' | 'NO_REGISTRATION' | 'MENUNGGU_VERIFIKASI' | 'PERBAIKAN' | 'TERDAFTAR' | 'SUDAH_REKAMAN' | 'DITOLAK';
    variant: 'amber' | 'blue' | 'indigo' | 'rose' | 'emerald' | 'zinc';
    title: string;
    subtitle: string;
    badge: string;
    cta_label: string;
    cta_url: string;
    deadline_at: string | null;
    notes: string | null;
}

export interface ActiveEntryData {
    id: number;
    status: string;
    status_label: string;
    ticket_code: string | null;
    schedule_date: string;
    time_window: string;
    location: string;
    direct_ktp: boolean;
    days_remaining: number;
}

export interface SchoolScheduleItem {
    id: number;
    date: string;
    quota: number;
    taken: number;
    remaining_quota: number;
    location: string;
    is_open: boolean;
}

export interface StudentDashboardProps {
    student: StudentInfo;
    stepper: {
        current_step: number;
        steps: StepItem[];
    };
    smart_hero: SmartHeroData;
    active_entry: ActiveEntryData | null;
    school_schedules: SchoolScheduleItem[];
    guidelines: string[];
}
```

- [ ] **Step 2: Tulis unit test untuk subkomponen UI di `resources/js/Components/student-dashboard/StudentComponents.test.ts`**

```typescript
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StudentStepper from './StudentStepper.vue';
import StudentSmartHero from './StudentSmartHero.vue';
import StudentActiveTicketCard from './StudentActiveTicketCard.vue';

describe('StudentStepper.vue', () => {
    it('merender seluruh 4 langkah alur pendaftaran siswa', () => {
        const wrapper = mount(StudentStepper, {
            props: {
                currentStep: 2,
                steps: [
                    { id: 1, title: 'Akun Terdaftar', status: 'completed', description: 'Akun aktif' },
                    { id: 2, title: 'Data Diri & Dokumen', status: 'current', description: 'Isi profil' },
                    { id: 3, title: 'Pendaftaran Sesi', status: 'upcoming', description: 'Pilih jadwal' },
                    { id: 4, title: 'Perekaman KTP-el', status: 'upcoming', description: 'Hari-H' },
                ],
            },
        });
        expect(wrapper.text()).toContain('Akun Terdaftar');
        expect(wrapper.text()).toContain('Data Diri & Dokumen');
        expect(wrapper.text()).toContain('Pendaftaran Sesi');
        expect(wrapper.text()).toContain('Perekaman KTP-el');
    });
});

describe('StudentSmartHero.vue', () => {
    it('merender judul, badge, dan tombol CTA aksi primer dengan benar', () => {
        const wrapper = mount(StudentSmartHero, {
            props: {
                hero: {
                    state: 'NO_PROFILE',
                    variant: 'amber',
                    title: 'Lengkapi Data Diri Anda',
                    subtitle: 'Isi biodata Anda terlebih dahulu.',
                    badge: 'Perlu Tindakan',
                    cta_label: 'Lengkapi Sekarang',
                    cta_url: '/me',
                    deadline_at: null,
                    notes: null,
                },
            },
            global: {
                stubs: { Link: { template: '<a :href="$attrs.href"><slot /></a>' } },
            },
        });
        expect(wrapper.text()).toContain('Lengkapi Data Diri Anda');
        expect(wrapper.text()).toContain('Perlu Tindakan');
        expect(wrapper.find('a').attributes('href')).toBe('/me');
    });
});
```

- [ ] **Step 3: Implementasikan kelima subkomponen UI**
1. `StudentStepper.vue`: Bar horizontal responsif, indikator checkmark untuk status `completed`, ring biru untuk `current`, ikon alert untuk `error`.
2. `StudentSmartHero.vue`: Banner hero dinamis dengan tombol aksi primer (`Link`), hitung mundur deadline jika status `PERBAIKAN`, dan badge status yang jelas.
3. `StudentActiveTicketCard.vue`: Kartu ringkasan sesi aktif, tanggal, lokasi, hitung mundur hari menuju sesi, dan lencana "KTP Langsung Cetak" jika `direct_ktp: true`.
4. `StudentUpcomingSchedules.vue`: Daftar jadwal sesi rekaman di sekolah siswa beserta status kuota kursi.
5. `StudentGuidelines.vue`: Kartu checklist tata tertib persiapan hari-H perekaman.

- [ ] **Step 4: Jalankan tes unit komponen UI (Green)**

Run: `npx vitest run resources/js/Components/student-dashboard/StudentComponents.test.ts --pool=threads`
Expected: PASS.

- [ ] **Step 5: Commit subkomponen UI**

```bash
git add resources/js/Components/student-dashboard/ resources/js/types/student-dashboard.d.ts
git commit -m "feat(ui): buat komponen modular beranda siswa (stepper, smart hero, tiket, jadwal, panduan)"
```

---

### Task 7: Halaman Beranda Siswa (`Pages/Student/Dashboard.vue`) & Vitest Integration Test

**Files:**
- Create: `resources/js/Pages/Student/Dashboard.vue`
- Create: `resources/js/Pages/Student/Dashboard.test.ts`

**Interfaces:**
- Consumes: `StudentDashboardProps` payload dari Inertia.
- Produces: Halaman terintegrasi di `AuthenticatedLayout` yang menggabungkan seluruh subkomponen secara harmonis.

- [ ] **Step 1: Tulis tes integrasi halaman di `resources/js/Pages/Student/Dashboard.test.ts`**

```typescript
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Dashboard from './Dashboard.vue';
import type { StudentDashboardProps } from '@/types/student-dashboard';

const mockProps: StudentDashboardProps = {
    student: {
        id: 1,
        name: 'Budi Santoso',
        nik: '2171012345670001',
        nisn: '0061234567',
        school_name: 'MAN 2 Batam',
        has_profile: true,
    },
    stepper: {
        current_step: 3,
        steps: [
            { id: 1, title: 'Akun Terdaftar', status: 'completed', description: 'Akun aktif' },
            { id: 2, title: 'Data Diri & Dokumen', status: 'completed', description: 'Lengkap' },
            { id: 3, title: 'Pendaftaran Sesi', status: 'current', description: 'Menunggu verifikasi' },
            { id: 4, title: 'Perekaman KTP-el', status: 'upcoming', description: 'Hari-H' },
        ],
    },
    smart_hero: {
        state: 'MENUNGGU_VERIFIKASI',
        variant: 'indigo',
        title: 'Berkas Sedang Diverifikasi',
        subtitle: 'Petugas sedang memeriksa berkas Anda.',
        badge: 'Dalam Antrean',
        cta_label: 'Lihat Sesi Rekaman',
        cta_url: '/me/sessions',
        deadline_at: null,
        notes: null,
    },
    active_entry: {
        id: 10,
        status: 'menunggu_verifikasi',
        status_label: 'Menunggu Verifikasi',
        ticket_code: null,
        schedule_date: '2026-11-05',
        time_window: '08:30 - 11:30 WIB',
        location: 'Aula MAN 2 Batam',
        direct_ktp: true,
        days_remaining: 9,
    },
    school_schedules: [],
    guidelines: ['Bawa fotokopi KK', 'Kenakan seragam rapi'],
};

describe('Pages/Student/Dashboard.vue', () => {
    it('merender judul sambutan nama siswa dan sekolah secara harmonis', () => {
        const wrapper = mount(Dashboard, {
            props: mockProps,
            global: {
                stubs: {
                    AuthenticatedLayout: { template: '<div><slot /></div>' },
                    Head: true,
                    Link: { template: '<a :href="$attrs.href"><slot /></a>' },
                },
            },
        });
        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain('MAN 2 Batam');
        expect(wrapper.text()).toContain('Berkas Sedang Diverifikasi');
    });
});
```

- [ ] **Step 2: Jalankan tes integrasi untuk memverifikasi kegagalan (Red)**

Run: `npx vitest run resources/js/Pages/Student/Dashboard.test.ts --pool=threads`
Expected: FAIL (File `Pages/Student/Dashboard.vue` does not exist).

- [ ] **Step 3: Implementasikan `resources/js/Pages/Student/Dashboard.vue`**

Gabungkan subkomponen dengan layout rapi:
- Header sambutan: Nama siswa, asal sekolah, dan status akun aktif.
- Seksi 1: `StudentStepper` di bagian atas dengan kartu berlatar belakang bersih.
- Seksi 2: `StudentSmartHero` sebagai kartu aksi primer yang menonjol.
- Seksi 3: Grid 2 kolom (atau 1 kolom pada mobile):
  - Kolom Kiri: `StudentActiveTicketCard` jika ada pendaftaran aktif, atau `StudentUpcomingSchedules` jika belum terdaftar.
  - Kolom Kanan: `StudentGuidelines` (tata tertib & persiapan hari-H).

- [ ] **Step 4: Jalankan tes integrasi halaman (Green)**

Run: `npx vitest run resources/js/Pages/Student/Dashboard.test.ts --pool=threads`
Expected: PASS.

- [ ] **Step 5: Verifikasi bundle Vite**

Run: `npm run build`
Expected: Build success tanpa warning atau error kompilasi.

- [ ] **Step 6: Commit halaman beranda siswa**

```bash
git add resources/js/Pages/Student/
git commit -m "feat(student): implementasikan halaman utama Beranda Siswa (Student Dashboard)"
```

---

### Task 8: Pembaruan Seluruh Test Suite Pest & Verifikasi Quality Gates Akhir

**Files:**
- Modify: `tests/Feature/**/*.php` (seluruh tes yang memanggil `route('saya.*')`, `route('siswa.*')`, `route('jadwal.*')`, dll.)
- Test: Seluruh unit & feature tests.

**Interfaces:**
- Consumes: Test environment dengan named routes baru.
- Produces: 100% green test suite.

- [ ] **Step 1: Ganti nama-nama rute lama pada seluruh file test di `tests/Feature/`**

Perbarui pemanggilan `route(...)` di dalam file tes:
- `tests/Feature/Students/*.php`
- `tests/Feature/Registrations/*.php`
- `tests/Feature/Schedules/*.php`
- `tests/Feature/Reports/*.php`
- `tests/Feature/Access/*.php`
- `tests/Feature/Notifications/*.php`
- `tests/Feature/Audit/*.php`

- [ ] **Step 2: Jalankan suite Pest PHP secara parallel**

Run: `php artisan test --parallel`
Expected: Seluruh tes (>650 tests) PASS tanpa error.

- [ ] **Step 3: Jalankan seluruh tes Vitest**

Run: `npx vitest run --pool=threads`
Expected: Seluruh unit tes frontend (>210 tests) PASS.

- [ ] **Step 4: Jalankan analisa statis PHPStan**

Run: `vendor/bin/phpstan analyse --memory-limit=512M`
Expected: [OK] No errors (Level 8).

- [ ] **Step 5: Jalankan linter Laravel Pint**

Run: `vendor/bin/pint --test`
Expected: 0 style violations.

- [ ] **Step 6: Jalankan Vite production build**

Run: `npm run build`
Expected: Build berhasil.

- [ ] **Step 7: Commit finalisasi perbaikan test suite**

```bash
git add tests/
git commit -m "test(routes): perbarui seluruh pemanggilan named route pada test suite ke bahasa inggris"
```
