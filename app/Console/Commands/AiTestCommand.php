<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ai\Contracts\SpeciesIdentifier;
use Illuminate\Console\Command;

class AiTestCommand extends Command
{
    protected $signature = 'ai:test {path : Path to a fish fillet image}';

    protected $description = 'Identify the species of a fish fillet using the configured AI provider';

    public function handle(SpeciesIdentifier $identifier): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Identifying species in: {$path}");
        $this->newLine();

        $start = microtime(true);

        try {
            $result = $identifier->identify($path);
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $elapsed = round((microtime(true) - $start) * 1000);

        $this->table(
            ['Field', 'Value'],
            [
                ['Scientific name', $result->scientificName],
                ['Common name', $result->commonName],
                ['Confidence', number_format($result->confidence, 2)],
                ['High confidence?', $result->isHighConfidence() ? 'yes' : 'no'],
                ['Elapsed', "{$elapsed} ms"],
                ['Adapter', get_class($identifier)],
            ]
        );

        return self::SUCCESS;
    }
}
