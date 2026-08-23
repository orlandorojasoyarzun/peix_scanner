<?php

declare(strict_types=1);

it('every response carries a strict Content-Security-Policy', function () {
    $response = $this->get('/');

    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeNull();
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("script-src 'self'");
    expect($csp)->toContain('sha384-zaqGaLZBjCeXCLnYyKyAJrDaZcsW8AqZN7iDmFDw3TzHJvhjMckT1H52jWWza0w8');
    expect($csp)->toContain("frame-ancestors 'none'");
    expect($csp)->toContain("object-src 'none'");
    expect($csp)->toContain("form-action 'self'");
    expect($csp)->toContain("base-uri 'self'");
    expect($csp)->toContain('upgrade-insecure-requests');

    // We never allow unsafe-eval in production.
    expect($csp)->not->toContain("'unsafe-eval'");
});

it('the CSP allows Wikimedia thumbnails on the species page', function () {
    // Plant a species result so the species page has content.
    \Illuminate\Support\Facades\Cache::put(
        \App\Support\CacheKeys::speciesResult('salmo-salar__salmo-salar'),
        [
            'scientific_name' => 'salmo salar',
            'common_name' => 'Atlantic salmon',
            'common_name_local' => 'Salmón atlántico',
            'regional_names' => [],
            'image_path' => null,
            'reference_image_url' => 'https://upload.wikimedia.org/wikipedia/commons/x/y/salmon.jpg',
        ],
        now()->addMinutes(30),
    );

    $response = $this->get(route('species.show', 'salmo-salar__salmo-salar'));
    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain('https://upload.wikimedia.org');
});

it('CSP is present on error responses too (404)', function () {
    $response = $this->get('/this-route-does-not-exist');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'");
});

it('Alpine.js is served locally with SRI', function () {
    // The script tag must point to the local /vendor/ path, not unpkg,
    // and must carry an integrity attribute.
    $response = $this->get('/');
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('vendor/alpinejs-3.14.9.min.js');
    expect($html)->not->toContain('unpkg.com/alpinejs');

    // Pin SRI: the integrity attribute must be present and non-empty.
    expect($html)->toMatch('/integrity="sha384-[A-Za-z0-9+\/=]+"/');
});

it('Alpine.js bundle exists on disk at the expected public path', function () {
    // The file lives under public/vendor/ so nginx (or any static file
    // server) can serve it directly. We don't go through the Laravel app
    // because that's not how it ships in production.
    $path = public_path('vendor/alpinejs-3.14.9.min.js');

    expect(file_exists($path))->toBeTrue();
    expect(mime_content_type($path))->toContain('javascript');

    // The SRI hash pinned in SecurityHeaders must match what's on disk.
    $expectedHash = 'sha384-'.base64_encode(hash('sha384', (string) file_get_contents($path), true));
    expect($expectedHash)->toBe('sha384-zaqGaLZBjCeXCLnYyKyAJrDaZcsW8AqZN7iDmFDw3TzHJvhjMckT1H52jWWza0w8');
});

it('every response carries a strict Referrer-Policy', function () {
    $response = $this->get('/');

    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
});

it('every response carries Permissions-Policy with camera enabled', function () {
    $response = $this->get('/');

    $permissions = $response->headers->get('Permissions-Policy');
    expect($permissions)->toContain('camera=(self)');
    expect($permissions)->toContain('microphone=()');
    expect($permissions)->toContain('geolocation=()');
});
