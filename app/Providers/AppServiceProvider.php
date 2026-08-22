<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\Adapters\OpenRouterVisionAdapter;
use App\Domain\Ai\Contracts\SpeciesIdentifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Allowlist of external hosts the application is allowed to call.
     * Patterns are matched against the full request URL via Str::is(), so
     * wildcards are supported (the trailing /* locks each pattern to its
     * own host). Any outbound HTTP request that doesn't match will throw
     * a `StrayRequestException`, which keeps SSRF / DNS-rebinding /
     * accidental data exfiltration attacks bounded to known providers.
     *
     * Add a pattern here only if a real feature depends on it — never as
     * a blanket allow for "external services in general".
     */
    private const ALLOWED_REMOTE_HOST_PATTERNS = [
        'https://api.nal.usda.gov/*',
        'https://en.wikipedia.org/*',
        'https://upload.wikimedia.org/*',
        'https://openrouter.ai/*',
    ];

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
        // Block any outbound HTTP request whose URL is not on the allowlist.
        // This catches SSRF, DNS-rebinding, accidental misconfiguration and
        // throws StrayRequestException if a request tries to escape. Tests
        // that use Http::fake() are unaffected — fakes short-circuit the
        // real client and bypass this guard entirely.
        Http::preventStrayRequests();
        Http::allowStrayRequests(self::ALLOWED_REMOTE_HOST_PATTERNS);

        // Cap anonymous traffic to AI-touching routes. Keyed by session ID
        // plus IP so that:
        //   - multiple users behind the same NAT/mobile carrier don't share
        //     a quota bucket (the session ID differentiates them);
        //   - a single attacker rotating IPs still gets capped because the
        //     session ID stays constant across requests from the same browser.
        // Falls back to IP-only when there's no active session.
        RateLimiter::for('ai', function (Request $request) {
            $key = $request->hasSession()
                ? $request->session()->getId() . '|' . $request->ip()
                : 'ip:' . $request->ip();

            return Limit::perMinute(5)->by($key)->response(function () {
                return response()->view('errors.429', [], 429);
            });
        });
    }
}
