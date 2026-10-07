# Fase 2 — Notifikasi dan Log Aktivitas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, tanpa subagent, pilihan user). Steps memakai checkbox (`- [ ]`).

**Goal:** Menyediakan notifikasi in-app + email (queued) berbasis permission dan penampil audit log baca-saja, lengkap dengan UI bell, halaman notifikasi, halaman log, dan kartu aktivitas di detail pengguna.

**Architecture:** Dua domain baru mengikuti pola yang ada (Controller → Service → Repository interface + Eloquent): `app/Domains/Notifications` (notifikasi bawaan Laravel, kelas dasar `BaseNotification`) dan `app/Domains/Audit` (query-builder atas model activitylog, masker properti). Penghitung belum dibaca dikirim sebagai shared prop `notifications` dan di-refresh lewat partial reload.

**Tech Stack:** Laravel 13, spatie/laravel-activitylog v5, spatie/laravel-query-builder, Inertia v2, Vue 3 + TS, Pest, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-08-fase-2-notifikasi-log-design.md`

## Global Constraints

- Notifikasi: channel `database` + `mail`, `ShouldQueue`, `tries = 3`, `backoff = [60, 300]`; antrean driver `database`.
- Penerima ditentukan permission, bukan nama role; pengguna nonaktif tidak menerima.
- Log hanya baca; `GET /log` di balik `can:log.lihat`; `password`, `password_confirmation`, `token`, `remember_token` dibuang; `nik` dimasker (4 awal + 4 akhir terlihat).
- Retensi log: `logs:prune --days=365`, dijadwalkan harian.
- Semua teks UI bahasa Indonesia; label aksi/jenis/subjek log di `lang/id/activity.php`.
- Pola repo: interface di `Repositories/Contracts`, implementasi `Eloquent*`, binding di `app/Providers/RepositoryServiceProvider.php`.
- FE: Prettier/ESLint Breeze (semicolon, 4 spasi), jalankan `npm run format` sebelum cek; tanpa dependensi baru; warna hanya lewat token.
- Commit: pesan detail, **tanpa** `Co-Authored-By` atau atribusi AI (aturan `CLAUDE.md`).
- Setelah semua task: review `impeccable` (FE) dan `ponytail:ponytail-review`, perbaiki temuan valid, verifikasi akhir.

## Rulings (hasil review ponytail atas rencana)

- Repository notifikasi dipertahankan walau tipis (membungkus `$user->notifications()`) karena pola service-repository adalah aturan proyek dari user; dipotong: `usePolling` (diganti `usePoll` bawaan Inertia), `NotificationService::toUser`, query `types()`, dan komponen dialog terpisah.

## Review Focus

- Query penerima harus memakai `withoutGlobalScopes()`: scope sekolah (`ScopedBySchool`) tidak boleh menyaring penerima saat pengirim operator atau saat dijalankan di queue (Task 2).
- ID notifikasi milik pengguna lain → 404, bukan 403/berhasil (Task 3).
- Pembuat akun yang sudah dihapus/nonaktif: aktivasi tetap selesai tanpa error (Task 4).
- Log dengan pelaku `null` (sistem) atau subjek yang sudah dihapus tetap tampil (Task 5).
- Filter `to` inklusif sampai akhir hari dan memakai zona waktu Asia/Jakarta (Task 5).
- `notifications` bernilai `null` untuk tamu/halaman Error; bell tidak error dan tidak melakukan polling (Task 7).
- Polling (`usePoll`) berhenti saat tab tersembunyi dan tidak berjalan untuk tamu (Task 7).

## File Structure

| File | Tanggung jawab |
|---|---|
| `database/migrations/*_create_notifications_table.php` | Tabel `notifications` standar |
| `app/Domains/Notifications/Notifications/BaseNotification.php` | Kelas dasar: channel, queue, `toArray`, `toMail` |
| `app/Domains/Notifications/Notifications/AccountActivatedNotification.php` | Notifikasi bukti pola |
| `app/Domains/Notifications/Repositories/Contracts/NotificationRepositoryInterface.php`, `EloquentNotificationRepository.php` | Akses data notifikasi + pencarian penerima |
| `app/Domains/Notifications/Services/NotificationService.php` | `toPermission`, `list`, `markRead`, `markAllRead`, `summary` |
| `app/Domains/Notifications/Http/Controllers/NotificationController.php` | Index, tandai dibaca |
| `routes/notifications.php` | Rute notifikasi |
| `app/Domains/Audit/Services/ActivityPropertyMasker.php` | Buang/masker properti sensitif |
| `app/Domains/Audit/Repositories/*` | Query log dengan filter, daftar jenis/pelaku, aktivitas per pengguna |
| `app/Domains/Audit/Services/ActivityLogService.php` | Membentuk `ActivityRow` |
| `app/Domains/Audit/Http/Controllers/ActivityLogController.php` | `GET /log` |
| `app/Console/Commands/PruneActivityLog.php` | `logs:prune` |
| `lang/id/activity.php` | Label aksi, jenis, subjek |
| `resources/js/Components/shared/NotificationItem.vue`, `NotificationBell.vue` | Item dan bell |
| `resources/js/Pages/Notifikasi/Index.vue`, `Pages/Log/Index.vue` | Halaman |
| `resources/js/types/notification.ts` | Tipe `NotificationRow`, `ActivityRow` |

---

### Task 1: Migrasi `notifications` dan `BaseNotification`

**Files:**
- Create: migrasi (via artisan), `app/Domains/Notifications/Notifications/BaseNotification.php`
- Test: `tests/Feature/Notifications/BaseNotificationTest.php`

**Interfaces:**
- Produces: `BaseNotification` abstract dengan `abstract title(): string`, `abstract body(): string`, `abstract url(): string` (path relatif, mis. `/pengguna/5`); `toArray($n): array{title, body, url}`; `toMail($n): MailMessage`.

- [ ] **Step 1: Buat migrasi** — `php artisan make:notifications-table`, lalu `php artisan migrate` dan `php artisan migrate --env=testing` bila DB tes terpisah (Pest memakai `sijebol_test` lewat `RefreshDatabase`, cukup migrasi aplikasi).
- [ ] **Step 2: Tes yang gagal**

```php
<?php

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

function contohNotifikasi(): BaseNotification
{
    return new class extends BaseNotification
    {
        public function title(): string { return 'Judul'; }
        public function body(): string { return 'Isi pesan.'; }
        public function url(): string { return '/pengguna/5'; }
    };
}

test('notifikasi dasar antre dengan channel database dan mail', function () {
    $n = contohNotifikasi();

    expect($n)->toBeInstanceOf(ShouldQueue::class)
        ->and($n->via(new User))->toBe(['database', 'mail'])
        ->and($n->tries)->toBe(3)
        ->and($n->backoff())->toBe([60, 300]);
});

test('toArray memuat title, body, dan url', function () {
    expect(contohNotifikasi()->toArray(new User))->toBe([
        'title' => 'Judul', 'body' => 'Isi pesan.', 'url' => '/pengguna/5',
    ]);
});

test('email berbahasa Indonesia dengan tautan absolut', function () {
    $user = User::factory()->make(['name' => 'Sari']);
    $mail = contohNotifikasi()->toMail($user);

    expect($mail->subject)->toBe('Judul')
        ->and($mail->greeting)->toBe('Halo, Sari.')
        ->and($mail->introLines)->toBe(['Isi pesan.'])
        ->and($mail->actionText)->toBe('Buka di SIJEBOL')
        ->and($mail->actionUrl)->toBe(url('/pengguna/5'));
});
```
- [ ] **Step 3: Jalankan** — `php artisan test --filter=BaseNotificationTest` → FAIL (kelas belum ada).
- [ ] **Step 4: Implementasi**

```php
<?php

namespace App\Domains\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dasar semua notifikasi SIJEBOL: subclass hanya menentukan judul, isi, dan tautan. */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    abstract public function title(): string;

    abstract public function body(): string;

    /** Path relatif di aplikasi, mis. `/pengguna/5`. */
    abstract public function url(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => $this->url()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Halo, '.$notifiable->name.'.')
            ->line($this->body())
            ->action('Buka di SIJEBOL', url($this->url()))
            ->salutation('Salam, SIJEBOL');
    }
}
```
- [ ] **Step 5: Jalankan tes** → PASS; `vendor/bin/pint` dan `vendor/bin/phpstan analyse --memory-limit=1G` bersih.
- [ ] **Step 6: Commit** — "feat: tambah migrasi notifikasi dan kelas dasar BaseNotification".

---

### Task 2: Repository dan `NotificationService`

**Files:**
- Create: `app/Domains/Notifications/Repositories/Contracts/NotificationRepositoryInterface.php`, `.../EloquentNotificationRepository.php`, `app/Domains/Notifications/Services/NotificationService.php`
- Modify: `app/Providers/RepositoryServiceProvider.php` (bind)
- Test: `tests/Feature/Notifications/NotificationServiceTest.php`

**Interfaces:**
- Consumes: `BaseNotification` (Task 1), helper `makeUserWith(array $permissions, ?School $school, bool $scoped, string $roleName)` dari `tests/Pest.php`.
- Produces:
  - `NotificationService::toPermission(string $permission, BaseNotification $notification, ?int $schoolId = null): int`
  - `list(User $user, bool $unreadOnly): LengthAwarePaginator` (item sudah dipetakan lewat `row()`)
  - `markRead(User $user, string $id): void` (404 bila bukan milik user), `markAllRead(User $user): void`
  - `summary(User $user): array{unread_count: int, latest: list<array>}`
  - bentuk baris: `array{id: string, title: string, body: string, url: string, read_at: ?string, created_at: string}`.

- [ ] **Step 1: Tes yang gagal** (`NotificationServiceTest.php`)

```php
<?php

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class NotifikasiUji extends BaseNotification
{
    public function title(): string { return 'T'; }
    public function body(): string { return 'B'; }
    public function url(): string { return '/x'; }
}

