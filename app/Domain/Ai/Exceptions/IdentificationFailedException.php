<?php

declare(strict_types=1);

namespace App\Domain\Ai\Exceptions;

use RuntimeException;

class IdentificationFailedException extends RuntimeException
{
    public static function fromProvider(string $provider, string $reason): self
    {
        return new self("Species identification failed using {$provider}: {$reason}");
    }
}
