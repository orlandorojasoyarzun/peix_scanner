<?php

declare(strict_types=1);

use App\Domain\Ai\Adapters\OpenRouterVisionAdapter;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use App\Domain\Ai\Support\OpenRouterCircuitBreaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

if (! function_exists('fakeImagePath')) {
    /**
     * The adapter takes a real filesystem path. UploadedFile::fake() in
     * Laravel 12 cleans up its temp files before getRealPath() resolves,
     * so we instead persist a tiny JPEG ourselves.
     */
    function fakeImagePath(string $name = 'salmon.jpg'): string
    {
        $fake = UploadedFile::fake()->image($name);
        $sourcePath = $fake->getPathname();

        // Copy into a path that survives the test (Fake files are
        // unlinked after the current request).
        $dest = sys_get_temp_dir() . '/peix_test_' . uniqid('', true) . '.jpg';
        copy($sourcePath, $dest);

        return $dest;
    }
}

beforeEach(function () {
    Cache::flush();
});

it('starts closed so the first call is allowed through', function () {
    $breaker = $this->app->make(OpenRouterCircuitBreaker::class);

    expect($breaker->isClosed())->toBeTrue();
});

it('opens after five consecutive failures', function () {
    $breaker = $this->app->make(OpenRouterCircuitBreaker::class);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }

    expect($breaker->isClosed())->toBeFalse();
});

it('does not open below the threshold', function () {
    $breaker = $this->app->make(OpenRouterCircuitBreaker::class);

    for ($i = 0; $i < 4; $i++) {
        $breaker->recordFailure();
    }

    expect($breaker->isClosed())->toBeTrue();
});

it('closes again when recordSuccess is called', function () {
    $breaker = $this->app->make(OpenRouterCircuitBreaker::class);

    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }

    expect($breaker->isClosed())->toBeFalse();

    $breaker->recordSuccess();

    expect($breaker->isClosed())->toBeTrue();
});

it('a successful call resets the rolling counter even before opening', function () {
    $breaker = $this->app->make(OpenRouterCircuitBreaker::class);

    $breaker->recordFailure();
    $breaker->recordFailure();
    $breaker->recordFailure();
    $breaker->recordSuccess(); // reset

    for ($i = 0; $i < 4; $i++) {
        $breaker->recordFailure(); // 4 fresh failures
    }

    expect($breaker->isClosed())->toBeTrue();
});

it('throws CIRCUIT_OPEN and skips the upstream call once tripped', function () {
    Http::fake(); // any call would be recorded by the fake

    // Force the breaker open by feeding it enough failures through the
    // public API.
    $breaker = $this->app->make(OpenRouterCircuitBreaker::class);
    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }

    $adapter = $this->app->make(OpenRouterVisionAdapter::class);
    $file = fakeImagePath();

    try {
        $adapter->identify($file);
        $this->fail('Expected IdentificationFailedException to be thrown');
    } catch (IdentificationFailedException $e) {
        expect($e->getReason())->toBe(IdentificationFailedException::REASON_CIRCUIT_OPEN);
    }

    // No HTTP request should have been made — the breaker short-circuits
    // before we ever touch the upstream.
    Http::assertNothingSent();

    @unlink($file);
});

it('records the failure against the upstream on a 5xx response', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response('boom', 500),
    ]);

    $adapter = $this->app->make(OpenRouterVisionAdapter::class);
    $file = fakeImagePath();

    try {
        $adapter->identify($file);
    } catch (IdentificationFailedException) {
        // expected
    }

    $breaker = $this->app->make(OpenRouterCircuitBreaker::class);
    expect((int) Cache::get('openrouter.circuit.failures', 0))->toBeGreaterThanOrEqual(1);

    @unlink($file);
});

it('records the failure against the upstream on a 429 response', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response('rate-limited', 429),
    ]);

    $adapter = $this->app->make(OpenRouterVisionAdapter::class);
    $file = fakeImagePath();

    try {
        $adapter->identify($file);
    } catch (IdentificationFailedException $e) {
        expect($e->getReason())->toBe(IdentificationFailedException::REASON_RATE_LIMIT);
    }

    expect((int) Cache::get('openrouter.circuit.failures', 0))->toBe(1);

    @unlink($file);
});

it('resets the counter after a successful identify', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => "Salmo salar (Atlantic salmon), 0.92\nOncorhynchus mykiss (Rainbow trout), 0.45",
                ],
            ]],
        ], 200),
    ]);

    $adapter = $this->app->make(OpenRouterVisionAdapter::class);
    $file = fakeImagePath();

    $result = $adapter->identify($file);

    expect($result->scientificName)->toBe('salmo salar');
    expect(Cache::get('openrouter.circuit.failures'))->toBeNull();

    @unlink($file);
});
