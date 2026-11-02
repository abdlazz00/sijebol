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

        $allowSameOriginFrame = $this->allowsSameOriginFraming($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $allowSameOriginFrame ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        // Sistem internal: tidak untuk diindeks (mencakup halaman error dan berkas).
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($nonce !== null) {
            $response->headers->set('Content-Security-Policy', $this->csp($nonce, $allowSameOriginFrame, $this->allowsTurnstile($request)));
        }

        return $response;
    }

    private function allowsSameOriginFraming(Request $request): bool
    {
        return $request->routeIs('students.documents.preview');
    }

    /** Widget Cloudflare Turnstile hanya dimuat di halaman pendaftaran siswa, dan hanya bila diaktifkan. */
    private function allowsTurnstile(Request $request): bool
    {
        return filled(config('services.turnstile.site_key')) && $request->routeIs('register.*');
    }

    private function cspEnabled(): bool
    {
        return (bool) config('security.csp') && ! app()->isLocal();
    }

    private function csp(string $nonce, bool $allowSameOriginFrame = false, bool $allowTurnstile = false): string
    {
        $frameAncestors = $allowSameOriginFrame ? "frame-ancestors 'self'" : "frame-ancestors 'none'";
        $cloudflare = $allowTurnstile ? ' https://challenges.cloudflare.com' : '';

        return implode('; ', array_filter([
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$cloudflare}",
            // 'unsafe-inline' hanya untuk gaya: reka-ui dan Sonner menyisipkan gaya inline; skrip tetap ketat.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data:",
            "connect-src 'self'{$cloudflare}",
            $allowTurnstile ? "frame-src{$cloudflare}" : null,
            $frameAncestors,
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]));
    }
}
