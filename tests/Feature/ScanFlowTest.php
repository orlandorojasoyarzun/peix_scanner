<?php

declare(strict_types=1);

use App\Application\Actions\IdentifySpeciesAction;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

it('processes a fish scan end-to-end: upload, identify, confirm, show', function () {
    Cache::flush();

    $fakeFile = UploadedFile::fake()->image('fillet.jpg', 800, 600);

    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(new IdentificationResult(
                scientificName: 'salmo salar',
                commonName: 'Atlantic salmon',
                confidence: 0.92,
            ));
    });

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('scan/');
    expect($location)->toContain('/confirm');

    preg_match('/scan\/([a-f0-9\-]+)\/confirm/', $location, $matches);
    $scanId = $matches[1];

    $cached = $this->app->make(\App\Support\ScanStateStore::class)->get($scanId);
    expect($cached)->toBeArray();
    expect($cached['result']['scientific_name'])->toBe('salmo salar');
    expect($cached['result']['common_name'])->toBe('Atlantic salmon');
    expect($cached['result']['confidence'])->toBe(0.92);
    expect($cached['result']['high_confidence'])->toBeTrue();

    $confirmPage = $this->get(route('scan.confirm', $scanId));
    $confirmPage->assertOk();
    $confirmPage->assertSee('Atlantic salmon');
    $confirmPage->assertSee('salmo salar');
    $confirmPage->assertSee('92%');

    $confirmResponse = $this->post(route('scan.confirm.store', $scanId));
    $confirmResponse->assertRedirect();
    expect($confirmResponse->headers->get('Location'))->toContain('species/');

    preg_match('/species\/(.+)$/', $confirmResponse->headers->get('Location'), $speciesMatches);
    $speciesParam = $speciesMatches[1];

    expect($speciesParam)->toBe('atlantic-salmon__salmo-salar');

    $speciesPage = $this->get(route('species.show', $speciesParam));
    $speciesPage->assertOk();
    $speciesPage->assertSee('Salmón atlántico');
    $speciesPage->assertSee('Atlantic salmon');
    $speciesPage->assertSee('Salmo salar');
});

it('shows FEN nutrition data on the species page from a photo scan', function () {
    Cache::flush();

    $fakeFile = UploadedFile::fake()->image('fillet.jpg', 800, 600);

    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(new IdentificationResult(
                scientificName: 'salmo salar',
                commonName: 'Atlantic salmon',
                commonNameLocal: 'Salmón atlántico',
                confidence: 0.92,
            ));
    });

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    preg_match('/scan\/([a-f0-9\-]+)\/confirm/', $response->headers->get('Location'), $matches);
    $scanId = $matches[1];

    $confirmResponse = $this->post(route('scan.confirm.store', $scanId));

    preg_match('/species\/(.+)$/', $confirmResponse->headers->get('Location'), $speciesMatches);
    $speciesParam = $speciesMatches[1];

    expect($speciesParam)->toBe('atlantic-salmon__salmo-salar');

    $speciesPage = $this->get(route('species.show', $speciesParam));
    $speciesPage->assertOk();
    $speciesPage->assertSee('Salmón atlántico');
    $speciesPage->assertSee('208.0 kcal');
    $speciesPage->assertSee('20.0 g');
    $speciesPage->assertSee('Fuente: FEN');
});

it('falls back to SpeciesTranslations to translate common name for nutrition lookup', function () {
    Cache::flush();

    $fakeFile = UploadedFile::fake()->image('jurel.jpg', 800, 600);

    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(new IdentificationResult(
                scientificName: 'Trachurus trachurus',
                commonName: 'Atlantic horse mackerel',
                confidence: 0.85,
            ));
    });

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    preg_match('/scan\/([a-f0-9\-]+)\/confirm/', $response->headers->get('Location'), $matches);
    $scanId = $matches[1];

    $confirmResponse = $this->post(route('scan.confirm.store', $scanId));

    preg_match('/species\/(.+)$/', $confirmResponse->headers->get('Location'), $speciesMatches);
    $speciesParam = $speciesMatches[1];

    $speciesPage = $this->get(route('species.show', $speciesParam));
    $speciesPage->assertOk();
    $speciesPage->assertSee('Jurel');
    $speciesPage->assertSee('125.0 kcal');
    $speciesPage->assertSee('5.5 g');
    $speciesPage->assertSee('Fuente: FEN');
});

