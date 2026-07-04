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

        $prompt = 'You are a fish species expert. Look at the raw fillet in the image and identify the species. '
            .'Answer with ONE line in this exact format: "Scientific name (Common name), confidence" where confidence is a number from 0.0 to 1.0. '
            .'Example: "Salmo salar (Atlantic salmon), 0.92". '
            .'If you cannot identify the species, answer: "unknown (unknown), 0".';

        try {
            $response = Http::timeout(120)->post(rtrim($this->host, '/').'/api/generate', [
                'model' => $this->model,
                'prompt' => $prompt,
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
     * Parse responses like:
     *   "Salmo salar (Atlantic salmon), 0.92"
     *   "Gadus morhua (Atlantic cod), 0.85"
     *   "unknown (unknown), 0"
     * The model may also return verbose responses with extra text;
     * we scan every line and take the first one that matches the pattern.
     */
    private function parseResponse(string $body): ?IdentificationResult
    {
        $body = trim($body);
        $lines = preg_split('/\R/', $body) ?: [$body];

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

            return new IdentificationResult(
                scientificName: $scientific,
                commonName: $common,
                confidence: max(0, min(1, $confidence)),
            );
        }

        return null;
    }
}
