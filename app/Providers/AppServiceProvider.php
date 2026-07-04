<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\Adapters\OllamaVisionAdapter;
use App\Domain\Ai\Contracts\SpeciesIdentifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpeciesIdentifier::class, function () {
            return new OllamaVisionAdapter(
                host: (string) env('OLLAMA_HOST', 'http://localhost:11434'),
                model: (string) env('OLLAMA_MODEL', 'llava:7b'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
