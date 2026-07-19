<?php

declare(strict_types=1);

namespace App\Domain\Ai\Adapters;

use App\Domain\Ai\Contracts\SpeciesIdentifier;
use App\Domain\Ai\DTOs\IdentificationResult;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use Illuminate\Support\Facades\Http;

class OpenRouterVisionAdapter implements SpeciesIdentifier
{
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public const PROMPT = OllamaVisionAdapter::PROMPT;

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
            throw IdentificationFailedException::fromProvider('openrouter', 'rate limit hit (429); retry later or switch to Ollama');
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

    /**
     * Resize the image to max 512x512 if larger. Reduces the payload sent to the provider (~8x smaller for typical photos).
     */
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

    /**
     * Parse the model response. Recognises top 1 candidate + ES (Spanish name) + ALT (regional names).
     * Robust against markdown (**bold**) on the candidate line and trailing explanation paragraphs.
     */
    private function parseResponse(string $body): ?IdentificationResult
    {
        $body = trim($body);
        $lines = preg_split('/\R/', $body) ?: [$body];

        $candidates = [];
        $current = null;
        $esName = '';
        $regional = [];

        $flush = function () use (&$candidates, &$current, &$esName, &$regional) {
            if ($current !== null) {
                $candidates[] = $current;
            }
            $current = null;
            $esName = '';
            $regional = [];
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            $line = trim((string) preg_replace('/^[\s\*]+|[\s\*]+$/', '', $line));

            if ($line === '') {
                continue;
            }

            if (preg_match('/^ES:\s*(.+)$/u', $line, $m)) {
                $raw = trim($m[1]);

                if (preg_match('/^(.+?)\s*(?:^|\s)(?:ALT|ATL):\s*(.+)$/su', $raw, $split)) {
                    $esName = trim($split[1]);
                    $parts = array_map(
                        fn ($p) => trim((string) preg_replace('/^[\s\*]+|[\s\*]+$/', '', $p)),
                        explode(',', $split[2])
                    );
                    $regional = array_values(array_filter($parts, fn ($p) => $p !== ''));
                } else {
                    $esName = $raw;
                }

                continue;
            }

            if (preg_match('/^ALT:\s*(.+)$/u', $line, $m)) {
                $parts = array_map(
                    fn ($p) => trim((string) preg_replace('/^[\s\*]+|[\s\*]+$/', '', $p)),
                    explode(',', $m[1])
                );
                $regional = array_values(array_filter($parts, fn ($p) => $p !== ''));

                continue;
            }

            if (preg_match('/^\*+\s*(.+?)\s*\*+\s*,\s*([0-9]*\.?[0-9]+)\s*\*?$/', $line, $m)) {
                $inner = trim((string) preg_replace('/\*+/', '', $m[1]));
                $flush();
                if (preg_match('/([A-Z][A-Za-z]+(?:\s+[a-z\-]+)?)\s*\(([^)]+)\)/u', $inner, $im)) {
                    $scientific = strtolower(trim($im[1]));
                    $confidence = (float) $m[2];
                    if ($scientific !== 'unknown' && $confidence > 0) {
                        $current = [
                            'scientific_name' => $scientific,
                            'common_name' => trim($im[2]),
                            'common_name_local' => '',
                            'regional_names' => [],
                            'confidence' => max(0, min(1, $confidence)),
                        ];
                    }
                }

                continue;
            }

            if (preg_match('/([A-Z][A-Za-z]+(?:\s+[a-z\-]+)?)\s*\(([^)]+)\)\s*,\s*([0-9]*\.?[0-9]+)/u', $line, $m)) {
                $flush();
                $scientific = strtolower(trim($m[1]));
                $confidence = (float) $m[3];
                if ($scientific !== 'unknown' && $confidence > 0) {
                    $current = [
                        'scientific_name' => $scientific,
                        'common_name' => trim($m[2]),
                        'common_name_local' => '',
                        'regional_names' => [],
                        'confidence' => max(0, min(1, $confidence)),
                    ];
                }

                continue;
            }

            if ($current === null) {
                continue;
            }

            if ($esName !== '' && $current['common_name_local'] === '') {
                $current['common_name_local'] = $esName;
                $esName = '';
            }
            if (! empty($regional)) {
                $current['regional_names'] = array_values(array_unique(array_merge($current['regional_names'], $regional)));
                $regional = [];
            }
        }

        if ($esName !== '' && $current !== null && $current['common_name_local'] === '') {
            $current['common_name_local'] = $esName;
        }
        if (! empty($regional) && $current !== null) {
            $current['regional_names'] = array_values(array_unique(array_merge($current['regional_names'], $regional)));
        }

        $flush();

        if ($candidates === []) {
            return null;
        }

        $top = $candidates[0];

        return new IdentificationResult(
            scientificName: $top['scientific_name'],
            commonName: $top['common_name'],
            commonNameLocal: $top['common_name_local'],
            confidence: $top['confidence'],
            candidates: $candidates,
            regionalNames: $top['regional_names'],
        );
    }
}
