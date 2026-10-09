# Fase 6c-1 — Hardening keamanan dan data: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup celah hardening tanpa mengubah perilaku fitur: header keamanan HTTP (CSP bernonce di luar `local`), batas laju menyeluruh, permukaan rute yang minimal dan diaudit otomatis, bukti penolakan unggahan berbahaya, indeks `activity_log.created_at`, dan jaminan daftar tidak melakukan N+1.

**Architecture:** Satu middleware `SecurityHeaders` di grup `web` (nonce dibuat per request, dipakai `@vite` dan `@routes`); limiter bernama `web` di `AppServiceProvider` dipasang sebagai `throttle:web`; dua rute bawaan yang tidak dipakai (`storage.*` dan `sanctum.csrf-cookie`) dimatikan lewat konfigurasi; tes struktural menelusuri `Route::getRoutes()`; tes jumlah query membandingkan data kecil dan besar untuk sepuluh halaman daftar.

**Tech Stack:** Laravel 13 / PHP 8.5 / MySQL 8, Inertia v2 + Vue 3, Ziggy, Pest, Larastan level 5, Pint, Playwright (verifikasi).

**Spec:** `docs/superpowers/specs/2026-10-09-fase-6c1-hardening-design.md`

## Global Constraints

- Header selalu: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- HSTS `Strict-Transport-Security: max-age=31536000; includeSubDomains` hanya bila `$request->secure()`.
- CSP hanya bila `! app()->isLocal()` dan `config('security.csp')`: `default-src 'self'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'`.
- Limiter `web`: 240 permintaan per menit per pengguna (tamu: per IP), `throttle:web` di grup `web`; limit khusus yang sudah ada tetap.
- Daftar pengecualian tes audit rute dikunci sebagai fungsi di berkas tes sehingga penambahan terlihat di diff.
- `APP_DEBUG=false`, `SESSION_SECURE_COOKIE`, trusted proxy, dan `APP_ENV=production` adalah konfigurasi rilis (ditahan), hanya dicatat di memory.
- Jangan mematikan Vite dev server milik user (port 5173); `public/hot` hanya dipindah sementara lalu dikembalikan.
- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; dataset Pest tanpa closure (pakai nama kasus + `match`); tes yang memicu notifikasi membuat permission (`Permission::findOrCreate`); helper tes global diberi nama unik; Larastan menolak `->pluck()->isNotEmpty()`; jangan edit PHP dengan Python heredoc yang memuat backslash namespace (pakai Edit/Write).
- Suite penuh backend >120 detik: jalankan di background. File FE yang diubah dijalankan `npx prettier --write`.
- Commit: pesan detail, **tanpa** `Co-Authored-By` atau atribusi tool/AI apa pun. Persetujuan rencana ini mencakup langkah commit di dalamnya.

## Rulings (keputusan saya yang melengkapi spec)

- **R1:** Nonce dibuat sendiri di middleware (`Str::random(40)`), diberikan ke `Vite::useCspNonce($nonce)` dan disimpan di `$request->attributes` (`csp_nonce`); `app.blade.php` membaca atribut itu untuk `@routes(nonce: ...)`. Alasannya: tidak bergantung pada perilaku `Vite` palsu pada tes (`withoutVite`).
- **R2:** Dua rute bawaan yang tidak dipakai dimatikan dan bukan dimasukkan sebagai pengecualian audit: `storage/{path}` (GET dan PUT; disk `local` memakai `'serve' => false`; dokumen diunduh lewat controller, bukan URL bertanda tangan) dan `sanctum/csrf-cookie` (`config/sanctum.php` dengan `'routes' => false`; aplikasi memakai sesi, bukan token). Ini penyimpangan dari spec §3 yang menyebut keduanya sebagai pengecualian; menghapus permukaan lebih baik daripada mengecualikannya.
- **R3:** Respons 429 ikut dirender sebagai halaman error bergaya aplikasi (tambah 429 pada daftar status di `bootstrap/app.php` dan pesan di `Error.vue`), dengan header `Retry-After` disalin. Spec hanya meminta "bila didukung"; menambahkannya kecil dan menghindari modal HTML mentah Inertia.
- **R4:** Perbaikan N+1 pada Task 4 ditentukan oleh keluaran tes (halaman mana yang melonjak); tiap perbaikan memakai eager loading di repository terkait dan diverifikasi oleh baris dataset yang sama (RED lalu GREEN).

