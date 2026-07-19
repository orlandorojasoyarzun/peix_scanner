<?php

declare(strict_types=1);

namespace App\Domain\Ai\DTOs;

final readonly class IdentificationResult
{
    /**
     * @param  list<array{
     *     scientific_name: string,
     *     common_name: string,
     *     common_name_local: string,
     *     regional_names: list<string>,
     *     confidence: float
     * }>  $candidates  Ordered by confidence (highest first). The first entry equals the top identification.
     */
    public function __construct(
        public string $scientificName,
        public string $commonName,
        public float $confidence = 0,
        public string $commonNameLocal = '',
        public array $candidates = [],
        public array $regionalNames = [],
    ) {}

    public function isHighConfidence(float $threshold = 0.75): bool
    {
        return $this->confidence >= $threshold;
    }

    /**
     * @return array{
     *     scientific_name: string,
     *     common_name: string,
     *     common_name_local: string,
     *     regional_names: list<string>,
     *     confidence: float
     * }|null
     */
    public function topCandidate(): ?array
    {
        return $this->candidates[0] ?? null;
    }
}