function notifikasiUji(): BaseNotification
{
    return new NotifikasiUji;
}

test('penerima adalah pemilik permission yang aktif', function () {
    Notification::fake();
    $penerima = makeUserWith(['verifikasi.putuskan'], roleName: 'verifikator');
    $lain = makeUserWith(['siswa.lihat'], roleName: 'pengamat');
    $nonaktif = makeUserWith(['verifikasi.putuskan'], roleName: 'verifikator');
    $nonaktif->update(['is_active' => false]);

    $jumlah = app(NotificationService::class)->toPermission('verifikasi.putuskan', notifikasiUji());

    expect($jumlah)->toBe(1);
    Notification::assertSentTo($penerima, NotifikasiUji::class);
    Notification::assertNotSentTo([$lain, $nonaktif], NotifikasiUji::class);
});

test('pembatasan sekolah: sekolah lain tidak menerima, pengguna tanpa sekolah menerima', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $opA = makeUserWith(['verifikasi.putuskan'], $a, true, 'op');
    $opB = makeUserWith(['verifikasi.putuskan'], $b, true, 'op');
    $admin = makeUserWith(['verifikasi.putuskan'], roleName: 'admin');

    $jumlah = app(NotificationService::class)->toPermission('verifikasi.putuskan', notifikasiUji(), $a->id);

    expect($jumlah)->toBe(2);
});

test('pencarian penerima tidak tersaring scope sekolah pengirim', function () {
    Notification::fake();
    $a = School::factory()->create();
    $b = School::factory()->create();
    $pengirim = makeUserWith([], $a, true, 'op');
    makeUserWith(['verifikasi.putuskan'], $b, true, 'op2');
    $this->actingAs($pengirim);

    expect(app(NotificationService::class)->toPermission('verifikasi.putuskan', notifikasiUji()))->toBe(1);
});

test('tandai dibaca dan ringkasan', function () {
    $user = User::factory()->create();
    $user->notify(notifikasiUji());
    $user->notify(notifikasiUji());
    $svc = app(NotificationService::class);

    expect($svc->summary($user)['unread_count'])->toBe(2);

    $id = $user->notifications()->first()->id;
    $svc->markRead($user, $id);
    expect($svc->summary($user)['unread_count'])->toBe(1);

    $svc->markAllRead($user);
    expect($svc->summary($user)['unread_count'])->toBe(0)
        ->and($svc->summary($user)['latest'])->toHaveCount(2)
        ->and($svc->summary($user)['latest'][0])->toHaveKeys(['id', 'title', 'body', 'url', 'read_at', 'created_at']);
});

