<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('deletes files older than the cutoff and keeps the rest', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $oldId = (string) Str::uuid();
    $freshId = (string) Str::uuid();

    $disk->put("scan-uploads/{$oldId}.jpg", 'old-content');
    $disk->put("scan-uploads/{$freshId}.jpg", 'fresh-content');

    // Backdate the old file's mtime by 48h.
    $oldAbsolute = $disk->path("scan-uploads/{$oldId}.jpg");
    touch($oldAbsolute, now()->subHours(48)->getTimestamp());

    $this->artisan('scans:purge', ['--older-than' => '24h'])
        ->expectsOutputToContain('deleted 1')
        ->assertSuccessful();

    expect($disk->exists("scan-uploads/{$oldId}.jpg"))->toBeFalse();
    expect($disk->exists("scan-uploads/{$freshId}.jpg"))->toBeTrue();
});

it('dry-run reports would-be deletions without removing files', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $oldId = (string) Str::uuid();
    $disk->put("scan-uploads/{$oldId}.jpg", 'old');
    touch($disk->path("scan-uploads/{$oldId}.jpg"), now()->subHours(48)->getTimestamp());

    $this->artisan('scans:purge', ['--older-than' => '24h', '--dry-run' => true])
        ->expectsOutputToContain('would delete 1 file')
        ->assertSuccessful();

    expect($disk->exists("scan-uploads/{$oldId}.jpg"))->toBeTrue();
});

it('reports nothing to purge when the directory does not exist', function () {
    Storage::fake('local');

    $this->artisan('scans:purge')
        ->expectsOutputToContain('No scan-uploads directory found')
        ->assertSuccessful();
});

it('rejects an invalid --older-than value', function () {
    $this->artisan('scans:purge', ['--older-than' => 'not-a-duration'])
        ->expectsOutputToContain('Invalid --older-than value')
        ->assertFailed();
});