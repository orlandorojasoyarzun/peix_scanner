<?php

declare(strict_types=1);

use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('returns 404 when the scan has no cache keys (no ownership proof)', function () {
    $scanId = (string) Str::uuid();

    $response = $this->get(route('scan.image', $scanId));

    $response->assertNotFound();
});

it('returns 404 when the scan has a result but no image cached', function () {
    $scanId = (string) Str::uuid();
    Cache::put(CacheKeys::scanResult($scanId), ['scientific_name' => 'Salmo salar'], now()->addMinutes(10));

    $response = $this->get(route('scan.image', $scanId));

    $response->assertNotFound();
});

it('serves the image bytes when the cache says the image exists', function () {
    Storage::fake('local');
    $scanId = (string) Str::uuid();
    $storedPath = "scan-uploads/{$scanId}.jpg";

    // Plant a tiny valid JPEG (1x1 pixel) and register it in the cache.
    Storage::disk('local')->put($storedPath, base64_decode(
        '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAr/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAFlf//EABQBAQAAAAAAAAAAAAAAAAAAAAr/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwBVf//Z'
    ));
    Cache::put(CacheKeys::scanImage($scanId), $storedPath, now()->addMinutes(10));

    $response = $this->get(route('scan.image', $scanId));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image');
});

it('returns 404 when cache points to a non-existent file', function () {
    Storage::fake('local');
    $scanId = (string) Str::uuid();
    Cache::put(CacheKeys::scanImage($scanId), 'scan-uploads/ghost.jpg', now()->addMinutes(10));

    $response = $this->get(route('scan.image', $scanId));

    $response->assertNotFound();
});