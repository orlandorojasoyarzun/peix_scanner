<?php

declare(strict_types=1);

use App\Support\ScanStateStore;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('removes files older than the cutoff and keeps the recent ones', function () {
    $disk = Storage::disk('local');
    $disk->put('scan-uploads/old.jpg', 'x');
    $disk->put('scan-uploads/new.jpg', 'y');

    // Force the "old" file's mtime to 2 days ago.
    $oldPath = $disk->path('scan-uploads/old.jpg');
    touch($oldPath, now()->subDays(2)->getTimestamp());

    $this->artisan('scans:purge', ['--older-than' => '24h'])
        ->assertExitCode(0);

    expect($disk->exists('scan-uploads/old.jpg'))->toBeFalse()
        ->and($disk->exists('scan-uploads/new.jpg'))->toBeTrue();
});

it('dry-run reports what would be deleted without removing anything', function () {
    $disk = Storage::disk('local');
    $disk->put('scan-uploads/old.jpg', 'x');
    touch($disk->path('scan-uploads/old.jpg'), now()->subDays(2)->getTimestamp());

    $this->artisan('scans:purge', ['--older-than' => '24h', '--dry-run' => true])
        ->expectsOutputToContain('would delete')
        ->assertExitCode(0);

    expect($disk->exists('scan-uploads/old.jpg'))->toBeTrue();
});

it('deep clean drops scan state cache slots whose backing file is missing', function () {
    $disk = Storage::disk('local');
    $disk->put('scan-uploads/alive.jpg', 'x');

    /** @var ScanStateStore $store */
    $store = $this->app->make(ScanStateStore::class);

    // Two states: one pointing at a real file, one pointing at a file
    // that no longer exists on disk.
    $alive = '11111111-1111-4111-8111-111111111111';
    $orphan = '22222222-2222-4222-8222-222222222222';

    $store->put($alive, ['mode' => 'fish', 'image' => 'scan-uploads/alive.jpg']);
    $store->put($orphan, ['mode' => 'fish', 'image' => 'scan-uploads/ghost.jpg']);

    $this->artisan('scans:purge', ['--deep' => true])
        ->assertExitCode(0);

    expect($store->get($alive))->not->toBeNull()
        ->and($store->get($orphan))->toBeNull();
});

it('deep clean in dry-run reports but does not drop the orphan', function () {
    $disk = Storage::disk('local');

    /** @var ScanStateStore $store */
    $store = $this->app->make(ScanStateStore::class);

    $orphan = '33333333-3333-4333-8333-333333333333';
    $store->put($orphan, ['mode' => 'fish', 'image' => 'scan-uploads/ghost.jpg']);

    $this->artisan('scans:purge', ['--deep' => true, '--dry-run' => true])
        ->expectsOutputToContain('Deep clean')
        ->assertExitCode(0);

    expect($store->get($orphan))->not->toBeNull();
});

it('a scan state whose file still exists is left alone by deep clean', function () {
    $disk = Storage::disk('local');
    $disk->put('scan-uploads/alive.jpg', 'x');

    /** @var ScanStateStore $store */
    $store = $this->app->make(ScanStateStore::class);
    $uuid = '44444444-4444-4444-8444-444444444444';
    $store->put($uuid, ['mode' => 'fish', 'image' => 'scan-uploads/alive.jpg']);

    $this->artisan('scans:purge', ['--deep' => true])
        ->assertExitCode(0);

    expect($store->get($uuid))->not->toBeNull();
});

it('an empty scan-uploads directory is reported as a no-op', function () {
    $this->artisan('scans:purge')
        ->expectsOutputToContain('No scan-uploads directory found')
        ->assertExitCode(0);
});

it('rejects an invalid --older-than value with a non-zero exit code', function () {
    $this->artisan('scans:purge', ['--older-than' => 'not-a-duration'])
        ->expectsOutputToContain('Invalid --older-than')
        ->assertExitCode(1);
});
