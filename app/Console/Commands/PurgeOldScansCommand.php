<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ScanStateStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes uploaded scan images older than the given age from the private disk.
 *
 * Without this, the storage/app/private/scan-uploads/ directory grows unbounded
 * (we already saw ~646 stale files on the public disk before the migration).
 * Run via cron: `php artisan scans:purge --older-than=24h` once per hour.
 *
 * Only the files are purged — cache keys expire on their own TTL.
 *
 * `--deep` adds an extra pass that walks the scan-state index and drops
 * cache entries whose backing file no longer exists on disk. This closes
 * the rare case where a file gets deleted out-of-band (manual cleanup,
 * disk pressure) but the encrypted state slot still lingers until its TTL.
 */
class PurgeOldScansCommand extends Command
{
    protected $signature = 'scans:purge
        {--older-than=24h : Minimum age of files to delete (e.g. 24h, 7d, 1h)}
        {--dry-run : Report what would be deleted without removing anything}
        {--deep : Also drop scan state cache slots whose backing file is missing}';

    protected $description = 'Purge old uploaded scan images from the private disk';

    public function handle(ScanStateStore $scanState): int
    {
        try {
            $cutoff = $this->parseOlderThan((string) $this->option('older-than'));
        } catch (\InvalidArgumentException $e) {
            $this->error("Invalid --older-than value: {$this->option('older-than')}");

            return self::FAILURE;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists('scan-uploads')) {
            $this->info('No scan-uploads directory found, nothing to purge.');
        } else {
            $dryRun = (bool) $this->option('dry-run');
            $deleted = 0;
            $kept = 0;

            foreach ($disk->files('scan-uploads') as $path) {
                $lastModified = $disk->lastModified($path);
                if ($lastModified < $cutoff) {
                    if ($dryRun) {
                        $this->line("would delete: {$path}");
                    } else {
                        $disk->delete($path);
                    }
                    $deleted++;
                } else {
                    $kept++;
                }
            }

            $verb = $dryRun ? 'would delete' : 'deleted';
            $this->info("{$verb} {$deleted} file(s); kept {$kept}.");
        }

        if ($this->option('deep')) {
            $this->deepCleanOrphans($scanState, $disk);
        }

        return self::SUCCESS;
    }

    /**
     * Walk every UUID registered in the scan-state index. If the cache
     * slot still holds a state but its image path points at a file that
     * no longer exists on disk, drop the state so confirm()/ScanImage
     * stop rendering broken pages for non-existent uploads.
     */
    private function deepCleanOrphans(ScanStateStore $scanState, \Illuminate\Contracts\Filesystem\Filesystem $disk): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $candidates = 0;
        $dropped = 0;

        foreach ($scanState->indexedUuids() as $uuid) {
            $state = $scanState->get($uuid);

            if ($state === null) {
                // Index entry is stale; let it expire naturally.
                continue;
            }

            $image = $state['image'] ?? null;

            if (! is_string($image) || $image === '') {
                continue;
            }

            $candidates++;

            if (! $disk->exists($image)) {
                if ($dryRun) {
                    $this->line("would drop state: {$uuid} (missing file {$image})");
                } else {
                    $scanState->forget($uuid);
                }
                $dropped++;
            }
        }

        $verb = $dryRun ? 'would drop' : 'dropped';
        $this->info("Deep clean: {$verb} {$dropped} orphan state(s); inspected {$candidates}.");
    }

    /**
     * Parse a duration string like "24h", "7d", "90m" into a Unix
     * timestamp. The format is strictly whitelisted — anything else
     * throws InvalidArgumentException so the command exits with a
     * clear error rather than silently doing nothing.
     */
    private function parseOlderThan(string $value): int
    {
        if (! preg_match('/^(\d+)([smhd])$/i', trim($value), $m)) {
            throw new \InvalidArgumentException("Unsupported duration: {$value}");
        }

        $n = (int) $m[1];
        $unit = strtolower($m[2]);

        $multiplier = match ($unit) {
            's' => 1,
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
        };

        return now()->subSeconds($n * $multiplier)->getTimestamp();
    }
}