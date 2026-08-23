<?php

declare(strict_types=1);

use App\Support\CacheKeys;
use App\Support\ScanStateStore;
use Illuminate\Support\Facades\Crypt;

it('round-trips a full scan state through the encrypted cache slot', function () {
    $store = $this->app->make(ScanStateStore::class);
    $uuid = '11111111-1111-4111-8111-111111111111';

    $store->put($uuid, [
        'mode' => 'fish',
        'image' => 'scan-uploads/x.jpg',
        'result' => ['scientific_name' => 'Salmo salar'],
    ]);

    $state = $store->get($uuid);

    expect($state)->toBe([
        'mode' => 'fish',
        'image' => 'scan-uploads/x.jpg',
        'result' => ['scientific_name' => 'Salmo salar'],
    ]);
});

it('persists the raw slot as an encrypted Laravel envelope, not plaintext', function () {
    $store = $this->app->make(ScanStateStore::class);
    $uuid = '22222222-2222-4222-8222-222222222222';

    $store->put($uuid, [
        'mode' => 'fish',
        'result' => ['scientific_name' => 'Salmo salar'],
    ]);

    // Read the raw cache value directly (bypassing ScanStateStore) and
    // confirm it is the encrypted Laravel envelope — the payload must
    // NOT contain the scientific name in plain text.
    $raw = \Illuminate\Support\Facades\Cache::get(CacheKeys::scanState($uuid));

    expect($raw)->toBeString()
        ->and($raw)->not->toContain('Salmo salar')
        ->and($raw)->not->toContain('scan-uploads');

    // Sanity: it's a real Laravel encrypted envelope and we can decrypt
    // it with the same APP_KEY to read the JSON inside.
    $decoded = Crypt::decryptString($raw);
    expect($decoded)->toContain('Salmo salar');
});

it('returns null when the slot does not exist', function () {
    $store = $this->app->make(ScanStateStore::class);

    expect($store->get('00000000-0000-0000-0000-000000000000'))->toBeNull();
});

it('forget drops both the state and the index entry', function () {
    $store = $this->app->make(ScanStateStore::class);
    $uuid = '33333333-3333-4333-8333-333333333333';

    $store->put($uuid, ['mode' => 'fish']);
    expect($store->indexedUuids())->toContain($uuid);

    $store->forget($uuid);

    expect($store->get($uuid))->toBeNull();
    expect($store->indexedUuids())->not->toContain($uuid);
});

it('tracks every put in the index, deduplicated', function () {
    $store = $this->app->make(ScanStateStore::class);

    $store->put('uuid-a', ['mode' => 'fish']);
    $store->put('uuid-b', ['mode' => 'label']);
    $store->put('uuid-a', ['mode' => 'fish', 'result' => ['scientific_name' => 'Salmo salar']]);

    expect($store->indexedUuids())->toContain('uuid-a', 'uuid-b');
    expect(count(array_keys(array_filter(
        $store->indexedUuids(),
        static fn (string $u): bool => $u === 'uuid-a'
    ))))->toBe(1);
});

it('a corrupted/rotated envelope returns null instead of throwing', function () {
    $store = $this->app->make(ScanStateStore::class);
    $uuid = '44444444-4444-4444-8444-444444444444';

    // Plant a poisoned cache value that the encryption layer cannot
    // decrypt (simulates APP_KEY rotation or DB tampering).
    \Illuminate\Support\Facades\Cache::put(CacheKeys::scanState($uuid), 'not-an-envelope', 600);

    expect($store->get($uuid))->toBeNull();
});

it('a legacy envelope (missing version field) is rejected cleanly', function () {
    $store = $this->app->make(ScanStateStore::class);
    $uuid = '55555555-5555-4555-8555-555555555555';

    // A valid envelope without the version marker — defends against a
    // future format change silently loading stale shapes.
    $poison = Crypt::encryptString(json_encode(['scan_id' => $uuid, 'state' => ['mode' => 'fish']]));
    \Illuminate\Support\Facades\Cache::put(CacheKeys::scanState($uuid), $poison, 600);

    expect($store->get($uuid))->toBeNull();
});

it('a single put writes the whole record atomically (no partial reads)', function () {
    // Even if confirm() reads in the millisecond after the put returns,
    // it must see the full record — never an image without a result
    // or vice versa. The whole point of unifying the slots.
    $store = $this->app->make(ScanStateStore::class);
    $uuid = '66666666-6666-4666-8666-666666666666';

    $store->put($uuid, [
        'mode' => 'fish',
        'image' => 'scan-uploads/y.jpg',
        'result' => ['scientific_name' => 'Salmo salar', 'confidence' => 0.92],
    ]);

    $state = $store->get($uuid);

    expect($state)->toHaveKeys(['mode', 'image', 'result'])
        ->and($state['result']['scientific_name'])->toBe('Salmo salar')
        ->and($state['result']['confidence'])->toBe(0.92);
});
