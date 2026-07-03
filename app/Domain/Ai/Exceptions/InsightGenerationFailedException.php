<?php

declare(strict_types=1);

namespace App\Domain\Ai\Exceptions;

use RuntimeException;

class InsightGenerationFailedException extends RuntimeException
{
    public static function fromProvider(string $provider, string $reason): self
    {
        return new self("Insight generation failed using {$provider}: {$reason}");
    }
}
