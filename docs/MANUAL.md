# Peix Scanner — Manual de funcionamiento

App web que reconoce la especie de un filete de pescado a partir de una foto (o de la foto de su etiqueta). Subes la imagen, una IA en la nube te dice qué pez es y te muestra una ficha con Nutrición, recomendaciones ("Para ti"), Sostenibilidad y Preparación.

Estado: MVP funcional en `develop`. Probado localmente con PHP 8.4 (Herd) + SQLite (default) o PostgreSQL 17 (DBngin) en macOS.

---

## Stack actual

- **Backend**: Laravel 12 + PHP 8.4
- **Frontend**: Blade + Alpine.js 3 (vía CDN) + Tailwind 4 (Vite 7, pnpm)
- **IA**: OpenRouter (cloud) via `OpenRouterVisionAdapter` — `nvidia/nemotron-nano-12b-v2-vl:free`
- **Datos nutricionales**: seed curado FEN (52 especies, principal) + USDA FoodData Central (fallback)
- **Imagen de referencia**: Wikipedia REST API (con caché)
- **Database**: SQLite por defecto; PostgreSQL 17 también soportado
- **Testing**: Pest 3.8 + Larastan 3.10 + Pint
- **Livewire 4**: instalado en `composer.json` pero **no se usa** en la app (solo `@livewireStyles`/`@livewireScripts` en el layout). Alpine.js cubre toda la interactividad.
- **Sin auth, sin `users` table, sin deploy a producción**

---

## Data flow: identificación de un pez

```
┌─────────────────────────────────────────────────────────────────────┐
│  USUARIO (navegador)                                                │
│  1. Abre http://localhost:8000                                       │
│  2. Click "Iniciar" → pantalla de escaneo                            │
│  3. Elige modo: pez (foto) o etiqueta (OCR)                          │
│  4. Sube foto del pez o de la etiqueta                               │
│  5. Ve la especie detectada + nombre común ES + % confianza          │
│  6. Confirma → ficha con tabs (Nutrición / Para ti / Prep / Sosten.) │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  HTTP LAYER (routes/web.php → ScanController / ScanImageController)  │
│  GET  /                          → ScanController::home              │
│  GET  /scan                      → ScanController::create            │
│  POST /scan                      → ScanController::store       [ai]   │
│  POST /scan/label                → ScanController::storeLabel   [ai]  │
│  GET  /scan/{uuid}/confirm       → ScanController::confirm           │
│  POST /scan/{uuid}/confirm       → ScanController::confirmStore      │
│  GET  /scan/{uuid}/image         → ScanImageController::show         │
│  POST /scan/{uuid}/rescan        → ScanController::rescan      [ai]  │
│  GET  /species/{slug}            → ScanController::show              │
│  POST /species/{slug}/explain    → ScanController::explain     [ai]  │
│                                                                     │
│  [ai] = middleware throttle:ai (10 req/min por IP)                  │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  APPLICATION LAYER                                                  │
│  IdentifySpeciesAction::execute($imagePath) → IdentificationResult   │
│  └─ inyecta SpeciesIdentifier (OpenRouterVisionAdapter)              │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  DOMAIN LAYER                                                       │
│  Contracts/SpeciesIdentifier (interface)                             │
│  └─ OpenRouterVisionAdapter (cloud, visión)                          │
│     ├─ identify()        → IdentificationResult (pez desde foto)     │
│     ├─ identifyFromLabel() → ?string  (etiqueta → nombre común)      │
│     └─ generateText()    → ?string  (explicación personalizada)      │
│                                                                     │
│  ParsesVisionResponse (trait)                                       │
│  └─ parseResponse(): convierte "Scientific (English), conf" → DTO   │
│                                                                     │
│  SpeciesLabelMapper                                                 │
│  └─ map(): nombre común etiqueta → nombre científico                 │
│                                                                     │
│  SpeciesTranslations::toSpanish() (mapeo EN→ES, 35+ especies)      │
│  └─ fallback cuando la IA no devuelve "ES:" estructurado             │
│                                                                     │
│  DTOs/IdentificationResult                                           │
│  ├─ scientificName, commonName, commonNameLocal                      │
│  ├─ confidence, regionalNames                                        │
│  └─ candidates[] (top 3)                                            │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  STORAGE                                                            │
│  storage/app/private/scan-uploads/{uuid}.{ext}   (la foto, privado)  │
│                                                                     │
│  Cache (driver=database, TTL variables):                             │
│  ├─ scan.{uuid}.result                  (10 min)  ← IdentificationResult │
│  ├─ scan.{uuid}.image                   (10 min)  ← path privado        │
│  ├─ scan.{uuid}.mode                    (10 min)  ← 'fish' | 'label'    │
│  ├─ scan.{uuid}.error                   (10 min)  ← mensaje de error    │
│  ├─ scan.{uuid}.reference_image         (30 min)  ← URL Wikipedia       │
│  ├─ species.{slug}.result               (30 min)  ← datos para la ficha │
│  ├─ explain.{slug}                      (24 h)    ← texto IA explicativo│
│  ├─ wikipedia.image.{scientificName}    (60 min)  ← caché Wikipedia     │
│  └─ usda.food.{es-name}[.{md5}]         (24 h)    ← caché USDA FDC     │
└─────────────────────────────────────────────────────────────────────┘
```