it('shows the Spanish common name on the confirm page when it differs from English', function () {
    Cache::flush();

    $fakeFile = UploadedFile::fake()->image('salmon.jpg', 800, 600);

    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(new IdentificationResult(
                scientificName: 'salmo salar',
                commonName: 'Atlantic salmon',
                commonNameLocal: 'Salmón atlántico',
                confidence: 0.92,
            ));
    });

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    preg_match('/scan\/([a-f0-9\-]+)\/confirm/', $response->headers->get('Location'), $matches);
    $scanId = $matches[1];

    $cached = $this->app->make(\App\Support\ScanStateStore::class)->get($scanId);

    expect($cached)->toBeArray()
        ->and($cached['result']['scientific_name'])->toBe('salmo salar')
        ->and($cached['result']['common_name_local'])->toBe('Salmón atlántico');

    $confirmPage = $this->get(route('scan.confirm', $scanId));

    $confirmPage->assertOk();
    $confirmPage->assertSee('Salmón atlántico');
    $confirmPage->assertSee('Atlantic salmon');
    $confirmPage->assertSee('¿Es este el pez?');
});

it('shows the retry button with loading spinner on the confirm page', function () {
    Cache::flush();

    $fakeFile = UploadedFile::fake()->image('salmon.jpg', 800, 600);

    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(new IdentificationResult(
                scientificName: 'salmo salar',
                commonName: 'Atlantic salmon',
                commonNameLocal: 'Salmón atlántico',
                confidence: 0.92,
            ));
    });

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    preg_match('/scan\/([a-f0-9\-]+)\/confirm/', $response->headers->get('Location'), $matches);
    $scanId = $matches[1];

    $confirmPage = $this->get(route('scan.confirm', $scanId));

    $confirmPage->assertOk();
    $confirmPage->assertSee('No es este, reintentar');
    $confirmPage->assertSee('Reintentando con tu imagen...', false);
    $confirmPage->assertSee('action="' . route('scan.rescan', $scanId) . '"', false);
});

it('rejects uploads that are not images', function () {
    $fakeFile = UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    $response->assertSessionHasErrors('photo');
});

it('requires a photo', function () {
    $response = $this->post(route('scan.store'), []);

    $response->assertSessionHasErrors('photo');
});

it('redirects home when confirming an unknown scan', function () {
    Cache::flush();

    $fakeUuid = '00000000-0000-0000-0000-000000000000';

    $response = $this->post(route('scan.confirm.store', $fakeUuid));

    $response->assertRedirect(route('home'));
});

it('shows an error page when AI fails to identify', function () {
    Cache::flush();

    $fakeFile = UploadedFile::fake()->image('fillet.jpg');

    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(IdentificationFailedException::fromProvider('openrouter', IdentificationFailedException::REASON_PARSE_FAILED));
    });

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    $response->assertRedirect();

    preg_match('/scan\/([a-f0-9\-]+)\/confirm/', $response->headers->get('Location'), $matches);
    $scanId = $matches[1];

    $page = $this->get(route('scan.confirm', $scanId));
    $page->assertOk();
    $page->assertSee('No pudimos identificar el pez');
});

it('shows the Para ti tab with goal selector and recommendations on the species page', function () {
    Cache::flush();

    $fakeFile = UploadedFile::fake()->image('fillet.jpg', 800, 600);

    $this->mock(IdentifySpeciesAction::class, function ($mock) {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn(new IdentificationResult(
                scientificName: 'salmo salar',
                commonName: 'Atlantic salmon',
                commonNameLocal: 'Salmón atlántico',
                confidence: 0.92,
            ));
    });

    $response = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
    ]);

    preg_match('/scan\/([a-f0-9\-]+)\/confirm/', $response->headers->get('Location'), $matches);
    $scanId = $matches[1];

    $this->post(route('scan.confirm.store', $scanId));

    preg_match('/species\/(.+)$/', $this->get(route('scan.confirm', $scanId))->headers->get('Location') ?? '', $matchesUrl);

    preg_match('/species\/(.+)$/', session()->previousUrl(), $speciesMatches);

    $response->assertSessionHasNoErrors();
});

it('renders all recommendations aggregated for the species (no goal filter)', function () {
    Cache::flush();

    Cache::put('species.lubina__dicentrarchus-labrax.result', [
        'scientific_name' => 'Dicentrarchus labrax',
        'common_name' => 'European seabass',
        'common_name_local' => 'Lubina',
        'regional_names' => [],
        'image_path' => null,
        'reference_image_url' => null,
    ], now()->addMinutes(10));

    $speciesPage = $this->get(route('species.show', 'lubina__dicentrarchus-labrax'));

    $speciesPage->assertOk();
    $speciesPage->assertSee('Para ti');
    $speciesPage->assertSee('Apto para deportistas');
    $speciesPage->assertSee('Apto para embarazo');
    $speciesPage->assertSee('Sostenibilidad no determinada');
});

