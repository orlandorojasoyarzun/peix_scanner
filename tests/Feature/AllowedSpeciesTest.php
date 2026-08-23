<?php

declare(strict_types=1);

use App\Application\Actions\IdentifySpeciesAction;
use App\Domain\Ai\AllowedSpecies;
use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;

it('accepts every canonical scientific name on the list', function () {
    foreach (AllowedSpecies::allScientificNames() as $name) {
        expect(AllowedSpecies::isAllowed($name))->toBeTrue("{$name} should be on the list");
    }
});

it('rejects an empty or null scientific name', function () {
    expect(AllowedSpecies::isAllowed(null))->toBeFalse();
    expect(AllowedSpecies::isAllowed(''))->toBeFalse();
    expect(AllowedSpecies::isAllowed('   '))->toBeFalse();
});

it('rejects an invented species', function () {
    expect(AllowedSpecies::isAllowed('Tursiops truncatus'))->toBeFalse();
    expect(AllowedSpecies::isAllowed('salmo salar imaginaryus'))->toBeFalse();
});

it('normalises whitespace and case on lookup', function () {
    expect(AllowedSpecies::isAllowed('SALMO SALAR'))->toBeTrue();
    expect(AllowedSpecies::isAllowed('  salmo   salar  '))->toBeTrue();
});

it('returns canonical English and Spanish common names', function () {
    expect(AllowedSpecies::commonEn('Salmo salar'))->toBe('Atlantic salmon');
    expect(AllowedSpecies::commonEs('Salmo salar'))->toBe('Salmón atlántico');
    expect(AllowedSpecies::commonEn('Merluccius merluccius'))->toBe('European hake');
});

it('strips HTML and control characters from any string', function () {
    $dirty = "Salmo <script>alert(1)</script>salar\u{0000}";
    $clean = AllowedSpecies::sanitise($dirty);

    expect($clean)->not->toContain('<');
    expect($clean)->not->toContain('>');
    expect($clean)->not->toContain("\0");
});

it('returns an empty string when sanitising null', function () {
    expect(AllowedSpecies::sanitise(null))->toBe('');
});

it('drops off-list candidates from an IdentificationResult', function () {
    $identifier = new class implements SpeciesIdentifier
    {
        public function identify(string $imagePath): IdentificationResult
        {
            return new IdentificationResult(
                scientificName: 'Tursiops truncatus',
                commonName: 'Dolphin',
                confidence: 0.95,
                commonNameLocal: 'Delfín',
                candidates: [
                    ['scientific_name' => 'Tursiops truncatus', 'common_name' => 'Dolphin', 'common_name_local' => 'Delfín', 'regional_names' => [], 'confidence' => 0.95],
                ],
            );
        }
    };

    $action = new IdentifySpeciesAction($identifier);

    expect(fn () => $action->execute('/dev/null'))
        ->toThrow(IdentificationFailedException::class);
});

it('keeps in-list candidates and rewrites the top with canonical names', function () {
    $identifier = new class implements SpeciesIdentifier
    {
        public function identify(string $imagePath): IdentificationResult
        {
            return new IdentificationResult(
                scientificName: 'Salmo salar',
                commonName: 'Salmo salar hallucinated common name',
                confidence: 0.92,
                commonNameLocal: 'inventado',
                candidates: [
                    ['scientific_name' => 'Salmo salar', 'common_name' => 'invented common', 'common_name_local' => 'inventado', 'regional_names' => ['Norte'], 'confidence' => 0.92],
                    ['scientific_name' => 'Tursiops truncatus', 'common_name' => 'Dolphin', 'common_name_local' => 'Delfín', 'regional_names' => [], 'confidence' => 0.40],
                ],
            );
        }
    };

    $action = new IdentifySpeciesAction($identifier);
    $result = $action->execute('/dev/null');

    // Off-list candidate dropped, in-list kept.
    expect($result->candidates)->toHaveCount(1);
    expect($result->candidates[0]['scientific_name'])->toBe('salmo salar');

    // Common names replaced with canonical values from the allowlist.
    expect($result->scientificName)->toBe('salmo salar');
    expect($result->commonName)->toBe('Atlantic salmon');
    expect($result->commonNameLocal)->toBe('Salmón atlántico');
    expect($result->regionalNames)->toBe(['Norte']);
});

it('falls back to PARSE_FAILED when every candidate is off-list', function () {
    $identifier = new class implements SpeciesIdentifier
    {
        public function identify(string $imagePath): IdentificationResult
        {
            return new IdentificationResult(
                scientificName: 'Tursiops truncatus',
                commonName: 'Dolphin',
                confidence: 0.9,
                candidates: [
                    ['scientific_name' => 'Tursiops truncatus', 'common_name' => 'Dolphin', 'common_name_local' => '', 'regional_names' => [], 'confidence' => 0.9],
                    ['scientific_name' => 'Loxodonta africana', 'common_name' => 'Elephant', 'common_name_local' => '', 'regional_names' => [], 'confidence' => 0.5],
                ],
            );
        }
    };

    $action = new IdentifySpeciesAction($identifier);

    try {
        $action->execute('/dev/null');
        test()->fail('Expected IdentificationFailedException');
    } catch (IdentificationFailedException $e) {
        expect($e->getReason())->toBe(IdentificationFailedException::REASON_PARSE_FAILED);
        expect($e->getContext())->toBe(['reason_detail' => 'all_candidates_off_list']);
    }
});

it('count is consistent with the allowlist contents', function () {
    $names = AllowedSpecies::allScientificNames();

    expect(AllowedSpecies::count())->toBe(count($names));
    expect($names)->toBeArray();
});