## Review Focus

1. Nonce CSP pada tag `@routes` harus sama dengan nonce di header dan berbeda antar-request; tanpa itu skrip Ziggy diblokir — tes di Task 1.
2. Halaman error (404) dan respons 429 tetap membawa header keamanan — tes di Task 1 dan Task 2.
3. Batas laju dihitung per pengguna, bukan per IP bersama: pengguna lain tidak ikut terkena 429 — tes di Task 2.
4. Rute baru tanpa penjaga, rute `storage.*`, dan `sanctum.*` tidak boleh muncul; entri pengecualian yang usang membuat tes gagal — tes di Task 3.
5. Berkas bernama `.pdf` tetapi berisi kode, serta ekstensi `.php`, `.svg`, `.html`, ditolak dan tidak tersimpan — tes di Task 3.

---

## File Structure

**Dibuat**
- `config/security.php`, `config/sanctum.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `database/migrations/2026_10_09_400001_add_created_at_index_to_activity_log.php`
- `tests/Feature/Security/{SecurityHeadersTest,RateLimitTest,RouteGuardAuditTest}.php`, `tests/Feature/Performance/ListQueryCountTest.php`

**Diubah**
- `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `config/filesystems.php`, `resources/views/app.blade.php`, `resources/js/Pages/Error.vue`
- `tests/Feature/Students/{StudentDocumentTest,StudentImportTest}.php`, `tests/Feature/ErrorPagesTest.php` (opsional, tidak diubah bila tidak perlu)
- Repository daftar yang terbukti N+1 (ditentukan Task 4).
- Dokumen: `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md`; memory `deferred-items.md`.

---

### Task 1: Middleware header keamanan dan CSP bernonce

**Files:**
- Create: `config/security.php`, `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php`, `resources/views/app.blade.php`
- Test: `tests/Feature/Security/SecurityHeadersTest.php`

**Interfaces:**
- Produces: `config('security.csp'): bool`, `config('security.rate_limit'): int`; atribut request `csp_nonce` (string atau null); `SecurityHeaders` di grup `web`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Security/SecurityHeadersTest.php`:

```php
<?php

/** Mengambil nonce dari header CSP. */
function cspNonceOf(string $header): string
{
    preg_match("/'nonce-([^']+)'/", $header, $m);

    return $m[1] ?? '';
}

