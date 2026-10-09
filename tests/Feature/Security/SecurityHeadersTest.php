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
