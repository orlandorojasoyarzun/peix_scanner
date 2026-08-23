<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralised cache key builders.
 *
 * Every cache key the application writes or reads MUST go through one of
 * these methods. Centralising them prevents typos (a forgotten suffix, a
 * different separator) that produce ghost keys that no one ever forgets
 * or invalidates, and gives a single grep target when renaming or
 * migrating.
 *
 * Naming conventions used here:
 *   scan.<uuid>.<slot>     — transient per-scan data (10-30 min TTL)
 *   species.<slug>.<slot>   — per-species results shown on the ficha page
 *   explain.<slug>          — cached AI explanation text
 *   wikipedia.image.<name>  — external API cache
 *   usda.food.<key>[.<h>]   — external API cache
 */
final class CacheKeys
{
    // ------------------------------------------------------------------
    // Per-scan transient state
    // ------------------------------------------------------------------

    /**
     * Atomic, single-slot cache for everything related to a scan in
     * flight: the upload path, the mode (fish | label), the result or
     * the error, and (once resolved) the reference image URL.
     *
     * Writing everything at once closes the partial-read window: when
     * confirm() reads the state, it either gets the whole record or
     * nothing — never a half-written mix that would render as "image
     * uploaded but no result yet".
     *
     * The legacy keys (scanResult / scanImage / scanMode / scanError /
     * scanReferenceImage) are still produced by their accessors for
     * backward compatibility with older code paths, but new code MUST
     * use scanState() + ScanState so there is exactly one read and one
     * write per scan.
     */
    public static function scanState(string $uuid): string
    {
        return "scan.{$uuid}.state";
    }

    public static function scanResult(string $uuid): string
    {
        return "scan.{$uuid}.result";
    }

    public static function scanImage(string $uuid): string
    {
        return "scan.{$uuid}.image";
    }

    public static function scanMode(string $uuid): string
    {
        return "scan.{$uuid}.mode";
    }

    public static function scanError(string $uuid): string
    {
        return "scan.{$uuid}.error";
    }

    public static function scanReferenceImage(string $uuid): string
    {
        return "scan.{$uuid}.reference_image";
    }

    // ------------------------------------------------------------------
    // Per-species cached results
    // ------------------------------------------------------------------

    public static function speciesResult(string $slug): string
    {
        return "species.{$slug}.result";
    }

    public static function explain(string $speciesSlug): string
    {
        return "explain.{$speciesSlug}";
    }

    // ------------------------------------------------------------------
    // External API caches
    // ------------------------------------------------------------------

    public static function wikipediaImage(string $scientificName): string
    {
        return "wikipedia.image.{$scientificName}";
    }

    /**
     * USDA cache key. When a scientific name is provided, its hash is
     * appended so two different species that map to the same Spanish
     * common name don't share a cache slot.
     */
    public static function usdaFood(string $spanishKey, ?string $scientificName = null): string
    {
        $key = "usda.food.{$spanishKey}";

        if ($scientificName !== null && $scientificName !== '') {
            $key .= '.'.md5($scientificName);
        }

        return $key;
    }
}