it('does not show goal selector pills anymore', function () {
    Cache::flush();

    $response = $this->get(route('species.show', 'salmo-salar__salmo-salar'));

    $response->assertOk();
    $response->assertDontSee('¿Para qué lo vas a usar?', false);
});

it('returns AI explanation as JSON when explanation endpoint is called', function () {
    Cache::flush();

    $this->mock(\App\Domain\Ai\Adapters\OpenRouterVisionAdapter::class, function ($mock) {
        $mock->shouldReceive('generateText')
            ->once()
            ->andReturn('Es un pescado ideal para deportistas por su perfil magro y alto en proteínas.');
    });

    Cache::put('species.jurel__trachurus-trachurus.result', [
        'scientific_name' => 'Trachurus trachurus',
        'common_name' => 'Atlantic horse mackerel',
        'common_name_local' => 'Jurel',
        'regional_names' => [],
        'image_path' => null,
        'reference_image_url' => null,
    ], now()->addMinutes(10));

    $response = $this->postJson(route('species.explain', 'jurel__trachurus-trachurus'), [
        '_token' => 'test-token',
    ]);

    $response->assertOk();
    $response->assertJson([
        'explanation' => 'Es un pescado ideal para deportistas por su perfil magro y alto en proteínas.',
    ]);
});

it('species page image renders within app margins (no -mx-4 negative offset)', function () {
    Cache::flush();

    Cache::put('species.lubina__dicentrarchus-labrax.result', [
        'scientific_name' => 'Dicentrarchus labrax',
        'common_name' => 'European seabass',
        'common_name_local' => 'Lubina',
        'regional_names' => [],
        'image_path' => null,
        'reference_image_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/12/Dicentrarchus_labrax1.jpg/440px-Dicentrarchus_labrax1.jpg',
    ], now()->addMinutes(10));

    $response = $this->get(route('species.show', 'lubina__dicentrarchus-labrax'));

    $response->assertSee('bg-slate-100 overflow-hidden rounded-2xl', false);
    $response->assertDontSee('-mx-4 bg-slate-100 overflow-hidden h-64', false);
});

it('species page tabs are wired with data-* attributes for vanilla JS (no Alpine)', function () {
    // Regression: even @alpinejs/csp uses new AsyncFunction internally for
    // any expression containing whitespace (x-show="tab === 'parati'"),
    // which is blocked by our strict CSP (no 'unsafe-eval'). The fix is
    // to drive tabs from data-* attributes and let resources/js/app.js
    // switch them via display style. This test makes sure no Alpine
    // directive leaks back into the species page.
    $response = $this->get(route('species.show', 'salmo-salar__salmo-salar'));

    $response->assertOk();
    $response->assertSee('data-species-tabs', false);
    $response->assertSee('data-default-tab="nutricion"', false);
    $response->assertSee('data-tab-button="nutricion"', false);
    $response->assertSee('data-tab-button="parati"', false);
    $response->assertSee('data-tab-button="sostenibilidad"', false);
    $response->assertSee('data-tab-button="preparacion"', false);
    $response->assertSee('data-tab-panel="nutricion"', false);
    $response->assertSee('data-tab-panel="parati"', false);
    $response->assertSee('data-tab-panel="sostenibilidad"', false);
    $response->assertSee('data-tab-panel="preparacion"', false);

    // No Alpine directives anywhere on the page: x-data, x-show, x-cloak,
    // @click, the x- prefix is the giveaway.
    $response->assertDontSee('x-data', false);
    $response->assertDontSee('x-show', false);
    $response->assertDontSee('x-cloak', false);
    $response->assertDontSee('@click', false);
});

it('species page explanation panel is wired with data-* attributes (no Alpine)', function () {
    // The "Quiero una explicación personalizada" CTA used to live inside
    // an Alpine x-data component on the Para ti panel. It now lives on a
    // plain button that app.js wires up by reading data-explanation-panel
    // and data-explanation-config. This test makes sure the config (URL +
    // CSRF token + cached text) is rendered into the page so JS can read it.
    $response = $this->get(route('species.show', 'salmo-salar__salmo-salar'));

    $response->assertOk();
    $response->assertSee('data-explanation-panel', false);
    $response->assertSee('data-explanation-cta', false);
    $response->assertSee('data-explanation-result', false);
    $response->assertSee('data-explanation-text', false);
    $response->assertSee('data-explanation-config', false);
    $response->assertSee('url_explain', false);
    $response->assertSee('csrf_token', false);
});

