# Peix Scanner — Manual de funcionamiento

App web que reconoce la especie de un filete de pescado a partir de una foto. Subes la imagen, una IA local (Ollama + llama3.2-vision) te dice qué pez es, te muestra una ficha con tabs de Nutrición, Sostenibilidad y Preparación.

---

## Data Flow: entrada de datos

```
┌─────────────────────────────────────────────────────────────────────┐
│  USUARIO                                                            │
│  1. Abre http://localhost:8000                                       │
│  2. Click "Escanear"                                                │
│  3. Sube una foto del filete                                        │
│  4. Confirma la especie detectada                                   │
│  5. Lee la ficha con tabs                                           │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  HTTP LAYER                                                         │
│  routes/web.php → ScanController                                    │
│  ├─ GET  /          → home()        → pages/home.blade.php          │
│  ├─ GET  /scan      → create()      → pages/scan.blade.php          │
│  ├─ POST /scan      → store()       → ScanImageRequest + action     │
│  ├─ GET  /scan/{id} → confirm()     → pages/confirm.blade.php       │
│  ├─ POST /scan/{id} → confirmStore()                                 │
│  └─ GET  /species/{slug} → show()  → pages/species.blade.php       │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  APPLICATION LAYER                                                  │
│  app/Application/Actions/IdentifySpeciesAction.php                  │
│  └─ Orquesta el caso de uso: recibe ruta de imagen, devuelve        │
│     IdentificationResult                                            │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  DOMAIN LAYER (reglas de negocio)                                   │
│  app/Domain/Ai/                                                     │
│  ├─ Contracts/SpeciesIdentifier          (interfaz)                 │
│  ├─ DTOs/IdentificationResult             (value object)             │
│  └─ Exceptions/IdentificationFailedException                        │
│                                                                     │
│  app/Domain/Species/Models/                                       │
│  ├─ Species.php                          (pez)                      │
│  └─ SpeciesImage.php                     (foto subida)               │
│                                                                     │
│  app/Domain/Nutrition/Models/NutritionProfile.php                   │
│  app/Domain/Recommendation/Models/Recommendation.php                │
│  app/Domain/Ai/Models/AiGeneration.php                              │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  INFRASTRUCTURE LAYER                                               │
│  app/Domain/Ai/Adapters/OllamaVisionAdapter.php                     │
│  └─ Implementación de SpeciesIdentifier                            │
│  └─ HTTP POST a http://localhost:11434/api/generate                 │
│  └─ Envía la imagen en base64 + prompt                              │
│  └─ Recibe "Pleuronectes platessa (European plaice), 0.98"          │
│  └─ Parsea con regex: /([A-Z][a-z]+)\s*\(([^)]+)\)\s*,\s*([0-9.]+)/ │
│  └─ Devuelve IdentificationResult                                   │
│                                                                     │
│  app/Providers/AppServiceProvider.php                              │
│  └─ Bind SpeciesIdentifier → OllamaVisionAdapter                   │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  EXTERNAL                                                           │
│  Ollama local (http://localhost:11434)                              │
│  └─ Modelo: llama3.2-vision:11b (7.8 GB)                            │
│  └─ Opcional: llava:7b (4.7 GB), más ligero pero menos preciso      │
└─────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  STORAGE                                                            │
│  storage/app/public/scan-uploads/{uuid}.jpg                         │
│  └─ Vinculado vía public/storage (symlink)                          │
│  └─ Accesible en el navegador vía /storage/scan-uploads/...        │
│                                                                     │
│  Cache (en memoria, 10 min)                                         │
│  └─ scan.{uuid}.result  → {species, common_name, confidence}        │
│  └─ scan.{uuid}.image   → ruta del archivo                          │
│  └─ scan.{uuid}.error   → mensaje si Ollama falló                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Flujo end-to-end (paso a paso)

### 1. Home (`GET /`)
**Vista:** `resources/views/pages/home.blade.php`

Botón "Escanear" → dirige a `/scan`.

### 2. Subir imagen (`GET /scan`)
**Vista:** `resources/views/pages/scan.blade.php`

Formulario con `<input type="file" accept="image/*" capture="environment">`.

`capture="environment"` activa la cámara trasera en móvil. En desktop abre el file picker normal. Después de elegir, Alpine.js muestra un preview.

### 3. Validar y guardar (`POST /scan`)
**Controller:** `app/Http/Controllers/ScanController.php` → método `store()`

```
Recibe UploadedFile
    │
    ├─→ ScanImageRequest valida:
    │   - requerido
    │   - tipo: image
    │   - mimes: jpeg, jpg, png, webp
    │   - max: 8192 KB (8 MB)
    │
    ├─→ set_time_limit(180)
    │   (Ollama puede tardar >30s en cold start)
    │
    ├─→ Storage::disk('public')->makeDirectory('scan-uploads')
    │
    ├─→ $file->storeAs('scan-uploads', "{uuid}.{ext}", 'public')
    │   Guarda en storage/app/public/scan-uploads/{uuid}.jpg
    │
    ├─→ IdentifySpeciesAction->execute($absolutePath)
    │   │
    │   └─→ OllamaVisionAdapter->identify($path)
    │       │
    │       ├─→ base64_encode(file_get_contents($path))
    │       │
    │       ├─→ HTTP POST localhost:11434/api/generate
    │       │   body: {
    │       │     model: "llama3.2-vision:11b",
    │       │     prompt: "Identifica el pez. Responde en formato:
    │       │              'Género especie (Nombre común), confianza'",
    │       │     images: ["data:image/jpeg;base64,..."]
    │       │   }
    │       │
    │       ├─→ Ollama responde (ejemplo):
    │       │   "Pleuronectes platessa (European plaice), 0.98"
    │       │
    │       ├─→ Parsea con regex (scan todas las líneas)
    │       │
    │       └─→ Devuelve IdentificationResult
    │           {scientificName: "pleuronectes platessa",
    │            commonName: "European plaice",
    │            confidence: 0.98}
    │
    ├─→ Cache::put("scan.{uuid}.result", {resultado}, 10min)
    ├─→ Cache::put("scan.{uuid}.image", ruta, 10min)
    │
    └─→ redirect(/scan/{uuid}/confirm)
