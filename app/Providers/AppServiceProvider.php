<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\Adapters\OpenRouterVisionAdapter;
use App\Domain\Ai\Contracts\SpeciesIdentifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $factory = fn () => new OpenRouterVisionAdapter(
            apiKey: (string) env('OPENROUTER_API_KEY'),
            model: (string) env('OPENROUTER_MODEL', 'nvidia/nemotron-nano-12b-v2-vl:free'),
        );

        $this->app->bind(SpeciesIdentifier::class, $factory);
        $this->app->bind(OpenRouterVisionAdapter::class, $factory);
    }

    public function boot(): void
    {
        // Cap anonymous traffic to AI-touching routes. Without this, a single
        // attacker can burn through the OpenRouter quota in minutes — each
        // /scan POST triggers a paid (or free-tier) vision call.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())->response(function () {
                return response()->view('errors.429', [], 429);
            });
        });
    }
}
