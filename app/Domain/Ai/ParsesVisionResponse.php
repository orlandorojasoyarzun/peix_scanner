<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Ai\DTOs\IdentificationResult;

trait ParsesVisionResponse
{
    private function parseResponse(string $body): ?IdentificationResult
    {
        $body = trim($body);
        $lines = preg_split('/\R/', $body) ?: [$body];

        $candidates = [];
        $current = null;
        $esName = '';
        $regional = [];

        $flush = function () use (&$candidates, &$current, &$esName, &$regional): void {
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