test('tidak bisa menandai notifikasi milik orang lain', function () {
    $pemilik = User::factory()->create();
    $lain = User::factory()->create();
    $pemilik->notify(notifikasiUji());

    app(NotificationService::class)->markRead($lain, $pemilik->notifications()->first()->id);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);
```
- [ ] **Step 2: Jalankan** → FAIL (kelas belum ada).
- [ ] **Step 3: Implementasi**

```php
<?php // Contracts/NotificationRepositoryInterface.php

namespace App\Domains\Notifications\Repositories\Contracts;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    /** @return LengthAwarePaginator<int, DatabaseNotification> */
    public function paginateFor(User $user, bool $unreadOnly, int $perPage = 15): LengthAwarePaginator;

    /** @return Collection<int, DatabaseNotification> */
    public function latestFor(User $user, int $limit = 8): Collection;

    public function unreadCount(User $user): int;

    /** @throws \Illuminate\Database\Eloquent\ModelNotFoundException */
    public function findFor(User $user, string $id): DatabaseNotification;

    public function markRead(DatabaseNotification $notification): void;

    public function markAllRead(User $user): void;

    /** @return Collection<int, User> */
    public function recipientsWithPermission(string $permission, ?int $schoolId): Collection;
}
```

```php
<?php // EloquentNotificationRepository.php

namespace App\Domains\Notifications\Repositories;

use App\Domains\Access\Models\Role;
use App\Domains\Notifications\Repositories\Contracts\NotificationRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function paginateFor(User $user, bool $unreadOnly, int $perPage = 15): LengthAwarePaginator
    {
        $query = $unreadOnly ? $user->unreadNotifications() : $user->notifications();

        return $query->paginate($perPage)->withQueryString();
    }

    public function latestFor(User $user, int $limit = 8): Collection
    {
        return $user->notifications()->limit($limit)->get();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function findFor(User $user, string $id): DatabaseNotification
    {
        return $user->notifications()->whereKey($id)->firstOrFail();
    }

    public function markRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function recipientsWithPermission(string $permission, ?int $schoolId): Collection
    {
        // Tanpa global scope: pengirim operator sekolah tidak boleh membatasi daftar penerima.
        return User::query()->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->permission($permission)
                ->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SUPER_ADMIN)))
            ->when($schoolId !== null, fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->whereNull('school_id')->orWhere('school_id', $schoolId)
            ))
            ->get();
    }
}
```

```php
<?php // Services/NotificationService.php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Domains\Notifications\Repositories\Contracts\NotificationRepositoryInterface;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications) {}

    /** Mengirim ke semua pengguna aktif pemilik permission; mengembalikan jumlah penerima. */
    public function toPermission(string $permission, BaseNotification $notification, ?int $schoolId = null): int
    {
        $recipients = $this->notifications->recipientsWithPermission($permission, $schoolId);
        Notification::send($recipients, $notification);

        return $recipients->count();
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function list(User $user, bool $unreadOnly): LengthAwarePaginator
    {
        return $this->notifications->paginateFor($user, $unreadOnly)->through(fn (DatabaseNotification $n) => $this->row($n));
    }

    public function markRead(User $user, string $id): void
    {
        $this->notifications->markRead($this->notifications->findFor($user, $id));
    }

    public function markAllRead(User $user): void
    {
        $this->notifications->markAllRead($user);
    }

    /** @return array{unread_count: int, latest: list<array<string, mixed>>} */
    public function summary(User $user): array
    {
        return [
            'unread_count' => $this->notifications->unreadCount($user),
            'latest' => $this->notifications->latestFor($user)->map(fn (DatabaseNotification $n) => $this->row($n))->values()->all(),
        ];
    }

    /** @return array{id: string, title: string, body: string, url: string, read_at: ?string, created_at: string} */
    private function row(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'title' => (string) ($n->data['title'] ?? ''),
            'body' => (string) ($n->data['body'] ?? ''),
            'url' => (string) ($n->data['url'] ?? '/dashboard'),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
```
Binding: tambahkan di `RepositoryServiceProvider` `NotificationRepositoryInterface::class => EloquentNotificationRepository::class` (ikuti format yang ada di file itu).
- [ ] **Step 4: Jalankan tes** → PASS; Pint + Larastan bersih.
- [ ] **Step 5: Commit** — "feat: tambah NotificationService dan repository notifikasi".

---

### Task 3: Endpoint notifikasi dan shared prop

**Files:**
- Create: `app/Domains/Notifications/Http/Controllers/NotificationController.php`, `routes/notifications.php`
- Modify: file yang memuat `routes/access.php` (cari dengan `grep -rn "access.php" routes bootstrap app`) untuk memuat `routes/notifications.php` dengan cara sama; `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Notifications/NotificationHttpTest.php`

**Interfaces:**
- Consumes: `NotificationService` (Task 2).
- Produces: rute `notifikasi.index` (GET `/notifikasi`), `notifikasi.read` (POST `/notifikasi/{id}/baca`), `notifikasi.read-all` (POST `/notifikasi/baca-semua`); Inertia page `Notifikasi/Index` dengan props `{ notifications: Paginator<NotificationRow>, filters: {status?: string} }`; shared prop `notifications: {unread_count, latest} | null`.

- [ ] **Step 1: Tes yang gagal**

```php
<?php

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Models\User;

class NotifikasiHttpUji extends BaseNotification
{
    public function title(): string { return 'Judul'; }
    public function body(): string { return 'Isi'; }
    public function url(): string { return '/dashboard'; }
}

test('tamu diarahkan ke login', function () {
    $this->get('/notifikasi')->assertRedirect('/login');
    $this->post('/notifikasi/baca-semua')->assertRedirect('/login');
});

test('daftar menampilkan notifikasi sendiri dan filter belum dibaca', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiHttpUji);
    $user->notify(new NotifikasiHttpUji);
    $user->notifications()->first()->markAsRead();

    $this->actingAs($user)->get('/notifikasi')
        ->assertInertia(fn ($p) => $p->component('Notifikasi/Index')->has('notifications.data', 2));

    $this->actingAs($user)->get('/notifikasi?filter[status]=unread')
        ->assertInertia(fn ($p) => $p->has('notifications.data', 1));
});

test('tandai satu dan semua dibaca', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiHttpUji);
    $user->notify(new NotifikasiHttpUji);
    $id = $user->notifications()->first()->id;

    $this->actingAs($user)->post("/notifikasi/{$id}/baca")->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)->post('/notifikasi/baca-semua')->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

test('notifikasi milik orang lain tidak bisa ditandai (404)', function () {
    $pemilik = User::factory()->create();
    $lain = User::factory()->create();
    $pemilik->notify(new NotifikasiHttpUji);

    $this->actingAs($lain)->post('/notifikasi/'.$pemilik->notifications()->first()->id.'/baca')->assertNotFound();
});

test('shared prop notifications berisi ringkasan untuk user dan null untuk tamu', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiHttpUji);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($p) => $p->where('notifications.unread_count', 1)->has('notifications.latest', 1));

    auth()->logout();
    $this->get('/login')->assertInertia(fn ($p) => $p->where('notifications', null));
});
```
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**

```php
<?php // routes/notifications.php

