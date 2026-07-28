<?php

declare(strict_types=1);

use App\Domain\Nutrition\NutritionAdvisor;
use App\Domain\Nutrition\UserGoal;

it('recommends athlete-friendly options for high-protein fish', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 22.0, 'fat' => 2.0, 'omega3' => 2.5, 'calories' => 200, 'vitamins' => [], 'minerals' => ['iron_mg' => 1.5]],
        UserGoal::Athlete
    );

    expect($recommendations)
        ->toBeArray()
        ->and(collect($recommendations)->pluck('title')->implode(' '))
        ->toContain('Alto en proteínas')
        ->toContain('Apto para deportistas')
        ->toContain('Aporta hierro');
});

it('warns about mercury for pregnant women on high-mercury fish', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 20.0, 'fat' => 5.0, 'omega3' => 1.6, 'calories' => 144, 'vitamins' => [], 'minerals' => [], 'contaminants' => ['methylmercury_mg_per_kg' => 0.5]],
        UserGoal::Pregnancy
    );

    $titles = collect($recommendations)->pluck('title')->implode(' ');

    expect($titles)->toContain('Evitar durante embarazo');
});

it('marks a fish as safe for pregnancy when mercury is low', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 17.0, 'fat' => 0.8, 'omega3' => 2.5, 'calories' => 84, 'vitamins' => [], 'minerals' => [], 'contaminants' => ['methylmercury_mg_per_kg' => 0.20]],
        UserGoal::Pregnancy
    );

    $titles = collect($recommendations)->pluck('title')->implode(' ');

    expect($titles)->toContain('Apto para embarazo')
        ->and($titles)->toContain('Beneficioso para el desarrollo fetal');
});

it('recommends a fish as good for weight loss when low cal and high protein', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 18.0, 'fat' => 1.5, 'omega3' => 0.3, 'calories' => 90, 'vitamins' => [], 'minerals' => []],
        UserGoal::WeightLoss
    );

    $titles = collect($recommendations)->pluck('title')->implode(' ');

    expect($titles)->toContain('Bajo en calorías')
        ->and($titles)->toContain('Perfecto para perder peso');
});

it('warns about high calories for weight loss on fatty fish', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 18.0, 'fat' => 12.0, 'omega3' => 2.5, 'calories' => 210, 'vitamins' => [], 'minerals' => []],
        UserGoal::WeightLoss
    );

    $titles = collect($recommendations)->pluck('title')->implode(' ');

    expect($titles)->toContain('Calorías altas');
});

it('provides only universal rules when goal is None', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 22.0, 'fat' => 0.8, 'omega3' => 2.5, 'calories' => 84, 'vitamins' => ['D_ug' => 11.0], 'minerals' => []],
        UserGoal::None
    );

    $titles = collect($recommendations)->pluck('title')->implode(' ');

    expect($titles)->toContain('Alto en proteínas')
        ->and($titles)->toContain('Pescado muy magro')
        ->and($titles)->toContain('Excelente fuente de Omega-3')
        ->and($titles)->toContain('Rico en vitamina D');
});

it('flags fatty fish as caution across goals', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 18.0, 'fat' => 11.0, 'omega3' => 2.5, 'calories' => 200, 'vitamins' => [], 'minerals' => []],
        UserGoal::None
    );

    $cautions = collect($recommendations)->where('tone', NutritionAdvisor::TONE_CAUTION);
    expect($cautions->pluck('title')->implode(' '))->toContain('Pescado graso');
});

it('marks athlete fish as iron-rich when iron_mg >= 1', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 19.0, 'fat' => 6.0, 'omega3' => 1.5, 'calories' => 192, 'vitamins' => [], 'minerals' => ['iron_mg' => 2.5]],
        UserGoal::Athlete
    );

    expect(collect($recommendations)->pluck('title')->implode(' '))->toContain('Aporta hierro');
});

