<?php

declare(strict_types=1);

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Nutrition\Models\NutritionProfile;
use App\Domain\Recommendation\Models\Recommendation;
use App\Domain\Species\Models\Species;
use App\Domain\Species\Models\SpeciesImage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;

uses(RefreshDatabase::class);

it('persists a species with its nutrition profile, recommendation and image', function () {
    $species = Species::factory()
        ->has(NutritionProfile::factory(), 'nutritionProfile')
        ->has(Recommendation::factory(), 'recommendations')
        ->has(SpeciesImage::factory(), 'images')
        ->create();

    assertDatabaseCount('species', 1);
    assertDatabaseCount('nutrition_profiles', 1);
    assertDatabaseCount('recommendations', 1);
    assertDatabaseCount('species_images', 1);

    expect($species->nutritionProfile)->toBeInstanceOf(NutritionProfile::class);
    expect($species->recommendations)->toHaveCount(1);
    expect($species->images)->toHaveCount(1);
});

it('cascade deletes related records when species is removed', function () {
    $species = Species::factory()
        ->has(NutritionProfile::factory(), 'nutritionProfile')
        ->has(Recommendation::factory(), 'recommendations')
        ->create();

    $species->delete();

    assertDatabaseCount('species', 0);
    assertDatabaseCount('nutrition_profiles', 0);
    assertDatabaseCount('recommendations', 0);
});

it('links an AI generation to a recommendation', function () {
    $recommendation = Recommendation::factory()
        ->has(AiGeneration::factory(), 'aiGeneration')
        ->create();

    expect($recommendation->aiGeneration)->toBeInstanceOf(AiGeneration::class);
    expect($recommendation->aiGeneration->recommendation_id)->toBe($recommendation->id);
});

it('uses uuid primary keys', function () {
    $species = Species::factory()->create();

    expect($species->id)
        ->toBeString()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('enforces unique scientific name', function () {
    Species::factory()->create(['scientific_name' => 'Gadus morhua']);

    expect(fn () => Species::factory()->create(['scientific_name' => 'Gadus morhua']))
        ->toThrow(QueryException::class);
});
