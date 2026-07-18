<?php

declare(strict_types=1);

namespace App\Domain\Ai\Adapters;

use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaVisionAdapter implements SpeciesIdentifier
{
    public const PROMPT = <<<'PROMPT'
You are a fish species expert at a Mediterranean/Atlantic seafood counter. Identify the species visible in the image.

Choose ONLY from this curated list of commercially available seafood:

WHITE FISH (magros / lean):
- Merluza / European hake (Merluccius merluccius)
- Merluza negra / Senegalese hake (Merluccius senegalensis)
- Bacalao / Atlantic cod (Gadus morhua)
- Bacaladilla / Blue whiting (Micromesistius poutassou)
- Carbonero / Saithe (Pollachius virens)
- Abadejo / Pollock (Pollachius pollachius)
- Rape blanco / White monkfish (Lophius piscatorius)
- Rape negro / Black angler (Lophius budegassa)
- Rodaballo / Turbot (Scophthalmus maximus)
- Lenguado / Common sole (Solea solea)
- Platija / European plaice (Pleuronectes platessa)
- Congrio / Conger eel (Conger conger)
- Cabracho / Red scorpionfish (Scorpaena scrofa)
- Salmonete / Red mullet (Mullus sp.)
- Gallineta / Blackbelly rosefish (Helicolenus dactylopterus)
- Pixota (Merluccius polli)

BLUE FISH (azules / oily):
- Salmón / Atlantic salmon (Salmo salar)
- Trucha arcoíris / Rainbow trout (Oncorhynchus mykiss)
- Atún rojo / Bluefin tuna (Thunnus thynnus)
- Atún claro / Yellowfin tuna (Thunnus albacares)
- Bonito del norte / Atlantic bonito (Sarda sarda)
- Caballa / Mackerel (Scomber scombrus)
- Sardina / European pilchard (Sardina pilchardus)
- Boquerón / Anchovy (Engraulis encrasicolus)
- Jurel / Atlantic horse mackerel (Trachurus trachurus)
- Melva / Bullet tuna (Auxis rochei)
- Pez espada / Swordfish (Xiphias gladius)

MEDITERRANEAN FARMED (piscifactoría mediterránea):
- Lubina / European seabass (Dicentrarchus labrax)
- Dorada / Gilthead seabream (Sparus aurata)
- Besugo / Blackspot seabream (Pagellus bogaraveo)
- Pargo / Common seabream (Pagrus pagrus)
- Corvina / Meagre (Argyrosomus regius)
- Dentón / Common dentex (Dentex dentex)

COMMON IMPORTED (súper habituales):
- Panga / Striped catfish (Pangasius hypophthalmus)
- Tilapia / Nile tilapia (Oreochromis niloticus)
- Fletán / Atlantic halibut (Hippoglossus hippoglossus)

CEPHALOPODS (cefalópodos):
- Sepia / Common cuttlefish (Sepia officinalis)
- Choco / Broadclub cuttlefish (Sepia elegans)
- Calamar / European squid (Loligo vulgaris)
- Pulpo / Common octopus (Octopus vulgaris)
- Pulpito / Horned octopus (Eledone cirrhosa)

CRUSTACEANS (crustáceos):
- Gamba / Red shrimp (Aristeus antennatus)
- Langostino / Tiger prawn (Penaeus monodon)
- Cigala / Norway lobster (Nephrops norvegicus)
- Bogavante / European lobster (Homarus gammarus)
- Langosta / Spiny lobster (Palinurus sp.)
- Buey de mar / Edible crab (Cancer pagurus)
- Centollo / Spider crab (Maja squinado)
- Nécora / Velvet crab (Necora puber)

SHELLFISH (moluscos bivalvos):
- Mejillón / Blue mussel (Mytilus edulis)
- Almeja / Common clam (Ruditapes decussatus)
- Vieira / Great scallop (Pecten maximus)
- Ostra / European flat oyster (Ostrea edulis)
- Berberecho / Common cockle (Cerastoderma edule)
- Navaja / Razor clam (Ensis siliqua)

FRESHWATER (raros pero por si acaso):
- Trucha común / Brown trout (Salmo trutta)
- Carpa / Common carp (Cyprinus carpio)
- Perca / European perch (Perca fluviatilis)

Answer with the TOP 3 most likely candidates, ordered by confidence (highest first). Format each on a separate line:
Scientific_name (English name), confidence
Scientific_name (English name), confidence
Scientific_name (English name), confidence

Example:
Salmo salar (Atlantic salmon), 0.92
Oncorhynchus mykiss (Rainbow trout), 0.45
Gadus morhua (Atlantic cod), 0.20

If you cannot identify the seafood confidently from the list, answer a single line:
unknown (unknown), 0
PROMPT;

    public function __construct(
        private readonly string $host,
        private readonly string $model,
    ) {}

    public function identify(string $imagePath): IdentificationResult
    {
        if (! is_file($imagePath)) {
            throw IdentificationFailedException::fromProvider('ollama', "image not found at {$imagePath}");
        }

        $imageBase64 = base64_encode((string) file_get_contents($imagePath));

        try {
            $response = Http::timeout(180)->post(rtrim($this->host, '/').'/api/generate', [
                'model' => $this->model,
                'prompt' => self::PROMPT,
                'images' => [$imageBase64],
                'stream' => false,
                'options' => [
                    'temperature' => 0.1,
                ],
            ]);
        } catch (\Throwable $e) {
            throw IdentificationFailedException::fromProvider('ollama', $e->getMessage());
        }

        if (! $response->successful()) {
            throw IdentificationFailedException::fromProvider('ollama', "HTTP {$response->status()}: {$response->body()}");
        }

        $body = (string) $response->json('response', '');

        if ($body === '') {
            throw IdentificationFailedException::fromProvider('ollama', 'empty response body');
        }

        $parsed = $this->parseResponse($body);

        if ($parsed === null) {
            Log::warning('Ollama returned unparseable response', ['raw' => $body]);
            throw IdentificationFailedException::fromProvider('ollama', "could not parse response: {$body}");
        }

        return $parsed;
    }

    /**
     * Parses multi-line responses (top 3 candidates) like:
     *   "Salmo salar (Atlantic salmon), 0.92"
     *   "Oncorhynchus mykiss (Rainbow trout), 0.45"
     *   "Gadus morhua (Atlantic cod), 0.20"
     * The first candidate becomes the primary result, the rest go into `candidates`.
     */
    private function parseResponse(string $body): ?IdentificationResult
    {
        $body = trim($body);
        $lines = preg_split('/\R/', $body) ?: [$body];

        $parsed = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/([A-Z][a-z]+(?:\s+[a-z\-]+)?)\s*\(([^)]+)\)\s*,\s*([0-9]*\.?[0-9]+)/u', $line, $m) !== 1) {
                continue;
            }

            $scientific = strtolower(trim($m[1]));
            $common = trim($m[2]);
            $confidence = (float) $m[3];

            if ($scientific === 'unknown' || $confidence <= 0) {
                continue;
            }

            $parsed[] = [
                'scientific_name' => $scientific,
                'common_name' => $common,
                'confidence' => max(0, min(1, $confidence)),
            ];
        }

        if ($parsed === []) {
            return null;
        }

        $top = $parsed[0];

        return new IdentificationResult(
            scientificName: $top['scientific_name'],
            commonName: $top['common_name'],
            confidence: $top['confidence'],
            candidates: $parsed,
        );
    }
}
