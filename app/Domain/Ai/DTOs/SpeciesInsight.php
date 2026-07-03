<?php

declare(strict_types=1);

namespace App\Domain\Ai\DTOs;

final readonly class SpeciesInsight
{
    /**
     * @param  array<string, string|float>  $nutrition  Per-100g values: calories, protein, omega3, fat.
     * @param  array<string, string>  $vitamins  Vitamin key => value (mg or µg).
     * @param  array<string, string>  $sustainability  Indicator key => value/label.
     * @param  list<string>  $preparation  Cooking advice lines.
     */
    public function __construct(
        public string $scientificName,
        public string $commonName,
        public string $summary,
        public array $nutrition = [],
        public array $vitamins = [],
        public array $sustainability = [],
        public array $preparation = [],
    ) {}
}
