<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\Adapters\OpenRouterVisionAdapter;
use App\Domain\Ai\Contracts\SpeciesIdentifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpeciesIdentifier::class, function () {
            return new OpenRouterVisionAdapter(
                apiKey: (string) env('OPENROUTER_API_KEY'),
                model: (string) env('OPENROUTER_MODEL', 'nvidia/nemotron-nano-12b-v2-vl:free'),
            );
        });

        $this->app->bind(OpenRouterVisionAdapter::class, function () {
            return new OpenRouterVisionAdapter(
                apiKey: (string) env('OPENROUTER_API_KEY'),
                model: (string) env('OPENROUTER_MODEL', 'nvidia/nemotron-nano-12b-v2-vl:free'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
