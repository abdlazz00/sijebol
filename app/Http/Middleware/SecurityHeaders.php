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
        // Sistem internal: tidak untuk diindeks (mencakup halaman error dan berkas).
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

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
