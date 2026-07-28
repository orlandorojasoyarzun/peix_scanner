# Checklist — Peix Scanner (hackaton)

> Estado al cierre de la sesión actual. Items marcados reflejan el código real en `develop`.

## Fase 0 — Bootstrap
- [x] Laravel 12 instalado en raíz del repo
- [x] PostgreSQL 17 (DBngin) configurado en `.env` (`127.0.0.1:5432`, `peix_scanner`, `postgres/postgres`)
- [x] `php artisan key:generate` ejecutado
- [x] Livewire 4 instalado (`composer require livewire/livewire`)
- [x] Tailwind 4 vía Vite (preset oficial, sin Volt — se quedó en Livewire)
- [x] `pnpm install` ejecutado con `pnpm-lock.yaml` + `pnpm-workspace.yaml`
- [x] `pnpm run build` compila Vite sin errores (~58 KB CSS)
- [x] Pest 3.8 instalado (`composer require pestphp/pest --dev`)
- [x] Alpine.js 3.x cargado vía CDN en el layout (para directivas `x-data`, `x-show`, `@click`, `@data`)
- [x] Pint incluido en Laravel (54 files OK)
- [x] PHPStan con Larastan 3.10 instalado y configurado
- [x] Esqueleto `app/Domain/{Species,Nutrition,Recommendation,Ai}` creado
- [x] Esqueleto `app/Application/Actions/` creado
- [x] Migración default de `users` **ELIMINADA**
- [x] Migración `password_reset_tokens` **ELIMINADA**
- [x] Migración `sessions` **ELIMINADA**
- [x] `php artisan migrate` corre limpia
- [x] `.gitignore` de Laravel añadido
- [x] `docs/PLAN.md` y `docs/CHECKLIST.md` commiteados (rama `develop`)

## Fase 1 — Dominio (slice vertical)
- [x] Migración `species` (UUID, scientific_name UNIQUE, timestamps)
- [x] Migración `species_images` (UUID, FK species CASCADE, hash INDEX)
- [x] Migración `nutrition_profiles` (UUID, FK species UNIQUE 1:1, decimals, vitamins JSON)
- [x] Migración `recommendations` (UUID, FK species CASCADE, language default 'es')
- [x] Migración `ai_generations` (UUID, FK recommendation NULLABLE, provider, model, prompt_hash INDEX, response, execution_time)
- [x] Entidad `Species` + VOs (`CommonName`, `ScientificName`, `ConfidenceScore`)
- [x] Entidad `NutritionProfile` (relación 1:1 con Species)
- [x] Entidad `Recommendation` ligada a Species (sin `user_id`)
- [x] Entidad `AiGeneration` (con FK nullable a Recommendation)
- [x] Model Eloquent `Species` delgado (fillable, relations)
- [x] Model Eloquent `NutritionProfile` delgado (cast vitamins → array)
- [x] Model Eloquent `Recommendation` delgado
- [x] Model Eloquent `SpeciesImage` delgado
- [x] Model Eloquent `AiGeneration` delgado
- [x] Factories: 5 (SpeciesFactory, SpeciesImageFactory, NutritionProfileFactory, RecommendationFactory, AiGenerationFactory)

## Fase 2 — Application + HTTP
- [x] `IdentifySpeciesAction` (con método `identifyBatch()` ensemble)
- [x] Confirmación de especie en `ScanController::confirmStore()` (sin `ConfirmSpeciesAction` separada)
- [x] DTOs entre capas
- [x] Interface `SpeciesIdentifier`
- [x] `OpenRouterVisionAdapter` (cloud) — modelo `nvidia/nemotron-nano-12b-v2-vl:free`
- [x] `ScanImageRequest` (FormRequest) con validación
- [x] Controllers delgados
- [x] Layout `app.blade.php` con header full-width + footer fixed (app shell)
- [x] 4 vistas: home (con SVG fishing), scan (con SVG camera + submit guard), confirm (con loading), species (con foto + tipografía DM Serif)