use App\Domains\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::post('notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifikasi.read-all');
    Route::post('notifikasi/{id}/baca', [NotificationController::class, 'read'])->name('notifikasi.read');
});
```

```php
<?php // NotificationController.php

namespace App\Domains\Notifications\Http\Controllers;

use App\Domains\Notifications\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $status = $request->query('filter')['status'] ?? null;

        return Inertia::render('Notifikasi/Index', [
            'notifications' => $this->notifications->list($request->user(), $status === 'unread'),
            'filters' => ['status' => $status],
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $this->notifications->markRead($request->user(), $id);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back();
    }
}
```
Di `HandleInertiaRequests::share`, tambahkan setelah `'menu'`: `'notifications' => fn () => $user ? app(NotificationService::class)->summary($user) : null,` (impor kelas). Muat `routes/notifications.php` di tempat yang sama dengan `routes/access.php`.
- [ ] **Step 4: Jalankan tes** → PASS; seluruh suite hijau (`php artisan test --compact`), Pint, Larastan.
- [ ] **Step 5: Commit** — "feat: tambah endpoint notifikasi dan shared prop ringkasan".

---

### Task 4: Notifikasi bukti pola — akun diaktifkan

**Files:**
- Create: `app/Domains/Notifications/Notifications/AccountActivatedNotification.php`
- Modify: `app/Domains/Access/Services/ActivationService.php` (`complete`)
- Test: `tests/Feature/Notifications/AccountActivatedTest.php`

**Interfaces:**
- Consumes: `User::creator()` (relasi `created_by`); memakai `$creator->notify(...)` langsung (satu penerima, tanpa service).
- Produces: `new AccountActivatedNotification(User $activated)`.

- [ ] **Step 1: Tes yang gagal** — baca `tests/Feature/Access/ActivationServiceTest.php` untuk cara membuat token (`ActivationService::issue`) dan ikuti.

```php
<?php

use App\Domains\Access\Services\ActivationService;
use App\Domains\Notifications\Notifications\AccountActivatedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('pembuat akun diberi tahu saat akun diaktifkan', function () {
    Notification::fake();
    $admin = User::factory()->create();
    $user = User::factory()->create(['created_by' => $admin->id, 'activated_at' => null]);
    $token = app(ActivationService::class)->issue($user);

    app(ActivationService::class)->complete($token, 'abcd1234');

    Notification::assertSentTo($admin, AccountActivatedNotification::class, function ($n) use ($user) {
        return str_contains($n->body(), $user->name) && $n->url() === "/pengguna/{$user->id}";
    });
});

test('aktivasi tetap selesai bila pembuat tidak ada atau nonaktif', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => false]);
    $user = User::factory()->create(['created_by' => $admin->id, 'activated_at' => null]);
    $tanpa = User::factory()->create(['created_by' => null, 'activated_at' => null]);
    $svc = app(ActivationService::class);

    $svc->complete($svc->issue($user), 'abcd1234');
    $svc->complete($svc->issue($tanpa), 'abcd1234');

    Notification::assertNothingSent();
    expect($user->fresh()->activated_at)->not->toBeNull()->and($tanpa->fresh()->activated_at)->not->toBeNull();
});
```
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Domains\Notifications\Notifications;

use App\Models\User;

class AccountActivatedNotification extends BaseNotification
{
    public function __construct(private readonly User $activated) {}

    public function title(): string
    {
        return 'Akun diaktifkan';
    }

    public function body(): string
    {
        return "{$this->activated->name} telah mengaktifkan akunnya.";
    }

    public function url(): string
    {
        return "/pengguna/{$this->activated->id}";
    }
}
```
Di `ActivationService::complete`, setelah `activity(...)->log('aktivasi.selesai')` dan masih di dalam transaksi? Kirim **setelah** transaksi selesai agar tidak terkirim bila rollback: ubah `return DB::transaction(...)` menjadi `$user = DB::transaction(...); ...; return $user;` dan sebelum return:

```php
$creator = $user->creator;
if ($creator !== null && $creator->is_active) {
    $creator->notify(new AccountActivatedNotification($user));
}
```
(Notifikasi antre sehingga tidak memperlambat respons.)
- [ ] **Step 4: Jalankan tes** → PASS + suite hijau (termasuk `ActivationServiceTest`, `ActivationHttpTest`).
- [ ] **Step 5: Commit** — "feat: kirim notifikasi ke pembuat akun saat akun diaktifkan".

---

### Task 5: Penampil log aktivitas (BE)

**Files:**
- Create: `app/Domains/Audit/Services/ActivityPropertyMasker.php`, `app/Domains/Audit/Repositories/Contracts/ActivityLogRepositoryInterface.php`, `app/Domains/Audit/Repositories/EloquentActivityLogRepository.php`, `app/Domains/Audit/Services/ActivityLogService.php`, `app/Domains/Audit/Http/Controllers/ActivityLogController.php`, `lang/id/activity.php`
- Modify: `RepositoryServiceProvider`, `routes/notifications.php`→ tidak; tambah `routes/audit.php` (dimuat seperti access.php), `app/Domains/Access/Services/MenuService.php` (item Log), `app/Domains/Access/Http/Controllers/UserController.php` (`show`), `tests/Feature/Access/RoutePermissionMatrixTest.php` (dataset `/log`), tes menu bila perlu
- Test: `tests/Feature/Audit/ActivityPropertyMaskerTest.php`, `tests/Feature/Audit/ActivityLogHttpTest.php`

**Interfaces:**
- Produces:
  - `ActivityPropertyMasker::apply(array $values): array`
  - `ActivityLogRepositoryInterface`: `paginate(int $perPage = 25): LengthAwarePaginator` (filter dari query string via QueryBuilder), `causers(): Collection<int, User>` (id, name), `recentFor(User $user, int $limit = 10): Collection<int, Activity>`
  - `ActivityLogService::list(): LengthAwarePaginator` (baris `ActivityRow`), `typeOptions(): list<array{value:string,label:string}>` (dari kunci `trans('activity.types')`, tanpa query), `userOptions(): list<array{id:int,name:string}>`, `recentFor(User $user): list<ActivityRow>`
  - `ActivityRow` = `array{id: int, created_at: string, causer: ?array{id:int,name:string}, type: string, type_label: string, action: string, subject: array{type: string, label: string}, changes: list<array{field: string, old: mixed, new: mixed}>}`
  - Rute `log.index` (GET `/log`, `can:log.lihat`), Inertia `Log/Index` props `{ activities, filters, types, users }`; prop baru `activities: list<ActivityRow>|null` pada `Pengguna/Show`.