```

### 4. Confirmar (`GET /scan/{id}/confirm`)
**Vista:** `resources/views/pages/confirm.blade.php`

Lee del cache:
- resultado (o error)
- imagen subida (genera URL con `Storage::url()`)

Muestra:
- Foto arriba
- "Detectado: Pleironectes platessa"
- Barra de confianza con color:
  - Verde ≥ 75%
  - Ámbar ≥ 50%
  - Rojo < 50%
- Botón "Sí, es este" (POST)
- Link "No es este, reintentar" (home)

### 5. Confirmar POST (`POST /scan/{id}/confirm`)
**Controller:** método `confirmStore()`

```
$resultado = Cache::get("scan.{uuid}.result")
$slug = "european-plaice__pleuronectes-platessa"
//         common slug __   scientific slug
redirect(/species/{slug})
```

### 6. Ficha (`GET /species/{slug}`)
**Vista:** `resources/views/pages/species.blade.php`

Parsea el slug (separador `__`):
- Parte anterior: common name
- Parte posterior: scientific name

Muestra:
- Nombre común capitalizado (ucwords)
- Nombre científico (ucfirst, italic)
- 3 tabs con Alpine.js:
  - Nutrición (placeholder por ahora)
  - Sostenibilidad (placeholder por ahora)
  - Preparación (placeholder por ahora)

---

## Ficheros importantes uno por uno

### Domain (reglas de negocio)

| Fichero | Qué hace |
|---|---|
| `app/Domain/Ai/Contracts/SpeciesIdentifier.php` | Interfaz: "algo que identifica peces desde imagen". Método `identify(string $path): IdentificationResult` |
| `app/Domain/Ai/DTOs/IdentificationResult.php` | Value object inmutable. Contiene `scientificName`, `commonName`, `confidence` (0-1), `candidates`. Tiene helper `isHighConfidence()` (≥0.75) |
| `app/Domain/Ai/Exceptions/IdentificationFailedException.php` | Excepción específica cuando falla la identificación (HTTP error, modelo no responde, respuesta no parseable) |
| `app/Domain/Species/Models/Species.php` | Entidad "pez". Nombre común, nombre científico (único), descripción. UUID PK. Relaciones a imágenes, perfil nutricional y recomendaciones |
| `app/Domain/Species/Models/SpeciesImage.php` | Foto subida. FK a species. Sin updated_at |
| `app/Domain/Nutrition/Models/NutritionProfile.php` | Perfil nutricional. FK 1:1 a species (unique). Calorías, proteína, omega 3, grasas, vitaminas (JSON) |
| `app/Domain/Recommendation/Models/Recommendation.php` | Recomendación. FK a species. Texto + idioma (default 'es') |
| `app/Domain/Ai/Models/AiGeneration.php` | Registro de llamada a IA. Proveedor, modelo, prompt_hash, response, execution_time |

### Application (casos de uso)

| Fichero | Qué hace |
|---|---|
| `app/Application/Actions/IdentifySpeciesAction.php` | Orquesta el caso de uso. Wrapper delgado sobre `SpeciesIdentifier`. Aquí añadiremos logs, métricas, caché cuando crezca |

### Http (la capa HTTP)

| Fichero | Qué hace |
|---|---|
| `app/Http/Controllers/ScanController.php` | 5 métodos (home, create, store, confirm, confirmStore, show). Hace `set_time_limit(180)` porque Ollama tarda |
| `app/Http/Requests/ScanImageRequest.php` | Valida el upload: requerido, imagen, JPG/PNG/WebP, max 8 MB |

### Infrastructure (cómo se conectan las piezas)

| Fichero | Qué hace |
|---|---|
| `app/Domain/Ai/Adapters/OllamaVisionAdapter.php` | Implementa `SpeciesIdentifier`. Convierte la imagen a base64, llama a Ollama, parsea la respuesta |
| `app/Providers/AppServiceProvider.php` | Bind: cuando alguien pide `SpeciesIdentifier`, devuelve `OllamaVisionAdapter`. Lee `OLLAMA_HOST` y `OLLAMA_MODEL` del .env |

### Console (herramientas CLI)

| Fichero | Qué hace |
|---|---|
| `app/Console/Commands/AiTestCommand.php` | Comando `php artisan ai:test imagen.jpg`. Prueba el adapter sin pasar por el navegador. Muestra tabla con el resultado |

### Vistas (lo que ve el usuario)

| Fichero | Qué muestra |
|---|---|
| `resources/views/components/layouts/app.blade.php` | Layout base: header con "Peix Scanner", slot para el contenido |
| `resources/views/pages/home.blade.php` | Landing con botón "Escanear" |
| `resources/views/pages/scan.blade.php` | Formulario de upload. Preview con Alpine.js |
| `resources/views/pages/confirm.blade.php` | Confirmación: foto, especie detectada, barra de confianza, botones Sí/No |
| `resources/views/pages/species.blade.php` | Ficha: nombre común, nombre científico, 3 tabs |

### Configuración

| Fichero | Qué contiene |
|---|---|
| `routes/web.php` | Las 6 rutas de la webapp |
| `.env` | Variables de entorno. Importantes: `OLLAMA_HOST=http://localhost:11434`, `OLLAMA_MODEL=llama3.2-vision:11b` |
| `config/database.php` | PostgreSQL en `127.0.0.1:5432`, DB `peix_scanner`, user `postgres`, password `postgres` |

