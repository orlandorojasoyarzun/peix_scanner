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

    $cached = Cache::get("scan.{$scanId}.result");
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
            ->andThrow(new IdentificationFailedException('cannot parse model output'));
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