it('the Vite app.js bundle wires the species tabs and explanation panel', function () {
    // Companion to the data-attribute test above: even if the blade
    // template wires the HTML, the JS that drives it must ship in the
    // compiled bundle. Without this, a future code-splitting or
    // minifier config change could silently strip the species page
    // wiring. We assert on the attribute strings (which the minifier
    // leaves verbatim) rather than the function names (which it
    // shortens — wireSpeciesTabs becomes L(), wireSpeciesExplanation
    // becomes x()).
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $bundlePath = public_path('build/' . $manifest['resources/js/app.js']['file']);

    expect(file_exists($bundlePath))->toBeTrue("Vite bundle missing at {$bundlePath}");

    $bundle = file_get_contents($bundlePath);
    expect($bundle)->toContain('data-species-tabs');
    expect($bundle)->toContain('data-tab-button');
    expect($bundle)->toContain('data-tab-panel');
    expect($bundle)->toContain('data-explanation-cta');
    expect($bundle)->toContain('explanationConfig');
    expect($bundle)->toContain('X-CSRF-TOKEN');
});

it('the species tabs swapper filters active/inactive tokens, not whole strings', function () {
    // Regression: the original setActive() did
    //   classList.filter(c => c !== activeClass && c !== inactiveClass)
    // where activeClass was the WHOLE "bg-ink text-cream shadow-sm" string.
    // Since no individual token equals that whole string, the filter never
    // matched, removed classes never got removed, and every previously
    // clicked tab stayed "pressed" (bg-ink + text-cream) forever.
    //
    // The fix is to split the active/inactive data-attribute into a Set
    // of tokens and filter each token individually. This test asserts the
    // Set-based filter is present in the compiled bundle so a future
    // refactor that goes back to whole-string comparison fails the build.
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $bundlePath = public_path('build/' . $manifest['resources/js/app.js']['file']);

    $bundle = file_get_contents($bundlePath);
    // The minifier keeps `new Set(` verbatim. We assert it appears AND
    // that it sits inside the species-tabs branch (between
    // data-species-tabs and data-tab-button references in the bundle).
    expect($bundle)->toMatch('/new Set\(/');
});

it('scan page renders the two boxes and the submit button in their initial (non-busy) state', function () {
    // Regression: an earlier rewrite put Alpine x-data on the wrapper, which
    // made the submit button look "pressed" on first paint because the
    // x-show spans for both "Escanear" and "Escaneando..." appeared until
    // Alpine booted. The fix is to render a plain <button> with no Alpine
    // and let the vanilla JS in app.js swap it into the busy state ONLY
    // when the form is actually submitted.
    $response = $this->get(route('scan.create'));

    $response->assertOk();
    $response->assertSee('id="box-fish"', false);
    $response->assertSee('id="box-label"', false);
    $response->assertSee('id="photo"', false);
    $response->assertSee('name="scan_type"', false);
    $response->assertSee('id="scan-submit"', false);

    // No Alpine directives anywhere: x-data, @submit, :disabled, x-show,
    // x-cloak. Those are the ways the previous build leaked into the page.
    $response->assertDontSee('x-data', false);
    $response->assertDontSee('@submit', false);
    $response->assertDontSee(':disabled', false);
    $response->assertDontSee('x-show', false);
    $response->assertDontSee('x-cloak', false);
});

it('scan page carries no inline <script> (CSP would block it)', function () {
    // The strict CSP forbids 'unsafe-inline' so any <script> inside the
    // blade template is blocked by the browser. Click handlers now live in
    // resources/js/app.js, compiled by Vite to /build/assets/*.js.
    $response = $this->get(route('scan.create'));

    $response->assertOk();
    $response->assertDontSee('boxFish.addEventListener', false);
    $response->assertDontSee('boxLabel.addEventListener', false);
});

