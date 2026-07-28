<?php

declare(strict_types=1);

namespace App\Domain\Nutrition;

class NutritionAdvisor
{
    public const TONE_POSITIVE = 'positive';

    public const TONE_CAUTION = 'caution';

    public const TONE_INFO = 'info';

    public static function recommend(array $nutrition, UserGoal $goal): array
    {
        $base = self::universalRecommendations($nutrition);
        $goalSpecific = self::goalSpecificRecommendations($nutrition, $goal);

        return array_merge($base, $goalSpecific);
    }

    /**
     * Recomendaciones completas: reglas universales + todas las reglas por objetivo.
     * Útil para pestañas que muestran TODA la información del FEN sin filtros.
     * Deduplica por título (algunas reglas pueden aplicar a varios objetivos).
     */
    public static function recommendAll(array $nutrition): array
    {
        $recommendations = self::universalRecommendations($nutrition);

        foreach ([UserGoal::Athlete, UserGoal::WeightLoss, UserGoal::Pregnancy, UserGoal::Sustainability] as $goal) {
            $goalRecs = self::goalSpecificRecommendations($nutrition, $goal);
            foreach ($goalRecs as $rec) {
                $duplicated = false;
                foreach ($recommendations as $existing) {
                    if ($existing['title'] === $rec['title']) {
                        $duplicated = true;
                        break;
                    }
                }
                if (! $duplicated) {
                    $recommendations[] = $rec;
                }
            }
        }

        return $recommendations;
    }

    public static function universalRecommendations(array $n): array
    {
        $r = [];
        $protein = (float) ($n['protein'] ?? 0);
        $fat = (float) ($n['fat'] ?? 0);
        $omega3 = (float) ($n['omega3'] ?? 0);
        $calories = (float) ($n['calories'] ?? 0);
        $vitamins = $n['vitamins'] ?? [];

        if ($protein >= 19) {
            $r[] = self::rec('💪', self::TONE_POSITIVE,
                'Alto en proteínas',
                'Más de 19 g por cada 100 g. Ideal para deportistas, recuperación muscular o personas con alta demanda física.');
        } elseif ($protein >= 16) {
            $r[] = self::rec('🍗', self::TONE_POSITIVE,
                'Buen aporte proteico',
                "{$protein} g de proteínas por 100 g. Aporte interesante para una dieta equilibrada.");
        }

        if ($fat <= 1) {
            $r[] = self::rec('🥗', self::TONE_POSITIVE,
                'Pescado muy magro',
                'Menos de 1 g de grasa por 100 g. Perfecto para personas con colesterol alto o en dieta baja en grasas.');
        } elseif ($fat < 3) {
            $r[] = self::rec('🥗', self::TONE_POSITIVE,
                'Pescado magro',
                'Bajo en grasas (menos de 3 g por 100 g). Adecuado para perder peso y mantener el corazón sano.');
        } elseif ($fat > 10) {
            $r[] = self::rec('🧈', self::TONE_CAUTION,
                'Pescado graso',
                "Más de 10 g de grasa por 100 g. Aporta más calorías ({$calories} kcal). Mejor con moderación (2-3 veces por semana).");
        }

        if ($omega3 >= 2) {
            $r[] = self::rec('🧠', self::TONE_POSITIVE,
                'Excelente fuente de Omega-3',
                "{$omega3} g de Omega-3 por 100 g. Beneficios cardiovasculares y cognitivos. Recomendado 2 veces/semana.");
        } elseif ($omega3 >= 1) {
            $r[] = self::rec('🐟', self::TONE_POSITIVE,
                'Buen aporte de Omega-3',
                'Aporte moderado de ácidos grasos esenciales. Combinar con otros pescados azules en la semana.');
        }

        if (! empty($vitamins['D_ug']) && (float) $vitamins['D_ug'] >= 5) {
            $r[] = self::rec('☀️', self::TONE_POSITIVE,
                'Rico en vitamina D',
                'Especialmente útil en meses de menor exposición solar (otoño-invierno).');
        }

        if (! empty($vitamins['B12_ug']) && (float) $vitamins['B12_ug'] >= 4) {
            $r[] = self::rec('🩸', self::TONE_POSITIVE,
                'Rico en vitamina B12',
                'Contribuye a la salud del sistema nervioso y previene la anemia.');
        }

        $mercury = (float) ($n['contaminants']['methylmercury_mg_per_kg'] ?? 0);
        if ($mercury > 0) {
            if ($mercury >= 0.5) {
                $r[] = self::rec('⚠️', self::TONE_CAUTION,
                    'Mercurio alto',
                    "{$mercury} mg/kg. Limitar consumo en embarazadas, niños y mujeres lactantes. Recomendado 1 vez/semana o menos.");
            } elseif ($mercury >= 0.3) {
                $r[] = self::rec('⚠️', self::TONE_CAUTION,
                    'Mercurio moderado',
                    "{$mercury} mg/kg. Moderar consumo: 2 veces/semana como máximo.");
            }
        }

        return $r;
    }