- [ ] **Step 1: Tes yang gagal — masker**

```php
<?php

use App\Domains\Audit\Services\ActivityPropertyMasker;

test('membuang kunci rahasia dan memasker NIK', function () {
    $hasil = ActivityPropertyMasker::apply([
        'name' => 'Budi', 'password' => 'x', 'password_confirmation' => 'x',
        'token' => 't', 'remember_token' => 'r', 'nik' => '3201123412341234',
    ]);

    expect($hasil)->toBe(['name' => 'Budi', 'nik' => '3201********1234']);
});

test('NIK pendek dimasker penuh dan nilai bukan string dibiarkan', function () {
    expect(ActivityPropertyMasker::apply(['nik' => '12345']))->toBe(['nik' => '*****'])
        ->and(ActivityPropertyMasker::apply(['nik' => null]))->toBe(['nik' => null]);
});
```
- [ ] **Step 2: Tes yang gagal — HTTP** (`ActivityLogHttpTest.php`)

```php
<?php

use App\Models\User;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

test('403 tanpa izin dan 200 dengan izin', function () {
    $this->actingAs(makeUserWith([], roleName: 'biasa'))->get('/log')->assertForbidden();
    $this->actingAs(makeUserWith(['log.lihat'], roleName: 'pengamat'))->get('/log')
        ->assertOk()->assertInertia(fn ($p) => $p->component('Log/Index')->has('activities.data')->has('types')->has('users'));
});

test('filter pengguna, jenis, dan rentang tanggal (to inklusif)', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    $a = User::factory()->create(['name' => 'Aa']);
    $b = User::factory()->create(['name' => 'Bb']);
    activity('akses')->causedBy($a)->log('login');
    activity('default')->causedBy($b)->log('updated');
    $akhir = activity('akses')->causedBy($a)->log('logout');
    $akhir->forceFill(['created_at' => Carbon::parse('2026-10-05 23:30', 'Asia/Jakarta')->utc()])->save();

    $this->actingAs($viewer);
    $this->get("/log?filter[user]={$a->id}")->assertInertia(fn ($p) => $p->has('activities.data', 2));
    $this->get('/log?filter[type]=default')->assertInertia(fn ($p) => $p->has('activities.data', 1));
    $this->get('/log?filter[from]=2026-10-05&filter[to]=2026-10-05')
        ->assertInertia(fn ($p) => $p->has('activities.data', 1)->where('activities.data.0.action', 'Logout'));
});

test('baris dengan pelaku sistem dan subjek terhapus tetap tampil, dengan label Indonesia', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    $hapus = User::factory()->create(['name' => 'Hilang']);
    activity('akses')->performedOn($hapus)->log('user.dinonaktifkan');
    $hapus->forceDelete();

    $this->actingAs($viewer)->get('/log')->assertOk()->assertInertia(fn ($p) => $p
        ->where('activities.data.0.causer', null)
        ->where('activities.data.0.action', 'Pengguna dinonaktifkan')
        ->where('activities.data.0.subject.type', 'Pengguna'));
});

test('perubahan memuat sebelum/sesudah dan properti sensitif tidak bocor', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    activity('data')->causedBy($viewer)->withProperties([
        'attributes' => ['name' => 'Baru', 'password' => 'rahasia', 'nik' => '3201123412341234'],
        'old' => ['name' => 'Lama', 'password' => 'lama'],
    ])->log('updated');

    $json = $this->actingAs($viewer)->get('/log')->assertOk()->getContent();

    expect($json)->not->toContain('rahasia')->and($json)->not->toContain('3201123412341234')->and($json)->toContain('3201********1234');
    $this->get('/log')->assertInertia(fn ($p) => $p->where('activities.data.0.changes.0', ['field' => 'name', 'old' => 'Lama', 'new' => 'Baru']));
});

test('tidak ada route tulis untuk log', function () {
    $viewer = makeUserWith(['log.lihat'], roleName: 'pengamat');
    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        $this->actingAs($viewer)->{$method}('/log')->assertStatus(405);
    }
});

test('detail pengguna menyertakan aktivitas hanya bagi pemilik log.lihat', function () {
    $target = User::factory()->create();
    activity('akses')->performedOn($target)->causedBy($target)->log('login');
    $dengan = makeUserWith(['user.lihat', 'log.lihat'], roleName: 'dengan');
    $tanpa = makeUserWith(['user.lihat'], roleName: 'tanpa');

    $this->actingAs($dengan)->get("/pengguna/{$target->id}")->assertInertia(fn ($p) => $p->has('activities', 1));
    $this->actingAs($tanpa)->get("/pengguna/{$target->id}")->assertInertia(fn ($p) => $p->where('activities', null));
});
```
- [ ] **Step 3: Jalankan** → FAIL.
- [ ] **Step 4: Implementasi**

`ActivityPropertyMasker`:
```php
<?php

namespace App\Domains\Audit\Services;

final class ActivityPropertyMasker
{
    private const DROP = ['password', 'password_confirmation', 'token', 'remember_token'];

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function apply(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (in_array($key, self::DROP, true)) {
                continue;
            }
            $out[$key] = $key === 'nik' && is_string($value) ? self::maskNik($value) : $value;
        }

        return $out;
    }

    private static function maskNik(string $nik): string
    {
        $length = strlen($nik);

        return $length <= 8
            ? str_repeat('*', $length)
            : substr($nik, 0, 4).str_repeat('*', $length - 8).substr($nik, -4);
    }
}
```

`lang/id/activity.php`:
```php
<?php

return [
    'types' => ['akses' => 'Akses', 'default' => 'Data'],
    'subjects' => ['User' => 'Pengguna', 'Role' => 'Role', 'School' => 'Sekolah'],
    'actions' => [
        'login' => 'Login', 'logout' => 'Logout', 'lockout' => 'Akun terkunci sementara',
        'created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus',
        'aktivasi.dikirim' => 'Link aktivasi dibuat', 'aktivasi.selesai' => 'Aktivasi selesai',
        'user.dinonaktifkan' => 'Pengguna dinonaktifkan', 'user.diaktifkan' => 'Pengguna diaktifkan',
        'role.dihapus' => 'Role dihapus', 'role.izin-diubah' => 'Izin role diubah',
    ],
];
```
(Sebelum menulis berkas, `grep -rn "log('" app` dan `->log(` untuk daftar deskripsi sebenarnya dan sesuaikan kunci `actions` agar semua deskripsi yang ada punya label; kunci yang tak dikenal jatuh ke teks aslinya.)

