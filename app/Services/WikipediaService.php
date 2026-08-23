<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikipediaService
{
    private const CACHE_TTL_MINUTES = 60;

    private const USER_AGENT = 'PeixScanner/1.0 (https://peix-scanner.local; contact@peix-scanner.local)';

    /**
     * Hosts we are willing to render as <img src> on a species page.
     *
     * Anything outside this list is treated as untrusted — even if
     * en.wikipedia.org's API returned it. This closes the case where a
     * compromised or hijacked API response points the browser at a
     * third-party host we never intended to load.
     */
    private const ALLOWED_THUMBNAIL_HOSTS = [
        'upload.wikimedia.org',
    ];

    public function getSpeciesImage(string $scientificName): ?string
    {
        $cacheKey = CacheKeys::wikipediaImage($scientificName);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($scientificName): ?string {
            $slug = $this->toWikipediaSlug($scientificName);

            $summaryUrl = "https://en.wikipedia.org/api/rest_v1/page/summary/{$slug}";

            try {
                $response = Http::timeout(10)->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                ])->get($summaryUrl);

                if (! $response->successful()) {
                    return $this->fallbackSearch($scientificName);
                }

                $data = $response->json();

                if (! empty($data['thumbnail']['source'])) {
                    return $this->validateThumbnailUrl((string) $data['thumbnail']['source']);
                }

                return $this->fallbackSearch($scientificName);
            } catch (\Throwable) {
                return null;
            }
        });
    }

    private function fallbackSearch(string $scientificName): ?string
    {
        try {
            $searchUrl = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $scientificName,
                'format' => 'json',
                'origin' => '*',
            ]);

            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get($searchUrl);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $pages = $data['query']['search'] ?? [];

            if ($pages === []) {
                return null;
            }

            $title = $pages[0]['title'];
            $summaryUrl = "https://en.wikipedia.org/api/rest_v1/page/summary/{$title}";
            $summaryResponse = Http::timeout(10)->withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get($summaryUrl);

            if (! $summaryResponse->successful()) {
                return null;
            }

            $summaryData = $summaryResponse->json();

            return $this->validateThumbnailUrl((string) ($summaryData['thumbnail']['source'] ?? ''));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns the URL only if its host is on the allowlist. Anything else
     * (a redirect to an attacker-controlled domain, a typo'd protocol, an
     * empty string, or a URL that fails to parse) is dropped and logged.
     *
     * Using parse_url() instead of regex means a URL like
     *   https://upload.wikimedia.org.evil.com/img.jpg
     * is correctly rejected — the actual host (last component of
     * the authority) is `evil.com`, not `upload.wikimedia.org`.
     */
    private function validateThumbnailUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            Log::warning('Wikipedia thumbnail URL could not be parsed', ['url' => $url]);

            return null;
        }

        // Force HTTPS — never load thumbnails over plain HTTP.
        if (strtolower($parts['scheme']) !== 'https') {
            Log::warning('Wikipedia thumbnail URL has non-HTTPS scheme', ['url' => $url]);

            return null;
        }

        $host = strtolower($parts['host']);

        if (! in_array($host, self::ALLOWED_THUMBNAIL_HOSTS, true)) {
            Log::warning('Wikipedia thumbnail URL host not in allowlist', [
                'url' => $url,
                'host' => $host,
            ]);

            return null;
        }

        return $url;
    }

    private function toWikipediaSlug(string $scientificName): string
    {
        $name = str_replace([' ', '_'], '_', trim($scientificName));

        return ucfirst($name);
    }
}
