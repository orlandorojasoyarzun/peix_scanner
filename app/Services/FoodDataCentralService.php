<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FoodDataCentralService
{
    private const BASE_URL = 'https://api.nal.usda.gov/fdc/v1';

    private const CACHE_TTL_MINUTES = 60 * 24;

    private const ERROR_CACHE_MINUTES = 5;

    private ?string $apiKey = null;

    private const SPECIES_MAP = [
        'merluza europea' => [
            'search' => 'hake',
            'prefer' => ['hake, raw', 'hake'],
            'fallback_desc' => 'hake',
        ],
        'merluza negra' => [
            'search' => 'hake',
            'prefer' => ['hake, raw', 'hake'],
            'fallback_desc' => 'hake',
        ],
        'bacalao' => [
            'search' => 'cod',
            'prefer' => ['cod, raw', 'cod'],
            'fallback_desc' => 'cod',
        ],
        'bacaladilla' => [
            'search' => 'whiting',
            'prefer' => ['whiting, raw', 'whiting'],
            'fallback_desc' => 'whiting',
        ],
        'carbonero' => [
            'search' => 'pollock',
            'prefer' => ['pollock, raw', 'saithe, raw'],
            'fallback_desc' => 'pollock',
        ],
        'abadejo' => [
            'search' => 'pollock',
            'prefer' => ['pollock, raw'],
            'fallback_desc' => 'pollock',
        ],
        'rape' => [
            'search' => 'monkfish',
            'prefer' => ['monkfish, raw', 'monkfish'],
            'fallback_desc' => 'monkfish',
        ],
        'rodaballo' => [
            'search' => 'turbot',
            'prefer' => ['turbot, raw', 'turbot'],
            'fallback_desc' => 'turbot',
        ],
        'lenguado' => [
            'search' => 'sole',
            'prefer' => ['sole, raw', 'common sole, raw'],
            'fallback_desc' => 'sole',
        ],
        'platija' => [
            'search' => 'plaice',
            'prefer' => ['plaice, raw', 'plaice'],
            'fallback_desc' => 'plaice',
        ],
        'congrio' => [
            'search' => 'eel',
            'prefer' => ['eel, raw'],
            'fallback_desc' => 'eel',
        ],
        'cabracho' => [
            'search' => 'scorpionfish',
            'prefer' => ['scorpionfish, raw'],
            'fallback_desc' => 'scorpionfish',
        ],
        'salmonete' => [
            'search' => 'mullet',
            'prefer' => ['mullet, raw'],
            'fallback_desc' => 'mullet',
        ],
        'gallineta' => [
            'search' => 'rockfish',
            'prefer' => ['rockfish, raw'],
            'fallback_desc' => 'rockfish',
        ],
        'salmón atlántico' => [
            'search' => 'salmon atlantic',
            'prefer' => ['salmon, atlantic, raw', 'atlantic salmon, raw', 'wild salmon, raw'],
            'fallback_desc' => 'salmon',
        ],
        'trucha arcoíris' => [
            'search' => 'trout rainbow',
            'prefer' => ['trout, rainbow, raw', 'rainbow trout, raw'],
            'fallback_desc' => 'trout',
        ],
        'atún rojo' => [
            'search' => 'tuna bluefin',
            'prefer' => ['tuna, bluefin, raw', 'bluefin tuna, raw'],
            'fallback_desc' => 'tuna',
        ],
        'atún claro' => [
            'search' => 'tuna yellowfin',
            'prefer' => ['tuna, yellowfin, raw'],
            'fallback_desc' => 'tuna',
        ],
        'bonito del norte' => [
            'search' => 'bonito',
            'prefer' => ['bonito, raw', 'skipjack, raw'],
            'fallback_desc' => 'bonito',
        ],
        'caballa' => [
            'search' => 'mackerel atlantic',
            'prefer' => ['mackerel, atlantic, raw', 'atlantic mackerel, raw'],
            'fallback_desc' => 'mackerel',
        ],
        'sardina' => [
            'search' => 'sardine',
            'prefer' => ['sardine, raw', 'sardines, raw'],
            'fallback_desc' => 'sardine',
        ],
        'boquerón' => [
            'search' => 'anchovy',
            'prefer' => ['anchovy, raw'],
            'fallback_desc' => 'anchovy',
        ],
        'jurel' => [
            'search' => 'mackerel jack',
            'prefer' => ['mackerel, jack, raw', 'fish, mackerel, jack, raw', 'mackerel, pacific and jack, mixed species, raw'],
            'fallback_desc' => 'mackerel',
        ],
        'pez espada' => [
            'search' => 'swordfish',
            'prefer' => ['swordfish, raw'],
            'fallback_desc' => 'swordfish',
        ],
        'lubina' => [
            'search' => 'seabass',
            'prefer' => ['seabass, raw', 'bass, european, raw'],
            'fallback_desc' => 'seabass',
        ],
        'dorada' => [
            'search' => 'seabream',
            'prefer' => ['seabream, raw', 'gilthead seabream, raw', 'bream, raw'],
            'fallback_desc' => 'seabream',
        ],
        'besugo' => [
            'search' => 'seabream',
            'prefer' => ['seabream, raw', 'red seabream, raw'],
            'fallback_desc' => 'seabream',
        ],
        'pargo' => [
            'search' => 'snapper',
            'prefer' => ['snapper, raw'],
            'fallback_desc' => 'snapper',
        ],
        'corvina' => [
            'search' => 'drum',
            'prefer' => ['drum, freshwater, raw'],
            'fallback_desc' => 'drum',
        ],
        'dentón' => [
            'search' => 'dentex',
            'prefer' => ['dentex, raw'],
            'fallback_desc' => 'dentex',
        ],
        'panga' => [
            'search' => 'catfish',
            'prefer' => ['catfish, raw', 'pangasius, raw'],
            'fallback_desc' => 'catfish',
        ],
        'tilapia' => [
            'search' => 'tilapia',
            'prefer' => ['tilapia, raw'],
            'fallback_desc' => 'tilapia',
        ],
        'fletán' => [
            'search' => 'halibut',
            'prefer' => ['halibut, raw', 'halibut, atlantic, raw'],
            'fallback_desc' => 'halibut',
        ],
        'sepia' => [
            'search' => 'cuttlefish',
            'prefer' => ['cuttlefish, raw'],
            'fallback_desc' => 'cuttlefish',
        ],
        'choco' => [
            'search' => 'cuttlefish',
            'prefer' => ['cuttlefish, raw'],
            'fallback_desc' => 'cuttlefish',
        ],
        'calamar' => [
            'search' => 'squid',
            'prefer' => ['squid, raw'],
            'fallback_desc' => 'squid',
        ],
        'pulpo' => [
            'search' => 'octopus',
            'prefer' => ['octopus, raw'],
            'fallback_desc' => 'octopus',
        ],
        'gamba' => [
            'search' => 'shrimp',
            'prefer' => ['shrimp, raw', 'shrimp, mixed species, raw'],
            'fallback_desc' => 'shrimp',
        ],
        'langostino' => [
            'search' => 'prawn',
            'prefer' => ['prawn, raw'],
            'fallback_desc' => 'prawn',
        ],
        'cigala' => [
            'search' => 'norway lobster',
            'prefer' => ['norway lobster, raw'],
            'fallback_desc' => 'lobster',
        ],
        'bogavante' => [
            'search' => 'lobster',
            'prefer' => ['lobster, european, raw', 'lobster, raw'],
            'fallback_desc' => 'lobster',
        ],
        'langosta' => [
            'search' => 'lobster',
            'prefer' => ['spiny lobster, raw'],
            'fallback_desc' => 'lobster',
        ],
        'buey de mar' => [
            'search' => 'crab',
            'prefer' => ['crab, raw'],
            'fallback_desc' => 'crab',
        ],
        'centollo' => [
            'search' => 'crab',
            'prefer' => ['crab, raw', 'spider crab, raw'],
            'fallback_desc' => 'crab',
        ],
        'nécora' => [
            'search' => 'crab',
            'prefer' => ['crab, raw', 'velvet crab, raw'],
            'fallback_desc' => 'crab',
        ],
        'mejillón' => [
            'search' => 'mussel',
            'prefer' => ['mussel, raw'],
            'fallback_desc' => 'mussel',
        ],
        'almeja' => [
            'search' => 'clam',
            'prefer' => ['clam, raw'],
            'fallback_desc' => 'clam',
        ],
        'vieira' => [
            'search' => 'scallop',
            'prefer' => ['scallop, raw'],
            'fallback_desc' => 'scallop',
        ],
        'ostra' => [
            'search' => 'oyster',
            'prefer' => ['oyster, raw'],
            'fallback_desc' => 'oyster',
        ],
        'berberecho' => [
            'search' => 'cockle',
            'prefer' => ['cockle, raw'],
            'fallback_desc' => 'cockle',
        ],
        'trucha común' => [
            'search' => 'trout',
            'prefer' => ['trout, raw', 'brown trout, raw'],
            'fallback_desc' => 'trout',
        ],
        'carpa' => [
            'search' => 'carp',
            'prefer' => ['carp, raw'],
            'fallback_desc' => 'carp',
        ],
        'perca' => [
            'search' => 'perch',
            'prefer' => ['perch, raw'],
            'fallback_desc' => 'perch',
        ],
    ];

    public function __construct()
    {
        $this->apiKey = config('services.usda.key');
    }

    public function getNutritionData(string $spanishCommonName, ?string $scientificName = null): ?array
    {
        $key = trim(mb_strtolower($spanishCommonName));

        if (! isset(self::SPECIES_MAP[$key])) {
            return null;
        }

        $config = self::SPECIES_MAP[$key];
        $cacheKey = "usda.food.{$key}";

        if ($scientificName !== null && $scientificName !== '') {
            $cacheKey .= '.'.md5($scientificName);
        }

        $cached = Cache::get($cacheKey);

        if ($cached === '__NOT_FOUND__') {
            return null;
        }

        if ($cached !== null) {
            return $cached;
        }

        $searchTerm = $config['search'];

        try {
            $response = Http::timeout(15)->get(self::BASE_URL.'/foods/search', [
                'api_key' => $this->apiKey,
                'query' => $searchTerm,
                'pageSize' => 25,
            ]);
        } catch (\Throwable $e) {
            Log::warning('USDA FDC request failed', ['error' => $e->getMessage(), 'term' => $searchTerm]);
            Cache::put($cacheKey, '__NOT_FOUND__', now()->addMinutes(self::ERROR_CACHE_MINUTES));

            return null;
        }

        if (! $response->successful()) {
            Log::warning('USDA FDC unsuccessful response', ['status' => $response->status(), 'term' => $searchTerm]);
            Cache::put($cacheKey, '__NOT_FOUND__', now()->addMinutes(self::ERROR_CACHE_MINUTES));

            return null;
        }

        $foods = $response->json('foods', []);

        if ($foods === []) {
            Cache::put($cacheKey, '__NOT_FOUND__', now()->addMinutes(self::CACHE_TTL_MINUTES));

            return null;
        }

        $bestFood = $this->pickBestFood($foods, $config['prefer'], $config['fallback_desc'], $scientificName);

        if ($bestFood === null) {
            Cache::put($cacheKey, '__NOT_FOUND__', now()->addMinutes(self::CACHE_TTL_MINUTES));

            return null;
        }

        $parsed = $this->parseNutrients($bestFood);

        if ($parsed !== null) {
            Cache::put($cacheKey, $parsed, now()->addMinutes(self::CACHE_TTL_MINUTES));
        } else {
            Cache::put($cacheKey, '__NOT_FOUND__', now()->addMinutes(self::CACHE_TTL_MINUTES));
        }

        return $parsed;
    }

    private function pickBestFood(array $foods, array $preferredDescriptions, string $fallbackKeyword, ?string $scientificName): ?array
    {
        $normalizedSciName = $scientificName !== null ? mb_strtolower($scientificName) : null;
        $sciGenus = $normalizedSciName !== null ? explode(' ', $normalizedSciName)[0] ?? null : null;

        foreach ($preferredDescriptions as $preferred) {
            $preferredLower = mb_strtolower($preferred);
            foreach ($foods as $food) {
                $description = mb_strtolower($food['description'] ?? '');
                if (str_contains($description, $preferredLower)) {
                    return $food;
                }
            }
        }

        foreach ($foods as $food) {
            $description = mb_strtolower($food['description'] ?? '');
            $sn = mb_strtolower($food['scientificName'] ?? '');
            $cn = mb_strtolower($food['commonNames'] ?? '');

            if ($sciGenus !== null && (
                str_contains($sn, $sciGenus) ||
                str_contains($cn, $sciGenus)
            )) {
                return $food;
            }
        }

        foreach ($foods as $food) {
            $description = mb_strtolower($food['description'] ?? '');
            if (str_contains($description, $fallbackKeyword) && str_contains($description, 'raw')) {
                return $food;
            }
        }

        foreach ($foods as $food) {
            $description = mb_strtolower($food['description'] ?? '');
            if (str_contains($description, $fallbackKeyword)) {
                return $food;
            }
        }

        return $foods[0] ?? null;
    }

    private function parseNutrients(array $food): ?array
    {
        $nutrients = $food['foodNutrients'] ?? [];

        $values = [];
        foreach ($nutrients as $nutrient) {
            $id = $nutrient['nutrientId'] ?? null;
            $value = $nutrient['value'] ?? null;

            if ($id === null || $value === null) {
                continue;
            }

            match ((int) $id) {
                208, 1008 => $values['calories'] = (float) $value,
                203, 1003 => $values['protein'] = (float) $value,
                204, 1004 => $values['fat'] = (float) $value,
                629, 1278, 631, 1280 => $values['omega3'] = ($values['omega3'] ?? 0) + (float) $value,
                1178 => $values['vitamins']['B12_ug'] = (float) $value,
                1114, 328 => $values['vitamins']['D_ug'] = ($values['vitamins']['D_ug'] ?? 0) + (float) $value,
                1106, 320 => $values['vitamins']['A_RAE_ug'] = ($values['vitamins']['A_RAE_ug'] ?? 0) + (float) $value,
                1109, 323 => $values['vitamins']['E_mg'] = (float) $value,
                1185, 430 => $values['vitamins']['K_ug'] = (float) $value,
                1170, 410 => $values['vitamins']['B5_mg'] = (float) $value,
                1175, 415 => $values['vitamins']['B6_mg'] = (float) $value,
                1167, 406 => $values['vitamins']['B3_mg'] = (float) $value,
                1087, 301 => $values['minerals']['calcium_mg'] = (float) $value,
                1089, 303 => $values['minerals']['iron_mg'] = (float) $value,
                1090, 304 => $values['minerals']['magnesium_mg'] = (float) $value,
                1091, 305 => $values['minerals']['phosphorus_mg'] = (float) $value,
                1092, 306 => $values['minerals']['potassium_mg'] = (float) $value,
                1103, 317 => $values['minerals']['selenium_ug'] = (float) $value,
                default => null,
            };
        }

        if ($values === []) {
            return null;
        }

        return $values;
    }
}
