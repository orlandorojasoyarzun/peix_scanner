<?php

declare(strict_types=1);

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\DTOs\SpeciesInsight;
use App\Domain\Ai\Exceptions\InsightGenerationFailedException;

interface InsightGenerator
{
    /**
     * Generate a consumer-friendly insight for the given species.
     *
     * @param  string  $scientificName  Scientific name of the identified species.
     * @return SpeciesInsight Nutrition, sustainability, preparation summary.
     *
     * @throws InsightGenerationFailedException
     */
    public function generate(string $scientificName): SpeciesInsight;
}