Repository (interface + Eloquent): `paginate` memakai
```php
QueryBuilder::for(Activity::query()->with(['causer', 'subject'])->latest('created_at')->latest('id'))
    ->allowedFilters(
        AllowedFilter::callback('user', fn (Builder $q, $v) => $q->where('causer_type', User::class)->where('causer_id', $v)),
        AllowedFilter::exact('type', 'log_name'),
        AllowedFilter::callback('from', fn (Builder $q, $v) => $q->where('created_at', '>=', Carbon::parse($v, 'Asia/Jakarta')->startOfDay()->setTimezone(config('app.timezone')))),
        AllowedFilter::callback('to', fn (Builder $q, $v) => $q->where('created_at', '<=', Carbon::parse($v, 'Asia/Jakarta')->endOfDay()->setTimezone(config('app.timezone')))),
    )->paginate($perPage)->withQueryString();
```
`causers()` = `User::query()->withoutGlobalScopes()->whereIn('id', Activity::query()->where('causer_type', User::class)->select('causer_id'))->orderBy('name')->get(['id','name'])`; `recentFor($user, $limit)` = activity di mana (`causer_type = User` dan `causer_id = id`) atau (`subject_type = User` dan `subject_id = id`), `with(['causer','subject'])->latest('created_at')->latest('id')->limit($limit)->get()`.

`ActivityLogService::row(Activity $a): array`: `type_label` = `label('types', $a->log_name)`; `action` = `label('actions', $a->description)`; `subject` = `['type' => label('subjects', class_basename($a->subject_type ?? '')), 'label' => nama subjek (`$a->subject?->name ?? $a->subject?->title`) atau `'#'.$a->subject_id` bila subjek hilang, atau `'—'` bila `subject_type` null]`; `causer` = `$a->causer ? ['id'=>..., 'name'=>...] : null`; `changes`: `$new = ActivityPropertyMasker::apply((array) ($a->properties['attributes'] ?? []))`, `$old = ActivityPropertyMasker::apply((array) ($a->properties['old'] ?? []))`, daftar `['field' => $k, 'old' => $old[$k] ?? null, 'new' => $v]` untuk tiap `$new`; `label($prefix, $key)` = `Lang::has("activity.$prefix.$key") ? __("activity.$prefix.$key") : (string) $key` — **cek**: kunci berisi titik (mis. `aktivasi.selesai`) tidak bisa dipakai di `Lang::has` dot-notation; gunakan `trans("activity.$prefix")` mengambil array lalu `$array[$key] ?? $key` agar aman.

Controller `ActivityLogController@index` merender `Log/Index` dengan `['activities' => $svc->list(), 'filters' => $request->query('filter', []), 'types' => $svc->typeOptions(), 'users' => $svc->userOptions()]`. `routes/audit.php`: `Route::middleware(['auth', 'can:log.lihat'])->get('log', [ActivityLogController::class, 'index'])->name('log.index');` dimuat seperti `access.php`. Bind repository di `RepositoryServiceProvider`.

`MenuService::ITEMS`: tambahkan `['group' => 'Sistem', 'label' => 'Log Aktivitas', 'href' => '/log', 'icon' => 'scroll-text', 'permission' => 'log.lihat']`. `UserController::show`: injeksikan `ActivityLogService` (konstruktor) dan tambahkan ke props `'activities' => $request->user()->can('log.lihat') ? $this->activityLog->recentFor($user) : null`. Tambahkan `['GET', '/log', 'log.lihat']` ke dataset `rute`.
- [ ] **Step 5: Jalankan tes** → PASS; seluruh suite (`MenuTest` mungkin perlu penyesuaian karena menu admin bertambah satu grup — ubah ekspektasinya), Pint, Larastan.
- [ ] **Step 6: Commit** — "feat: tambah penampil log aktivitas dengan filter dan masker properti".

---

### Task 6: Retensi `logs:prune`

**Files:**
- Create: `app/Console/Commands/PruneActivityLog.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Audit/PruneActivityLogTest.php`

**Interfaces:** command `logs:prune {--days=365}` mencetak "N baris log dihapus." dan keluar 0.

- [ ] **Step 1: Tes yang gagal**

