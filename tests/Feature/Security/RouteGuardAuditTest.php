<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/** Rute publik yang sah (tanpa login). Menambah entri di sini harus terlihat di diff dan ditinjau. */
function auditPublicNames(): array
{
    return ['activation.show', 'activation.store', 'password.request', 'password.email', 'password.reset', 'password.store', 'register.show', 'register.store'];
}

/** Rute publik tak bernama: `/` hanya mengalihkan ke /dashboard yang sendirinya ber-auth. */
function auditPublicUris(): array
{
    return ['/'];
}

/** Rute ber-auth tanpa `can:` karena hanya menyentuh akun atau notifikasi pengguna sendiri. */
function auditOwnAccountNames(): array
{
    return ['dashboard', 'logout', 'password.update', 'profile.edit', 'profile.update', 'profile.sessions.destroy', 'notifications.index', 'notifications.mark-read', 'notifications.mark-all-read'];
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

        return ! in_array('auth', $mw, true) && ! in_array('guest', $mw, true)
            && ! in_array($r->getName(), auditPublicNames(), true) && ! in_array($r->uri(), auditPublicUris(), true);
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
