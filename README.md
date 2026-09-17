![Peix Scanner Logo](public/images/peix-scanner-logo-750.png)

# Peix Scanner

A webapp that recognises a fish fillet species from a photo. You upload an image, an AI tells you what fish it is, and it shows you a card with nutritional info, sustainability, and how to cook it.

Built as a hackathon project: a working demo that someone can open on their phone, point at the fish counter in a supermarket, take a picture, and know what they're buying.

---

## The problem

When you buy fresh fish, knowing exactly what species you're getting isn't trivial. Fish counters aren't always well-labelled, fillets look alike (many are just white flesh), and traceability is hit-or-miss.

Apps like "Yuka" already exist for packaged products, but for the fish counter there's no equivalent. This project tries to fill that gap: a visual scanner for fillets.

---

## How it works (in plain language)

The user opens the webapp, sees a welcome screen with a "Scan" button. Click → they can take a photo or upload one from the camera roll. The photo goes to a vision model that analyses it and returns the species with a confidence percentage.

If the species looks right, the user confirms and sees a card with the name in Spanish, the scientific name, regional variants (Galician, Catalan, Basque) and three tabs with nutritional info, sustainability and preparation.

If confidence is low or the user thinks the detection is wrong, they can correct it manually.

---

## Stack and technical decisions

**Backend**: Laravel 12 on PHP 8.4.

**Frontend**: Blade + Alpine.js + Tailwind 4. Livewire is installed but not heavily used. The app is mostly server-rendered with small client-side islands of interactivity (image preview, validation before submit).

**Database**: PostgreSQL (locally any version; Railway provides the production instance). No `users` table (conscious decision: MVP has no auth, none needed).

**AI**: OpenRouter via `OpenRouterVisionAdapter` using `inclusionai/ling-3.0-flash-vl:free` (free tier, no credit consumption).

**Not built yet**: FishBase integration for sustainability data, text generation for the Preparation tab (currently placeholders).

---

## Project structure

```
app/
├── Domain/                          ← model + business rules
│   ├── Ai/
│   │   ├── Adapters/                ← OpenRouterVisionAdapter
│   │   ├── Contracts/               ← SpeciesIdentifier interface
│   │   ├── DTOs/                    ← IdentificationResult
│   │   └── SpeciesTranslations.php  ← curated fallback for 35+ species
│   ├── Species/Models/              ← Species, SpeciesImage
│   ├── Nutrition/Models/            ← NutritionProfile
│   ├── Recommendation/Models/       ← Recommendation
│   └── Ai/Models/                   ← AiGeneration
├── Application/Actions/             ← IdentifySpeciesAction (orchestrates the use case)
├── Http/
│   ├── Controllers/                 ← ScanController
│   └── Requests/                    ← ScanImageRequest (validation)
└── Providers/                       ← AppServiceProvider (conditional AI binding)

resources/
├── css/app.css                      ← design system + Tailwind
├── svg/                             ← camera, fishing hook, favicon (all SVGs)
└── views/                           ← layouts + pages + components
```

The folders aren't ceremonious: each file exists because it's used. If you want to change the AI adapter, you edit `AppServiceProvider`. If you want to change the image validation, you edit `ScanImageRequest`. There are no abstract layers to traverse to get to the logic.

---

## Data model

5 tables that cover the full cycle:

| Table | What for |
|---|---|
| `species` | The species record (common name, scientific name, description) |
| `species_images` | Photos users upload to identify |
| `nutrition_profiles` | Nutritional data per 100g (calories, protein, omega 3, vitamins) |
| `recommendations` | AI-generated insights for each species |
| `ai_generations` | Log of each AI call (provider, model, prompt_hash for caching) |

`users` doesn't exist. Conscious decision: the MVP has no auth and all content is public.

Migrations are in `database/migrations/`. Test factories in `database/factories/`.

---

## Tests

```bash
./vendor/bin/pest                          # all (61 tests, 227 assertions)
./vendor/bin/pest tests/Feature/ScanFlowTest.php   # one file
./vendor/bin/pest --filter="identifies"    # by name
```

Tests use mocks so they don't burn API calls. Expected output: `61 passed (227 assertions)`.

## Deploy on Railway

The app ships with `Procfile`, `nixpacks.toml`, `railway.toml` and `.dockerignore` so a fresh Railway project can boot with zero manual configuration beyond setting secrets.

### Services to provision

| Service | Purpose | Notes |
|---|---|---|
| Web (PHP) | Serves HTTP via Laravel Octane + FrankenPHP | `web` process in Procfile |
| Worker (PHP) | Queue worker | `worker` process in Procfile |
| Scheduler (PHP) | Runs `schedule:work` (scans:purge hourly + daily deep) | `scheduler` process in Procfile |
| Postgres | Database | DB_CONNECTION=pgsql. Used for cache + queue too (CACHE_STORE=database, QUEUE_CONNECTION=database) |
| Volume | Persistent storage for `storage/app/` | Mount at `/app/storage` |

### Environment variables to set on Railway

| Variable | Value | Source |
|---|---|---|
| `APP_ENV` | `production` | hardcoded in this repo's defaults |
| `APP_DEBUG` | `false` | hardcoded in this repo's defaults |
| `APP_KEY` | `base64:…` (32 random bytes) | `php artisan key:generate` locally, paste into Railway |
| `APP_URL` | `https://<your-domain>` | Railway public domain or custom domain |
| `DB_CONNECTION` | `pgsql` | matches the Postgres service |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from Railway Postgres reference vars | auto-injected if you use the `DATABASE_URL` reference |
| `CACHE_STORE` | `database` | cache table in Postgres |
| `QUEUE_CONNECTION` | `database` | jobs table in Postgres |
| `SESSION_DRIVER` | `database` | sessions table in Postgres |
| `SESSION_ENCRYPT` | `true` | default already, but explicit is safer |
| `SESSION_SECURE_COOKIE` | `true` | auto-true when `APP_ENV=production`, but explicit for clarity |
| `LOG_CHANNEL` | `stderr` | JSON-formatted; auto-set when `APP_ENV=production` |
| `OPENROUTER_API_KEY` | `sk-or-v1-…` | rotated; inject via Railway Variables |
| `OPENROUTER_MODEL` | `inclusionai/ling-3.0-flash-vl:free` | current model |
| `USDA_API_KEY` | `…` | rotated; inject via Railway Variables |
| `OCTANE_SERVER` | `frankenphp` | baked into the Procfile but exposed for clarity |

### Build / start

Nixpacks runs:

```
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan octane:install --server=frankenphp
```

Then on each deploy start:

```
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan storage:link
```

The `web` process boots Octane over FrankenPHP on `0.0.0.0:${PORT}`.

### Healthcheck

Laravel exposes `/up` (declared in `bootstrap/app.php`). `railway.toml` tells Railway to hit that route for healthchecks.

### Why Octane/FrankenPHP instead of `php artisan serve`?

`php artisan serve` is the **single-threaded** development server. It cannot serve concurrent requests, so a handful of parallel users at peak (e.g. demo day at the hackathon) would queue up and time out. Octane keeps the PHP runtime resident across requests, supports HTTP/2, and is what every real Laravel-on-Railway deploy uses.
