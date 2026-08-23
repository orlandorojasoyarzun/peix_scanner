<?php

declare(strict_types=1);

use App\Http\Middleware\ForceHttpsOnProduction;
use Illuminate\Http\Request;

beforeEach(function () {
    // Rebind 'env' to 'production' for every test in this file. Pest refreshes
    // the application between tests, so a single binding at file load would
    // leak across runs and break the second+ test.
    app()->forgetInstance('env');
    app()->instance('env', 'production');
});

it('passes through in production when X-Forwarded-Proto is missing', function () {
    // If the LB did not set XFP at all we cannot tell if the request arrived
    // as plain HTTP or as HTTPS — refusing to redirect avoids a redirect loop
    // for legitimate clients (e.g. healthcheck probes hitting the LB port).
    $request = Request::create('http://localhost/foo', 'GET');

    $middleware = new ForceHttpsOnProduction;
    $response = $middleware->handle($request, fn (Request $r) => response('ok', 200));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Location'))->toBeNull();
});

it('passes through in production when the LB forwarded the request over TLS', function () {
    // X-Forwarded-Proto=https means the user's browser hit the LB over HTTPS.
    // There is nothing to upgrade — assets and links should already be https.
    $request = Request::create('http://localhost/foo', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'https');

    $middleware = new ForceHttpsOnProduction;
    $response = $middleware->handle($request, fn (Request $r) => response('ok', 200));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Location'))->toBeNull();
});

it('redirects http arrivals to https in production (X-Forwarded-Proto=http)', function () {
    // The user typed (or followed an old bookmark to) http://. The LB
    // forwarded it as X-Forwarded-Proto=http. The 301 sends their browser
    // to the https:// version, locking in via HSTS on the next round-trip.
    $request = Request::create('http://localhost/foo?bar=1', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'http');

    $middleware = new ForceHttpsOnProduction;
    $response = $middleware->handle($request, fn (Request $r) => response('ok', 200));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://localhost/foo?bar=1');
});

it('treats X-Forwarded-Proto=http regardless of case as a plain HTTP arrival', function () {
    // Some proxies send the header uppercase.
    $request = Request::create('http://localhost/path', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'HTTP');

    $middleware = new ForceHttpsOnProduction;
    $response = $middleware->handle($request, fn (Request $r) => response('ok', 200));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toStartWith('https://');
});
