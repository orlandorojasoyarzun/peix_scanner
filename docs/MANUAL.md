# Peix Scanner — Manual de funcionamiento

App web que reconoce la especie de un filete de pescado a partir de una foto. Subes una foto, una IA (local o cloud) te dice qué pez es, te muestra una ficha con tabs de Nutrición, Sostenibilidad y Preparación.

Estado: MVP funcional en `develop`. Probado localmente con PostgreSQL 17 (DBngin) y Ollama 0.5+ en macOS.

---

## Stack actual

- **Backend**: Laravel 12 + PHP 8.4
- **Frontend**: Blade + Alpine.js + Livewire 4 + Tailwind 4 (Vite 7, pnpm)
- **Database**: PostgreSQL 17 vía DBngin
- **Testing**: Pest 3.8 + Pint + Larastan
- **IA**: Ollama (local) **o** OpenRouter (cloud) — binding condicional por env
- **Sin auth, sin `users` table, sin deploy a producción todavía**

---

## Data flow: entrada de datos

```
┌─────────────────────────────────────────────────────────────────────┐
│  USUARIO (navegador)                                                │
│  1. Abre http://localhost:8000                                       │
│  2. Click "Escanear"                                                │
│  3. Sube una foto del pez (cámara o file picker)                    │
│  4. Ve la especie detectada con % de confianza                      │
│  5. Click "Sí, es este" para confirmar                              │
│  6. Ve la ficha con la foto + nombre común + tabs                   │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  HTTP LAYER (routes/web.php → ScanController)                        │
│  GET  /              → home()       → pages/home.blade.php           │
│  GET  /scan          → create()     → pages/scan.blade.php           │
│  POST /scan          → store()      → ScanImageRequest + action      │
│  GET  /scan/{uuid}   → confirm()    → pages/confirm.blade.php        │
│  POST /scan/{uuid}   → confirmStore()                                │
│  GET  /species/{slug} → show()      → pages/species.blade.php        │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  APPLICATION LAYER (app/Application/Actions/)                        │
│  IdentifySpeciesAction::execute($imagePath): IdentificationResult   │
│  └─ Orquesta: el adapter (Ollama u OpenRouter) hace la llamada      │
│     y el resultado se mapea al DTO con campos localizados            │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  DOMAIN LAYER (app/Domain/Ai/)                                       │
│  Contracts/SpeciesIdentifier (interface)                            │
│  ├─ OllamaVisionAdapter        (dev, local)                          │
│  └─ OpenRouterVisionAdapter    (prod, cloud)                         │
│                                                                      │
│  DTOs/IdentificationResult                                            │
│  ├─ scientificName, commonName, commonNameLocal                      │
│  ├─ confidence, regionalNames                                        │
│  └─ candidates[] (top 3 alternativos)                                │
│                                                                      │
│  SpeciesTranslations::toSpanish() (mapeo fallback 35+ especies)    │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  STORAGE                                                             │
│  storage/app/public/scan-uploads/{uuid}.{ext}   (la foto)            │
│  Cache (en memoria, 30 min):                                         │
│  ├─ scan.{uuid}.result                                              │
│  └─ species.{slug}.result  (con image_path)                          │
└─────────────────────────────────────────────────────────────────────┘
```

---

## IA dual: Ollama ↔ OpenRouter

**Binding condicional** en `app/Providers/AppServiceProvider.php`:

```php
if (env('OPENROUTER_API_KEY') !== '') {
    return new OpenRouterVisionAdapter(
        apiKey: env('OPENROUTER_API_KEY'),
        model: env('OPENROUTER_MODEL', 'nvidia/nemotron-nano-12b-v2-vl:free'),
    );
}

return new OllamaVisionAdapter(
    host: env('OLLAMA_HOST', 'http://localhost:11434'),
    model: env('OLLAMA_MODEL', 'llama3.2-vision:11b'),
);
```

**Configuración vía `.env`:**

```env
# Local (Ollama)
OPENROUTER_API_KEY=
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=llama3.2-vision:11b

# Cloud (OpenRouter)
OPENROUTER_API_KEY=sk-or-v1-xxxxxxxx
OPENROUTER_MODEL=nvidia/nemotron-nano-12b-v2-vl:free
```

**Modelos gratuitos en Open Router (verificados):**
- `nvidia/nemotron-nano-12b-v2-vl:free` ← default, especializado en visión
- `google/gemma-4-31b-it:free` (visión, más grande)
- `qwen/qwen-2-vl-7b-instruct:free` (chino, fuerte en detalles visuales)
- `openrouter/free` (auto-router)

---

## Prompt compartido (curado)

`OllamaVisionAdapter::PROMPT` (constante pública, también la usa `OpenRouterVisionAdapter`):

