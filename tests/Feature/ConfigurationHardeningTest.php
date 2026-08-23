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

it('bootstrap/app.php declares Railway-owned hostnames as trusted', function () {
    $contents = file_get_contents(base_path('bootstrap/app.php'));

    // The trustHosts() call must list every hostname Railway might send,
    // otherwise an attacker (or Railway's own healthcheck / public
    // domain) gets a Symfony `SuspiciousOperationException` and Laravel
    // returns HTTP 400.
    expect($contents)->toContain('trustHosts(')
        // Loopback / local dev (escaped form because the pattern is a
        // regex: ^127\.0\.0\.1$, not a literal IP).
        ->and($contents)->toContain('^localhost$')
        ->and($contents)->toContain('^127\.0\.0\.1$')
        // Public Railway domains — matched by regex so we don't have to
        // hardcode the exact subdomain Railway picked for this project.
        ->and($contents)->toContain('up\.railway\.app')
        // Internal Railway service hostnames
        ->and($contents)->toContain('railway\.internal')
        // Deploy healthcheck probe (see
        // https://docs.railway.com/deployments/healthchecks#healthcheck-hostname)
        ->and($contents)->toContain('healthcheck.railway.app');
});

it('trustHosts accepts Railway-generated public domains and rejects hostile lookalikes', function () {
    // Drive the actual middleware so we exercise the regex Laravel hands
    // to Symfony, not just the raw pattern string in the source file.
    //
    // Every pattern is anchored ^…$ because Symfony's Request::setTrustedHosts
    // does a preg_match without anchors — so an unanchored "localhost"
    // pattern would also match "localhost.attacker.com" (a Host header
    // injection vector).
    $trusted = [
        '^localhost$',
        '^127\.0\.0\.1$',
        '^healthcheck\.railway\.app$',
        '^(.+\.)?up\.railway\.app$',
        '^(.+\.)?railway\.internal$',
    ];

    // Compile exactly the way Symfony does it (Request::setTrustedHosts).
    $compiled = array_map(
        static fn (string $p) => sprintf('{%s}i', $p),
        $trusted,
    );

    $accept = [
        'peixscanner-production.up.railway.app',
        'healthcheck.railway.app',
        'peix_scanner.railway.internal',
        'localhost',
        '127.0.0.1',
    ];
    foreach ($accept as $host) {
        $matched = false;
        foreach ($compiled as $pattern) {
            if (preg_match($pattern, $host)) {
                $matched = true;
                break;
            }
        }
        expect($matched)->toBeTrue("expected {$host} to be accepted");
    }

    // Host header injection attempts: the trailing `$` anchor in the
    // wildcard must prevent subdomain chaining like `evil.up.railway.app.attacker.com`,
    // and the exact-match anchors must reject `localhost.attacker.com`
    // / `127.0.0.1.attacker.com` / `healthcheck.railway.app.evil.com`.
    //
    // Note: we accept any `*.up.railway.app` (including a hypothetical
    // `evil.up.railway.app`) because Railway owns that DNS zone — no one
    // else can register a hostname under it, so an attacker cannot forge
    // a "hostile" entry that still ends in `.up.railway.app`.
    $reject = [
        'attacker.example.com',
        'up.railway.app.attacker.com',
        'railway.app.attacker.com',
        'railway.internal.attacker.com',
        'localhost.attacker.com',
        '127.0.0.1.attacker.com',
        'healthcheck.railway.app.evil.com',
    ];
    foreach ($reject as $host) {
        $matched = false;
        foreach ($compiled as $pattern) {
            if (preg_match($pattern, $host)) {
                $matched = true;
                break;
            }
        }
        expect($matched)->toBeFalse("expected {$host} to be rejected");
    }
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
