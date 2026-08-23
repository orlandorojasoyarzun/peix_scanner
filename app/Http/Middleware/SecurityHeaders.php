<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security response headers to every HTTP response.
 *
 * Scope (intentionally narrow but explicit):
 *  - X-Content-Type-Options: nosniff   — block MIME sniffing
 *  - Referrer-Policy: no-referrer      — don't leak referrer
 *  - Permissions-Policy                — restrict browser features
 *  - COOP / CORP: same-origin          — cross-origin isolation
 *  - Content-Security-Policy           — declarative whitelist of what the
 *                                       page may load and execute
 *
 * CSP policy (in directive order):
 *   default-src 'self'                 — only same-origin by default
 *   script-src 'self' <alpine-sha384>  — own scripts + pinned Alpine.js
 *   style-src 'self' 'unsafe-inline'
 *       https://fonts.googleapis.com    — own CSS, Tailwind inline, Google Fonts CSS
 *   font-src 'self' data:
 *       https://fonts.gstatic.com       — own fonts + Google Fonts files
 *   img-src 'self' data:
 *       https://upload.wikimedia.org    — Wikipedia thumbnails on the ficha
 *   connect-src 'self'                 — fetch/XHR only to our own origin
 *   frame-ancestors 'none'             — modern clickjacking block
 *   form-action 'self'                 — forms can only POST to us
 *   base-uri 'self'                    — <base> cannot point elsewhere
 *   object-src 'none'                  — close Flash/Java legacy vector
 *   upgrade-insecure-requests          — force HTTPS for any lingering http://
 *
 * Alpine.js SHA384 is pinned to the file under public/vendor/alpinejs-3.14.9.min.js.
 * If you upgrade Alpine, regenerate the hash with:
 *   openssl dgst -sha384 -binary public/vendor/alpinejs-3.14.X.min.js | openssl base64 -A
 *
 * Not included here:
 *  - Strict-Transport-Security — only meaningful behind HTTPS (handled at the proxy)
 *  - X-Frame-Options — superseded by `frame-ancestors` in the CSP
 */
class SecurityHeaders
{
    /**
     * SHA384 hash of the Alpine.js bundle, computed at install time.
     * Update this constant together with the file under public/vendor/.
     */
    private const ALPINE_SHA384 = 'sha384-zaqGaLZBjCeXCLnYyKyAJrDaZcsW8AqZN7iDmFDw3TzHJvhjMckT1H52jWWza0w8';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    private function permissionsPolicy(): string
    {
        // camera=(self) is REQUIRED because the scan page uses
        // <input type="file" accept="image/*" capture="environment"> to invoke
        // the rear camera on mobile. Everything else is denied.
        return implode(', ', [
            'camera=(self)',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
            'autoplay=()',
            'fullscreen=(self)',
        ]);
    }

    private function contentSecurityPolicy(): string
    {
        $alpine = self::ALPINE_SHA384;

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' {$alpine}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: https://upload.wikimedia.org",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);
    }
}
