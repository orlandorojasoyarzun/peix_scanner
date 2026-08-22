<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WikipediaService
{
    private const CACHE_TTL_MINUTES = 60;

    private const USER_AGENT = 'PeixScanner/1.0 (https://peix-scanner.local; contact@peix-scanner.local)';

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
                    return $data['thumbnail']['source'];
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

            return $summaryData['thumbnail']['source'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function toWikipediaSlug(string $scientificName): string
    {
        $name = str_replace([' ', '_'], '_', trim($scientificName));

        return ucfirst($name);
    }
}
