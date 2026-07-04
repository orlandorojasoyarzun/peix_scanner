<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;

class IdentifySpeciesAction
{
    public function __construct(
        private readonly SpeciesIdentifier $identifier,
    ) {}

    public function execute(string $imagePath): IdentificationResult
    {
        return $this->identifier->identify($imagePath);
    }
}