### Flujo de la ficha (`GET /species/{slug}`)

1. Lee `species.{slug}.result` de cache.
2. `loadOrFetchNutrition()`:
   1. Busca en `SpeciesNutritionSeed` (curado FEN, 52 especies) — **camino principal**.
   2. Si no está, busca `nutrition_profiles` por `scientific_name`.
   3. Si tampoco, llama a `FoodDataCentralService` (USDA FDC API) — **fallback**.
   4. Si devuelve datos, persiste en `nutrition_profiles` para la próxima vez.
3. `NutritionAdvisor::recommendAll($nutrition)` → array de recomendaciones universales + todas las reglas por objetivo (deportista, perder peso, embarazo, sostenibilidad). Dedupica por título.
4. Renderiza `pages/species.blade.php` con tabs.

### Flujo de "explicación personalizada" (`POST /species/{slug}/explain`)

Solo se invoca cuando el usuario hace click en 🪄 "Quiero una explicación personalizada". Construye un prompt con los datos nutricionales + mercurio y pide a `OpenRouterVisionAdapter::generateText()` que redacte 2-3 párrafos. Cache 24 h por especie.

---

## IA: OpenRouter

**Binding** en `app/Providers/AppServiceProvider.php`:

```php
$this->app->bind(SpeciesIdentifier::class, fn () => new OpenRouterVisionAdapter(
    apiKey: env('OPENROUTER_API_KEY'),
    model: env('OPENROUTER_MODEL', 'nvidia/nemotron-nano-12b-v2-vl:free'),
));

$this->app->bind(OpenRouterVisionAdapter::class, fn () => new OpenRouterVisionAdapter(
    apiKey: env('OPENROUTER_API_KEY'),
    model: env('OPENROUTER_MODEL', 'nvidia/nemotron-nano-12b-v2-vl:free'),
));
```

**Configuración vía `.env`:**

```env
OPENROUTER_API_KEY=sk-or-v1-xxxxxxxx
OPENROUTER_MODEL=nvidia/nemotron-nano-12b-v2-vl:free
```

**Rate limit** (en `AppServiceProvider::boot()`):

```php
RateLimiter::for('ai', fn ($r) => Limit::perMinute(10)->by($r->ip()));
```

Aplicado a `POST /scan`, `POST /scan/label`, `POST /scan/{uuid}/rescan`, `POST /species/{slug}/explain`.

**Modelos gratuitos en OpenRouter verificados:**
- `nvidia/nemotron-nano-12b-v2-vl:free` ← default, visión
- `google/gemma-3-27b-it:free` (visión)
- `qwen/qwen-2-vl-7b-instruct:free` (chino, fuerte en detalles)
- `openrouter/free` (auto-router)

---

## Modos de escaneo

### Foto de pez (fish mode)
POST /scan → `ScanController::store`:
- Valida con `ScanImageRequest` (image, mimes:jpeg,jpg,png,webp, max:8192)
- Guarda en `storage/app/private/scan-uploads/{uuid}.{ext}`
- `IdentifySpeciesAction::execute()` → `OpenRouterVisionAdapter::identify()`
- Resultado cacheado en `scan.{uuid}.result`

### Foto de etiqueta (label mode)
POST /scan/label → `ScanController::storeLabel`:
- Misma validación y guardado
- `OpenRouterVisionAdapter::identifyFromLabel()` → nombre común en ES
- `SpeciesLabelMapper::map()` → nombre científico
- Si no se reconoce especie → error cacheado, vista muestra mensaje

### Reintento
POST /scan/{uuid}/rescan → `ScanController::rescan`:
- Lee modo (fish|label) de cache
- Delega a `rescanFish()` o `rescanLabel()` según modo
- Actualiza cache `scan.{uuid}.result` y limpia `scan.{uuid}.error`

---

## Estructura de carpetas

