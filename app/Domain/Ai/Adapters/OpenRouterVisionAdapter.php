<?php

declare(strict_types=1);

namespace App\Domain\Ai\Adapters;

use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterVisionAdapter implements SpeciesIdentifier
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    private const PROMPT = OllamaVisionAdapter::PROMPT;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    public function identify(string $imagePath): IdentificationResult
    {
        if (! is_file($imagePath)) {
            throw IdentificationFailedException::fromProvider('openrouter', "image not found at {$imagePath}");
        }

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
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer' => 'https://peix-scanner.local',
                'X-Title' => 'Peix Scanner',
            ])->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            throw IdentificationFailedException::fromProvider('openrouter', $e->getMessage());
        }

        if ($response->status() === 429) {
            throw IdentificationFailedException::fromProvider('openrouter', 'rate limit hit (429); retry later or switch to Ollama');
        }

        if (! $response->successful()) {
            throw IdentificationFailedException::fromProvider('openrouter', "HTTP {$response->status()}: {$response->body()}");
        }

        $body = (string) $response->json('choices.0.message.content', '');

        if ($body === '') {
            Log::warning('OpenRouter returned empty content', ['payload' => $response->json()]);
            throw IdentificationFailedException::fromProvider('openrouter', 'empty response body');
        }

        $parsed = $this->parseResponse($body);

        if ($parsed === null) {
            Log::warning('OpenRouter returned unparseable response', ['raw' => $body]);
            throw IdentificationFailedException::fromProvider('openrouter', "could not parse response: {$body}");
        }

        return $parsed;
    }

    private function detectMimeType(string $path): string
    {
        $mime = @mime_content_type($path);

        return is_string($mime) && $mime !== '' ? $mime : 'image/jpeg';
    }

    /**
     * Same multi-line parser as Ollama adapter: extracts top candidates.
     */
    private function parseResponse(string $body): ?IdentificationResult
    {
        $body = trim($body);
        $lines = preg_split('/\R/', $body) ?: [$body];

        $parsed = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '*')) {
                continue;
            }

            $cleaned = ltrim($line, "- 0123456789.\t ");

            if (preg_match('/([A-Z][a-z]+(?:\s+[a-z\-]+)?)\s*\(([^)]+)\)\s*,\s*([0-9]*\.?[0-9]+)/u', $cleaned, $m) !== 1) {
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
