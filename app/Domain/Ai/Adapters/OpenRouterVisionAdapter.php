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

}
