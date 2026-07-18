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

    public function identifyBatch(string $imagePath, int $attempts = 3): IdentificationResult
    {
        $votes = [];
        $bestResult = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $result = $this->identifier->identify($imagePath);
            } catch (\Throwable) {
                continue;
            }

            $key = $result->scientificName;

            if (! isset($votes[$key])) {
                $votes[$key] = ['count' => 0, 'result' => $result];
            }

            $votes[$key]['count']++;
            $votes[$key]['result'] = $result;

            if ($bestResult === null || $result->confidence > $bestResult->confidence) {
                $bestResult = $result;
            }
        }

        if ($bestResult === null) {
            return $this->identifier->identify($imagePath);
        }

        arsort($votes);

        $top = reset($votes);

        return $top['result'];
    }
}
