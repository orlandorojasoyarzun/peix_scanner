<?php

declare(strict_types=1);

use App\Support\ScanStateStore;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('returns 404 when the scan has no state cached (no ownership proof)', function () {
    $scanId = (string) Str::uuid();

    $response = $this->get(route('scan.image', $scanId));

    $response->assertNotFound();
});

it('returns 404 when the scan has a result but no image in state', function () {
    $scanId = (string) Str::uuid();
    $store = $this->app->make(ScanStateStore::class);
    $store->put($scanId, [
        'mode' => 'fish',
        'result' => ['scientific_name' => 'Salmo salar'],
    ]);

    $response = $this->get(route('scan.image', $scanId));

    $response->assertNotFound();
});

it('serves the image bytes when the state slot points to a real file', function () {
    Storage::fake('local');
    $scanId = (string) Str::uuid();
    $storedPath = "scan-uploads/{$scanId}.jpg";

    // Plant a tiny valid JPEG (1x1 pixel) and register it in the state.
    Storage::disk('local')->put($storedPath, base64_decode(
        '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAr/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAFlf//EABQBAQAAAAAAAAAAAAAAAAAAAAr/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwBVf//Z'
    ));

    $store = $this->app->make(ScanStateStore::class);
    $store->put($scanId, [
        'mode' => 'fish',
        'image' => $storedPath,
        'result' => ['scientific_name' => 'Salmo salar'],
    ]);

    $response = $this->get(route('scan.image', $scanId));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image');
});

it('returns 404 when state points to a non-existent file', function () {
    Storage::fake('local');
    $scanId = (string) Str::uuid();

    $store = $this->app->make(ScanStateStore::class);
    $store->put($scanId, [
        'mode' => 'fish',
        'image' => 'scan-uploads/ghost.jpg',
    ]);

    $response = $this->get(route('scan.image', $scanId));

    $response->assertNotFound();
});
