<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security response headers to every HTTP response.
 *
 * Scope (intentionally narrow):
 *  - X-Content-Type-Options: nosniff      — block MIME sniffing
 *  - X-Frame-Options: DENY                 — block clickjacking
 *  - Referrer-Policy: no-referrer         — don't leak referrer
 *  - Permissions-Policy                   — restrict browser features
 *  - COOP / CORP: same-origin             — cross-origin isolation
 *
 * NOT included here (deferred to Phase 1):
 *  - Content-Security-Policy  — needs Alpine.js/Vite audit first
 *  - Strict-Transport-Security — only meaningful behind HTTPS (handled at the proxy)
 *
 * Applied globally via bootstrap/app.php `$middleware->append(...)` so it
 * covers every route including errors, redirects and asset responses.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());

        return $response;
    }

    /**
     * Permissions-Policy directive. `camera=(self)` is REQUIRED because the
     * scan page uses <input type="file" accept="image/*" capture="environment">
     * to invoke the rear camera on mobile. Everything else is denied.
     */
    private function permissionsPolicy(): string
    {
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
}
