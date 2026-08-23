<?php

declare(strict_types=1);

use App\Services\WikipediaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    // The StrayRequestException guard lives in AppServiceProvider and
    // permits exactly four hosts. en.wikipedia.org is one of them, so the
    // real allowlist is in effect here, but we still use Http::fake() so
    // the test never touches the network.
});

it('accepts a thumbnail URL from upload.wikimedia.org', function () {
    $allowedThumb = 'https://upload.wikimedia.org/wikipedia/commons/0/0a/Salmo_salar.jpg';

    Http::fake([
        'en.wikipedia.org/api/rest_v1/page/summary/Salmo_salar' => Http::response([
            'thumbnail' => ['source' => $allowedThumb],
        ], 200),
    ]);

    $url = (new WikipediaService)->getSpeciesImage('Salmo salar');

    expect($url)->toBe($allowedThumb);
});

it('rejects a thumbnail URL whose host is not on the allowlist', function () {
    $evilThumb = 'https://evil.example.com/fake-salmon.jpg';

    Http::fake([
        'en.wikipedia.org/api/rest_v1/page/summary/Salmo_salar' => Http::response([
            'thumbnail' => ['source' => $evilThumb],
        ], 200),
    ]);

    $url = (new WikipediaService)->getSpeciesImage('Salmo salar');

    expect($url)->toBeNull();

    // We should NOT have cached the malicious URL — a second call must
    // not return the stale poison either.
    $second = (new WikipediaService)->getSpeciesImage('Salmo salar');
    expect($second)->toBeNull();
});

it('rejects a thumbnail URL that uses a subdomain trick to spoof the host', function () {
    // Classic allowlist-bypass attempt: upload.wikimedia.org.evil.com.
    // parse_url() returns the rightmost host component, so this must be
    // rejected — not matched as an upload.wikimedia.org URL.
    $spoofedThumb = 'https://upload.wikimedia.org.evil.com/wikipedia/commons/x/y/salmon.jpg';

    Http::fake([
        'en.wikipedia.org/api/rest_v1/page/summary/Salmo_salar' => Http::response([
            'thumbnail' => ['source' => $spoofedThumb],
        ], 200),
    ]);

    $url = (new WikipediaService)->getSpeciesImage('Salmo salar');

    expect($url)->toBeNull();
});

it('rejects a thumbnail URL served over plain HTTP', function () {
    // Wikimedia only ever serves thumbnails over HTTPS. If a response
    // somehow contains an http:// URL we treat it as untrusted.
    $plainThumb = 'http://upload.wikimedia.org/wikipedia/commons/x/y/salmon.jpg';

    Http::fake([
        'en.wikipedia.org/api/rest_v1/page/summary/Salmo_salar' => Http::response([
            'thumbnail' => ['source' => $plainThumb],
        ], 200),
    ]);

    $url = (new WikipediaService)->getSpeciesImage('Salmo salar');

    expect($url)->toBeNull();
});

it('rejects a thumbnail URL that fails to parse', function () {
    Http::fake([
        'en.wikipedia.org/api/rest_v1/page/summary/Salmo_salar' => Http::response([
            'thumbnail' => ['source' => 'http://'],
        ], 200),
    ]);

    $url = (new WikipediaService)->getSpeciesImage('Salmo salar');

    expect($url)->toBeNull();
});

it('returns null when the Wikipedia API responds with a non-2xx status', function () {
    Http::fake([
        'en.wikipedia.org/api/rest_v1/page/summary/*' => Http::response('not found', 404),
        'en.wikipedia.org/w/api.php*' => Http::response([], 200),
    ]);

    $url = (new WikipediaService)->getSpeciesImage('Salmo salar');

    expect($url)->toBeNull();
});
