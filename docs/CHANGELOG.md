# Changelog — Peix Scanner

Registro de las sesiones de trabajo. Las sesiones más recientes arriba.

## Sesión 2026-07-25 (imágenes)

**Objetivo**: que las imágenes se ajusten a los márgenes del app sin espacios blancos a los lados.

**Cambios:**
- `species.blade.php` y `confirm.blade.php`: contenedor de imagen ahora usa `-mx-4 bg-slate-100 overflow-hidden h-64` con `<img class="w-full h-full object-cover">`. Imagen a sangre (edge-to-edge) cubriendo todo el ancho disponible del contenedor mobile.
- Se quita `object-contain` que generaba bandas blancas/letras a los lados para imágenes con aspect ratio distinto al contenedor.
- Se sube la altura a `h-64` (256px) para tener un buen hero.

**Verificación:**
- Imagen edge-to-edge: ocupa todo el ancho del app
- Sin bandas blancas laterales
- El contenedor respeta los bordes del app (`px-4` del padre)

## Sesión 2026-07-25 (sin filtro)

**Objetivo**: eliminar el selector de objetivo del tab "Para ti". El usuario notó que la pregunta "¿Para qué lo vas a usar?" era inconsistente (otras pestañas no tenían filtro similar) y hacía el flujo raro.

**Cambios:**

- `app/Domain/Nutrition/NutritionAdvisor.php`: nuevo método `recommendAll(array $nutrition)` que devuelve las reglas universales + todas las reglas por objetivo (deportista, perder peso, embarazo, sostenibilidad). Dedupica por título.
- `app/Http/Controllers/ScanController.php`:
  - `show()` usa `recommendAll($nutrition)` — muestra todas las recomendaciones agregadas
  - Quitado `$goal` del view y de toda la firma del método
  - Quitado método `updateGoal()` (ya no hay cookie `peix_goal`)
  - Quitado método `currentGoalFromCookie()` (sin uso)
  - Quitado parámetro `$goal` de `buildExplanationPrompt`
  - Quitada lectura de `peix_goal` en `explain()` — el caché de explicaciones ahora es `explain.{species}` en lugar de `explain.{species}.{goal}`
- `resources/views/pages/species.blade.php`:
  - Quitado el selector de pills "¿Para qué lo vas a usar?" del tab "Para ti"
  - Quitada la lógica Alpine de actualización reactiva del goal
  - Renombrada la función global de `recommendationsPanel(config)` a `explanationPanel(config)` — más simple, solo maneja la explicación IA
  - El botón "🪄 Quiero una explicación personalizada" sigue disponible
- `routes/web.php`: quitada la ruta `POST /species/{slug}/goal` (ya no se necesita; el goal selector fue eliminado). `GET /species/{slug}/recommendations` nunca existió como ruta pública — solo existe el método privado `ScanController::recommendations()` usado internamente para renderizar el partial.

**Tests:**
- Quitados los tests de goal selector, weight loss vs athlete, validación de `peix_goal`
- Añadidos tests para `recommendAll`:
  - Universal + todas las reglas por objetivo
  - Dedupica por título (cada `<h3>` aparece como máximo 1 vez)
  - Sostenibilidad siempre incluida
- Renombrada aserción: `recommendationsPanel` → `explanationPanel`
- Añadido test: `it does not show goal selector pills anymore`

**Resultado UX:**
- Tab "Para ti" muestra TODA la información (universal + todos los objetivos combinados)
- Sin selector confuso en una sola pestaña
- Tiempo de carga instantáneo (todo se renderiza server-side, no hace falta swap de HTML)

## Sesión 2026-07-25 (Alpine.js fix)

**Objetivo**: arreglar que los botones del selector de objetivo no respondían al click (Alpine.js nunca se había cargado en la app).

**Bug raíz:**
- La app usaba directivas `x-data`, `x-show`, `@click` de Alpine.js en `species.blade.php` y `confirm.blade.php`
- **Alpine.js no estaba cargado en ninguna parte** — ni en vite, ni en CDN, ni en un `<script>` tag
- Esto causaba que el `x-data` se renderizara como texto literal en el HTML (visible para el usuario) y los `@click` no hicieran nada
- En el commit donde se añadió el selector de objetivo (`recommendationsPanel(@js({{ ... }}))`) el JS dentro de `x-data` se renderizaba con sintaxis corrupta por Blade, produciendo la página de error
- Todos los tabs (`Para ti`, `Sostenibilidad`, etc.) tampoco funcionaban — el switch era JS dummies

**Cambios:**

