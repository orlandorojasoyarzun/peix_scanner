<?php

declare(strict_types=1);

namespace App\Domain\Ai\Exceptions;

use RuntimeException;

/**
 * Thrown when a SpeciesIdentifier cannot produce a result.
 *
 * IMPORTANT — message safety:
 * The exception message is meant to be safe to log, surface in Sentry, or
 * display to operators. It is built from a fixed pattern
 *   "Species identification failed using {provider}: {reason}"
 * where {reason} is always one of the REASON_* constants below. Sensitive
 * details (HTTP response body, raw API error strings, file paths, etc.)
 * must NEVER be interpolated into the message.
 *
 * Pass those details via the $context array and log them separately with
 * Log::warning(...) before throwing. Callers can read them back through
 * getContext() when handling the exception locally.
 */
class IdentificationFailedException extends RuntimeException
{
    public const REASON_IMAGE_NOT_FOUND = 'image_not_found';

    public const REASON_HTTP_ERROR = 'http_error';

    public const REASON_RATE_LIMIT = 'rate_limit';

    public const REASON_EMPTY_BODY = 'empty_body';

    public const REASON_PARSE_FAILED = 'parse_failed';

    public const REASON_UNKNOWN = 'unknown';

    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    private string $provider = '';

    private string $reason = '';

    private function __construct(string $message = '')
    {
        parent::__construct($message, 0);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function fromProvider(string $provider, string $reason, array $context = []): self
    {
        $safeReason = self::normalizeReason($reason);

        $exception = new self("Species identification failed using {$provider}: {$safeReason}");
        $exception->provider = $provider;
        $exception->reason = $safeReason;
        $exception->context = $context;

        return $exception;
    }

    private static function normalizeReason(string $reason): string
    {
        return match ($reason) {
            self::REASON_IMAGE_NOT_FOUND,
            self::REASON_HTTP_ERROR,
            self::REASON_RATE_LIMIT,
            self::REASON_EMPTY_BODY,
            self::REASON_PARSE_FAILED => $reason,
            default => self::REASON_UNKNOWN,
        };
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