### Datos

| Carpeta | Qué contiene |
|---|---|
| `database/migrations/` | 5 migraciones: `species`, `species_images`, `nutrition_profiles`, `recommendations`, `ai_generations`. UUID PKs, snake_case, FK `*_id` |
| `database/factories/` | 5 factories: `SpeciesFactory`, `SpeciesImageFactory`, `NutritionProfileFactory`, `RecommendationFactory`, `AiGenerationFactory` |

### Tests

| Fichero | Qué cubre |
|---|---|
| `tests/Feature/SpeciesSliceTest.php` (6 tests) | Dominio: persistencia, cascade, UUID, unique scientific_name |
| `tests/Feature/ScanFlowTest.php` (5 tests) | Flujo HTTP: upload, validación, error de IA, redirect a ficha |
| `tests/Feature/ScanStorageTest.php` (1 test) | El archivo se guarda y el path es válido |

Total: **12 tests, 50 assertions**.

---

## Capas de la arquitectura

```
app/
├── Domain/         Reglas de negocio (no sabe nada de Laravel)
├── Application/    Orquesta casos de uso
├── Http/           Recibe HTTP, devuelve respuestas
├── Console/        Comandos artisan
├── Providers/      Inyección de dependencias (interfaces → implementaciones)
└── Models/         (no se usa; los models están en cada Domain)

resources/
├── views/          Plantillas Blade
├── css/            Tailwind base
└── js/             Alpine.js, Livewire

routes/
└── web.php         Las URLs

tests/
├── Feature/        Tests de integración
└── Unit/           (vacío; los unit tests están en Feature)

database/
├── migrations/     Schema
├── factories/      Datos de prueba
└── seeders/        (vacío por ahora)
```

