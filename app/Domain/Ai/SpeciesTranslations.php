<?php

declare(strict_types=1);

namespace App\Domain\Ai;

class SpeciesTranslations
{
    /**
     * Mapeo de nombre común en inglés -> nombre común en castellano.
     * Cubre todas las especies de la lista del prompt de OllamaVisionAdapter.
     * Permite que el controller siempre tenga un nombre en castellano,
     * aunque el modelo no devuelva el formato ES: estructurado.
     */
    private const ENGLISH_TO_SPANISH = [
        // White fish
        'european hake' => 'Merluza europea',
        'senegalese hake' => 'Merluza negra',
        'atlantic cod' => 'Bacalao',
        'blue whiting' => 'Bacaladilla',
        'saithe' => 'Carbonero',
        'pollock' => 'Abadejo',
        'monkfish' => 'Rape',
        'white monkfish' => 'Rape blanco',
        'black angler' => 'Rape negro',
        'turbot' => 'Rodaballo',
        'common sole' => 'Lenguado',
        'european plaice' => 'Platija',
        'conger eel' => 'Congrio',
        'red scorpionfish' => 'Cabracho',
        'red mullet' => 'Salmonete',
        'blackbelly rosefish' => 'Gallineta',

        // Blue fish
        'atlantic salmon' => 'Salmón atlántico',
        'rainbow trout' => 'Trucha arcoíris',
        'bluefin tuna' => 'Atún rojo',
        'yellowfin tuna' => 'Atún claro',
        'atlantic bonito' => 'Bonito del norte',
        'mackerel' => 'Caballa',
        'european pilchard' => 'Sardina',
        'anchovy' => 'Boquerón',
        'atlantic horse mackerel' => 'Jurel',
        'bullet tuna' => 'Melva',
        'swordfish' => 'Pez espada',

        // Mediterranean farmed
        'european seabass' => 'Lubina',
        'gilthead seabream' => 'Dorada',
        'blackspot seabream' => 'Besugo',
        'common seabream' => 'Pargo',
        'meagre' => 'Corvina',
        'common dentex' => 'Dentón',

        // Common imported
        'striped catfish' => 'Panga',
        'nile tilapia' => 'Tilapia',
        'atlantic halibut' => 'Fletán',

        // Cephalopods
        'common cuttlefish' => 'Sepia',
        'broadclub cuttlefish' => 'Choco',
        'european squid' => 'Calamar',
        'common octopus' => 'Pulpo',
        'horned octopus' => 'Pulpito',

        // Crustaceans
        'red shrimp' => 'Gamba',
        'tiger prawn' => 'Langostino',
        'norway lobster' => 'Cigala',
        'european lobster' => 'Bogavante',
        'spiny lobster' => 'Langosta',
        'edible crab' => 'Buey de mar',
        'spider crab' => 'Centollo',
        'velvet crab' => 'Nécora',

        // Shellfish
        'blue mussel' => 'Mejillón',
        'common clam' => 'Almeja',
        'great scallop' => 'Vieira',
        'european flat oyster' => 'Ostra',
        'common cockle' => 'Berberecho',
        'razor clam' => 'Navaja',

        // Freshwater
        'brown trout' => 'Trucha común',
        'common carp' => 'Carpa',
        'european perch' => 'Perca',
    ];

    /**
     * Devuelve el nombre común en castellano a partir del nombre en inglés.
     * Si no encuentra traducción exacta, devuelve null.
     */
    public static function toSpanish(?string $englishName): ?string
    {
        if ($englishName === null || $englishName === '') {
            return null;
        }

        $key = strtolower(trim($englishName));

        if (isset(self::ENGLISH_TO_SPANISH[$key])) {
            return self::ENGLISH_TO_SPANISH[$key];
        }

        // Intento por coincidencia parcial: si el nombre en inglés contiene
        // alguna clave conocida, devolver el castellano
        foreach (self::ENGLISH_TO_SPANISH as $english => $spanish) {
            if (str_contains($key, $english) || str_contains($english, $key)) {
                return $spanish;
            }
        }

        return null;
    }
}
