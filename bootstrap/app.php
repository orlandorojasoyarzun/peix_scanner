<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\URL;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // HTTPS redirect runs before everything else so it sees the raw
        // X-Forwarded-Proto header (trustProxies rewrites isSecure() but
        // we still want the explicit check in the middleware itself).
        $middleware->prepend(\App\Http\Middleware\ForceHttpsOnProduction::class);

        // Railway terminates TLS at its reverse proxy. The proxy sets
        // X-Forwarded-Proto and X-Forwarded-For on every request. We must
        // trust those headers, otherwise:
        //   - request->ip() returns the proxy's IP, not the client's
        //   - request->isSecure() returns false, breaking the rate limiter
        //     key derivation (session id collides across users on the same
        //     proxy pool)
        //   - URL generation returns http:// instead of https://
        //
        // HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_HOST |
        // HEADER_X_FORWARDED_PORT | HEADER_X_FORWARDED_PROTO | HEADER_X_FORWARDED_AWS_ELB
        // covers both classic proxies and AWS ELB (Railway uses the former).
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        );

        // Only accept Host: headers for our actual domain. Without this,
        // an attacker who can force a Host header injection would get
        // generated absolute URLs pointing at their domain.
        //
        // Railway-owned hostnames (`.up.railway.app`, `.railway.internal`,
        // and the `healthcheck.railway.app` probe) are matched via regex
        // patterns because Railway generates the public domain per-service
        // (e.g. `peixscanner-production.up.railway.app`) and we don't want
        // to redeploy every time the prefix changes. Railway owns these
        // TLDs, so an attacker can't register a hostile name under them.
        //
        // `healthcheck.railway.app` is mandatory: Railway's deploy
        // healthcheck sends GET /up with that exact Host header (see
        // https://docs.railway.com/deployments/healthchecks#healthcheck-hostname).
        // If it isn't on the allow-list, Symfony throws
        // `SuspiciousOperationException("Untrusted Host ...")` and Laravel
        // returns HTTP 400 — Railway treats that as "service unavailable"
        // and marks the deploy failed even though the app is otherwise
        // healthy (confirmed: FrankenPHP booted, migrations ran, /up route
        // never reached the controller).
        $middleware->trustHosts(at: [
            // Every pattern is anchored ^…$ because Symfony does a
            // substring match (preg_match without anchors). Without
            // anchors, "localhost" would also accept "localhost.attacker.com"
            // and Laravel would generate URLs pointing at the attacker —
            // a classic Host header injection vector.
            '^localhost$',
            '^127\.0\.0\.1$',
            '^healthcheck\.railway\.app$',
            // Public Railway domains: <anything>.up.railway.app
            '^(.+\.)?up\.railway\.app$',
            // Railway internal service hostnames: <service>.railway.internal
            '^(.+\.)?railway\.internal$',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booted(function () {
        // Behind Railway's reverse proxy every request arrives as plain
        // HTTP at the PHP-FPM worker. Without forcing the scheme here,
        // asset() and url() helpers produce http:// URLs in templates
        // and break CSP/SRI integrity checks.
        //
        // Pinned to APP_ENV=production so local development (APP_ENV=local)
        // keeps using the http:// URL the dev server actually serves.
        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }
    })
    ->create();
