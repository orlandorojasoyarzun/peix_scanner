<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
 */
class PurgeOldScansCommand extends Command
{
    protected $signature = 'scans:purge
        {--older-than=24h : Minimum age of files to delete (e.g. 24h, 7d, 1h)}
        {--dry-run : Report what would be deleted without removing anything}';

    protected $description = 'Purge old uploaded scan images from the private disk';

    public function handle(): int
    {
        $disk = Storage::disk('local');

        if (! $disk->exists('scan-uploads')) {
            $this->info('No scan-uploads directory found, nothing to purge.');

            return self::SUCCESS;
        }

        try {
            $cutoff = now()->sub((string) $this->option('older-than'))->getTimestamp();
        } catch (\Throwable $e) {
            $this->error("Invalid --older-than value: {$this->option('older-than')}");

            return self::FAILURE;
        }

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

        return self::SUCCESS;
    }
}