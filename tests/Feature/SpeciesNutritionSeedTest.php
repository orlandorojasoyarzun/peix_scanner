<?php

declare(strict_types=1);

use App\Domain\Nutrition\SpeciesNutritionSeed;

it('returns curated data for Lubina with FEN values', function () {
    $data = SpeciesNutritionSeed::for('Lubina');

    expect($data)->toBeArray()
        ->and($data['calories'])->toBe(91)
        ->and($data['protein'])->toBe(18.5)
        ->and($data['fat'])->toBe(2.0)
        ->and($data['omega3'])->toBe(0.50)
        ->and($data['minerals']['calcium_mg'])->toBe(20)
        ->and($data['vitamins']['D_ug'])->toBe(4.50)
        ->and($data['source'])->toBe('FEN');
});

it('returns curated data for Dorada', function () {
    $data = SpeciesNutritionSeed::for('Dorada');

    expect($data)->toBeArray()
        ->and($data['calories'])->toBe(96)
        ->and($data['protein'])->toBe(19.0)
        ->and($data['fat'])->toBe(2.0)
        ->and($data['source'])->toBe('FEN');
});

it('returns curated data for Salmón atlántico', function () {
    $data = SpeciesNutritionSeed::for('Salmón atlántico');

    expect($data)->toBeArray()
        ->and($data['calories'])->toBe(208)
        ->and($data['protein'])->toBe(20.0)
        ->and($data['fat'])->toBe(13.0)
        ->and($data['source'])->toBe('FEN');
});

it('returns null for species not in the seed', function () {
    expect(SpeciesNutritionSeed::for('Especie inventada'))->toBeNull();
});

it('normalizes input case and whitespace', function () {
    $data = SpeciesNutritionSeed::for('LUBINA   ');

    expect($data)->toBeArray()
        ->and($data['calories'])->toBe(91);
});

it('includes vitamins array when present', function () {
    $data = SpeciesNutritionSeed::for('Lubina');

    expect($data['vitamins'])->toBeArray()
        ->and($data['vitamins']['B12_ug'])->toBe(2.00)
        ->and($data['vitamins']['D_ug'])->toBe(4.50);
});

it('has() returns true for known species', function () {
    expect(SpeciesNutritionSeed::has('Merluza europea'))->toBeTrue()
        ->and(SpeciesNutritionSeed::has('lubina'))->toBeTrue()
        ->and(SpeciesNutritionSeed::has('Especie inventada'))->toBeFalse();
});

it('curated lubina is biologically lean (fat under 3g)', function () {
    $data = SpeciesNutritionSeed::for('Lubina');

    expect($data['fat'])->toBeLessThan(3.0)
        ->and($data['protein'])->toBeGreaterThan(17.0);
});

it('congrio is biologically lean, not an eel', function () {
    $data = SpeciesNutritionSeed::for('Congrio');

    expect($data['calories'])->toBeLessThan(100)
        ->and($data['fat'])->toBeLessThan(2.0)
        ->and($data['protein'])->toBeGreaterThan(17.0);
});

it('chicharro resolves to jurel data via alias', function () {
    $chicharro = SpeciesNutritionSeed::for('Chicharro');
    $jurel = SpeciesNutritionSeed::for('Jurel');

    expect($chicharro)->toBe($jurel)
        ->and($chicharro['calories'])->toBe(125);
});

it('choco is no longer a standalone entry (alias of sepia, removed)', function () {
    expect(SpeciesNutritionSeed::for('Choco'))->toBeNull();
});

it('anchoa shows very high sodium content', function () {
    $data = SpeciesNutritionSeed::for('Anchoa');

    expect($data)->toBeArray()
        ->and($data['minerals']['sodium_mg'])->toBe(5600)
        ->and($data['source'])->toBe('FEN');
});

it('large predators include methylmercury contaminants', function () {
    $pez_espada = SpeciesNutritionSeed::for('Pez espada');
    $atun_rojo = SpeciesNutritionSeed::for('Atún rojo');
    $bonito = SpeciesNutritionSeed::for('Bonito del norte');
    $merluza = SpeciesNutritionSeed::for('Merluza europea');

    expect($pez_espada['contaminants']['methylmercury_mg_per_kg'])->toBeGreaterThan(0.5)
        ->and($atun_rojo['contaminants']['methylmercury_mg_per_kg'])->toBeGreaterThan(0.3)
        ->and($bonito['contaminants']['methylmercury_mg_per_kg'])->toBeGreaterThan(0.3)
        ->and($merluza['contaminants']['methylmercury_mg_per_kg'])->toBeLessThan(0.3);
});

it('six new Spanish species are in the seed', function () {
    expect(SpeciesNutritionSeed::has('Mero'))->toBeTrue()
        ->and(SpeciesNutritionSeed::has('Gallo'))->toBeTrue()
        ->and(SpeciesNutritionSeed::has('Breca'))->toBeTrue()
        ->and(SpeciesNutritionSeed::has('Aligote'))->toBeTrue()
        ->and(SpeciesNutritionSeed::has('Anguila'))->toBeTrue()
        ->and(SpeciesNutritionSeed::has('Pota'))->toBeTrue();
});

it('sardina includes a note about canned variants', function () {
    $data = SpeciesNutritionSeed::for('Sardina');

    expect($data['note'])->toContain('sodio');
});

it('sodium only appears in minerals when present in source data', function () {
    $lubina = SpeciesNutritionSeed::for('Lubina');
    $anchoa = SpeciesNutritionSeed::for('Anchoa');

    expect($lubina['minerals'])->not->toHaveKey('sodium_mg')
        ->and($anchoa['minerals'])->toHaveKey('sodium_mg');
});