- `resources/views/components/layouts/app.blade.php`: añadido `<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>` después de `@vite`. Ahora Alpine se carga globalmente y todas las directivas funcionan.
- `species.blade.php`: refactorizado el `x-data` para usar `@json()` simple + `@verbatim` alrededor del `<script>` con la función `recommendationsPanel`. Esto evita que Blade corrompa las llaves `{`/`}` de JavaScript.
- `recommendationsPanel(configJson)` ahora parsea el string JSON automáticamente (`typeof === 'string' → JSON.parse`).

**Tests añadidos:**
- `it species page loads Alpine.js for reactive goal selector`
- `it species page includes recommendationsPanel global function`
- `it species page x-data on Para ti tab receives config JSON`

**Resultado**: tabs, spinners, el selector reactivo de objetivo y el botón "🪄 explicación personalizada" ahora funcionan en el navegador.

## Sesión 2026-07-25 (reactivo + móvil)

**Objetivo**: que el selector de objetivo sea reactivo (sin recargar página) y que las imágenes respeten los márgenes laterales en móvil.

**Cambios principales:**

- Selector de objetivo ahora **reactivo** (sin reload):
  - `POST /species/{slug}/goal` ahora devuelve JSON `{ok: true, goal: ...}` (antes redirigía)
  - Nuevo `GET /species/{slug}/recommendations?goal=X` que devuelve solo el partial HTML de recomendaciones
  - JavaScript Alpine.js hace fetch → swap del HTML en lugar de recargar
  - El usuario ve la lista cambiar al instante sin perder scroll

- Imágenes dentro de márgenes laterales (mobile-first):
  - Quitado `-mx-4` que extendía la imagen sobre el padding
  - Cambiado `object-cover` → `object-contain` para no cortar la imagen
  - Contenedor `bg-slate-100 overflow-hidden rounded-2xl flex items-center justify-center` con `max-height: 22rem`
  - Imagen `w-full h-auto max-h-96 object-contain` (no distorsión, máx 384px de alto)
  - Aplicado tanto a `species.blade.php` como a `confirm.blade.php`

**Archivos:**
- Nuevo: `resources/views/partials/recommendations-list.blade.php`
- Modificado: `species.blade.php`, `confirm.blade.php`, `ScanController.php` (updateGoal return type, recommendations endpoint), `routes/web.php`

**Tests añadidos:**
- `ScanFlowTest`: `it returns HTML recommendations list for the recommended goal endpoint`
- `ScanFlowTest`: `it species page shows different recommendations based on goal cookie`
- `ScanFlowTest`: `it species page image renders within app margins (no -mx-4 negative offset)`

## Sesión 2026-07-25 (recomendaciones)

**Objetivo**: traducir los datos FEN en interpretaciones prácticas para el usuario según su objetivo.

**Cambios principales:**

- `app/Domain/Nutrition/UserGoal.php` (NUEVO): enum con 5 casos — `Athlete`, `WeightLoss`, `Pregnancy`, `Sustainability`, `None`. Cada uno con `label()`, `icon()` y `description()`.
- `app/Domain/Nutrition/NutritionAdvisor.php` (NUEVO): motor determinista que aplica ~30 reglas categorizadas. Devuelve `array<Recommendation>` con `icon`, `tone` (positive/caution/info), `title` y `body`. Reglas universales (proteína, grasa, Omega-3, vitaminas) + reglas por objetivo (deportista, perder peso, embarazo, sostenibilidad).
- `OpenRouterVisionAdapter::generateText()` (NUEVO): método para generar texto libre vía OpenRouter (modo chat), timeout 20s. Usado solo si el usuario quiere explicación detallada.
- `ScanController::updateGoal()` (NUEVO): guarda el objetivo en cookie `peix_goal` (1 año). Validado contra los 5 valores del enum.
- `ScanController::explain()` (NUEVO): genera interpretación personalizada con caché de 24h por `species + goal`. Devuelve JSON si se llama vía AJAX.
- Rutas nuevas: `POST /species/{slug}/goal` y `POST /species/{slug}/explain`.
- `species.blade.php`: nuevo tab "Para ti" entre Nutrición y Sostenibilidad con:
  - Pills selector del objetivo (5 opciones con iconos)
  - Lista de recomendaciones (deterministas, gratis, instantáneas)
  - Botón "🪄 Quiero una explicación personalizada" → POST vía fetch → muestra respuesta IA en panel emergente

**Tests añadidos:**
- `NutritionAdvisorTest`: 13 tests cubriendo cada goal × cada categoría de pescado (magro/graso/alto proteína)
- `ScanFlowTest`: 4 tests nuevos (selector visible, cookie funciona, validación goal inválido, endpoint explain devuelve JSON)

**Resultado:** un salmón atlántico (athlete) muestra "Alto en proteínas", "Apto para deportistas", "Aporta hierro". El mismo salmón (pregnancy) muestra "Apto para embarazo" porque su mercurio es bajo. El jurel (pregnancy) muestra "Evitar durante embarazo" por su mercurio alto.