1. Identifica solo peces de la **lista curada de 35+ especies comercializables** en Mediterráneo/Atlántico
2. Lista organizada por categorías: blancos magros, azules, piscifactoría, cefalópodos, crustáceos, moluscos, agua dulce
3. Devuelve **1 candidato top** con: `Scientific name (English), confidence` + `ES:` nombre castellano + `ALT:` nombres regionales (Gallego/Catalán/Vasco)

**Si el modelo no devuelve el formato estructurado**, `SpeciesTranslations::toSpanish()` (35+ mapeos) hace fallback al nombre en castellano.

---

## Estructura de carpetas (pragmática-modular)

```
app/
├── Domain/
│   ├── Ai/
│   │   ├── Adapters/
│   │   │   ├── OllamaVisionAdapter.php
│   │   │   └── OpenRouterVisionAdapter.php
│   │   ├── Contracts/
│   │   │   └── SpeciesIdentifier.php
│   │   └── DTOs/
│   │       └── IdentificationResult.php
│   ├── Species/Models/{Species,SpeciesImage}.php
│   ├── Nutrition/Models/NutritionProfile.php
│   ├── Recommendation/Models/Recommendation.php
│   └── Ai/Models/AiGeneration.php
├── Application/Actions/IdentifySpeciesAction.php
├── Console/Commands/AiTestCommand.php (php artisan ai:test imagen.jpg)
├── Http/
│   ├── Controllers/ScanController.php
│   └── Requests/ScanImageRequest.php
└── Providers/AppServiceProvider.php (binding condicional)

resources/
├── css/app.css (design system: flame, mint, cream, display, mono)
├── svg/
│   ├── camera.svg
│   └── fishing.svg
└── views/
    ├── components/
    │   ├── layouts/app.blade.php
    │   └── loading.blade.php
    └── pages/{home,scan,confirm,species}.blade.php

public/
├── favicon.svg
└── build/ (assets de Vite)
```

---

## Layout app shell

```html
<body class="h-screen overflow-hidden">         <!-- viewport fijo, sin scroll global -->
  <header class="bg-gradient-to-r from-slate-900 to-slate-800 text-white">  <!-- full-width -->
    <div class="mx-auto max-w-screen-sm">  <!-- contenido centrado en 640px -->
      <svg>🐟</svg>
      <span>Peix Scanner</span>
    </div>
  </header>

  <div class="mx-auto max-w-screen-sm h-full flex flex-col overflow-hidden">
    <main class="flex-1 overflow-y-auto px-4 pt-6 pb-20">  <!-- scroll INTERNO -->
      {{ $slot }}
    </main>
  </div>

  <footer class="fixed bottom-0 inset-x-0 z-10 bg-white border-t">  <!-- siempre visible -->
    MVP demo · Peix Scanner
  </footer>
</body>
```

- **Header**: `full-width` (atraviesa toda la ventana)
- **Contenedor central**: `max-w-screen-sm` (640px), `flex-1` (crece)
- **Main**: `flex-1 overflow-y-auto` (scroll **interno** si el contenido es largo)
- **Footer**: `fixed bottom-0` (siempre pegado al viewport)

---

## Comandos útiles

```bash
# Levantar la app
php artisan serve              # backend en :8000
pnpm run dev                    # HMR del frontend (opcional)

# Probar la IA directamente
php artisan ai:test storage/app/test-images/atlantic-salmon.jpg

# Tests
./vendor/bin/pest                          # todos los tests
./vendor/bin/pest tests/Feature/ScanFlowTest.php   # un archivo
./vendor/bin/pest --filter="identifies"    # por nombre
./vendor/bin/pest --stop-on-failure       # para en el primer fallo

# Calidad
./vendor/bin/pint                          # formatea
./vendor/bin/phpstan analyse               # análisis estático

# Laravel
php artisan route:list                     # ver todas las rutas
php artisan migrate:fresh                  # resetear DB (BORRA DATOS)
php artisan storage:link                   # crear symlink de storage
php artisan view:clear                     # limpiar cache de vistas
php artisan cache:clear                     # limpiar cache de aplicación
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

# Sincronizar develop en tu rama (si develop tiene cambios nuevos)
git fetch origin
git rebase origin/develop
```

**Convención:**
- Mensajes de commit en **español**, una línea
- **No push** a `develop` (está protegido)
- **No force-push** (también está protegido)
- **No PRs automáticos**: tú los apruebas en GitHub web

---

## Limitaciones conocidas (sesión actual)

- **Sin auth / sin deploy**: solo funciona local
- **Sin FishBase**: la ficha tiene placeholders en Nutrición / Sostenibilidad / Preparación
- **Tasa de acierto del modelo**: ~80% con peces enteros, ~60-70% con filetes
- **Cache en memoria**: al cerrar el server se pierde
- **CSS recompilado manualmente**: si modificas `app.css`, ejecuta `pnpm run build`
- **Tests PENDIENTES**: faltan tests para `IdentifySpeciesAction`, `OpenRouterVisionAdapter` parser, `SpeciesTranslations`
