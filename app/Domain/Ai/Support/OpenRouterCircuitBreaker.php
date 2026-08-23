<?php

declare(strict_types=1);

namespace App\Domain\Ai\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

/**
 * Tiny per-process circuit breaker for the OpenRouter upstream.
 *
 * Why we have this:
 *  - Each call to OpenRouter costs money and can take up to ~180s when the
 *    upstream is slow. Without protection, a short outage can burn through
 *    a month's quota in hours.
 *  - Instead of hammering a dead provider, we count consecutive failures
 *    and, once the threshold is hit, reject requests immediately for a
 *    cooldown window. After the cooldown, the next call is allowed through
 *    (HALF_OPEN) — if it succeeds the circuit closes again; if it fails
 *    the cooldown restarts.
 *
 * Storage:
 *  - Two cache keys: `openrouter.circuit.failures` (counter, rolling TTL)
 *    and `openrouter.circuit.opened_at` (Unix timestamp, fixed cooldown TTL).
 *  - Driver-agnostic: works on the array driver in tests and on the
 *    database/redis driver in production without code changes.
 *
 * Thread-safety:
 *  - This is best-effort across concurrent requests on the same PHP-FPM
 *    worker pool. The blast radius of a race is "one extra request sneaks
 *    through before the breaker trips" — acceptable for an MVP. For
 *    stricter guarantees, swap the driver for an atomic Redis counter.
 */
class OpenRouterCircuitBreaker
{
    /** Number of consecutive failures before the breaker opens. */
    private const FAILURE_THRESHOLD = 5;

    /**
     * Cooldown window in seconds. During this window the breaker rejects
     * all requests without touching the upstream.
     */
    private const COOLDOWN_SECONDS = 60;

    /**
     * TTL on the failure counter, used so the counter resets on its own
     * if failures stop coming in. Must be at least a few minutes longer
     * than a typical request so we don't lose the count mid-flight.
     */
    private const FAILURE_TTL_SECONDS = 300;

    private const FAILURES_KEY = 'openrouter.circuit.failures';

    private const OPENED_AT_KEY = 'openrouter.circuit.opened_at';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Returns true when the breaker is CLOSED (i.e. the call may proceed).
     * Returns false when the breaker is OPEN or has just tripped — the
     * caller should reject the request without touching the upstream.
     */
    public function isClosed(): bool
    {
        $openedAt = $this->cache->get(self::OPENED_AT_KEY);

        if ($openedAt === null) {
            return true;
        }

        // Auto-expire: once the cooldown has elapsed, behave as closed so
        // the next call is allowed through (HALF_OPEN behaviour). The
        // opened_at key itself expires via its TTL, but we double-check
        // here against wall time in case the cache driver preserves the
        // value past its declared TTL.
        $age = time() - (int) $openedAt;

        return $age >= self::COOLDOWN_SECONDS;
    }

    /**
     * Call when an upstream call succeeded. Resets the failure counter and
     * clears the open state. Idempotent.
     */
    public function recordSuccess(): void
    {
        $this->cache->forget(self::FAILURES_KEY);
        $this->cache->forget(self::OPENED_AT_KEY);
    }

    /**
     * Call when an upstream call failed. Increments the consecutive
     * failure counter; once it crosses the threshold, opens the breaker.
     */
    public function recordFailure(): void
    {
        $failures = (int) $this->cache->get(self::FAILURES_KEY, 0);
        $failures++;
        $this->cache->put(self::FAILURES_KEY, $failures, self::FAILURE_TTL_SECONDS);

        if ($failures >= self::FAILURE_THRESHOLD && $this->cache->get(self::OPENED_AT_KEY) === null) {
            $this->cache->put(self::OPENED_AT_KEY, time(), self::COOLDOWN_SECONDS);

            Log::warning('OpenRouter circuit breaker tripped', [
                'consecutive_failures' => $failures,
                'cooldown_seconds' => self::COOLDOWN_SECONDS,
            ]);
        }
    }
}
