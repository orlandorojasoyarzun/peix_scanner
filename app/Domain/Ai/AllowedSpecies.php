<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * Closed allowlist of fish/seafood species the application recognises.
 *
 * This list is the single source of truth for what can appear on a species
 * page, get cached in `species.{slug}.result`, or be passed to the
 * nutrition lookup. Adding a species here is the *only* way to make the
 * app recognise it — even if the AI model returns it confidently.
 *
 * The list mirrors the PROMPT in `OpenRouterVisionAdapter`. If you change
 * one, change the other.
 *
 * The shape of each entry is:
 *   'scientific_name' => ['common_en' => ..., 'common_es' => ...]
 *
 * Keys are lowercase scientific names (the canonical lookup form).
 * Values carry the canonical English and Spanish common names so we can
 * always render a clean label even if the AI invents a variant.
 */
final class AllowedSpecies
{
    private const LIST = [
        // ─────────── WHITE FISH ───────────
        'merluccius merluccius'     => ['en' => 'European hake',         'es' => 'Merluza europea'],
        'merluccius senegalensis'   => ['en' => 'Senegalese hake',       'es' => 'Merluza negra'],
        'gadus morhua'              => ['en' => 'Atlantic cod',          'es' => 'Bacalao'],
        'micromesistius poutassou'  => ['en' => 'Blue whiting',          'es' => 'Bacaladilla'],
        'pollachius virens'         => ['en' => 'Saithe',                'es' => 'Carbonero'],
        'pollachius pollachius'     => ['en' => 'Pollock',               'es' => 'Abadejo'],
        'lophius piscatorius'       => ['en' => 'White monkfish',        'es' => 'Rape blanco'],
        'lophius budegassa'         => ['en' => 'Black angler',          'es' => 'Rape negro'],
        'scophthalmus maximus'      => ['en' => 'Turbot',                'es' => 'Rodaballo'],
        'solea solea'               => ['en' => 'Common sole',           'es' => 'Lenguado'],
        'pleuronectes platessa'     => ['en' => 'European plaice',       'es' => 'Platija'],
        'conger conger'             => ['en' => 'Conger eel',            'es' => 'Congrio'],
        'scorpaena scrofa'          => ['en' => 'Red scorpionfish',      'es' => 'Cabracho'],
        'mullus sp.'                => ['en' => 'Red mullet',            'es' => 'Salmonete'],
        'helicolenus dactylopterus' => ['en' => 'Blackbelly rosefish',   'es' => 'Gallineta'],
        'merluccius polli'          => ['en' => 'Benguela hake',         'es' => 'Pixota'],

        // ─────────── BLUE FISH ───────────
        'salmo salar'               => ['en' => 'Atlantic salmon',       'es' => 'Salmón atlántico'],
        'oncorhynchus mykiss'       => ['en' => 'Rainbow trout',         'es' => 'Trucha arcoíris'],
        'thunnus thynnus'           => ['en' => 'Bluefin tuna',          'es' => 'Atún rojo'],
        'thunnus albacares'         => ['en' => 'Yellowfin tuna',        'es' => 'Atún claro'],
        'sarda sarda'               => ['en' => 'Atlantic bonito',       'es' => 'Bonito del norte'],
        'scomber scombrus'          => ['en' => 'Mackerel',              'es' => 'Caballa'],
        'sardina pilchardus'        => ['en' => 'European pilchard',     'es' => 'Sardina'],
        'engraulis encrasicolus'    => ['en' => 'Anchovy',               'es' => 'Boquerón'],
        'trachurus trachurus'       => ['en' => 'Atlantic horse mackerel', 'es' => 'Jurel'],
        'auxis rochei'              => ['en' => 'Bullet tuna',           'es' => 'Melva'],
        'xiphias gladius'           => ['en' => 'Swordfish',             'es' => 'Pez espada'],

        // ─────────── MEDITERRANEAN FARMED ───────────
        'dicentrarchus labrax'      => ['en' => 'European seabass',      'es' => 'Lubina'],
        'sparus aurata'             => ['en' => 'Gilthead seabream',     'es' => 'Dorada'],
        'pagellus bogaraveo'        => ['en' => 'Blackspot seabream',    'es' => 'Besugo'],
        'pagrus pagrus'             => ['en' => 'Common seabream',       'es' => 'Pargo'],
        'argyrosomus regius'        => ['en' => 'Meagre',                'es' => 'Corvina'],
        'dentex dentex'             => ['en' => 'Common dentex',         'es' => 'Dentón'],

        // ─────────── COMMON IMPORTED ───────────
        'pangasius hypophthalmus'   => ['en' => 'Striped catfish',       'es' => 'Panga'],
        'oreochromis niloticus'     => ['en' => 'Nile tilapia',          'es' => 'Tilapia'],
        'hippoglossus hippoglossus' => ['en' => 'Atlantic halibut',      'es' => 'Fletán'],

        // ─────────── CEPHALOPODS ───────────
        'sepia officinalis'         => ['en' => 'Common cuttlefish',     'es' => 'Sepia'],
        'sepia elegans'             => ['en' => 'Broadclub cuttlefish',  'es' => 'Choco'],
        'loligo vulgaris'           => ['en' => 'European squid',        'es' => 'Calamar'],
        'octopus vulgaris'          => ['en' => 'Common octopus',        'es' => 'Pulpo'],
        'eledone cirrhosa'          => ['en' => 'Horned octopus',        'es' => 'Pulpito'],

        // ─────────── CRUSTACEANS ───────────
        'aristeus antennatus'       => ['en' => 'Red shrimp',            'es' => 'Gamba'],
        'penaeus monodon'           => ['en' => 'Tiger prawn',           'es' => 'Langostino'],
        'nephrops norvegicus'       => ['en' => 'Norway lobster',        'es' => 'Cigala'],
        'homarus gammarus'          => ['en' => 'European lobster',      'es' => 'Bogavante'],
        'palinurus sp.'             => ['en' => 'Spiny lobster',         'es' => 'Langosta'],
        'cancer pagurus'            => ['en' => 'Edible crab',           'es' => 'Buey de mar'],
        'maja squinado'             => ['en' => 'Spider crab',           'es' => 'Centollo'],
        'necora puber'              => ['en' => 'Velvet crab',           'es' => 'Nécora'],

        // ─────────── SHELLFISH ───────────
        'mytilus edulis'            => ['en' => 'Blue mussel',           'es' => 'Mejillón'],
        'ruditapes decussatus'      => ['en' => 'Common clam',           'es' => 'Almeja'],
        'pecten maximus'            => ['en' => 'Great scallop',         'es' => 'Vieira'],
        'ostrea edulis'             => ['en' => 'European flat oyster',  'es' => 'Ostra'],
        'cerastoderma edule'        => ['en' => 'Common cockle',         'es' => 'Berberecho'],
        'ensis siliqua'             => ['en' => 'Razor clam',            'es' => 'Navaja'],

        // ─────────── FRESHWATER ───────────
        'salmo trutta'              => ['en' => 'Brown trout',           'es' => 'Trucha común'],
        'cyprinus carpio'           => ['en' => 'Common carp',           'es' => 'Carpa'],
        'perca fluviatilis'         => ['en' => 'European perch',        'es' => 'Perca'],
    ];

