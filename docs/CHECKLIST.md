# Checklist — Peix Scanner (hackaton)

> Marcar con `[x]` al cerrar cada ítem. Propietario: tú.

## Fase 0 — Bootstrap
- [ ] Laravel 12 instalado en raíz del repo
- [ ] PostgreSQL configurado en `.env`
- [ ] `php artisan key:generate` ejecutado
- [ ] Livewire instalado (`composer require livewire/livewire`)
- [ ] Volt listo (`composer require laravel/volt`) o alternativa equivalente
- [ ] Tailwind vía preset oficial (Vite + pnpm)
- [ ] `pnpm install` ejecutado
- [ ] `pnpm run dev` levanta Vite sin errores
- [ ] Pest instalado (`composer require pestphp/pest --dev`)
- [ ] Pint instalado (incluido en Laravel)
- [ ] PHPStan instalado (`composer require larastan/larastan --dev`)
- [ ] `phpstan.neon` configurado
- [ ] Esqueleto `app/Domain/{Species,Nutrition,Recommendation,Ai}` creado
- [ ] Esqueleto `app/Application/Actions/` creado
- [ ] Migración default de `users` ELIMINADA
- [ ] Migración `password_reset_tokens` ELIMINADA
- [ ] Migración `sessions` ELIMINADA
- [ ] `php artisan migrate` corre limpia con tablas vacías o solo de Laravel
- [ ] `.gitignore` de Laravel añadido
- [ ] `docs/PLAN.md` y `docs/CHECKLIST.md` commiteados

## Fase 1 — Dominio (slice vertical)
- [ ] Migración `species`
- [ ] Migración `species_images`
- [ ] Migración `nutrition_profiles`
- [ ] Migración `recommendations`
- [ ] Migración `ai_generations`
- [ ] Entidad `Species` + relaciones
- [ ] VOs `CommonName`, `ScientificName`, `ConfidenceScore`
- [ ] Entidad `NutritionProfile`
- [ ] Entidad `Recommendation`
- [ ] Model Eloquent `Species` delgado
- [ ] Model Eloquent `NutritionProfile` delgado
- [ ] Model Eloquent `Recommendation` delgado
- [ ] Model Eloquent `SpeciesImage` delgado
- [ ] Model Eloquent `AiGeneration` delgado
- [ ] Factories para todas las entidades
- [ ] Test Pest: identificación → resultado → ficha (mockeando AI)
- [ ] `php artisan test` pasa

## Fase 2 — Application + HTTP
- [ ] `IdentifySpeciesAction`
- [ ] `ConfirmSpeciesAction` (BR-007)
- [ ] `GenerateSpeciesInsightAction`
- [ ] DTO `IdentificationResult`
- [ ] DTO `SpeciesInsight`
- [ ] Interface `SpeciesIdentifier`
- [ ] Interface `InsightGenerator`
- [ ] `OllamaVisionAdapter` (dev, local)
- [ ] `ExternalVisionAdapter` (prod) — proveedor decidido
- [ ] Controller `ScanController` (≤20 líneas)
- [ ] Vista Livewire/Volt: cámara + preview
- [ ] Vista Livewire/Volt: resultado con confianza
- [ ] Vista Livewire/Volt: confirmación manual de especie
- [ ] Vista Livewire/Volt: ficha enriquecida
- [ ] BR-006 implementado (confidence score)
- [ ] BR-007 implementado (confirmación usuario)

## Fase 3 — Datos externos
- [ ] Interface `SpeciesDataSource`
- [ ] Adapter `FishBaseAdapter` (mínimo)
- [ ] Adapter `OpenFishDataAdapter` (si da tiempo)
- [ ] Adapter `FaoAdapter` (si da tiempo)
- [ ] `SyncSpeciesAction`
- [ ] Comando Artisan `species:sync`
- [ ] Seed de especies mediterráneas/gallegas (≥10)
- [ ] Cache AI en `ai_generations` con `prompt_hash`
- [ ] Test Pest del flujo con datos reales

## Fase 4 — Demo end-to-end
- [ ] Ficha con ancla visual tipo Yuka (verde/amarillo/rojo aunque sea provisional)
- [ ] UI responsive y limpia
- [ ] Test manual con foto real (≥3 especies)
- [ ] Variables `.env` de producción configuradas
- [ ] Deploy en Railway/Fly/Render con adapter externo
- [ ] URL pública accesible
- [ ] README final con instrucciones para jueces

## Fase 5 — Extensiones (post-MVP)
- [ ] Migración `traceability_records`
- [ ] Vista de trazabilidad
- [ ] Score sostenibilidad
- [ ] Alternativas locales
- [ ] Auth + reintroducir `users`
- [ ] `recommendations.user_id` reintroducido
- [ ] Favoritos
- [ ] Historial de scans

## Fase 6 — Empaquetado nativo (post-demo, decisión basada en resultado)
- [ ] Evaluar NativePHP for Mobile si la demo gana y se quiere publicar en stores
- [ ] Evaluar NativePHP for Desktop si se necesita versión offline
- [ ] Configurar certificados Apple Developer y Play Console
- [ ] Compilar builds nativas (Android Studio / Xcode)
