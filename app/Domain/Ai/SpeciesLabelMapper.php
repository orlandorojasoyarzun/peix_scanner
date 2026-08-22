<?php

declare(strict_types=1);

namespace App\Domain\Ai;

class SpeciesLabelMapper
{
    private const MAP = [
        // WHITE FISH
        'merluza' => 'Merluccius merluccius',
        'merluza europea' => 'Merluccius merluccius',
        'european hake' => 'Merluccius merluccius',
        'merluza negra' => 'Merluccius senegalensis',
        'senegalese hake' => 'Merluccius senegalensis',
        'bacalao' => 'Gadus morhua',
        'atlantic cod' => 'Gadus morhua',
        'bacaladilla' => 'Micromesistius poutassou',
        'blue whiting' => 'Micromesistius poutassou',
        'carbonero' => 'Pollachius virens',
        'saithe' => 'Pollachius virens',
        'abadejo' => 'Pollachius pollachius',
        'pollock' => 'Pollachius pollachius',
        'rape' => 'Lophius piscatorius',
        'rape blanco' => 'Lophius piscatorius',
        'white monkfish' => 'Lophius piscatorius',
        'monkfish' => 'Lophius piscatorius',
        'rape negro' => 'Lophius budegassa',
        'black angler' => 'Lophius budegassa',
        'rodaballo' => 'Scophthalmus maximus',
        'turbot' => 'Scophthalmus maximus',
        'lenguado' => 'Solea solea',
        'common sole' => 'Solea solea',
        'sole' => 'Solea solea',
        'platija' => 'Pleuronectes platessa',
        'european plaice' => 'Pleuronectes platessa',
        'plaice' => 'Pleuronectes platessa',
        'congrio' => 'Conger conger',
        'conger eel' => 'Conger conger',
        'cabracho' => 'Scorpaena scrofa',
        'red scorpionfish' => 'Scorpaena scrofa',
        'scorpionfish' => 'Scorpaena scrofa',
        'salmonete' => 'Mullus sp.',
        'red mullet' => 'Mullus sp.',
        'mullet' => 'Mullus sp.',
        'gallineta' => 'Helicolenus dactylopterus',
        'blackbelly rosefish' => 'Helicolenus dactylopterus',
        'rosefish' => 'Helicolenus dactylopterus',
        'pixota' => 'Merluccius polli',

        // BLUE FISH
        'salmon' => 'Salmo salar',
        'salmon atlantico' => 'Salmo salar',
        'salmon del atlantico' => 'Salmo salar',
        'atlantic salmon' => 'Salmo salar',
        'salmon rojo' => 'Salmo salar',
        'trucha' => 'Oncorhynchus mykiss',
        'trucha arcoiris' => 'Oncorhynchus mykiss',
        'rainbow trout' => 'Oncorhynchus mykiss',
        'trout' => 'Oncorhynchus mykiss',
        'atun' => 'Thunnus thynnus',
        'atún' => 'Thunnus thynnus',
        'atun rojo' => 'Thunnus thynnus',
        'atún rojo' => 'Thunnus thynnus',
        'bluefin tuna' => 'Thunnus thynnus',
        'tuna' => 'Thunnus thynnus',
        'atun claro' => 'Thunnus albacares',
        'atún claro' => 'Thunnus albacares',
        'yellowfin tuna' => 'Thunnus albacares',
        'bonito' => 'Sarda sarda',
        'bonito del norte' => 'Sarda sarda',
        'atlantic bonito' => 'Sarda sarda',
        'caballa' => 'Scomber scombrus',
        'mackerel' => 'Scomber scombrus',
        'sardina' => 'Sardina pilchardus',
        'european pilchard' => 'Sardina pilchardus',
        'pilchard' => 'Sardina pilchardus',
        'boqueron' => 'Engraulis encrasicolus',
        'boquerón' => 'Engraulis encrasicolus',
        'anchovy' => 'Engraulis encrasicolus',
        'anchoveta' => 'Engraulis encrasicolus',
        'jurel' => 'Trachurus trachurus',
        'atlantic horse mackerel' => 'Trachurus trachurus',
        'horse mackerel' => 'Trachurus trachurus',
        'mackerel' => 'Trachurus trachurus',
        'melva' => 'Auxis rochei',
        'bullet tuna' => 'Auxis rochei',
        'pez espada' => 'Xiphias gladius',
        'swordfish' => 'Xiphias gladius',

        // MEDITERRANEAN FARMED
        'lubina' => 'Dicentrarchus labrax',
        'european seabass' => 'Dicentrarchus labrax',
        'seabass' => 'Dicentrarchus labrax',
        'bass' => 'Dicentrarchus labrax',
        'dorada' => 'Sparus aurata',
        'gilthead seabream' => 'Sparus aurata',
        'seabream' => 'Sparus aurata',
        'bream' => 'Sparus aurata',
        'besugo' => 'Pagellus bogaraveo',
        'blackspot seabream' => 'Pagellus bogaraveo',
        'pargo' => 'Pagrus pagrus',
        'common seabream' => 'Pagrus pagrus',
        'corvina' => 'Argyrosomus regius',
        'meagre' => 'Argyrosomus regius',
        'denton' => 'Dentex dentex',
        'dentón' => 'Dentex dentex',
        'common dentex' => 'Dentex dentex',
        'dentex' => 'Dentex dentex',

        // COMMON IMPORTED
        'panga' => 'Pangasius hypophthalmus',
        'striped catfish' => 'Pangasius hypophthalmus',
        'catfish' => 'Pangasius hypophthalmus',
        'tilapia' => 'Oreochromis niloticus',
        'nile tilapia' => 'Oreochromis niloticus',
        'fletan' => 'Hippoglossus hippoglossus',
        'fletán' => 'Hippoglossus hippoglossus',
        'atlantic halibut' => 'Hippoglossus hippoglossus',
        'halibut' => 'Hippoglossus hippoglossus',

        // CEPHALOPODS
        'sepia' => 'Sepia officinalis',
        'common cuttlefish' => 'Sepia officinalis',
        'cuttlefish' => 'Sepia officinalis',
        'choco' => 'Sepia elegans',
        'broadclub cuttlefish' => 'Sepia elegans',
        'calamar' => 'Loligo vulgaris',
        'european squid' => 'Loligo vulgaris',
        'squid' => 'Loligo vulgaris',
        'pulpo' => 'Octopus vulgaris',
        'common octopus' => 'Octopus vulgaris',
        'octopus' => 'Octopus vulgaris',
        'pulpito' => 'Eledone cirrhosa',
        'horned octopus' => 'Eledone cirrhosa',

        // CRUSTACEANS
        'gamba' => 'Aristeus antennatus',
        'red shrimp' => 'Aristeus antennatus',
        'shrimp' => 'Aristeus antennatus',
        'langostino' => 'Penaeus monodon',
        'tiger prawn' => 'Penaeus monodon',
        'prawn' => 'Penaeus monodon',
        'cigala' => 'Nephrops norvegicus',
        'norway lobster' => 'Nephrops norvegicus',
        'lobster' => 'Nephrops norvegicus',
        'bogavante' => 'Homarus gammarus',
        'european lobster' => 'Homarus gammarus',
        'langosta' => 'Palinurus sp.',
        'spiny lobster' => 'Palinurus sp.',
        'buey de mar' => 'Cancer pagurus',
        'edible crab' => 'Cancer pagurus',
        'crab' => 'Cancer pagurus',
        'centollo' => 'Maja squinado',
        'spider crab' => 'Maja squinado',
        'necora' => 'Necora puber',
        'nécora' => 'Necora puber',
        'velvet crab' => 'Necora puber',

        // SHELLFISH
        'meillon' => 'Mytilus edulis',
        'mejillon' => 'Mytilus edulis',
        'mejillón' => 'Mytilus edulis',
        'blue mussel' => 'Mytilus edulis',
        'mussel' => 'Mytilus edulis',
        'almeja' => 'Ruditapes decussatus',
        'common clam' => 'Ruditapes decussatus',
        'clam' => 'Ruditapes decussatus',
        'vieira' => 'Pecten maximus',
        'great scallop' => 'Pecten maximus',
        'scallop' => 'Pecten maximus',
        'ostra' => 'Ostrea edulis',
        'european flat oyster' => 'Ostrea edulis',
        'oyster' => 'Ostrea edulis',
        'berberecho' => 'Cerastoderma edule',
        'common cockle' => 'Cerastoderma edule',
        'cockle' => 'Cerastoderma edule',
        'navaja' => 'Ensis siliqua',
        'razor clam' => 'Ensis siliqua',

        // FRESHWATER
        'trucha comun' => 'Salmo trutta',
        'trucha común' => 'Salmo trutta',
        'brown trout' => 'Salmo trutta',
        'carpa' => 'Cyprinus carpio',
        'common carp' => 'Cyprinus carpio',
        'carp' => 'Cyprinus carpio',
        'perca' => 'Perca fluviatilis',
        'european perch' => 'Perca fluviatilis',
        'perch' => 'Perca fluviatilis',
    ];

