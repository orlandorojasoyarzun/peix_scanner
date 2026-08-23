<?php

declare(strict_types=1);

use App\Http\Middleware\ForceHttpsOnProduction;
use Illuminate\Http\Request;

/*
 * Tests for ForceHttpsOnProduction middleware in ANY environment
 * (it must run, look at X-Forwarded-Proto, and only redirect when prod).
 *
 * Pest refreshes the application between tests (createApplication in setUp),
 * so any environment override must be re-applied at the start of EACH test
 * via the container's 'env' key — Laravel's Application::environment()
 * reads from $this['env']. The companion file
 * ForceHttpsOnProductionInProductionTest.php covers the production branch.
 */

it('passes through in non-production regardless of X-Forwarded-Proto', function () {
    $request = Request::create('http://localhost/foo', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'http');

    $middleware = new ForceHttpsOnProduction;
    $response = $middleware->handle($request, fn (Request $r) => response('ok', 200));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Location'))->toBeNull();
});

it('passes through in any environment when X-Forwarded-Proto is missing', function () {
    $request = Request::create('http://localhost/foo', 'GET');

    $middleware = new ForceHttpsOnProduction;
    $response = $middleware->handle($request, fn (Request $r) => response('ok', 200));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Location'))->toBeNull();
});
