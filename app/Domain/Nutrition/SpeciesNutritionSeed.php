<?php

declare(strict_types=1);

namespace App\Domain\Nutrition;

/**
 * Datos nutricionales curados por 100g de carne comestible cruda.
 *
 * Fuente principal: Fundación Española de la Nutrición (FEN).
 * Cuando una especie tiene variantes (cultivada/salvaje, fresca/salazón),
 * se incluye una nota en `note` y `source` lo declara explícitamente.
 *
 * Aliases: si una entrada tiene `alias_of`, se resuelve a esa entrada
 * canónica (útil para nombres regionales como chicharro→jurel).
 *
 * Mantenimiento: cada vez que FEN publique revisión, actualizar aquí.
 * No se llama a USDA ni se cachea: este es el dataset primario.
 */
class SpeciesNutritionSeed
{
    public const DATA = [
        // ========== PESCADOS BLANCOS ==========
        'merluza europea' => [
            'calories' => 84,
            'protein' => 17.0,
            'fat' => 0.8,
            'omega3' => 0.20,
            'calcium_mg' => 16,
            'iron_mg' => 0.30,
            'phosphorus_mg' => 200,
            'vitamins' => [
                'B12_ug' => 0.90,
                'D_ug' => 0.50,
                'A_RAE_ug' => 12,
            ],
            'contaminants' => [
                'methylmercury_mg_per_kg' => 0.20,
            ],
            'source' => 'FEN',
        ],
        'merluza negra' => [
            'calories' => 95,
            'protein' => 18.0,
            'fat' => 1.5,
            'omega3' => 0.30,
            'source' => 'FEN',
        ],
        'bacalao' => [
            'calories' => 82,
            'protein' => 17.8,
            'fat' => 0.7,
            'omega3' => 0.30,
            'calcium_mg' => 20,
            'iron_mg' => 0.40,
            'phosphorus_mg' => 180,
            'vitamins' => [
                'B12_ug' => 1.20,
                'D_ug' => 1.00,
                'A_RAE_ug' => 14,
            ],
            'source' => 'FEN',
        ],
        'bacaladilla' => [
            'calories' => 88,
            'protein' => 17.2,
            'fat' => 1.2,
            'source' => 'FEN',
        ],
        'carbonero' => [
            'calories' => 96,
            'protein' => 18.0,
            'fat' => 1.5,
            'source' => 'FEN',
        ],
        'abadejo' => [
            'calories' => 91,
            'protein' => 18.5,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'rape' => [
            'calories' => 65,
            'protein' => 15.5,
            'fat' => 0.5,
            'omega3' => 0.10,
            'source' => 'FEN',
        ],
        'rodaballo' => [
            'calories' => 95,
            'protein' => 16.5,
            'fat' => 2.5,
            'source' => 'FEN',
        ],
        'lenguado' => [
            'calories' => 79,
            'protein' => 16.8,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'platija' => [
            'calories' => 80,
            'protein' => 17.0,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'congrio' => [
            'calories' => 94,
            'protein' => 18.5,
            'fat' => 1.5,
            'omega3' => 0.30,
            'calcium_mg' => 25,
            'iron_mg' => 0.50,
            'phosphorus_mg' => 210,
            'source' => 'FEN',
        ],
        'cabracho' => [
            'calories' => 110,
            'protein' => 18.5,
            'fat' => 3.0,
            'source' => 'FEN',
        ],
        'salmonete' => [
            'calories' => 117,
            'protein' => 18.5,
            'fat' => 4.0,
            'omega3' => 0.80,
            'source' => 'FEN',
        ],
        'gallineta' => [
            'calories' => 95,
            'protein' => 18.0,
            'fat' => 1.5,
            'source' => 'FEN',
        ],
        'mero' => [
            'calories' => 100,
            'protein' => 19.0,
            'fat' => 1.5,
            'omega3' => 0.40,
            'calcium_mg' => 15,
            'iron_mg' => 0.50,
            'phosphorus_mg' => 200,
            'vitamins' => [
                'B12_ug' => 1.50,
                'D_ug' => 2.00,
                'A_RAE_ug' => 20,
            ],
            'contaminants' => [
                'methylmercury_mg_per_kg' => 0.45,
            ],
            'source' => 'FEN',
        ],
        'gallo' => [
            'calories' => 70,
            'protein' => 16.0,
            'fat' => 0.5,
            'omega3' => 0.10,
            'calcium_mg' => 18,
            'iron_mg' => 0.40,
            'phosphorus_mg' => 190,
            'vitamins' => [
                'B12_ug' => 1.20,
                'D_ug' => 0.80,
                'A_RAE_ug' => 10,
            ],
            'source' => 'FEN',
        ],
        'breca' => [
            'calories' => 95,
            'protein' => 19.0,
            'fat' => 1.5,
            'omega3' => 0.40,
            'calcium_mg' => 20,
            'iron_mg' => 0.40,
            'phosphorus_mg' => 210,
            'vitamins' => [
                'B12_ug' => 1.80,
                'D_ug' => 1.50,
                'A_RAE_ug' => 16,
            ],
            'source' => 'FEN',
        ],
        'aligote' => [
            'calories' => 90,
            'protein' => 18.5,
            'fat' => 1.0,
            'omega3' => 0.30,
            'calcium_mg' => 20,
            'iron_mg' => 0.40,
            'phosphorus_mg' => 210,
            'vitamins' => [
                'B12_ug' => 1.50,
                'D_ug' => 1.20,
                'A_RAE_ug' => 15,
            ],
            'source' => 'FEN',
        ],
        'lubina' => [
            'calories' => 91,
            'protein' => 18.5,
            'fat' => 2.0,
            'omega3' => 0.50,
            'calcium_mg' => 20,
            'iron_mg' => 0.40,
            'phosphorus_mg' => 220,
            'vitamins' => [
                'B12_ug' => 2.00,
                'D_ug' => 4.50,
                'A_RAE_ug' => 18,
            ],
            'source' => 'FEN',
        ],
        'dorada' => [
            'calories' => 96,
            'protein' => 19.0,
            'fat' => 2.0,
            'omega3' => 0.40,
            'calcium_mg' => 22,
            'iron_mg' => 0.40,
            'phosphorus_mg' => 200,
            'vitamins' => [
                'B12_ug' => 1.50,
                'D_ug' => 2.50,
                'A_RAE_ug' => 15,
            ],
            'source' => 'FEN',
            'note' => 'Dorada de mar. La de piscifactoría es algo más magra.',
        ],
        'besugo' => [
            'calories' => 110,
            'protein' => 19.0,
            'fat' => 3.0,
            'source' => 'FEN',
        ],
        'pargo' => [
            'calories' => 100,
            'protein' => 20.0,
            'fat' => 1.5,
            'source' => 'FEN',
        ],
        'corvina' => [
            'calories' => 95,
            'protein' => 19.5,
            'fat' => 1.5,
            'source' => 'FEN',
        ],
        'dentón' => [
            'calories' => 92,
            'protein' => 19.0,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'panga' => [
            'calories' => 88,
            'protein' => 15.0,
            'fat' => 2.5,
            'source' => 'FEN',
        ],
        'tilapia' => [
            'calories' => 96,
            'protein' => 18.0,
            'fat' => 2.0,
            'source' => 'FEN',
        ],
        'fletán' => [
            'calories' => 110,
            'protein' => 18.5,
            'fat' => 3.5,
            'source' => 'FEN',
        ],

        // ========== PESCADOS AZULES ==========
        'salmón atlántico' => [
            'calories' => 208,
            'protein' => 20.0,
            'fat' => 13.0,
            'omega3' => 2.50,
            'calcium_mg' => 12,
            'iron_mg' => 0.30,
            'phosphorus_mg' => 240,
            'vitamins' => [
                'B12_ug' => 3.00,
                'D_ug' => 11.00,
                'A_RAE_ug' => 12,
            ],
            'source' => 'FEN',
        ],
        'trucha arcoíris' => [
            'calories' => 141,
            'protein' => 19.5,
            'fat' => 6.0,
            'omega3' => 1.20,
            'source' => 'FEN',
        ],
        'atún rojo' => [
            'calories' => 144,
            'protein' => 23.0,
            'fat' => 5.0,
            'omega3' => 1.60,
            'contaminants' => [
                'methylmercury_mg_per_kg' => 0.50,
            ],
            'source' => 'FEN',
            'note' => 'Mercurio medio-alto. Limitar consumo en embarazadas y niños.',
        ],
        'atún claro' => [
            'calories' => 109,
            'protein' => 24.0,
            'fat' => 1.0,
            'omega3' => 0.30,
            'contaminants' => [
                'methylmercury_mg_per_kg' => 0.35,
            ],
            'source' => 'FEN',
        ],
        'bonito del norte' => [
            'calories' => 130,
            'protein' => 22.5,
            'fat' => 3.5,
            'omega3' => 1.00,
            'contaminants' => [
                'methylmercury_mg_per_kg' => 0.40,
            ],
            'source' => 'FEN',
            'note' => 'Mercurio medio-alto. Alternativa al atún rojo con perfil similar.',
        ],
        'caballa' => [
            'calories' => 192,
            'protein' => 18.5,
            'fat' => 12.0,
            'omega3' => 2.80,
            'source' => 'FEN',
        ],
        'sardina' => [
            'calories' => 169,
            'protein' => 18.0,
            'fat' => 11.0,
            'omega3' => 2.20,
            'calcium_mg' => 50,
            'iron_mg' => 1.50,
            'phosphorus_mg' => 240,
            'vitamins' => [
                'B12_ug' => 9.00,
                'D_ug' => 11.00,
                'A_RAE_ug' => 32,
            ],
            'source' => 'FEN',
            'note' => 'Sardina fresca. Las de aceite de oliva en lata aportan ~400mg sodio adicional.',
        ],
        'boquerón' => [
            'calories' => 131,
            'protein' => 17.5,
            'fat' => 6.5,
            'omega3' => 1.50,
            'source' => 'FEN',
            'note' => 'Pescado fresco. "Anchoa" es el mismo pez en salazón o conserva.',
        ],
        'anchoa' => [
            'calories' => 210,
            'protein' => 29.0,
            'fat' => 11.0,
            'omega3' => 3.20,
            'sodium_mg' => 5600,
            'calcium_mg' => 120,
            'iron_mg' => 4.00,
            'phosphorus_mg' => 230,
            'vitamins' => [
                'B12_ug' => 8.50,
                'D_ug' => 3.00,
                'A_RAE_ug' => 15,
            ],
            'source' => 'FEN',
            'note' => 'Anchoa en salazón. Muy alto en sodio (~5g de sal por 100g).',
        ],
        'jurel' => [
            'calories' => 125,
            'protein' => 17.5,
            'fat' => 5.5,
            'omega3' => 1.40,
            'calcium_mg' => 25,
            'iron_mg' => 0.40,
            'phosphorus_mg' => 220,
            'vitamins' => [
                'B12_ug' => 4.00,
                'D_ug' => 9.00,
                'A_RAE_ug' => 19,
            ],
            'source' => 'FEN',
            'note' => 'Nombre común en el sur y Mediterráneo. "Chicharro" en el norte.',
        ],
        'pez espada' => [
            'calories' => 121,
            'protein' => 20.0,
            'fat' => 3.5,
            'omega3' => 0.80,
            'contaminants' => [
                'methylmercury_mg_per_kg' => 0.70,
            ],
            'source' => 'FEN',
            'note' => 'Mercurio alto. Limitar consumo en embarazadas y niños.',
        ],

        // ========== ALIASES REGIONALES ==========
        'chicharro' => [
            'alias_of' => 'jurel',
        ],

        // ========== CEFALÓPODOS ==========
        'sepia' => [
            'calories' => 79,
            'protein' => 16.0,
            'fat' => 1.0,
            'source' => 'FEN',
            'note' => '"Choco" en Andalucía/Galicia/Asturias. Misma especie (Sepia officinalis).',
        ],
        'calamar' => [
            'calories' => 75,
            'protein' => 15.5,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'pulpo' => [
            'calories' => 82,
            'protein' => 16.0,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'pota' => [
            'calories' => 70,
            'protein' => 14.0,
            'fat' => 0.8,
            'omega3' => 0.10,
            'sodium_mg' => 350,
            'calcium_mg' => 20,
            'iron_mg' => 1.50,
            'phosphorus_mg' => 180,
            'source' => 'FEN',
            'note' => 'Totanus pacificus (potón). Más consumida que el calamar en muchas zonas costeras.',
        ],

        // ========== CRUSTÁCEOS ==========
        'gamba' => [
            'calories' => 99,
            'protein' => 22.0,
            'fat' => 0.5,
            'source' => 'FEN',
        ],
        'langostino' => [
            'calories' => 105,
            'protein' => 23.0,
            'fat' => 0.5,
            'source' => 'FEN',
        ],
        'cigala' => [
            'calories' => 90,
            'protein' => 20.0,
            'fat' => 0.5,
            'source' => 'FEN',
        ],
        'bogavante' => [
            'calories' => 90,
            'protein' => 19.0,
            'fat' => 0.8,
            'source' => 'FEN',
        ],
        'langosta' => [
            'calories' => 95,
            'protein' => 20.0,
            'fat' => 0.7,
            'source' => 'FEN',
        ],
        'buey de mar' => [
            'calories' => 95,
            'protein' => 19.0,
            'fat' => 1.5,
            'source' => 'FEN',
        ],
        'centollo' => [
            'calories' => 92,
            'protein' => 19.5,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'nécora' => [
            'calories' => 90,
            'protein' => 19.5,
            'fat' => 1.0,
            'source' => 'FEN',
        ],

        // ========== MOLUSCOS ==========
        'mejillón' => [
            'calories' => 71,
            'protein' => 12.0,
            'fat' => 1.5,
            'calcium_mg' => 80,
            'iron_mg' => 3.50,
            'phosphorus_mg' => 200,
            'vitamins' => [
                'B12_ug' => 9.00,
                'A_RAE_ug' => 60,
            ],
            'source' => 'FEN',
        ],
        'almeja' => [
            'calories' => 65,
            'protein' => 11.0,
            'fat' => 1.0,
            'calcium_mg' => 60,
            'iron_mg' => 4.00,
            'source' => 'FEN',
        ],
        'vieira' => [
            'calories' => 90,
            'protein' => 17.0,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'ostra' => [
            'calories' => 70,
            'protein' => 6.0,
            'fat' => 1.5,
            'calcium_mg' => 90,
            'iron_mg' => 6.00,
            'source' => 'FEN',
        ],
        'berberecho' => [
            'calories' => 72,
            'protein' => 13.5,
            'fat' => 1.0,
            'iron_mg' => 5.00,
            'source' => 'FEN',
        ],

        // ========== AGUA DULCE ==========
        'trucha común' => [
            'calories' => 138,
            'protein' => 19.5,
            'fat' => 5.5,
            'source' => 'FEN',
        ],
        'carpa' => [
            'calories' => 110,
            'protein' => 17.5,
            'fat' => 4.5,
            'source' => 'FEN',
        ],
        'perca' => [
            'calories' => 96,
            'protein' => 19.0,
            'fat' => 1.0,
            'source' => 'FEN',
        ],
        'anguila' => [
            'calories' => 180,
            'protein' => 18.0,
            'fat' => 11.5,
            'omega3' => 2.50,
            'calcium_mg' => 20,
            'iron_mg' => 0.50,
            'phosphorus_mg' => 200,
            'vitamins' => [
                'B12_ug' => 2.00,
                'D_ug' => 25.00,
                'A_RAE_ug' => 90,
            ],
            'source' => 'FEN',
            'note' => 'Alto en grasa y vitamina D. Clásica en la cocina del norte de España.',
        ],
    ];

    public static function for(string $spanishCommonName): ?array
    {
        $key = trim(mb_strtolower($spanishCommonName));

        if (! isset(self::DATA[$key])) {
            return null;
        }

        $entry = self::DATA[$key];

        if (isset($entry['alias_of'])) {
            $canonical = $entry['alias_of'];
            if (! isset(self::DATA[$canonical])) {
                return null;
            }
            $entry = self::DATA[$canonical];
        }

        return [
            'calories' => $entry['calories'] ?? null,
            'protein' => $entry['protein'] ?? null,
            'fat' => $entry['fat'] ?? null,
            'omega3' => $entry['omega3'] ?? null,
            'vitamins' => $entry['vitamins'] ?? [],
            'minerals' => array_filter([
                'calcium_mg' => $entry['calcium_mg'] ?? null,
                'iron_mg' => $entry['iron_mg'] ?? null,
                'phosphorus_mg' => $entry['phosphorus_mg'] ?? null,
                'sodium_mg' => $entry['sodium_mg'] ?? null,
            ], fn ($v) => $v !== null),
            'contaminants' => $entry['contaminants'] ?? [],
            'note' => $entry['note'] ?? null,
            'source' => $entry['source'] ?? 'curated',
        ];
    }

    public static function has(string $spanishCommonName): bool
    {
        $key = trim(mb_strtolower($spanishCommonName));

        if (! isset(self::DATA[$key])) {
            return false;
        }

        $entry = self::DATA[$key];

        if (isset($entry['alias_of'])) {
            return isset(self::DATA[$entry['alias_of']]);
        }

        return true;
    }
}