**Regla de dependencias:** HTTP depende de Application, Application depende de Domain, Infrastructure implementa contratos de Domain. Domain no sabe nada de HTTP ni de Laravel en general.

---

## Cómo extender

### Cambiar la IA (ej: de Ollama a OpenAI)

1. Crear `app/Domain/Ai/Adapters/OpenAiVisionAdapter.php` que implemente `SpeciesIdentifier`
2. Cambiar el binding en `AppServiceProvider`:
   ```php
   $this->app->bind(SpeciesIdentifier::class, fn() => new OpenAiVisionAdapter(env('OPENAI_API_KEY')));
   ```
3. Añadir `OPENAI_API_KEY=sk-...` al `.env`
4. **El resto del código no cambia.** El controller sigue llamando al action, que sigue llamando a la interfaz

### Añadir un nuevo bounded context (ej: Trazabilidad)

1. Crear `app/Domain/Traceability/` con Model, Value Objects, Repositorio si hace falta
2. Crear migración `database/migrations/..._create_traceability_records_table.php`
3. Crear factory
4. Tests Pest del nuevo contexto
5. Conectar al flujo desde el controller (después de confirmar la especie, redirigir a la ficha de trazabilidad)

### Cambiar el modelo de Ollama

Solo cambia `.env`:
```
OLLAMA_MODEL=llava:7b          # más ligero, menos preciso
OLLAMA_MODEL=llama3.2-vision:11b  # balance (recomendado)
OLLAMA_MODEL=llama3.2-vision:90b # más preciso, requiere más RAM
```

---

## Comandos útiles

```bash
# Levantar la app
php artisan serve              # backend en :8000
pnpm run dev                    # HMR del frontend (opcional)

# Probar la IA directamente sin navegador
php artisan ai:test storage/app/test-images/sample-fish.jpeg

# Tests
./vendor/bin/pest                          # todos los tests
./vendor/bin/pest tests/Feature/ScanFlowTest.php   # un archivo
./vendor/bin/pest --filter="identifies"    # por nombre
./vendor/bin/pest --stop-on-failure       # para en el primer fallo

# Calidad de código
./vendor/bin/pint                          # formatea (Pint, estilo Laravel)
./vendor/bin/phpstan analyse               # análisis estático nivel 5

# Laravel
php artisan route:list                     # ver todas las rutas
php artisan migrate:fresh                  # resetear DB (BORRA DATOS)
php artisan storage:link                   # crear symlink de storage
php artisan view:clear                     # limpiar caché de vistas
```

---

## Limitaciones conocidas

1. **Modelo inconsistente**: llama3.2-vision:11b a veces da respuestas distintas para la misma imagen. Variabilidad ~10-15% entre llamadas.
2. **Filetes difíciles**: visualmente es complicado distinguir especies similares (ej: merluza vs bacalao).
3. **Cold start de Ollama**: primera llamada tarda ~30s, las siguientes ~10s.
4. **Storage temporal**: las fotos subidas se quedan en `storage/app/public/scan-uploads/`. En producción habría que limpiar o moverlas.
5. **Sin persistencia de scans**: usamos cache (10 min). Al cerrar el servidor, se pierden.
6. **Sin auth ni user_id**: MVP sin login. La ficha es anónima (decidido al inicio).

---

## Stack

- PHP 8.4 + Laravel 12
- PostgreSQL 17 (DBngin) en `127.0.0.1:5432`, DB `peix_scanner`
- Tailwind 4 + Vite 7
- Pest 3.8 + Pint + Larastan nivel 5
- Ollama local + llama3.2-vision:11b

---

## Roadmap

- **Fase 0** ✓ Bootstrap Laravel 12 + PostgreSQL + Pest + Pint + PHPStan
- **Fase 1** ✓ Dominio + DB (5 modelos, 5 migraciones, 12 tests)
- **Fase 2** ✓ UI scaffold + scanner web (4 vistas, layout, controller, routes)
- **Fase 3** ✓ Ollama adapter + IdentifySpeciesAction + ScanFlow
- **Fase 4** → Deploy Railway, datos externos (FishBase), InsightGenerator, ficha rellena
- **Fase 5** → Sostenibilidad, alternativas locales, auth, favoritos
- **Fase 6** → NativePHP si la demo gana y queremos publicar en stores