```php
<?php

use Spatie\Activitylog\Models\Activity;

test('menghapus hanya log yang lebih tua dari batas hari', function () {
    $lama = activity('akses')->log('lama');
    $lama->forceFill(['created_at' => now()->subDays(400)])->save();
    $batas = activity('akses')->log('hampir');
    $batas->forceFill(['created_at' => now()->subDays(364)])->save();
    activity('akses')->log('baru');

    $this->artisan('logs:prune')->expectsOutput('1 baris log dihapus.')->assertExitCode(0);

    expect(Activity::count())->toBe(2)->and(Activity::where('description', 'lama')->exists())->toBeFalse();
});

test('opsi --days mengubah batas', function () {
    $log = activity('akses')->log('x');
    $log->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->artisan('logs:prune', ['--days' => 30])->expectsOutput('1 baris log dihapus.');
    expect(Activity::count())->toBe(0);
});
```
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class PruneActivityLog extends Command
{
    protected $signature = 'logs:prune {--days=365 : Hapus log yang lebih tua dari jumlah hari ini}';

    protected $description = 'Hapus log aktivitas yang melewati masa retensi';

    public function handle(): int
    {
        $deleted = Activity::query()->where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
        $this->info("{$deleted} baris log dihapus.");

        return self::SUCCESS;
    }
}
```
`routes/console.php`: `use Illuminate\Support\Facades\Schedule;` dan `Schedule::command('logs:prune')->daily();`.
- [ ] **Step 4: Jalankan tes** → PASS; `php artisan schedule:list` menampilkan `logs:prune`; seluruh suite + Larastan + Pint hijau.
- [ ] **Step 5: Verifikasi worker lokal (exit criteria BE):** set `MAIL_MAILER=log` di `.env` lokal bila belum; jalankan `php artisan queue:work --once` setelah membuat satu notifikasi lewat `php artisan tinker` (`User::first()->notify(new App\Domains\Notifications\Notifications\AccountActivatedNotification(User::latest()->first()))`); pastikan baris email muncul di `storage/logs/laravel.log` dan `jobs` kosong. Catat langkah queue worker + supervisor di `docs/` (`docs/operasional-queue.md`, ±15 baris).
- [ ] **Step 6: Commit** — "feat: tambah command retensi log dan dokumentasi queue worker".

---

### Task 7: UI — panduan 5.17, utilitas, `NotificationItem`, `NotificationBell`

**Files:**
- Modify: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (tambah 5.17 Log Aktivitas, dari spec Fase 2 §4.2), `resources/js/lib/date.ts` + `date.test.ts` (kembalikan `formatTime`/`formatDateTime`), `resources/js/types/index.d.ts` (prop `notifications`), `resources/js/config/menuIcons.ts` (`'scroll-text': ScrollText`), `resources/js/Layouts/partials/Topbar.vue` (ganti dropdown bell)
- Create: `resources/js/types/notification.ts`, `resources/js/Components/shared/NotificationItem.vue`, `resources/js/Components/shared/NotificationBell.vue`

**Interfaces:**
- Produces: `formatTime(value): string` ("08.00"), `formatDateTime(value): string` ("6 Okt 2026 08.00"→ gunakan format spec "6 Okt 2026 · 08.00": `${formatDate} · ${formatTime}`); `type NotificationRow = { id; title; body; url; read_at: string|null; created_at: string }`; `type ActivityRow` sesuai Task 5; `<NotificationItem :notification="row" @read="id" />`.

- [ ] **Step 1: Tes yang gagal** — `date.test.ts`: `formatTime('2026-10-07T07:30:00Z')` → `'14.30'`; `formatDateTime('2026-10-07T07:30:00Z')` → `'7 Okt 2026 · 14.30'`; null → `'—'`.
- [ ] **Step 2: Jalankan** `npm test` → FAIL.
- [ ] **Step 3: Implementasi util.** `date.ts`: tambahkan `timeFmt` (`id-ID`, `hour:'2-digit'`, `minute:'2-digit'`, `hourCycle:'h23'`, zona `Asia/Jakarta`), `formatTime`, `formatDateTime = d => ${formatDate(d)} · ${formatTime(d)}` (kembalikan `—` bila tidak valid). Polling memakai `usePoll` bawaan Inertia (tidak ada composable sendiri).
- [ ] **Step 4: Panduan UI 5.17** — tambahkan bagian sebelum "6. Pola lintas halaman" dengan isi spec §4.2 (header, filter, tabel, dialog detail, mobile) dan tambah baris `log` ke daftar menu bila ada.
- [ ] **Step 5: `NotificationItem.vue`** — props `{ notification: NotificationRow }`, emit `read: [id: string]`. Markup: `<li>` dengan `<Link :href="notification.url" @click="emit('read', notification.id)">` badan: titik biru `size-2 rounded-full bg-primary` (hanya bila `read_at === null`, sisanya spacer), judul satu baris `truncate text-sm` (`font-semibold` bila belum dibaca), deskripsi `truncate text-xs text-muted-foreground`, waktu `relativeTime(created_at)` `text-xs`; latar `bg-primary-soft/40` bila belum dibaca; tombol ikon `Check` (`size-8`, `aria-label="Tandai dibaca"`) hanya bila belum dibaca, terlihat `sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100` dan selalu tampil di mobile; klik tombol `@click.prevent.stop` emit `read`.
- [ ] **Step 6: `NotificationBell.vue`** — props tidak ada; baca `usePage<PageProps>().props.notifications` (bisa `null` → render tombol bell tanpa badge dan tanpa polling). `DropdownMenu` (komponen shadcn yang sama dengan Topbar): trigger `Button` ghost `size-10` dengan `Bell` + badge `absolute` (`min-w-4 h-4 rounded-full bg-danger text-[10px] text-white`, teks `9+` bila >9, tidak dirender bila 0), `aria-label` = `Notifikasi, N belum dibaca` / `Notifikasi`. Konten: header (`h-16`, "Notifikasi" + tombol teks "Tandai semua dibaca" bila `unread_count > 0` → `router.post(route('notifikasi.read-all'), {}, { preserveScroll: true })`), daftar `latest` (maks 8) dengan `NotificationItem` (`@read` → `router.post(route('notifikasi.read', id), {}, { preserveScroll: true, preserveState: true })`), empty state yang sudah ada ("Belum ada notifikasi"), footer `Link` "Lihat semua" ke `route('notifikasi.index')`. `usePoll(60_000, { only: ['notifications'] })` (dari `@inertiajs/vue3`; otomatis menjeda saat tab tersembunyi) — dipanggil hanya bila `notifications !== null` di awal `setup` (tamu/halaman Error tidak berpolling).
- [ ] **Step 7: Topbar** — ganti seluruh blok `DropdownMenu` bell (±30 baris) dengan `<NotificationBell />`; hapus impor yang tak terpakai. Tambah `ScrollText` di `menuIcons.ts` (+ tes `menuIcons.test.ts` untuk kunci `'scroll-text'`). Tambahkan `notifications: { unread_count: number; latest: NotificationRow[] } | null` ke `PageProps`.
- [ ] **Step 8: Cek** — `npm run format && npm test && npm run typecheck && npm run lint && npm run build` bersih.
- [ ] **Step 9: Verifikasi browser** — buat 2 notifikasi via tinker untuk Sari; bell menampilkan badge "2", dropdown berisi 2 item, tandai satu → badge "1" tanpa reload penuh, "Tandai semua dibaca" → badge hilang; dengan `document.visibilityState` hidden tidak ada request polling (hitung `performance.getEntriesByType('resource')` sebelum/sesudah menunggu >60 dtk memakai interval pendek sementara bila perlu); halaman Error/tamu tidak error; mobile 390 dropdown tidak melebihi layar.
- [ ] **Step 10: Commit** — "feat: tambah lonceng notifikasi dengan polling dan utilitas waktu".

---

### Task 8: Halaman `/notifikasi`

**Files:**
- Create: `resources/js/Pages/Notifikasi/Index.vue`
- Test: browser

**Interfaces:**
- Consumes: `NotificationItem`, props `{ notifications: Paginator<NotificationRow>, filters: { status?: string | null } }`, rute `notifikasi.index|read|read-all`, `Pagination`, `PageHeader`, `EmptyState`.

- [ ] **Step 1: Halaman** — `AppLayout title="Notifikasi"`; `PageHeader title="Notifikasi" description="Pemberitahuan sistem untuk akun Anda."` dengan tombol outline "Tandai semua dibaca" (`h-10`, nonaktif bila tidak ada yang belum dibaca di halaman — gunakan `shared.notifications.unread_count`); segmen dua tombol "Semua" / "Belum dibaca" (`role="tablist"` sederhana: dua `Link` dengan `aria-current`, `href` ke `route('notifikasi.index')` dan `route('notifikasi.index', { filter: { status: 'unread' } })`, gaya `h-9 px-4 rounded-lg` aktif `bg-primary-soft text-primary`); daftar `<ul class="bg-card divide-y rounded-lg border">` berisi `NotificationItem`; `EmptyState` (`icon=Bell`, "Tidak ada notifikasi", deskripsi berbeda untuk filter belum dibaca: "Semua notifikasi sudah dibaca."); `Pagination :paginator="notifications"`.
- [ ] **Step 2: Cek** format/typecheck/lint/build.
- [ ] **Step 3: Verifikasi browser** — daftar tampil, segmen "Belum dibaca" mengubah URL `?filter[status]=unread`, klik item membuka tujuan dan menandai dibaca, empty state, paginasi (buat 20 notifikasi via tinker), mobile 390 tanpa overflow.
- [ ] **Step 4: Commit** — "feat: tambah halaman daftar notifikasi".

---

### Task 9: Halaman Log Aktivitas

**Files:**
- Create: `resources/js/Pages/Log/Index.vue`

**Interfaces:**
- Consumes: props `{ activities: Paginator<ActivityRow>, filters: Record<string,string|undefined>, types: {value,label}[], users: {id,name}[] }`; `useDataTable('/log', { user, type, from, to })`; `DataTable`, `FormField`, `SelectField`, `Input` (type=date), `Pagination`, `EmptyState`, `Dialog*`, `formatDateTime`, `relativeTime`.

- [ ] **Step 1: Index.vue** — pola `Pengguna/Index.vue`: `PageHeader title="Log Aktivitas" description="Rekam jejak aktivitas di SIJEBOL."`; filter grid: Pengguna (`SelectField all-label="Semua pengguna"`, opsi dari `users`), Jenis (`SelectField all-label="Semua jenis"`, opsi `types`), Dari tanggal & Sampai tanggal (`Input type="date" class="h-10"`), semuanya memicu `apply` (`watch` pada keempat nilai); `DataTable` kolom Waktu (`class="w-[168px]"`; < 24 jam → `relativeTime`, selain itu `formatDateTime`; atribut `title` = `formatDateTime`), Pengguna (`causer?.name ?? 'Sistem'`), Aksi (`action`), Subjek (`subject.type` + `subject.label` di bawahnya kecil), Ringkasan (dua perubahan pertama `field: lama → baru`, `truncate`, kosong → "—"), Aksi (tombol teks "Detail" membuka dialog yang ditulis inline di halaman ini: `DialogTitle` = `activity.action`, deskripsi = `${causer ?? 'Sistem'} · ${formatDateTime(created_at)}`, baris subjek, tabel Kolom / Sebelum / Sesudah bila `changes.length` (null → "—", objek/array → `JSON.stringify`), kosong → "Tidak ada perubahan data.", tombol "Tutup"); state: loading skeleton (bawaan), `EmptyState` "Belum ada aktivitas" dan hasil-filter-kosong dengan "Reset filter"; petunjuk "↔ Geser tabel" mobile; `table-class="min-w-[960px]"`; `Pagination`.
- [ ] **Step 2: Cek** format/typecheck/lint/build.
- [ ] **Step 3: Verifikasi browser** — login sebagai Sari (admin kecamatan memiliki `log.lihat`; bila tidak, cek `AccessSeeder`), menu "Log Aktivitas" muncul; filter pengguna/jenis/tanggal mengubah URL `filter[...]` dan hasil; dialog detail menampilkan sebelum/sesudah (ubah satu sekolah dulu untuk membuat log); operator melihat 403 (halaman Error); mobile 390 tanpa overflow.
- [ ] **Step 4: Commit** — "feat: tambah halaman log aktivitas dengan filter dan detail".

---

### Task 10: Kartu "Aktivitas terbaru" di detail pengguna

**Files:**
- Modify: `resources/js/Pages/Pengguna/Show.vue`

**Interfaces:**
- Consumes: prop `activities: ActivityRow[] | null` (Task 5), `usePermission`, `PERMISSIONS.LogLihat` (tambahkan bila belum ada di `lib/permissions.ts`).

- [ ] **Step 1: Kartu** — di bawah kartu "Informasi akun", bila `activities !== null`: `section.bg-card.overflow-hidden.rounded-lg.border` dengan header (`h-14 px-6`): judul "Aktivitas terbaru" + tautan "Lihat semua" (`Link`, `text-primary`, ke `route('log.index', { filter: { user: user.id } })`, hanya bila `can(PERMISSIONS.LogLihat)`); baris kolom Waktu / Aksi / Ringkasan (md ke atas; header `bg-secondary text-xs`), tiap baris `border-t px-6 py-3 text-sm`: waktu (`relativeTime` bila <24 jam else `formatDateTime`, `text-muted-foreground`, `w-[200px]`), aksi (`font-medium`, `w-[160px]`), ringkasan (`"{pelaku} · {subjek}"` bila subjek bukan pengguna ini, atau ringkasan perubahan pertama); di mobile menumpuk: waktu kecil + aksi (kanan) di atas, ringkasan di bawah. Kosong → `EmptyState` kecil "Belum ada aktivitas".
- [ ] **Step 2: Cek** format/typecheck/lint/build.
- [ ] **Step 3: Verifikasi browser** — detail pengguna menampilkan hingga 10 baris termasuk login/logout dan "Pengguna dibuat"; tautan "Lihat semua" menyaring `/log?filter[user]=`; akun tanpa `log.lihat` tidak melihat kartu; mobile 390.
- [ ] **Step 4: Commit** — "feat: tampilkan kartu aktivitas terbaru di detail pengguna".

---

### Task 11: Verifikasi akhir, review, dan penutupan

- [ ] **Step 1:** `php artisan test --compact`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npm test`, `npm run typecheck`, `npm run lint`, `npm run build` → semua hijau.
- [ ] **Step 2:** End-to-end browser (desktop 1440 + mobile 390): buat pengguna → aktivasi dengan token → admin pembuat menerima notifikasi (jalankan `php artisan queue:work --once`) → bell menunjukkan badge → buka notifikasi → pindah ke detail pengguna → kartu aktivitas memuat "Aktivasi selesai" → `/log` menampilkan entri yang sama dengan filter.
- [ ] **Step 3:** Jalankan skill `impeccable` (critique + audit, laporan saja) pada halaman baru dan `ponytail:ponytail-review` pada seluruh perubahan Fase 2; perbaiki temuan valid dalam satu batch; konfirmasi sekali.
- [ ] **Step 4:** Perbarui `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (Fase 2: tandai Track BE dan FE selesai, catat penyimpangan) dan ledger; commit akhir tanpa `Co-Authored-By`; ringkasan ke user.
