<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ScanStateStore;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves uploaded scan images from the private disk.
 *
 * Until auth is added, "ownership" is approximated by requiring the scan's
 * state slot (scan.{uuid}.state) to exist. Anyone who can produce the UUID
 * has access, but UUIDs are generated server-side and only returned to the
 * user that uploaded them, so leakage is bounded to the same threat model
 * as the rest of the cache-as-session flow.
 */
class ScanImageController
{
    /**
     * Allowlist of relative paths this controller may serve. Restricting
     * the prefix prevents a poisoned cache value (or any future caller that
     * reads $storedPath from an external source) from escaping into the
     * rest of the private disk.
     */
    private const ALLOWED_PATH_PREFIX = 'scan-uploads/';

    public function __construct(
        private readonly ScanStateStore $scanState,
    ) {}

    public function show(string $scan): Response
    {
        $state = $this->scanState->get($scan);

        if ($state === null) {
            abort(404);
        }

        $storedPath = $state['image'] ?? null;

        if (! is_string($storedPath) || $storedPath === '') {
            abort(404);
        }

        if (! is_string($storedPath) || $storedPath === '') {
            abort(404);
        }

        // Path traversal guard: the value comes from cache (server-controlled
        // today) but defense in depth is cheap and protects against future
        // callers, cache poisoning, or DB tampering.
        if (! Str::startsWith($storedPath, self::ALLOWED_PATH_PREFIX)
            || Str::contains($storedPath, '..')
            || ! $this->isAsciiSafe($storedPath)) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($storedPath)) {
            abort(404);
        }

        $absolutePath = $disk->path($storedPath);

        // Canonical-path check: confirm the resolved file is actually inside
        // the disk root. Catches symlink tricks and any filesystem quirk that
        // bypasses the prefix check above.
        $diskRoot = realpath($disk->path('')) ?: $disk->path('');
        $resolved = realpath($absolutePath);

        if ($resolved === false || ! Str::startsWith($resolved, $diskRoot)) {
            abort(404);
        }

        $mime = $disk->mimeType($storedPath) ?: 'application/octet-stream';

        return new BinaryFileResponse($absolutePath, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Reject any path containing non-printable bytes, NULs or control chars
     * that could confuse downstream filesystems or HTTP layers.
     */
    private function isAsciiSafe(string $path): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $path) === 0;
    }
}