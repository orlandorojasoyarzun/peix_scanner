<?php

declare(strict_types=1);

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;

interface SpeciesIdentifier
{
    /**
     * Identify a fish species from a photo.
     *
     * @param  string  $imagePath  Absolute path to the uploaded image.
     * @return IdentificationResult Result with species, confidence and alternative candidates.
     *
     * @throws IdentificationFailedException
     */
    public function identify(string $imagePath): IdentificationResult;
}