## Fase 3 — IA + traducciones
- [x] **OpenRouterVisionAdapter** (cloud, NVIDIA Nemotron Nano 12B VL)
- [x] Parser multi-línea robusto que maneja `**` markdown
- [x] **Fallback de traducciones** vía `SpeciesTranslations.php` (35+ especies inglés → castellano)
- [x] `cleanSpanishName()` en ScanController para limpiar `ALT:` residual
- [x] `IdentificationResult` DTO con campos: scientificName, commonName, commonNameLocal, confidence, candidates[], regionalNames[]
- [x] Cache keys: `scan.{uuid}.result` y `species.{slug}.result` (con `image_path`)

## Fase 4 — UI / Iconos / Layout
- [x] `resources/css/app.css` con design system: variables `--color-flame`, `--color-mint`, `--color-cream`, `--color-ink`, `--font-display` (DM Serif), `--font-mono` (JetBrains Mono)
- [x] SVG **fishing** (`public/svg/fishing.svg`) — anzuelo + pez, blanco, usado en home
- [x] SVG **camera** (`public/svg/camera.svg`) — cámara, en scan
- [x] **Favicon** SVG (`public/favicon.svg`) — pez estilizado, blanco sobre fondo negro
- [x] Componente `<x-loading>` con SVG animado (círculo rotando)
- [x] Header layout: `h-screen overflow-hidden` + header arriba + contenedor con `flex-1` + main con scroll interno + footer `fixed bottom-0`
- [x] Submit guard en scan: `@submit.prevent` con `form.checkValidity()` (porque `:disabled` en input file no envía el archivo)
- [x] Sin fondo en el home (quité `bg-slate-50` para evitar el rectángulo blanco)
- [x] Footer con fondo blanco y borde superior sutil

## Fase 5 — Tests
- [x] 61 tests Pest passing (227 assertions)
  - 18 en `ScanFlowTest` (con mocks del adapter)
  - 1 en `ScanStorageTest` (verifica storage real)
  - 5 en `SpeciesSliceTest` (dominio: persistencia, cascade, UUID, unique)
  - 5 en `FoodDataCentralServiceTest` (parser, fallback, selección de especie)
  - 17 en `SpeciesNutritionSeedTest` (datos curados FEN, aliases, mercury)
  - 15 en `NutritionAdvisorTest` (UserGoal, recommendAll, dedup)
- [x] Mock del `IdentifySpeciesAction` para evitar gastar API calls en CI
- [x] Test "Salmón atlántico" valida el fallback castellano

## Fase 7 — Datos nutricionales
- [x] `SpeciesNutritionSeed` con 52 especies curadas de FEN (lubina, dorada, merluza, jurel, salmón, sardina, atún, cefalópodos, crustáceos, moluscos)
- [x] Aliases regionales (chicharro → jurel)
- [x] Notas para variantes (anchoa en salazón, dorada piscifactoría, sardina en lata)
- [x] Alerta de mercurio para grandes depredadores (pez espada, atún rojo, bonito)
- [x] `FoodDataCentralService` queda como fallback para especies no cubiertas por el seed
- [x] Vista `species.blade.php` muestra vitaminas y minerales con nombres amigables

## Fase 6 — Git workflow
- [x] Convención: **feature branches** + commits en **español** + 1 línea + PRs contra `develop`
- [x] Mensajes commit: "Mejoras en la IA y traducciones", "Mejoras visuales y de iconos", "Tests actualizados"
- [x] PRs mergeadas: #9, #10, #11, #12, #13, #14 (footer), #15 (docs), #16 (quality), #17 #18 (label scan UX), #19 (remove Ollama)
- [x] **`develop` protegido mínimo**: `allow_force_pushes: false`, `allow_deletions: false`, sin status checks requeridos, sin reviews requeridos
- [x] Usuario aprueba PRs en GitHub web
- [x] Sin push a develop (solo feature branches)

## Fase 8 — Próximos pasos (no hechos aún)
- [ ] Tabs Sostenibilidad y Preparación con datos reales
- [ ] Deploy en Railway
- [ ] PWA manifest + service worker (instalable en móvil)
- [ ] NativePHP for Mobile (Fase 6 del plan original)
- [ ] CI con GitHub Actions
- [ ] Integrar `users` con auth para historial de scans
