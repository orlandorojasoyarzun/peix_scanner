<?php

declare(strict_types=1);

namespace App\Domain\Ai\Adapters;

use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use App\Domain\Ai\ParsesVisionResponse;
use Illuminate\Support\Facades\Http;

class OpenRouterVisionAdapter implements SpeciesIdentifier
{
    use ParsesVisionResponse;

    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

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

    public const LABEL_PROMPT = <<<'PROMPT'
You are a fish species expert at a Mediterranean/Atlantic seafood counter. Read the product label in the image and extract all visible text.

Return ONLY a single line with this exact format:
TEXT: <all text found on the label in Spanish>
SPECIES: <the fish or seafood species name written on the label>
CONFIDENCE: <how confident you are that the species name is correct, between 0 and 1>

Only write in the SPECIES field the exact species name you can read. Do not invent or suggest species not written on the label.

Examples:
TEXT: FILETE DE SALMON ATLANTICO Salmo salar
SPECIES: Salmon atlantico
CONFIDENCE: 0.95

TEXT: MERLUZA EUROPEA Merluccius merluccius
SPECIES: Merluza europea
CONFIDENCE: 0.90

TEXT: PESCADO VARIADO
SPECIES: desconocido
CONFIDENCE: 0.00
PROMPT;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function identify(string $imagePath): IdentificationResult
    {
        if (! is_file($imagePath)) {
            throw IdentificationFailedException::fromProvider('openrouter', "image not found at {$imagePath}");
        }

        $imagePath = $this->resizeIfNeeded($imagePath);
        $imageBase64 = base64_encode((string) file_get_contents($imagePath));
        $mimeType = $this->detectMimeType($imagePath);
        $dataUri = "data:{$mimeType};base64,{$imageBase64}";

        $payload = [
            'model' => $this->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => self::PROMPT,
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => $dataUri],
                    ],
                ],
            ]],
        ];

        try {
            $response = Http::timeout(180)->connectTimeout(15)->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer' => 'https://peix-scanner.local',
                'X-Title' => 'Peix Scanner',
            ])->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            throw IdentificationFailedException::fromProvider('openrouter', $e->getMessage());
        }

        if ($response->status() === 429) {
            throw IdentificationFailedException::fromProvider('openrouter', 'rate limit hit (429); retry later');
        }

        if (! $response->successful()) {
            throw IdentificationFailedException::fromProvider('openrouter', "HTTP {$response->status()}: {$response->body()}");
        }

        $body = (string) $response->json('choices.0.message.content', '');

        if ($body === '') {
            throw IdentificationFailedException::fromProvider('openrouter', 'empty response body');
        }

        $parsed = $this->parseResponse($body);

        if ($parsed === null) {
            throw IdentificationFailedException::fromProvider('openrouter', "could not parse response: {$body}");
        }

        return $parsed;
    }

    public function identifyFromLabel(string $imagePath): ?string
    {
        if (! is_file($imagePath)) {
            throw IdentificationFailedException::fromProvider('openrouter', "image not found at {$imagePath}");
        }

        $imagePath = $this->resizeIfNeeded($imagePath);
        $imageBase64 = base64_encode((string) file_get_contents($imagePath));
        $mimeType = $this->detectMimeType($imagePath);
        $dataUri = "data:{$mimeType};base64,{$imageBase64}";

        $payload = [
            'model' => $this->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => self::LABEL_PROMPT,
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => $dataUri],
                    ],
                ],
            ]],
        ];

        try {
            $response = Http::timeout(180)->connectTimeout(15)->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer' => 'https://peix-scanner.local',
                'X-Title' => 'Peix Scanner',
            ])->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            throw IdentificationFailedException::fromProvider('openrouter', $e->getMessage());
        }

        if ($response->status() === 429) {
            throw IdentificationFailedException::fromProvider('openrouter', 'rate limit hit (429); retry later');
        }

        if (! $response->successful()) {
            throw IdentificationFailedException::fromProvider('openrouter', "HTTP {$response->status()}: {$response->body()}");
        }

        $body = (string) $response->json('choices.0.message.content', '');

        if ($body === '') {
            throw IdentificationFailedException::fromProvider('openrouter', 'empty response body');
        }

        return $this->parseLabelResponse($body);
    }

    private function parseLabelResponse(string $body): ?string
    {
        $lines = preg_split('/\R/', trim($body)) ?: [$body];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^SPECIES:\s*(.+)$/iu', $line, $m)) {
                $species = trim($m[1]);
                if ($species !== '' && strtolower($species) !== 'desconocido') {
                    return $species;
                }
            }
        }

        return null;
    }

    private function resizeIfNeeded(string $imagePath): string
    {
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return $imagePath;
        }

        [$width, $height] = $imageInfo;

        if ($width <= 512 && $height <= 512) {
            return $imagePath;
        }

        $max = 512;
        $ratio = min($max / $width, $max / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        $mime = $this->detectMimeType($imagePath);

        if (str_contains($mime, 'png')) {
            $source = @imagecreatefrompng($imagePath);
        } elseif (str_contains($mime, 'webp') && function_exists('imagecreatefromwebp')) {
            $source = @imagecreatefromwebp($imagePath);
        } else {
            $source = @imagecreatefromjpeg($imagePath);
        }

        if ($source === false) {
            return $imagePath;
        }

        $dest = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $resizedPath = tempnam(sys_get_temp_dir(), 'peix_');
        if ($resizedPath === false) {
            imagedestroy($source);
            imagedestroy($dest);

            return $imagePath;
        }

        if (str_contains($mime, 'png')) {
            imagepng($dest, $resizedPath);
        } else {
            imagejpeg($dest, $resizedPath, 85);
        }

        imagedestroy($source);
        imagedestroy($dest);

        return $resizedPath;
    }

    private function detectMimeType(string $path): string
    {
        $mime = @mime_content_type($path);

        return is_string($mime) && $mime !== '' ? $mime : 'image/jpeg';
    }
}