```
app/
├── Domain/
│   ├── Ai/
│   │   ├── Adapters/OpenRouterVisionAdapter.php
│   │   ├── Contracts/SpeciesIdentifier.php
│   │   ├── DTOs/IdentificationResult.php
│   │   ├── Exceptions/IdentificationFailedException.php
│   │   ├── Models/AiGeneration.php
│   │   ├── ParsesVisionResponse.php           (trait)
│   │   ├── SpeciesLabelMapper.php             (etiqueta → científico)
│   │   └── SpeciesTranslations.php            (EN→ES, 35+ especies)
│   ├── Nutrition/
│   │   ├── Models/NutritionProfile.php
│   │   ├── NutritionAdvisor.php               (reglas deterministas)
│   │   ├── SpeciesNutritionSeed.php           (52 especies FEN curadas)
│   │   └── UserGoal.php                       (enum)
│   ├── Recommendation/
│   │   └── Models/Recommendation.php
│   └── Species/
│       └── Models/{Species.php, SpeciesImage.php}
├── Application/Actions/IdentifySpeciesAction.php
├── Console/Commands/
│   ├── AiTestCommand.php                      (php artisan ai:test <img>)
│   └── PurgeOldScansCommand.php               (php artisan scans:purge)
├── Http/
│   ├── Controllers/
│   │   ├── ScanController.php                 (home/scan/confirm/species/explain)
│   │   └── ScanImageController.php            (sirve fotos privadas con gate de cache)
│   └── Requests/ScanImageRequest.php
├── Providers/AppServiceProvider.php           (binding OpenRouter + RateLimiter 'ai')
└── Services/
    ├── FoodDataCentralService.php             (USDA FDC, fallback nutricional)
    └── WikipediaService.php                   (imagen referencia, cache 60 min)

resources/
├── css/app.css                                (design system + Tailwind 4)
├── js/{app.js, bootstrap.js}                  (axios setup, no usado actualmente)
└── views/
    ├── components/
    │   ├── layouts/app.blade.php              (shell con header + main + slot)
    │   └── loading.blade.php                  (spinner SVG)
    ├── errors/429.blade.php                   (rate-limit ai)
    ├── pages/{home,scan,confirm,species}.blade.php
    ├── partials/recommendations-list.blade.php
    └── welcome.blade.php                      (default de Laravel, NO se usa)

public/
├── favicon.svg
├── svg/{camera,fishing,bill}.svg
├── demo/peix_scanner_demo.gif                 (gif del README)
└── build/                                     (assets generados por Vite)

storage/app/private/scan-uploads/              (fotos de usuarios, privado)
```

---

## Layout app shell

```html
<body class="h-full overflow-hidden bg-slate-50">
  <div class="flex flex-col h-full">
    <header class="bg-gradient-to-r from-slate-900 to-slate-800 text-white shrink-0">
      <!-- logo + título, full-width -->
    </header>
    <main class="flex-1 overflow-y-auto bg-slate-50 min-h-0">
      <div class="mx-auto w-full max-w-screen-sm px-4 pt-6 pb-20">
        {{ $slot }}
      </div>
    </main>
  </div>
  <!-- @livewireStyles / @livewireScripts en head/body -->
</body>
```

- Header: `full-width`, fondo oscuro gradiente
- Main: `flex-1 overflow-y-auto`, scroll interno, contenido centrado en 640px
- Footer: **desactivado temporalmente** (en móvil pisaba el contenido del scan). Ver comentario en `app.blade.php`.

---

## Comandos útiles

```bash
# Levantar la app
php artisan serve              # backend en :8000
pnpm run dev                    # HMR del frontend (Vite)

# Probar la IA directamente
php artisan ai:test storage/app/test-images/atlantic-salmon.jpg

# Mantenimiento
php artisan scans:purge                      # borra uploads >24h (default)
php artisan scans:purge --older-than=7d
php artisan scans:purge --older-than=24h --dry-run

# Tests
./vendor/bin/pest                          # todos los tests
./vendor/bin/pest tests/Feature/ScanFlowTest.php
./vendor/bin/pest --filter="identifies"

# Calidad
./vendor/bin/pint                          # formatea
./vendor/bin/phpstan analyse               # análisis estático (level 5)

# Laravel
php artisan route:list                     # ver las 9 rutas
php artisan migrate:fresh                  # resetear DB (BORRA DATOS)
php artisan view:clear                     # limpiar cache de vistas
php artisan cache:clear                    # limpiar cache de aplicación
php artisan optimize:clear                 # limpiar todo
```

---

## Comandos de git

```bash
# Crear rama nueva desde develop
git checkout develop
git checkout -b feature/nombre-descriptivo

# Commitear en español
git add .
git commit -m "Descripción en una línea"

# Push y PR
git push -u origin feature/nombre-descriptivo
gh pr create --base develop --head feature/nombre-descriptivo

# Sincronizar develop en tu rama
git fetch origin
git rebase origin/develop
```

**Convención:**
- Mensajes de commit en **español**, una línea, descriptivos (no "fix")
- **No push** a `develop` (está protegido contra force-push/delete)
- **No PRs automáticos**: tú los apruebas en GitHub web

---

## Limitaciones conocidas

- **Sin auth / sin deploy**: solo funciona local. El "ownership" de las imágenes se aproxima por UUID en cache (cualquiera con el UUID puede ver la imagen; el threat model es equivalente al del resto del flujo cache-as-session).
- **Tabs Sostenibilidad y Preparación**: la pestaña Sostenibilidad está **vacía** (sin contenido renderizado). Preparación muestra placeholder. Pendiente: integrar FishBase + generación de texto.
- **Tab "Para ti"**: ahora muestra TODAS las recomendaciones (universales + por objetivo combinadas y deducadas). Sin selector reactivo.
- **Cache en DB**: CACHE_STORE=database, así que sobrevive a reinicios del server.
- **Storage crece**: las fotos se purgan con `php artisan scans:purge` (no hay cron configurado todavía).
- **Tasa de acierto del modelo**: ~80% con peces enteros, ~60-70% con filetes.
- **CSS recompilado manualmente**: si modificas `resources/css/app.css`, ejecuta `pnpm run build` (o `pnpm run dev` para HMR).