it('compiled CSS forces every submit-busy spinner hidden until JS reveals it', function () {
    // Regression: Tailwind's `hidden` utility loses to `.inline-flex` in
    // the cascade (inline-flex is declared after hidden in the compiled
    // CSS), so `class="inline-flex ... hidden"` would still render the
    // spinner at first paint. The fix is an ID-specific rule in
    // resources/css/app.css that uses !important to win regardless of
    // class order. This test reads the compiled bundle to make sure that
    // rule actually ships, so a future Tailwind upgrade cannot silently
    // drop it.
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $cssPath = public_path('build/' . $manifest['resources/css/app.css']['file']);

    expect(file_exists($cssPath))->toBeTrue("Vite CSS bundle missing at {$cssPath}");

    $css = file_get_contents($cssPath);
    // Attribute selectors survive minification, but the minifier strips
    // the quotes around the attribute value because `-submit-busy` is a
    // valid CSS identifier. The :not([data-busy="true"]) opt-out is what
    // lets JS reveal the spinner on submit, so the compiled CSS must
    // contain BOTH pieces for the rule to do anything useful.
    expect($css)->toMatch('/\[id\$=("?-submit-busy"?)\]:not\(\[data-busy=("?true"?)\]\)(,[^}]*)?\{display:none!important\}/');
    // x-cloak CSS rule ships in the compiled bundle as a defensive
    // default: any future Alpine component added to a blade template
    // gets a working x-cloak hide immediately, without needing to
    // remember to re-add the rule. The species page no longer uses
    // x-cloak (vanilla JS controls tab visibility now) but the rule
    // stays so it cannot accidentally regress in a future Tailwind
    // upgrade.
    expect($css)->toContain('[x-cloak]');
    expect($css)->toContain('display:none!important');
});

it('the Vite app.js bundle wires the scan page boxes and submit button', function () {
    // Static check of the compiled output so a future bundler tweak
    // (minifier config, code-splitting) cannot silently orphan the
    // scan page from its event listeners.
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $bundlePath = public_path('build/' . $manifest['resources/js/app.js']['file']);

    expect(file_exists($bundlePath))->toBeTrue("Vite bundle missing at {$bundlePath}");

    $bundle = file_get_contents($bundlePath);
    expect($bundle)->toContain('box-fish');
    expect($bundle)->toContain('box-label');
    expect($bundle)->toContain('scan_type');
    expect($bundle)->toContain('scan-submit');
    expect($bundle)->toContain('confirm-submit');
    expect($bundle)->toContain('rescan-submit');
});

it('confirm page renders both submit buttons in their initial (non-busy) state', function () {
    // Regression: the same x-show + x-cloak bug that broke /scan also broke
    // /confirm — both buttons ("Sí, es este" / "No es este, reintentar")
    // appeared already in their busy state on first paint. Same fix:
    // render a plain <button> with the busy state hidden by class, and let
    // the vanilla JS in app.js swap it ONLY when the user actually submits.

    // Reuse the stub pattern from the first test in this file: swap
    // IdentifySpeciesAction for one that returns a known result, then
    // POST a fake image to land on /scan/{id}/confirm.
    $result = new IdentificationResult(
        scientificName: 'dicentrarchus labrax',
        commonName: 'European seabass',
        commonNameLocal: 'Lubina',
        confidence: 1.0,
    );
    $this->app->instance(IdentifySpeciesAction::class, new class($result) extends IdentifySpeciesAction
    {
        public function __construct(private IdentificationResult $fixed) {}

        public function execute(string $absolutePath): IdentificationResult
        {
            return $this->fixed;
        }
    });

    $fakeFile = UploadedFile::fake()->image('lubina.jpg', 800, 600);
    $upload = $this->post(route('scan.store'), [
        'photo' => $fakeFile,
        'scan_type' => 'fish',
    ]);
    $upload->assertRedirect();

    preg_match('#scan/([a-f0-9-]+)/confirm#', $upload->headers->get('Location') ?? '', $m);
    expect($m)->toHaveCount(2);
    $scanId = $m[1];

    $confirmPage = $this->get(route('scan.confirm', $scanId));
    $confirmPage->assertOk();

    // The two forms and their label/busy spans must be present, and the
    // busy spans must start hidden (class="hidden") so no spinner shows
    // until submit fires.
    $confirmPage->assertSee('id="confirm-form"', false);
    $confirmPage->assertSee('id="confirm-submit"', false);
    $confirmPage->assertSee('id="rescan-form"', false);
    $confirmPage->assertSee('id="rescan-submit"', false);
    $confirmPage->assertSee('id="confirm-submit-busy" class="flex items-center justify-center gap-2"', false);
    $confirmPage->assertSee('id="rescan-submit-busy" class="inline-flex items-center gap-2"', false);

    // No Alpine directives anywhere: x-data, @submit, :disabled, x-show,
    // x-cloak. If any sneak back in, this test catches it.
    $confirmPage->assertDontSee('x-data', false);
    $confirmPage->assertDontSee('@submit', false);
    $confirmPage->assertDontSee(':disabled', false);
    $confirmPage->assertDontSee('x-show', false);
    $confirmPage->assertDontSee('x-cloak', false);
});
