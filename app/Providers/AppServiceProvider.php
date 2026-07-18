<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\Adapters\OllamaVisionAdapter;
use App\Domain\Ai\Adapters\OpenRouterVisionAdapter;
use App\Domain\Ai\Contracts\SpeciesIdentifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpeciesIdentifier::class, function () {
            $apiKey = (string) env('OPENROUTER_API_KEY', '');

            if ($apiKey !== '') {
                return new OpenRouterVisionAdapter(
                    apiKey: $apiKey,
                    model: (string) env('OPENROUTER_MODEL', 'nvidia/nemotron-nano-12b-v2-vl:free'),
                );
            }

            return new OllamaVisionAdapter(
                host: (string) env('OLLAMA_HOST', 'http://localhost:11434'),
                model: (string) env('OLLAMA_MODEL', 'llama3.2-vision:11b'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
