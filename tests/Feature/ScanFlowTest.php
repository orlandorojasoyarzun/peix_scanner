<?php

declare(strict_types=1);

use App\Application\Actions\IdentifySpeciesAction;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use App\Support\CacheKeys;
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

    $cached = Cache::get(CacheKeys::scanResult($scanId));
    expect($cached)->toBeArray();
    expect($cached['scientific_name'])->toBe('salmo salar');
    expect($cached['common_name'])->toBe('Atlantic salmon');
    expect($cached['confidence'])->toBe(0.92);
    expect($cached['high_confidence'])->toBeTrue();

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

    $cached = Cache::get(CacheKeys::scanResult($scanId));

    expect($cached)->toBeArray()
        ->and($cached['scientific_name'])->toBe('salmo salar')
        ->and($cached['common_name_local'])->toBe('Salmón atlántico');

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

it('species page loads Alpine.js for reactive goal selector', function () {
    $response = $this->get(route('species.show', 'salmo-salar__salmo-salar'));

    $response->assertOk();
    $response->assertSee('alpinejs@3.x.x', false);
});

it('species page includes explanationPanel global function (used by x-data)', function () {
    $response = $this->get(route('species.show', 'salmo-salar__salmo-salar'));

    $response->assertOk();
    $response->assertSee('function explanationPanel', false);
});

it('species page x-data on Para ti tab uses explanationPanel (not goal selector)', function () {
    $response = $this->get(route('species.show', 'salmo-salar__salmo-salar'));

    $response->assertOk();
    $response->assertSee('x-data=\'explanationPanel(', false);
    $response->assertSee('url_explain', false);
    $response->assertDontSee('url_goal', false);
});
