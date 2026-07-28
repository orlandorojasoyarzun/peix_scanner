<?php

declare(strict_types=1);

use App\Services\FoodDataCentralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns null when the species is not in the USDA mapping', function () {
    $service = new FoodDataCentralService;

    expect($service->getNutritionData('Especie inventada que no existe'))->toBeNull();
});

it('returns null when the USDA API request fails', function () {
    Http::fake([
        'api.nal.usda.gov/*' => Http::response(['error' => 'bad request'], 500),
    ]);

    $service = new FoodDataCentralService;

    expect($service->getNutritionData('Merluza europea'))->toBeNull();
});

it('returns parsed nutrition data for Merluza europea with correct nutrient IDs', function () {
    Http::fake([
        'api.nal.usda.gov/*' => Http::response([
            'foods' => [
                [
                    'description' => 'Fish, hake, raw',
                    'scientificName' => 'Merluccius merluccius',
                    'foodNutrients' => [
                        ['nutrientId' => 1008, 'value' => 92.00],
                        ['nutrientId' => 1003, 'value' => 17.30],
                        ['nutrientId' => 1004, 'value' => 1.30],
                        ['nutrientId' => 1278, 'value' => 0.30],
                        ['nutrientId' => 1280, 'value' => 0.20],
                        ['nutrientId' => 1178, 'value' => 1.50],
                        ['nutrientId' => 328, 'value' => 1.00],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new FoodDataCentralService;

    $data = $service->getNutritionData('Merluza europea');

    expect($data)->toBeArray()
        ->and($data['calories'])->toBe(92.0)
        ->and($data['protein'])->toBe(17.3)
        ->and($data['fat'])->toBe(1.3)
        ->and($data['omega3'])->toBe(0.5)
        ->and($data['vitamins']['B12_ug'])->toBe(1.5)
        ->and($data['vitamins']['D_ug'])->toBe(1.0);
});

it('prefers species-specific match over generic first result', function () {
    Http::fake([
        'api.nal.usda.gov/*' => Http::response([
            'foods' => [
                [
                    'description' => 'Fish, mackerel, Atlantic, raw',
                    'scientificName' => 'Scomber scombrus',
                    'foodNutrients' => [
                        ['nutrientId' => 1008, 'value' => 205.0],
                        ['nutrientId' => 1003, 'value' => 18.6],
                        ['nutrientId' => 1004, 'value' => 13.9],
                    ],
                ],
                [
                    'description' => 'Fish, mackerel, jack, raw',
                    'scientificName' => 'Trachurus symmetricus',
                    'foodNutrients' => [
                        ['nutrientId' => 1008, 'value' => 158.0],
                        ['nutrientId' => 1003, 'value' => 20.1],
                        ['nutrientId' => 1004, 'value' => 7.89],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new FoodDataCentralService;

    $data = $service->getNutritionData('Jurel');

    expect($data['calories'])->toBe(158.0)
        ->and($data['fat'])->toBe(7.89);
});

it('uses scientific name genus to pick the right food when no preferred description matches', function () {
    Http::fake([
        'api.nal.usda.gov/*' => Http::response([
            'foods' => [
                [
                    'description' => 'Fish, mackerel, Atlantic, raw',
                    'scientificName' => 'Scomber scombrus',
                    'foodNutrients' => [
                        ['nutrientId' => 1008, 'value' => 205.0],
                    ],
                ],
                [
                    'description' => 'Fish, mackerel, jack, raw',
                    'scientificName' => 'Trachurus symmetricus',
                    'foodNutrients' => [
                        ['nutrientId' => 1008, 'value' => 158.0],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new FoodDataCentralService;

    $data = $service->getNutritionData('Jurel', 'Trachurus trachurus');

    expect($data['calories'])->toBe(158.0);
});
