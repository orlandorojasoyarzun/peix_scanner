<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves uploaded scan images from the private disk.
 *
 * Until auth is added, "ownership" is approximated by requiring the scan's
 * cache key (scan.{uuid}.result|image|mode|error) to exist. Anyone who can
 * produce the UUID has access, but UUIDs are generated server-side and only
 * returned to the user that uploaded them, so leakage is bounded to the
 * same threat model as the rest of the cache-as-session flow.
 */
class ScanImageController
{
    public function show(string $scan): Response
    {
        $hasSession = Cache::has("scan.{$scan}.image")
            || Cache::has("scan.{$scan}.result")
            || Cache::has("scan.{$scan}.error");

        if (! $hasSession) {
            abort(404);
        }

        $storedPath = Cache::get("scan.{$scan}.image");

        if (! is_string($storedPath) || $storedPath === '') {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($storedPath)) {
            abort(404);
        }

        $absolutePath = $disk->path($storedPath);
        $mime = $disk->mimeType($storedPath) ?: 'application/octet-stream';

        return new BinaryFileResponse($absolutePath, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}