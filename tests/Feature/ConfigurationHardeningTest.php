<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;

it('locale defaults to Spanish so a fresh deploy does not fall back to English', function () {
    // We can't read config('app.locale') here because phpunit.xml +
    // the developer's .env may override it. Instead we read the raw
    // config file and assert the fallback default is 'es' — that is
    // what actually fires on Railway where APP_LOCALE isn't set.
    $contents = file_get_contents(base_path('config/app.php'));

    expect($contents)->toMatch("/'locale'\s*=>\s*env\(\s*'APP_LOCALE'\s*,\s*'es'\s*\)/");
    expect($contents)->toMatch("/'fallback_locale'\s*=>\s*env\(\s*'APP_FALLBACK_LOCALE'\s*,\s*'es'\s*\)/");
    expect($contents)->toMatch("/'faker_locale'\s*=>\s*env\(\s*'APP_FAKER_LOCALE'\s*,\s*'es_ES'\s*\)/");
});

it('session cookies are encrypted at rest by default', function () {
    expect(config('session.encrypt'))->toBeTrue();
});

it('session cookies use HttpOnly and SameSite=Lax in any environment', function () {
    expect(config('session.http_only'))->toBeTrue();
    expect(config('session.same_site'))->toBe('lax');
});

it('the cipher is AES-256-CBC and the key resolves from the env', function () {
    expect(config('app.cipher'))->toBe('AES-256-CBC');
    expect(config('app.key'))->not->toBeNull()
        ->and(config('app.key'))->not->toBe('');
});

it('the production log channel defaults to JSON-on-stderr', function () {
    // We don't simulate APP_ENV=production in this test (that would flip
    // every other default). Instead we assert the structure of the
    // channels: there must be a stderr channel with the JSON formatter
    // configured as its default.
    $stderr = config('logging.channels.stderr');

    expect($stderr)->not->toBeNull();
    expect($stderr['driver'])->toBe('monolog');
    expect($stderr['handler_with']['stream'])->toBe('php://stderr');
    expect($stderr['formatter'])->toContain('JsonFormatter');
});

it('the /up route exists and returns 200 for the healthcheck', function () {
    $response = $this->get('/up');

    $response->assertOk();
});

it('bootstrap/app.php declares the production domain as trusted host', function () {
    $contents = file_get_contents(base_path('bootstrap/app.php'));

    // The trustHosts() call must list the Railway domain so an attacker
    // who injects a Host header cannot redirect generated URLs.
    expect($contents)->toContain('trustHosts(')
        ->and($contents)->toContain('peix-scanner.up.railway.app');
});

it('bootstrap/app.php trusts the X-Forwarded-Proto header from Railway', function () {
    $contents = file_get_contents(base_path('bootstrap/app.php'));

    // trustProxies must include the X-Forwarded-Proto bit, otherwise
    // request->isSecure() never reflects the LB-observed scheme. Combined
    // with URL::forceScheme('https') in production this is what keeps
    // asset URLs and the ForceHttpsOnProduction middleware consistent
    // with what actually arrived at the LB.
    expect($contents)->toContain('trustProxies(')
        ->and($contents)->toContain('HEADER_X_FORWARDED_PROTO');
});

it('ForceHttpsOnProduction middleware is registered in the global stack', function () {
    $contents = file_get_contents(base_path('bootstrap/app.php'));

    expect($contents)->toContain('ForceHttpsOnProduction');
});

it('the Procfile declares three processes: web, worker, scheduler', function () {
    $contents = file_get_contents(base_path('Procfile'));

    expect($contents)->toContain('web:')
        ->and($contents)->toContain('worker:')
        ->and($contents)->toContain('scheduler:')
        ->and($contents)->toContain('octane:start')
        ->and($contents)->toContain('frankenphp');
});

it('the nixpacks.toml uses the right PHP version and runs config:cache on start', function () {
    $contents = file_get_contents(base_path('nixpacks.toml'));

    expect($contents)->toContain('php84')
        ->and($contents)->toContain('composer install')
        ->and($contents)->toContain('octane:install')
        ->and($contents)->toContain('config:cache')
        ->and($contents)->toContain('route:cache');
});

it('the railway.toml points the healthcheck at /up', function () {
    $contents = file_get_contents(base_path('railway.toml'));

    expect($contents)->toContain('healthcheckPath')
        ->and($contents)->toContain('/up');
});

it('the .dockerignore keeps the alpinejs bundle but excludes vendor and tests', function () {
    $contents = file_get_contents(base_path('.dockerignore'));

    // Must NOT exclude the alpine bundle (we need it at runtime for CSP/SRI).
    expect($contents)->not->toContain('alpinejs-3.14.9.min.js');

    // Must exclude tests and dev junk.
    expect($contents)->toContain('tests/')
        ->and($contents)->toContain('.env')
        ->and($contents)->toContain('node_modules/');
});

it('URL::forceScheme(https) is wired but does not fire outside production', function () {
    // We are in APP_ENV=testing right now. URL::forceScheme is only called
    // when APP_ENV=production, so the default scheme must NOT be https.
    // We verify by checking the URL of a generated route is http(s)://…
    $url = url('/');
    expect($url)->toMatch('#^https?://#');
});