test('semua respons web membawa header keamanan dasar', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test('halaman error dan unduhan ikut membawa header', function () {
    $this->get('/tidak-ada')->assertStatus(404)->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');

    $user = makeUserWith(['siswa.impor'], roleName: 'pengunduh');
    $this->actingAs($user)->get('/siswa/impor/template')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('HSTS hanya pada HTTPS', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('CSP diberlakukan di luar local dengan nonce yang sama dengan tag skrip dan berbeda antar-request', function () {
    $pertama = $this->get('/login');
    $csp = (string) $pertama->headers->get('Content-Security-Policy');
    $nonce = cspNonceOf($csp);

    expect($nonce)->not->toBe('')
        ->and($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("script-src 'self' 'nonce-{$nonce}'")
        ->and($csp)->toContain("style-src 'self' 'unsafe-inline' https://fonts.bunny.net")
        ->and($csp)->toContain("font-src 'self' https://fonts.bunny.net data:")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("form-action 'self'");
    $pertama->assertSee('nonce="'.$nonce.'"', false);

    $kedua = (string) $this->get('/login')->headers->get('Content-Security-Policy');
    expect(cspNonceOf($kedua))->not->toBe('')->and(cspNonceOf($kedua))->not->toBe($nonce);
});

test('CSP tidak dipasang di lingkungan local atau saat dimatikan', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->get('/login')->assertHeaderMissing('Content-Security-Policy');

    app()->detectEnvironment(fn () => 'testing');
    config(['security.csp' => false]);
    $this->get('/login')->assertHeaderMissing('Content-Security-Policy')->assertHeader('X-Frame-Options', 'DENY');
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Security/SecurityHeadersTest.php`
Expected: FAIL (header tidak ada).

- [ ] **Step 3: Implementasi**

`config/security.php`:

```php
<?php

return [
    // CSP diberlakukan di luar lingkungan local; matikan lewat SECURITY_CSP=false hanya untuk keadaan darurat.
    'csp' => (bool) env('SECURITY_CSP', true),

    // Batas permintaan web per menit per pengguna (tamu: per IP).
    'rate_limit' => (int) env('SECURITY_RATE_LIMIT', 240),
];
```

`app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->cspEnabled() ? Str::random(40) : null;

        if ($nonce !== null) {
            // Nonce yang sama dipakai tag @vite (lewat Vite) dan @routes (lewat atribut request).
            Vite::useCspNonce($nonce);
            $request->attributes->set('csp_nonce', $nonce);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($nonce !== null) {
            $response->headers->set('Content-Security-Policy', $this->csp($nonce));
        }

        return $response;
    }

    private function cspEnabled(): bool
    {
        return (bool) config('security.csp') && ! app()->isLocal();
    }

    private function csp(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            // 'unsafe-inline' hanya untuk gaya: reka-ui dan Sonner menyisipkan gaya inline; skrip tetap ketat.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
```

`bootstrap/app.php` — tambahkan `use App\Http\Middleware\SecurityHeaders;` dan ubah grup `web`:

```php
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            EnsureAccountIsActive::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
```
(`SecurityHeaders` paling awal supaya respons error yang melewatinya juga berheader.)

`resources/views/app.blade.php` — ganti `@routes` dengan:

```blade
        @routes(nonce: request()->attributes->get('csp_nonce'))
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Security/SecurityHeadersTest.php tests/Feature/ErrorPagesTest.php`
Expected: PASS. Bila `@routes(nonce: ...)` gagal dikompilasi oleh Blade, ganti dengan `@routes(null, request()->attributes->get('csp_nonce'))` (argumen posisi; tanda tangan Ziggy `generate($group = null, $nonce = null)`).

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app config tests bootstrap && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add config/security.php app/Http/Middleware/SecurityHeaders.php bootstrap/app.php resources/views/app.blade.php tests/Feature/Security/SecurityHeadersTest.php
git commit -m "feat: tambah header keamanan dan CSP bernonce" -m "Middleware SecurityHeaders di grup web memasang nosniff, X-Frame-Options DENY, Referrer-Policy, dan Permissions-Policy pada semua respons (termasuk halaman error dan unduhan), HSTS hanya pada HTTPS, serta CSP dengan nonce per request di luar lingkungan local. Nonce dipakai tag Vite dan skrip Ziggy; gaya inline tetap diizinkan karena reka-ui dan Sonner menyisipkannya."
```

---

### Task 2: Limiter `web` dan halaman 429

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `resources/js/Pages/Error.vue`
- Test: `tests/Feature/Security/RateLimitTest.php`

**Interfaces:**
- Consumes: `config('security.rate_limit')` (Task 1).
- Produces: limiter `web`; grup `web` memuat `throttle:web`; respons 429 dirender sebagai `Error` Inertia dengan `Retry-After`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Security/RateLimitTest.php`:

```php
<?php

test('melebihi batas web menghasilkan 429 per pengguna dan pengguna lain tidak terdampak', function () {
    config(['security.rate_limit' => 3]);
    $a = makeUserWith([], roleName: 'rl-a');
    $b = makeUserWith([], roleName: 'rl-b');

    $this->actingAs($a);
    foreach (range(1, 3) as $_) {
        $this->get('/dashboard')->assertOk();
    }
    $this->get('/dashboard')->assertStatus(429);

    $this->actingAs($b)->get('/dashboard')->assertOk();
});

test('respons 429 dirender sebagai halaman error aplikasi, membawa Retry-After dan header keamanan', function () {
    config(['security.rate_limit' => 1]);
    $this->actingAs(makeUserWith([], roleName: 'rl-c'));
    $this->get('/dashboard')->assertOk();

    $this->get('/dashboard')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 429));
});

test('tamu dibatasi per IP', function () {
    config(['security.rate_limit' => 2]);

    $this->get('/login')->assertOk();
    $this->get('/login')->assertOk();
    $this->get('/login')->assertStatus(429);
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Security/RateLimitTest.php`
Expected: FAIL (tidak ada 429).

- [ ] **Step 3: Implementasi**

`app/Providers/AppServiceProvider.php` — tambahkan impor `Illuminate\Cache\RateLimiting\Limit`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\RateLimiter` dan di `boot()`:

```php
        // Batas umum semua rute web; limit khusus yang lebih ketat (login, impor, ekspor) tetap berlaku di atasnya.
        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(config('security.rate_limit'))
            ->by($request->user()?->id ?? $request->ip()));
```

`bootstrap/app.php` — tambahkan `'throttle:web'` ke `append` grup `web` (setelah `AddLinkHeadersForPreloadedAssets::class`), lalu ubah penanganan error:

```php
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 429, 500, 503], true) || $request->expectsJson() || ($status === 500 && config('app.debug'))) {
                return $response;
            }

            $page = Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            if ($response->headers->has('Retry-After')) {
                $page->headers->set('Retry-After', (string) $response->headers->get('Retry-After'));
            }

            return $page;
        });
```

`resources/js/Pages/Error.vue` — impor ikon `Clock` dari `@lucide/vue` dan tambahkan pada `messages`:

```ts
    429: {
        icon: Clock,
        title: 'Terlalu banyak permintaan',
        text: 'Tunggu sebentar, lalu coba lagi.',
    },
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Security tests/Feature/ErrorPagesTest.php tests/Feature/Auth`
Expected: PASS. Tes yang melakukan banyak permintaan dalam satu tes tetap di bawah 240; bila ada yang melampaui, naikkan permintaannya jadi dua tes atau set `config(['security.rate_limit' => 100000])` pada `beforeEach` tes itu.

- [ ] **Step 5: Format FE, Pint, Larastan**

Run: `npx prettier --write resources/js/Pages/Error.vue && npm run typecheck && npm run lint && vendor/bin/pint app bootstrap tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/AppServiceProvider.php bootstrap/app.php resources/js/Pages/Error.vue tests/Feature/Security/RateLimitTest.php
git commit -m "feat: tambah batas laju web per pengguna dan halaman 429" -m "Limiter web (240 per menit per pengguna, tamu per IP, dapat diatur lewat konfigurasi) dipasang di grup web sehingga semua rute, termasuk PUT dan DELETE, punya batas; limit khusus yang lebih ketat tetap berlaku. Respons 429 dirender sebagai halaman error aplikasi dengan Retry-After dan tetap membawa header keamanan."
```

---

### Task 3: Permukaan rute minimal, audit rute struktural, dan tes unggah

**Files:**
- Create: `config/sanctum.php`, `tests/Feature/Security/RouteGuardAuditTest.php`
- Modify: `config/filesystems.php:36`
- Test: `tests/Feature/Security/RouteGuardAuditTest.php`, `tests/Feature/Students/StudentDocumentTest.php`, `tests/Feature/Students/StudentImportTest.php`

**Interfaces:** Produces: tidak ada rute `storage*` dan `sanctum*`; fungsi tes `auditPublicNames()`, `auditOwnAccountNames()`, `auditRoutes()`, `auditMiddleware()`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Security/RouteGuardAuditTest.php`:

```php
<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/** Rute publik yang sah (tanpa login). Menambah entri di sini harus terlihat di diff dan ditinjau. */
function auditPublicNames(): array
{
    return ['aktivasi.show', 'aktivasi.store', 'password.request', 'password.email', 'password.reset', 'password.store'];
}

/** Rute ber-auth tanpa `can:` karena hanya menyentuh akun atau notifikasi pengguna sendiri. */
function auditOwnAccountNames(): array
{
    return ['dashboard', 'logout', 'password.update', 'profile.edit', 'profile.update', 'notifikasi.index', 'notifikasi.read', 'notifikasi.read-all'];
}

/** @return list<LaravelRoute> */
function auditRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (LaravelRoute $r) => $r->isFallback || $r->uri() === 'up' || str_starts_with($r->uri(), '_'))
        ->values()->all();
}

/** @return list<string> */
function auditMiddleware(LaravelRoute $route): array
{
    return array_values(array_filter($route->gatherMiddleware(), 'is_string'));
}

function auditLabel(LaravelRoute $route): string
{
    return implode('|', $route->methods()).' '.$route->uri();
}

test('setiap rute web dijaga: auth, guest, atau publik bernama', function () {
    $offenders = collect(auditRoutes())->filter(function (LaravelRoute $r) {
        $mw = auditMiddleware($r);

        return ! in_array('auth', $mw, true) && ! in_array('guest', $mw, true) && ! in_array($r->getName(), auditPublicNames(), true);
    })->map(fn (LaravelRoute $r) => auditLabel($r))->values()->all();

    expect($offenders)->toBe([]);
});

test('setiap rute ber-auth memakai can: kecuali rute akun sendiri bernama', function () {
    $offenders = collect(auditRoutes())->filter(function (LaravelRoute $r) {
        $mw = auditMiddleware($r);
        $hasCan = collect($mw)->contains(fn (string $m) => str_starts_with($m, 'can:'));

        return in_array('auth', $mw, true) && ! $hasCan && ! in_array($r->getName(), auditOwnAccountNames(), true);
    })->map(fn (LaravelRoute $r) => auditLabel($r))->values()->all();

    expect($offenders)->toBe([]);
});

test('daftar pengecualian tidak memuat nama yang rutenya sudah tidak ada', function () {
    $names = collect(auditRoutes())->map(fn (LaravelRoute $r) => $r->getName())->filter()->all();
    $stale = array_values(array_diff([...auditPublicNames(), ...auditOwnAccountNames()], $names));

    expect($stale)->toBe([]);
});

test('tidak ada rute penyaji berkas storage atau sanctum', function () {
    $uris = collect(auditRoutes())->map(fn (LaravelRoute $r) => $r->uri())
        ->filter(fn (string $u) => str_starts_with($u, 'storage') || str_starts_with($u, 'sanctum'))->values()->all();

    expect($uris)->toBe([]);
});
```

Tambahkan di akhir `tests/Feature/Students/StudentDocumentTest.php`:

```php
test('berkas bernama pdf tetapi isinya kode ditolak dan tidak tersimpan', function () {
    $this->actingAs($this->op);
    $palsu = UploadedFile::fake()->createWithContent('kk.pdf', "<?php echo 'x';");

    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => $palsu])->assertSessionHasErrors('file');

    expect($this->student->documents()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('ekstensi berbahaya ditolak', function (string $nama) {
    $this->actingAs($this->op);

    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => UploadedFile::fake()->createWithContent($nama, '<svg onload="alert(1)"></svg>')])
        ->assertSessionHasErrors('file');

    expect($this->student->documents()->count())->toBe(0);
})->with(['shell.php', 'gambar.svg', 'halaman.html']);

test('disk dokumen privat tanpa penyaji publik', function () {
    expect(config('filesystems.disks.local.serve'))->toBeFalse()
        ->and(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'));
});
```

Tambahkan di akhir `tests/Feature/Students/StudentImportTest.php`:

```php
test('berkas bernama xlsx tetapi isinya bukan xlsx ditolak', function () {
    $this->actingAs($this->op);
    $palsu = UploadedFile::fake()->createWithContent('siswa.xlsx', "nik,nisn,nama\n1,2,Budi\n");

    $this->post('/siswa/impor', ['file' => $palsu])->assertSessionHasErrors('file');

    expect(Student::count())->toBe(0);
});
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Security/RouteGuardAuditTest.php tests/Feature/Students/StudentDocumentTest.php tests/Feature/Students/StudentImportTest.php`
Expected: FAIL pada `tidak ada rute penyaji berkas storage atau sanctum` dan `disk dokumen privat tanpa penyaji publik`. Tes audit lain dan tes unggah palsu/ekstensi mungkin langsung lulus karena aturan sudah benar; itu tes karakterisasi dan sah (catat di ledger bila ya).

- [ ] **Step 3: Implementasi**

`config/filesystems.php` baris 36: ubah `'serve' => true,` menjadi:

```php
            // Dokumen diunduh lewat controller berizin; penyaji URL bertanda tangan bawaan dimatikan agar tidak ada rute storage/{path}.
            'serve' => false,
```

`config/sanctum.php` (baru; aplikasi memakai sesi, bukan token, jadi rute `sanctum/csrf-cookie` dimatikan):

```php
<?php

return [
    // Paket memakai default untuk kunci lain; hanya rute csrf-cookie yang dimatikan karena tidak dipakai.
    'routes' => false,
];
```

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan test tests/Feature/Security tests/Feature/Students tests/Feature/Access`
Expected: PASS. Bila ada tes yang memakai `Storage::temporaryUrl` atau rute `storage.local`, sesuaikan (tidak diharapkan; dokumen diunduh lewat controller).

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app config tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add config/filesystems.php config/sanctum.php tests/Feature/Security/RouteGuardAuditTest.php tests/Feature/Students
git commit -m "feat: kecilkan permukaan rute dan tambah audit rute otomatis" -m "Rute penyaji berkas storage dan rute csrf-cookie Sanctum dimatikan karena tidak dipakai (dokumen diunduh lewat controller berizin, aplikasi memakai sesi). Tes struktural menelusuri semua rute dan menolak rute tanpa auth atau guest, rute ber-auth tanpa can: di luar daftar akun-sendiri bernama, dan pengecualian yang usang. Tes unggah menambah berkas menyamar, ekstensi berbahaya, dan disk privat."
```

---

### Task 4: Indeks `activity_log.created_at` dan tes jumlah query daftar

**Files:**
- Create: `database/migrations/2026_10_09_400001_add_created_at_index_to_activity_log.php`, `tests/Feature/Performance/ListQueryCountTest.php`
- Modify: repository daftar yang terbukti N+1 (ditentukan keluaran tes, R4)

**Interfaces:** Produces: indeks `activity_log(created_at)`; tes yang menjamin jumlah query halaman daftar tidak tumbuh menurut jumlah baris.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Feature/Performance/ListQueryCountTest.php`:

```php
<?php

use App\Domains\Access\Enums\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Registrations\Enums\RegistrationStatus as S;
use App\Domains\Schools\Models\School;
use App\Domains\Students\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('indeks created_at ada pada activity_log', function () {
    expect(Schema::hasIndex('activity_log', ['created_at']))->toBeTrue();
});

/** Menambah `$n` baris data untuk halaman daftar tertentu. */
function perfSeedRows(string $halaman, int $n, School $school): void
{
    for ($i = 0; $i < $n; $i++) {
        match ($halaman) {
            'pengguna' => User::factory()->create(),
            'role' => Role::create(['name' => 'role-'.Str::random(8), 'guard_name' => 'web']),
            'sekolah' => School::factory()->create(),
            'siswa' => Student::factory()->for($school)->create(),
            'pendaftaran', 'verifikasi' => makeEntry(S::Diajukan, $school),
            'jadwal' => makeScheduledEntry(S::Terjadwal, makeSchedule($school)),
            'log' => activity('uji')->log("aksi {$i}"),
            'notifikasi' => DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => 'uji', 'notifiable_type' => User::class,
                'notifiable_id' => auth()->id(),
                'data' => json_encode(['title' => 'T', 'body' => 'B', 'url' => '/dashboard']),
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'laporan' => makeEntry(S::Diajukan, School::factory()->create()),
        };
    }
}

/** Jumlah query yang dipakai satu permintaan GET. */
function perfQueryCount(object $test, string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $test->get($url)->assertOk();
    $jumlah = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $jumlah;
}

test('jumlah query halaman daftar tidak tumbuh menurut jumlah baris', function (string $halaman, string $url) {
    $school = School::factory()->create();
    $admin = makeUserWith(Permission::values(), roleName: 'perf-admin');
    $this->actingAs($admin);

    $this->get($url)->assertOk();            // pemanasan: cache izin dan sejenisnya
    perfSeedRows($halaman, 3, $school);
    $kecil = perfQueryCount($this, $url);

    perfSeedRows($halaman, 20, $school);
    $besar = perfQueryCount($this, $url);

    expect($besar)->toBeLessThanOrEqual($kecil, "{$halaman}: {$kecil} query untuk 3 baris, {$besar} untuk 23 baris (N+1)");
})->with([
    ['pengguna', '/pengguna'],
    ['role', '/role'],
    ['sekolah', '/sekolah'],
    ['siswa', '/siswa'],
    ['pendaftaran', '/pendaftaran'],
    ['verifikasi', '/verifikasi'],
    ['jadwal', '/jadwal'],
    ['log', '/log'],
    ['notifikasi', '/notifikasi'],
    ['laporan', '/laporan'],
]);
```

- [ ] **Step 2: Jalankan tes, pastikan gagal**

Run: `php artisan test tests/Feature/Performance/ListQueryCountTest.php`
Expected: FAIL pada `indeks created_at` dan pada setiap halaman yang N+1 (pesan menyebut halaman beserta jumlah query). Catat halaman mana yang gagal di ledger. Bila `Schema::hasIndex` tidak tersedia, gunakan `collect(Schema::getIndexes('activity_log'))->contains(fn ($i) => $i['columns'] === ['created_at'])`.

- [ ] **Step 3: Implementasi**

`database/migrations/2026_10_09_400001_add_created_at_index_to_activity_log.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Halaman Log menyaring rentang waktu pada created_at.
    public function up(): void
    {
        Schema::table('activity_log', fn (Blueprint $table) => $table->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('activity_log', fn (Blueprint $table) => $table->dropIndex(['created_at']));
    }
};
```

Untuk setiap halaman yang gagal pada Step 2: buka `Eloquent*Repository` yang melayani halaman itu dan controller/service yang memetakan barisnya, cari relasi yang diakses di dalam `->through(...)`/`->map(...)` tetapi tidak dimuat (`with([...])`), dan tambahkan eager loading di query repository (pola yang sudah dipakai, mis. `->with(['school', 'entries:id,schedule_id,status'])` pada `EloquentScheduleRepository::paginate`). Tiap perbaikan diverifikasi dengan menjalankan baris dataset halaman itu sampai hijau. Jangan mengubah bentuk data yang dikirim ke frontend.

- [ ] **Step 4: Jalankan tes, pastikan lulus**

Run: `php artisan migrate && php artisan test tests/Feature/Performance tests/Feature/Access tests/Feature/Registrations tests/Feature/Schedules tests/Feature/Students tests/Feature/Audit tests/Feature/Notifications tests/Feature/Reports`
Expected: PASS.

- [ ] **Step 5: Pint dan Larastan**

Run: `vendor/bin/pint app database tests && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: bersih.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_10_09_400001_add_created_at_index_to_activity_log.php tests/Feature/Performance app
git commit -m "feat: tambah indeks log aktivitas dan tes jumlah query halaman daftar" -m "Indeks created_at pada activity_log mempercepat penyaringan rentang waktu di halaman Log. Tes membandingkan jumlah query sepuluh halaman daftar pada 3 dan 23 baris data sehingga N+1 tidak dapat masuk; N+1 yang ditemukan diperbaiki dengan eager loading di repository terkait tanpa mengubah data yang dikirim ke frontend."
```

---

### Task 5: Verifikasi CSP di browser, review, dan penutupan

**Files:**
- Modify: `docs/superpowers/plans/2026-10-07-sijebol-sprint-plan.md` (status 6c-1); memory `deferred-items.md`.

- [ ] **Step 1: Suite penuh backend di background**

Run (background): `php artisan test > /tmp/f6c1-full.txt 2>&1` lalu pantau. Expected: semua lulus (410 sebelumnya + tes baru). Juga `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=1G`, `npx vitest run`, `npm run typecheck`, `npm run lint`, `npm run build` hijau.

- [ ] **Step 2: Verifikasi CSP dengan build produksi**

1. `npm run build`; pindahkan sementara `public/hot` (milik Vite dev server user): `mv public/hot public/hot.bak`.
2. Jalankan `APP_ENV=production APP_DEBUG=false php artisan serve --port=8099` (background). Periksa `curl -sI http://127.0.0.1:8099/login` memuat `Content-Security-Policy` dan `X-Frame-Options`; bila CSP tidak muncul (env tidak diteruskan), jalankan `APP_ENV=production APP_DEBUG=false php -S 127.0.0.1:8099 -t public` sebagai gantinya.
3. Login lewat `browser_run_code_unsafe` (`page.getByLabel('Email').fill(...)`) sebagai `sari@example.com` dan pasang `page.on('console')` untuk menangkap pesan berisi "Content Security Policy" atau "Refused to". Kunjungi pada 1440 dan 390 px: Login, Beranda, Pengguna, Jadwal (buka dialog Pindahkan), Pendaftaran, Verifikasi (centang), Laporan, Log, dialog dan toast. Expected: tidak ada pelanggaran CSP; font Inter dari `fonts.bunny.net`, ikon, dialog, menu, dan toast berfungsi. Jangan klik tautan unduhan (bila klik tersangkut, `browser_close`).
4. Bersihkan: hentikan server 8099, **kembalikan** `mv public/hot.bak public/hot`, hapus tangkapan layar. Pastikan `public/hot` ada dan Vite dev server user masih hidup.

Bila ada pelanggaran CSP, tambahkan sumber yang tepat ke kebijakan di `SecurityHeaders::csp()` (dan tes Task 1 yang memeriksa string) serta catat sebagai Ruling; jangan melonggarkan `script-src`.

- [ ] **Step 3: Review**

`ponytail:ponytail-review` pada `git diff 9c5e1f1..HEAD -- app config routes bootstrap resources tests database`. Perbaiki temuan valid dengan TDD dan commit terpisah "refactor: terapkan hasil review Fase 6c-1".

- [ ] **Step 4: Dokumentasi dan memory**

Sprint plan: tambahkan "Status Fase 6c-1 (2026-10-09): SELESAI" (header keamanan dan CSP bernonce, limiter web dan 429, rute storage dan sanctum dimatikan, audit rute otomatis, tes unggah, indeks activity_log, tes jumlah query); 6c-2 dan 6c-3 belum dimulai; rilis ditahan. Memory `deferred-items.md`: tandai 6c-1 selesai; catat daftar konfigurasi rilis (`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, trusted proxy, `APP_ENV=production` agar CSP aktif, opsi darurat `SECURITY_CSP=false`, `SECURITY_RATE_LIMIT`), dan catat bahwa impor Excel lewat queue dikerjakan di 6c-2 (keputusan user).

- [ ] **Step 5: Verifikasi akhir dan commit**

Jalankan ulang semua pemeriksaan Step 1; semuanya hijau sebelum klaim selesai.

```bash
git add docs/superpowers
git commit -m "docs: tandai Fase 6c-1 selesai" -m "Rencana sprint mencatat status Fase 6c-1 (header keamanan, batas laju, permukaan rute minimal dengan audit otomatis, tes unggah, indeks, dan tes jumlah query); 6c-2 dan 6c-3 belum dimulai dan rilis masih ditahan."
```
