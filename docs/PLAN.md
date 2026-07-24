# PLAN — Peix Scanner (hackaton, MVP)

> Documento vivo. Se actualiza al cerrar cada fase. Marcar `DONE` al terminar.

## Principios rectores
- **Pragmático-modular**: bounded contexts como namespaces, no capas ceremoniosas.
- **Slice vertical delgado primero**; la estructura emerge del código, no del diseño.
- **Regla de los tres**: no se abstrae hasta tener 3 usos.
- **Tests solo del flujo crítico** (identificar especie → ficha).
- **Sin auth, sin `users`, sin `user_id` en MVP**.
- **Repositories solo si duele**; mientras Eloquent alcance, no se abstrae.

## Decisiones cerradas
- **Entregable**: demo funcional end-to-end.
- **Identificación**: CV real con modelo entrenado/inference.
- **Fuente de datos**: APIs públicas (FishBase, OpenFishData, FAO).
- **Stack**: Laravel 12 + Livewire + Volt + PostgreSQL (cerrado).
- **CV runtime**: OpenRouter (cloud) para todas las entornos.
- **Equipo**: solo.
- **Timeline**: 1–2 meses.
- **Hosting demo**: cloud (Railway/Fly/Render/VPS).
- **Tabla `users`**: fuera del MVP. No se crea la migración.
- **Disciplina tests**: Pest solo del flujo crítico.
- **Package manager frontend**: pnpm.

## Estructura objetivo

```
app/
  Domain/
    Species/      (entidad, VOs, modelos delgados)
    Nutrition/    (perfil nutricional)
    Recommendation/ (recomendaciones e insights)
    Ai/           (contratos para adapters de AI/CV)
  Application/
    Actions/      (casos de uso concretos)
  Http/
    Controllers/  (delgados, ≤20 líneas)
docs/
  PLAN.md         (este archivo)
  CHECKLIST.md
  README.md       (creado en Fase 0)
  architecture.md
  domain.md
  database.md
```

---

## Fase 0 — Bootstrap
- [ ] Laravel 12 instalado en el directorio actual
- [ ] PostgreSQL configurado en `.env`
- [ ] Livewire + Volt funcionando
- [ ] Tailwind compilando (Vite)
- [ ] Pest / Pint / PHPStan instalados
- [ ] Esqueleto de carpetas creado
- [ ] **Sin** migración `users`, `password_reset_tokens`, `sessions`
- [ ] `docs/PLAN.md` y `docs/CHECKLIST.md` presentes
- [ ] Primer commit NO se hace (queda staged)

## Fase 1 — Slice vertical del Dominio
Tablas MVP: `species`, `species_images`, `nutrition_profiles`, `recommendations`, `ai_generations`.

- [ ] Migraciones creadas
- [ ] Entidad `Species` + VOs (`CommonName`, `ScientificName`, `ConfidenceScore`)
- [ ] Entidad `NutritionProfile` (BR-003)
- [ ] Entidad `Recommendation` ligada a Species (BR-001, BR-002). Sin `user_id`
- [ ] Models Eloquent delgados (solo relaciones + accessors/mutators)
- [ ] Factories
- [ ] Tests Pest del flujo de identificación (mockeando AI)

## Fase 2 — Application + HTTP
- [ ] `IdentifySpeciesAction`
- [ ] `ConfirmSpeciesAction` (BR-007)
- [ ] `GenerateSpeciesInsightAction`
- [ ] DTOs entre capas
- [ ] Interface `AIProvider` + `SpeciesIdentifier` + `InsightGenerator`
- [ ] `ExternalVisionAdapter` (prod) — decidir proveedor en esta fase
- [ ] Controllers delgados
- [ ] Vista Livewire/Volt: cámara → preview → resultado → confirmar → ficha

## Fase 3 — Datos externos
- [ ] Interface `SpeciesDataSource`
- [ ] Adapter FishBase (mínimo viable)
- [ ] Comando Artisan de ingesta
- [ ] Seed con especies mediterráneas/gallegas
- [ ] Cache AI en `ai_generations`

## Fase 4 — Demo end-to-end
- [ ] Ficha pulida visualmente
- [ ] Test manual con foto real
- [ ] Deploy en cloud con adapter externo
- [ ] README de demo para jueces

## Fase 5 — Extensiones (post-MVP, tras validar)
- [ ] `traceability_records` + vista de origen
- [ ] Score sostenibilidad (verde/amarillo/rojo)
- [ ] Alternativas locales
- [ ] Auth + reintroducir `users` y `user_id`
- [ ] Favoritos / historial / analytics

## Fase 6 — Empaquetado nativo (post-demo, decisión basada en resultado)
- [ ] Evaluar NativePHP for Mobile (Capacitor) si el proyecto se publica en App Store / Play Store
- [ ] Evaluar NativePHP for Desktop si se necesita versión offline
- [ ] Requiere certificados Apple Developer ($99/año) y Play Console ($25 único)
- [ ] **Fuera del MVP**: la webapp responsive en Railway cubre la demo

---

## Hosting
- **Recomendado**: Railway (Laravel nativo, PostgreSQL incluido, HTTPS automático, deploy con git push)
- **No recomendado para Laravel**: Vercel (PHP community builder, storage efímero, cold starts)
- **Plan de deploy**: Fase 4 con `railway.json` + variables de entorno

## Cámara en móvil
- Webapp responsive con `getUserMedia` o `<input type="file" capture="environment">`
- HTTPS obligatorio (Railway lo da gratis)
- Atributo `playsinline` en `<video>` para iOS Safari

---

## Tensiones conocidas (registro)
1. ~~Ollama ↔ cloud~~: Eliminado. Solo OpenRouter.
2. **DDD pragmático vs completo**: disciplina modular sin ceremony. Repositories solo si duele.
3. **Auth fuera del MVP**: `recommendations.user_id` se reintroduce en Fase 5; implica migración adicional.
4. **NativePHP fuera del MVP**: webapp responsive es suficiente. Replantear solo si la demo gana y se quiere publicar.