## Sesión 2026-07-25 (UX)

**Objetivo**: añadir el nombre común en castellano a la pantalla "¿Es este el pez?" y dar feedback de loading al reintentar.

**Cambios:**
- `confirm.blade.php`: el bloque "Detectado" ahora muestra `common_name_local` como nombre principal (grande, prominente), con el nombre en inglés debajo más sutil y el nombre científico en itálica
- Solo se muestra el castellano cuando difiere del inglés; si son iguales (raro), se oculta para no duplicar
- Botón "No es este, reintentar" rediseñado: ahora es un botón con borde verde, spinner SVG animado y mensaje "Reintentando con tu imagen..." mientras `submitting=true` (controlado con Alpine.js)
- Al hacer click el botón se deshabilita, oculta el texto normal y muestra el spinner + mensaje

**Tests añadidos:**
- `ScanFlowTest`: "shows the Spanish common name on the confirm page when it differs from English"
- `ScanFlowTest`: "shows the retry button with loading spinner on the confirm page" — verifica que ambos textos (normal y loading) están presentes

## Sesión 2026-07-25 (foto)

**Verificación end-to-end:**
- Escaneo de foto (no etiqueta) → IA identifica especie en inglés → `SpeciesTranslations::toSpanish()` traduce a español → `common_name_local` se guarda en cache → `show()` lee del seed por nombre español
- Resultado: foto de Jurel muestra "125.0 kcal / 17.5 g proteína / 5.5 g grasa / 1.40 g omega-3" + "Fuente: FEN"
- Sin llamada API externa: el seed cubre 52 especies, incluyendo los nombres comunes en español

**Tests añadidos:**
- `ScanFlowTest`: "shows FEN nutrition data on the species page from a photo scan" (Salmón → 208 kcal, 20g proteína)
- `ScanFlowTest`: "falls back to SpeciesTranslations to translate common name for nutrition lookup" (Jurel → 125 kcal, 5.5g grasa)

**Verificación: ambos flujos (foto + etiqueta) muestran los mismos datos FEN.**

## Sesión 2026-07-25

**Objetivo**: integrar datos nutricionales en tiempo real para las especies identificadas.

**Cambios principales:**
- Nuevo servicio `App\Services\FoodDataCentralService` que consulta la API de USDA FoodData Central
- Mapeo interno de 50+ nombres comunes en español a términos de búsqueda USDA con descripciones preferidas (e.g. "mackerel, jack, raw" para Jurel, "hake, raw" para Merluza)
- `ScanController::show()` ahora carga o descarga datos nutricionales y los persiste en `nutrition_profiles` la primera vez
- `species.blade.php` muestra los datos nutricionales reales: calorías, proteína, grasa, omega-3, vitaminas y minerales con nombres amigables (no IDs de DB crudos)
- Nuevo `.env` var `USDA_API_KEY` (gratis en https://fdc.nal.usda.gov/api-key-signup.html)
- Tests nuevos en `FoodDataCentralServiceTest.php`: 5 tests con mapeo, respuesta exitosa, selección de especie específica, fallback

**Fix de precisión nutricional:**
- Bug inicial: el sistema agarraba datos de "Fish, mackerel, Atlantic, raw" para Jurel (205 kcal, 13.9g grasa) porque era el primer resultado de búsqueda "mackerel"
- Solución: cada mapeo ahora tiene una lista de descripciones preferidas; `pickBestFood()` prioriza específicamente la especie correcta; usando `scientific_name` busca match por género (e.g. "Trachurus")
- Resultado: Jurel ahora muestra 158 kcal, 7.9g grasa, 20.1g proteína (de "Fish, mackerel, jack, raw" — el equivalente USDA del jurel)
- Vitaminas formateadas en nombres humanos con unidades µg/mg en vez de B12_ug, D_IU, K_ug, E_mg

**Cómo funciona ahora:**
1. Usuario identifica un pez (escaneo o etiqueta)
2. Se abre la ficha de especie
3. Si la especie está en `SpeciesNutritionSeed` (52 especies curadas FEN), se sirve directo
4. Si no, se consulta la API de USDA como fallback para especies raras
5. Próximas consultas ya están en DB (no más llamadas externas)

**Decisión arquitectónica: seed curado vs API**
- USDA FoodData Central demostró ser poco fiable para especies mediterráneas crudas (devuelve datos cocinados/procesados para lubina, dorada, etc.)
- Seed curado con datos de la Fundación Española de la Nutrición (FEN) garantiza exactitud biológica
- 52 especies cubiertas: las 35+ especies del prompt + cefalópodos + mariscos
- Vista muestra "Fuente: FEN" o "Fuente: USDA FoodData Central" según el origen

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