    public function map(string $text): ?string
    {
        $normalized = $this->normalize($text);

        if (isset(self::MAP[$normalized])) {
            return self::MAP[$normalized];
        }

        $words = preg_split('/\s+/', $normalized) ?: [];

        foreach (self::MAP as $key => $scientificName) {
            if (str_contains($normalized, $key) || str_contains($key, $normalized)) {
                return $scientificName;
            }
        }

        foreach (self::MAP as $key => $scientificName) {
            $keyWords = preg_split('/\s+/', $key) ?: [];
            if (count($keyWords) === 1 && strlen($keyWords[0]) >= 4) {
                foreach ($words as $word) {
                    if (strlen($word) >= 4 && (str_contains($word, $keyWords[0]) || str_contains($keyWords[0], $word))) {
                        return $scientificName;
                    }
                }
            }
        }

        return null;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (function_exists('iconv')) {
            // Suppress the warning iconv emits on unsupported charsets via
            // a temporary error handler rather than the `@` operator, which
            // hides fatal errors and is flagged by static analysis.
            set_error_handler(static fn (): bool => true);

            try {
                $stripped = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            } finally {
                restore_error_handler();
            }

            if (is_string($stripped) && $stripped !== '') {
                $text = preg_replace('/[^a-z0-9\s]/', '', strtolower($stripped)) ?? $text;
                $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            }
        }

        return trim($text);
    }
}