it('handles minimal nutrition without errors', function () {
    $recommendations = NutritionAdvisor::recommend([], UserGoal::Athlete);

    expect($recommendations)->toBeArray();
});

it('returns recommendations as arrays with icon, tone, title, body', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 22.0, 'fat' => 2.0, 'omega3' => 2.5, 'calories' => 200, 'vitamins' => [], 'minerals' => []],
        UserGoal::Athlete
    );

    foreach ($recommendations as $rec) {
        expect($rec)->toHaveKeys(['icon', 'tone', 'title', 'body']);
    }
});

it('warns about moderate mercury for pregnancy fish in 0.3-0.5 range', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 20.0, 'fat' => 5.0, 'omega3' => 0.5, 'calories' => 100, 'vitamins' => [], 'minerals' => [], 'contaminants' => ['methylmercury_mg_per_kg' => 0.4]],
        UserGoal::Pregnancy
    );

    expect(collect($recommendations)->pluck('title')->implode(' '))
        ->toContain('Consumo moderado en embarazo');
});

it('emits no Calorias-altas warning for low-cal athletes fish', function () {
    $recommendations = NutritionAdvisor::recommend(
        ['protein' => 20.0, 'fat' => 1.5, 'omega3' => 1.0, 'calories' => 105, 'vitamins' => [], 'minerals' => []],
        UserGoal::WeightLoss
    );

    $titles = collect($recommendations)->pluck('title')->implode(' ');
    expect($titles)->not->toContain('Calorías altas');
});

it('UserGoal::fromString falls back to None when value is unknown', function () {
    expect(UserGoal::fromString('weird'))->toBe(UserGoal::None)
        ->and(UserGoal::fromString(null))->toBe(UserGoal::None)
        ->and(UserGoal::fromString('athlete'))->toBe(UserGoal::Athlete);
});

it('recommendAll returns universal + all goal-specific recommendations without filtering', function () {
    $nutrition = [
        'protein' => 22.0,
        'fat' => 1.5,
        'omega3' => 3.0,
        'calories' => 200,
        'vitamins' => ['D_ug' => 6.0, 'B12_ug' => 5.0],
        'minerals' => ['iron_mg' => 2.0],
        'contaminants' => ['methylmercury_mg_per_kg' => 0.4],
    ];

    $recommendations = NutritionAdvisor::recommendAll($nutrition);

    $titles = collect($recommendations)->pluck('title')->implode(' ');

    expect($recommendations)->toBeArray()
        ->and($titles)->toContain('Alto en proteínas')
        ->and($titles)->toContain('Excelente fuente de Omega-3')
        ->and($titles)->toContain('Apto para deportistas')
        ->and($titles)->toContain('Consumo moderado en embarazo')
        ->and($titles)->toContain('Aporta hierro')
        ->and($titles)->toContain('Rico en vitamina D')
        ->and($titles)->toContain('Rico en vitamina B12');
});

it('recommendAll deduplicates recommendations across goals', function () {
    $nutrition = [
        'protein' => 22.0,
        'fat' => 12.0,
        'omega3' => 1.0,
        'calories' => 210,
        'vitamins' => [],
        'minerals' => [],
    ];

    $recommendations = NutritionAdvisor::recommendAll($nutrition);
    $titles = array_count_values(array_column($recommendations, 'title'));

    foreach ($titles as $title => $count) {
        expect($count)->toBeLessThanOrEqual(1, "Title '{$title}' appears {$count} times, expected once");
    }
});

it('recommendAll includes sustainability guidance for all species', function () {
    $recommendations = NutritionAdvisor::recommendAll([
        'protein' => 20.0,
        'fat' => 5.0,
        'omega3' => 1.5,
        'calories' => 150,
        'vitamins' => [],
        'minerals' => [],
    ]);

    $titles = collect($recommendations)->pluck('title')->implode(' ');

    expect($titles)->toContain('Sostenibilidad no determinada');
});