    /**
     * Normalise a scientific name to the canonical lookup form.
     * Lowercases, trims, collapses internal whitespace.
     */
    public static function normaliseScientificName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = (string) preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    public static function isAllowed(?string $scientificName): bool
    {
        if ($scientificName === null || $scientificName === '') {
            return false;
        }

        return isset(self::LIST[self::normaliseScientificName($scientificName)]);
    }

    /**
     * @return array{en: string, es: string}|null
     */
    public static function get(?string $scientificName): ?array
    {
        if ($scientificName === null) {
            return null;
        }

        return self::LIST[self::normaliseScientificName($scientificName)] ?? null;
    }

    /**
     * Canonical English common name, or null if not on the list.
     */
    public static function commonEn(?string $scientificName): ?string
    {
        return self::get($scientificName)['en'] ?? null;
    }

    /**
     * Canonical Spanish common name, or null if not on the list.
     */
    public static function commonEs(?string $scientificName): ?string
    {
        return self::get($scientificName)['es'] ?? null;
    }

    /**
     * Sanitise a string so it can never contain HTML/control characters.
     *
     * The AI response is user-influenced (model output), and even though we
     * validate against the whitelist, defensive stripping means a future
     * caller that ever passes a non-canonical value (e.g. "Pixota (from
     * <b>Cameroon</b>)") can't push markup into a Blade template.
     */
    public static function sanitise(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Strip control characters and anything that isn't printable text,
        // punctuation or whitespace. Keeps UTF-8 letters/digits intact.
        $value = (string) preg_replace('/[^\P{C}\s]/u', '', $value);

        // Neutralise any HTML-ish angle brackets before they're rendered.
        $value = str_replace(['<', '>', '"', "'", '`'], '', $value);

        return trim($value);
    }

    /**
     * Number of species in the allowlist. Useful for diagnostics / tests.
     */
    public static function count(): int
    {
        return count(self::LIST);
    }

    /**
     * @return list<string>
     */
    public static function allScientificNames(): array
    {
        return array_keys(self::LIST);
    }
}