    private static function goalSpecificRecommendations(array $n, UserGoal $goal): array
    {
        return match ($goal) {
            UserGoal::Athlete => self::athleteRules($n),
            UserGoal::WeightLoss => self::weightLossRules($n),
            UserGoal::Pregnancy => self::pregnancyRules($n),
            UserGoal::Sustainability => self::sustainabilityRules($n),
            UserGoal::None => [],
        };
    }

    private static function athleteRules(array $n): array
    {
        $r = [];
        $protein = (float) ($n['protein'] ?? 0);
        $iron = (float) ($n['minerals']['iron_mg'] ?? 0);

        if ($protein >= 18) {
            $r[] = self::rec('🏋️', self::TONE_POSITIVE,
                'Apto para deportistas',
                'Buen perfil para una dieta de entrenamiento o hipertrofia. Recomendado post-entreno o como comida principal.');
        } elseif ($protein < 14) {
            $r[] = self::rec('🥛', self::TONE_INFO,
                'Pescado bajo en proteínas para deportistas',
                'Recomendado combinar con huevos, legumbres o un suplemento proteico.');
        }

        if ($iron >= 1) {
            $r[] = self::rec('💪', self::TONE_POSITIVE,
                'Aporta hierro',
                "{$iron} mg/100 g. Importante para resistencia y recuperación muscular.");
        }

        return $r;
    }

    private static function weightLossRules(array $n): array
    {
        $r = [];
        $calories = (float) ($n['calories'] ?? 0);
        $fat = (float) ($n['fat'] ?? 0);
        $protein = (float) ($n['protein'] ?? 0);

        if ($calories <= 0) {
            return [];
        }

        if ($calories <= 100) {
            $r[] = self::rec('✅', self::TONE_POSITIVE,
                'Bajo en calorías',
                "Solo {$calories} kcal por ración de 100 g. Excelente para una cena ligera.");
        } elseif ($calories > 200) {
            $r[] = self::rec('🛑', self::TONE_CAUTION,
                'Calorías altas',
                "{$calories} kcal por ración. Cuidado con las cantidades si buscas perder peso.");
        }

        if ($fat > 5) {
            $r[] = self::rec('⚠️', self::TONE_INFO,
                'Aporte graso considerable',
                "{$fat} g de grasa por 100 g. Combinar con verduras para saciedad, no como plato único.");
        }

        if ($protein >= 15 && $fat < 3) {
            $r[] = self::rec('⭐', self::TONE_POSITIVE,
                'Perfecto para perder peso',
                'Alta proteína con muy poca grasa. Te mantiene saciado sin sumar calorías.');
        }

        return $r;
    }

    private static function pregnancyRules(array $n): array
    {
        $r = [];
        $mercury = (float) ($n['contaminants']['methylmercury_mg_per_kg'] ?? 0);
        $omega3 = (float) ($n['omega3'] ?? 0);

        if ($mercury >= 0.5) {
            $r[] = self::rec('🚫', self::TONE_CAUTION,
                'Evitar durante embarazo',
                'Mercurio alto. Recomendado sustituirlo por pescado blanco magro hasta finalizar la lactancia.');
        } elseif ($mercury >= 0.3) {
            $r[] = self::rec('⚠️', self::TONE_CAUTION,
                'Consumo moderado en embarazo',
                "Mercurio ({$mercury} mg/kg). Limitar a una vez cada dos semanas como máximo.");
        } else {
            $r[] = self::rec('✅', self::TONE_POSITIVE,
                'Apto para embarazo',
                'Bajo en mercurio. Apto para embarazadas y lactantes.');
        }

        if ($omega3 >= 2) {
            $r[] = self::rec('🤱', self::TONE_POSITIVE,
                'Beneficioso para el desarrollo fetal',
                'Aporte importante de DHA (Omega-3), esencial para el desarrollo neurológico del bebé.');
        }

        return $r;
    }

    private static function sustainabilityRules(array $n): array
    {
        $common = '';
        $species = '';

        return [
            self::rec('🌍', self::TONE_INFO,
                'Sostenibilidad no determinada',
                'Aún no tenemos datos de origen y método de captura. Por ahora te sugerimos buscar pescado con certificación MSC o sello azul.'),
            self::rec('🌿', self::TONE_INFO,
                'Consejo general',
                'Alternativas más sostenibles: sardina, caballa, jurel, boquerón. Son especies abundantes y de pesca local.'),
        ];
    }

    private static function rec(string $icon, string $tone, string $title, string $body): array
    {
        return [
            'icon' => $icon,
            'tone' => $tone,
            'title' => $title,
            'body' => $body,
        ];
    }
}
