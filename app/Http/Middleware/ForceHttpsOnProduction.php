<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Last-line-of-defence HTTPS enforcement.
 *
 * Behind Railway (and any TLS-terminating reverse proxy) every request that
 * reaches the PHP worker is plain HTTP at the TCP layer; trustProxies in
 * bootstrap/app.php flips isSecure() to true when X-Forwarded-Proto=https.
 * That isSymptom, but it does NOT actually move the user's browser to HTTPS.
 *
 * If the user lands on http://<domain> (types it, follows an old link) their
 * request to the LB arrives with X-Forwarded-Proto=http. We must 301 them
 * to https:// so the connection becomes encrypted and locks in via
 * Strict-Transport-Security on the next hop.
 *
 * We only redirect when:
 *   - APP_ENV is "production" (dev must keep working on http://localhost), AND
 *   - X-Forwarded-Proto=http (the LB only sets this on REAL HTTP arrivals;
 *     if XFP is missing we cannot tell, so we let it pass to avoid loops), AND
 *   - the host is not the literal https-only test (no https XFP at all).
 *
 * If XFP=https, the LB already terminated TLS at the edge; the user is on
 * HTTPS and there is nothing to fix (asset URLs generated via
 * URL::forceScheme('https') are also correct).
 */
class ForceHttpsOnProduction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $forwardedProto = strtolower((string) $request->header('X-Forwarded-Proto', ''));

        // If XFP is unset or "https", the request did not arrive as plain
        // HTTP through the proxy — nothing to upgrade.
        if ($forwardedProto !== 'http') {
            return $next($request);
        }

        $httpsUrl = 'https://' . $request->getHost() . $request->getRequestUri();

        return redirect()->to($httpsUrl, 301);
    }
}
