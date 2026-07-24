# Changelog — Peix Scanner

Registro de las sesiones de trabajo. Las sesiones más recientes arriba.

## Sesión 2026-07-19

**Objetivo**: pulir la app para MVP funcional y dejar la documentación al día.

**Cambios principales:**
- Layout app shell: `body h-screen overflow-hidden` + contenedor con `flex-1` + main con scroll interno + footer `fixed bottom-0`
- Header full-width (atraviesa toda la ventana)
- Quitado `bg-slate-50` del contenedor central (dejaba un rectángulo blanco)
- Footer `fixed` siempre visible al fondo del viewport

**Problemas resueltos en esta sesión:**
- "Revisa la imagen" tras subir foto: `:disabled="submitting"` en input file impedía enviar el archivo. Fix: `@submit.prevent` con `form.checkValidity()`
- Footer quedaba en el medio del viewport porque `<body>` no era flex container. Fix: `body h-screen overflow-hidden flex flex-col`
- Backend OpenRouter daba timeout: aumentado a `timeout(180)->connectTimeout(15)` + `resizeIfNeeded()` para reducir imagen a 512x512
- Parser no separaba `ES:` y `ALT:` cuando venían en la misma línea: nueva regex que splittea `(.+?)\s*(?:^|\s)(?:ALT|ATL):\s*(.+)$`
- `cleanSpanishName()` en `ScanController` como cinturón de seguridad

**Archivos modificados:**
- `app/Http/Controllers/ScanController.php` (fallback traducciones, cleanSpanishName, image_path)
- `app/Domain/Ai/Adapters/OllamaVisionAdapter.php` (parser robusto, prompt top 1) ← ELIMINADO
- `app/Domain/Ai/Adapters/OpenRouterVisionAdapter.php` (timeout 180s, resize, parser)
- `app/Domain/Ai/DTOs/IdentificationResult.php` (commonNameLocal, regionalNames)
- `app/Domain/Ai/SpeciesTranslations.php` (NUEVO — fallback curado 35+ especies)
- `app/Console/Commands/AiTestCommand.php` (muestra candidatos)
- `app/Providers/AppServiceProvider.php` (binding OpenRouter, Ollama eliminado)
- `resources/css/app.css` (design system: flame, mint, display, mono)
- `resources/views/components/layouts/app.blade.php` (header full-width, footer fixed)
- `resources/views/components/loading.blade.php` (NUEVO — spinner SVG animado)
- `resources/views/pages/{home,scan,confirm,species}.blade.php` (SVGs inline, foto, tipografía)
- `tests/Feature/ScanFlowTest.php` (espera "Salmón atlántico")
- `public/favicon.svg` (NUEVO — pez estilizado blanco sobre negro)
- `public/svg/{camera,fishing}.svg` (NUEVOS)

**Comandos git ejecutados:**
- 3 commits en español en `feature/ai-y-traducciones`:
  - "Mejoras en la IA y traducciones"
  - "Mejoras visuales y de iconos"
  - "Tests actualizados"
- 1 merge commit de develop
- 1 PR (#13) mergeada en develop

**Estado al cierre:**
- Rama actual: `develop`
- 12 tests Pest passing
- 0 PRs abiertos
- `develop` protegido (force-push/delete bloqueados)
- App funcional, header y footer arreglados

**Pendiente para futuras sesiones:**
- Fix del footer en rama feature (no commit en develop)
- Crear rama y commit del layout fixed
- Integrar FishBase para datos externos
- Implementar `GenerateSpeciesInsightAction` real
- Deploy en Railway
- NativePHP for Mobile (Fase 6 del plan original)

---

## Sesión 2026-07-18 (resumen)

- Subida completa al repo:
  - PRs mergeadas: #9 (fish-counter-ai-prompt), #10 (counter-focused-ui-copy), #11 (app-documentation), #12 (openrouter-adapter), #13 (ai-y-traducciones)
- 12 tests Pest
- OpenRouter como provider default
- `SpeciesTranslations` para fallback castellano
- Layout inicial con header full-width, design system restaurado
- Confirmación visual: la IA reconoce congrio (Conger conger) con confianza 0.95 en 2.4s
- Protección mínima de `develop` configurada
- 3 archivos borrados: `architecture.md`, `domain.md`, `structure.md`, `README.md` (obsoletos)
