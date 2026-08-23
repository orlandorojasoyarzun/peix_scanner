<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Crypt;

/**
 * Single source of truth for the per-scan state cache.
 *
 * Why this exists:
 *  - The old code wrote the scan result, image path, mode, error and
 *    reference image as separate cache keys (5+ writes per scan). If the
 *    user reloads confirm() in the millisecond window between two of
 *    those writes, they see partial state — an image with no result, a
 *    mode with no image — and the UI breaks.
 *  - This class collapses all of that into one atomic write under
 *    `scan.<uuid>.state`. confirm() does exactly one Cache::get and
 *    either gets the whole record or nothing.
 *  - As a side benefit the whole record is encrypted with APP_KEY before
 *    hitting the cache driver, so a database dump doesn't expose the
 *    species names, paths, and Wikipedia URLs of in-flight scans.
 *
 * The class wraps the cache repository through Laravel's container so it
 * honours the configured store (database, redis, file, ...). Tests use
 * the array store by default.
 */
class ScanStateStore
{
    /** TTL for an in-flight scan — same window the old per-key caches used. */
    public const TTL_SECONDS = 600; // 10 min

    /**
     * Cache key holding the set of scan UUIDs that currently have a
     * state slot. Used by the deep-cleanup purge command to walk the
     * cache without depending on driver-specific SCAN/KEYS support.
     */
    public const INDEX_KEY = 'scan.state.index';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Atomically persist the full scan state. Overwrites whatever was
     * there (a previous attempt, a stale rescan).
     *
     * @param  array<string, mixed>  $state
     */
    public function put(string $uuid, array $state): void
    {
        $payload = $this->encode($uuid, $state);
        $this->cache->put(CacheKeys::scanState($uuid), $payload, self::TTL_SECONDS);

        $this->registerInIndex($uuid);
    }

    /**
     * Read the full scan state. Returns null if the scan has expired or
     * was never written — callers must treat that as "no scan".
     *
     * @return array<string, mixed>|null
     */
    public function get(string $uuid): ?array
    {
        $raw = $this->cache->get(CacheKeys::scanState($uuid));

        if ($raw === null) {
            return null;
        }

        return $this->decode($uuid, $raw);
    }

    /** Drop the state, e.g. after the user navigates away or the scan is purged. */
    public function forget(string $uuid): void
    {
        $this->cache->forget(CacheKeys::scanState($uuid));
        $this->unregisterFromIndex($uuid);
    }

    /**
     * Returns every scan UUID currently registered in the index.
     *
     * The deep-cleanup purge command uses this to walk the cache in
     * O(n) without depending on a driver-specific SCAN/KEYS command.
     * The index itself has its own TTL and is refreshed on every put;
     * on cache restart the worst case is an empty index, which is safe
     * (purge just doesn't find any orphans).
     *
     * @return list<string>
     */
    public function indexedUuids(): array
    {
        $index = $this->cache->get(self::INDEX_KEY);

        if (! is_array($index)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $index), 'is_string'));
    }

    private function registerInIndex(string $uuid): void
    {
        $index = $this->cache->get(self::INDEX_KEY);

        if (! is_array($index)) {
            $index = [];
        }

        if (! in_array($uuid, $index, true)) {
            $index[] = $uuid;
        }

        // Index TTL is long enough to outlive every scan state but short
        // enough to self-clean if writes stop happening.
        $this->cache->put(self::INDEX_KEY, $index, now()->addHours(24));
    }

    private function unregisterFromIndex(string $uuid): void
    {
        $index = $this->cache->get(self::INDEX_KEY);

        if (! is_array($index)) {
            return;
        }

        $index = array_values(array_filter($index, static fn (mixed $entry): bool => $entry !== $uuid));

        if ($index === []) {
            $this->cache->forget(self::INDEX_KEY);

            return;
        }

        $this->cache->put(self::INDEX_KEY, $index, now()->addHours(24));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function encode(string $uuid, array $state): string
    {
        $envelope = [
            'v' => 1,
            'scan_id' => $uuid,
            'state' => $state,
        ];

        return Crypt::encryptString(json_encode($envelope, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $uuid, string $raw): ?array
    {
        try {
            $json = Crypt::decryptString($raw);
            $envelope = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            // Corrupted or rotated key. Treat the slot as empty — the user
            // gets a fresh "scan not found" path rather than a 500.
            return null;
        }

        if (! is_array($envelope) || ($envelope['v'] ?? null) !== 1) {
            return null;
        }

        $state = $envelope['state'] ?? null;

        return is_array($state) ? $state : null;
    }
}
