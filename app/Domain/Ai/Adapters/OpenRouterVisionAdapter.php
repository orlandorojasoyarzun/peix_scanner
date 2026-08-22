<?php

declare(strict_types=1);

namespace App\Domain\Ai\Adapters;

use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use App\Domain\Ai\ParsesVisionResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
You are reading a product label of packaged fish/seafood. Your task is to extract the fish species name written on the label.

Look carefully at the image and find the commercial species name (usually in Spanish, e.g. "Lubina", "Salmón", "Merluza", "Bacalao", "Dorada", "Rodaballo").

Respond with EXACTLY this format, no other text before or after:

SPECIES: <name>
CONFIDENCE: <number between 0 and 1>

Examples:
SPECIES: Lubina
CONFIDENCE: 0.95

SPECIES: Salmón atlántico
CONFIDENCE: 0.90

SPECIES: Merluza europea
CONFIDENCE: 0.85

If you cannot read a species name, respond:
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
            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_IMAGE_NOT_FOUND,
            );
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
            Log::warning('OpenRouter HTTP transport error', [
                'error_preview' => mb_substr($e->getMessage(), 0, 200),
            ]);

            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_HTTP_ERROR,
                ['transport_error' => true],
            );
        }

        if ($response->status() === 429) {
            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_RATE_LIMIT,
                ['status' => 429],
            );
        }

        if (! $response->successful()) {
            Log::warning('OpenRouter HTTP error response', [
                'status' => $response->status(),
                'body_preview' => mb_substr($response->body(), 0, 500),
            ]);

            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_HTTP_ERROR,
                ['status' => $response->status()],
            );
        }

        $body = (string) $response->json('choices.0.message.content', '');

        if ($body === '') {
            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_EMPTY_BODY,
            );
        }

        $parsed = $this->parseResponse($body);

        if ($parsed === null) {
            Log::warning('OpenRouter response parse failed', [
                'body_preview' => mb_substr($body, 0, 500),
            ]);

            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_PARSE_FAILED,
            );
        }

        return $parsed;
    }

    public function identifyFromLabel(string $imagePath): ?string
    {
        if (! is_file($imagePath)) {
            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_IMAGE_NOT_FOUND,
            );
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
            Log::warning('OpenRouter HTTP transport error', [
                'error_preview' => mb_substr($e->getMessage(), 0, 200),
            ]);

            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_HTTP_ERROR,
                ['transport_error' => true],
            );
        }

        if ($response->status() === 429) {
            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_RATE_LIMIT,
                ['status' => 429],
            );
        }

        if (! $response->successful()) {
            Log::warning('OpenRouter HTTP error response', [
                'status' => $response->status(),
                'body_preview' => mb_substr($response->body(), 0, 500),
            ]);

            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_HTTP_ERROR,
                ['status' => $response->status()],
            );
        }

        $body = (string) $response->json('choices.0.message.content', '');

        if ($body === '') {
            \Illuminate\Support\Facades\Log::warning('OpenRouter label scan: empty body', [
                'status' => $response->status(),
                'body_preview' => mb_substr($response->body(), 0, 500),
            ]);
            throw IdentificationFailedException::fromProvider(
                'openrouter',
                IdentificationFailedException::REASON_EMPTY_BODY,
            );
        }

        $parsed = $this->parseLabelResponse($body);

        if ($parsed === null) {
            \Illuminate\Support\Facades\Log::warning('OpenRouter label scan: parse failed', [
                'body_preview' => mb_substr($body, 0, 500),
            ]);
        }

        return $parsed;
    }

    private function parseLabelResponse(string $body): ?string
    {
        if (! preg_match('/SPECIES:\s*([^\n\r]+)/iu', $body, $m)) {
            return null;
        }

        $species = trim($m[1]);

        if (preg_match('/CONFIDENCE:\s*([\d.]+)/iu', $species, $c)) {
            $confidence = (float) $c[1];
            if ($confidence < 0.3) {
                return null;
            }
            $species = trim(preg_replace('/\s*CONFIDENCE:.*$/iu', '', $species) ?? '');
        }

        if ($species === '' || strtolower($species) === 'desconocido') {
            return null;
        }

        return $species;
    }

    private function resizeIfNeeded(string $imagePath): string
    {
        $imageInfo = $this->silently(static fn () => getimagesize($imagePath));
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
            $source = $this->silently(static fn () => imagecreatefrompng($imagePath));
        } elseif (str_contains($mime, 'webp') && function_exists('imagecreatefromwebp')) {
            $source = $this->silently(static fn () => imagecreatefromwebp($imagePath));
        } else {
            $source = $this->silently(static fn () => imagecreatefromjpeg($imagePath));
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

    /**
     * Genera una interpretación en texto a partir de un prompt de OpenRouter.
     * Usado por el flujo de "Para ti" para obtener una explicación detallada del pescado.
     */
    public function generateText(string $prompt, int $maxTokens = 400): ?string
    {
        try {
            $response = Http::timeout(20)->post(self::ENDPOINT, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un nutricionista español. Responde en español, en 2-3 párrafos cortos, sin usar listas largas ni markdown.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => 0.5,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OpenRouter generateText failed', [
                'error_preview' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('OpenRouter generateText unsuccessful', ['status' => $response->status()]);

            return null;
        }

        $body = (string) $response->json('choices.0.message.content', '');

        return trim($body) !== '' ? trim($body) : null;
    }

    private function detectMimeType(string $path): string
    {
        $mime = $this->silently(static fn () => mime_content_type($path));

        return is_string($mime) && $mime !== '' ? $mime : 'image/jpeg';
    }

    /**
     * Run a callable while suppressing PHP warnings/notices that the
     * wrapped function would otherwise emit (e.g. GD failures on malformed
     * images). Used in places where a user-supplied file is allowed to be
     * invalid and we want a silent fallback instead of noisy error_log spam.
     *
     * Prefer this over the `@` operator — `@` hides everything including
     * fatal errors and is flagged by static analysis.
     */
    private function silently(callable $fn): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}
