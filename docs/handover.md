# Peix Scanner — Handover Document

> Generado: 2026-09-16
> Última sesión activa: orlandorojasoyarzun / develop

---

## 1. Proyecto

**Peix Scanner** es una app Laravel que permite identificar pescado fresco o etiquetas de packaged seafood via visión artificial, y mostrar una ficha nutricional detallada.

- **Stack**: Laravel (FrankenPHP runtime en Railway), Blade + Alpine.js, Tailwind CSS
- **Repositorio**: `https://github.com/orlandorojasoyarzun/peix_scanner`
- **Rama de trabajo**: `develop`
- **Producción**: Railway (app principal + Postgres)

---

## 2. Arquitectura general

```
┌─────────────────────────────────────────────────────────────┐
│                      Routing / Controllers                  │
│   ScanController (scan flow + species page)                 │
│   UploadController (label scan route)                       │
└──────────────────┬──────────────────────────────────────────┘
                   │
     ┌─────────────┴──────────────┐
     ▼                            ▼
┌────────────────┐        ┌──────────────────────┐
│ ScanState      │        │ WikipediaService     │
│ (cache-based.  │        │ (parcialmente        │
│  state machine)│        │  desactivado)        │
└────────────────┘        └──────────────────────┘
     │
     ▼
┌─────────────────────┐
│ OpenRouterVision    │ ← identificación via IA (vision model)
│ Adapter             │
└─────────────────────┘
     │
     ▼
┌─────────────────────┐
│ SpeciesNutrition    │ ← datos nutricionales
│ Advisor + FDC API   │
└─────────────────────┘
```

---

## 3. Modelo de IA — Decisión importante

### Solución actual
Modelo en uso: **`inclusionai/ling-3.0-flash-vl:free`**

- Funciona perfectamente para identificación de especies (respuesta: Lubina → Dicentrarchus labrax, 100% confidence)
- Es **free tier** — no consume crédito de OpenRouter
- Se configura vía variable de entorno `OPENROUTER_MODEL`
- En **Railway** esta variable está seteada en el entorno; en **local** se lee del `.env`

### Prompt del modelo
El prompt está en `OpenRouterVisionAdapter::PROMPT` y define una lista curada de ~60 especies de pescado/marisco comercializadas en España. El modelo responde con las 3 especies más probables y su confidence.

### Ubicación del código
- `app/Domain/Ai/Adapters/OpenRouterVisionAdapter.php` — Prompt + lógica de parseo
- Variable de entorno: `OPENROUTER_MODEL` (default: `inclusionai/ling-3.0-flash-vl:free`)

---

## 4. Feature: Wikipedia reference images — DESACTIVADA

### Decisión
Se deshabilitaron las imágenes de referencia de Wikipedia. El usuario prefiere no mostrarlas.

### Por qué
1. `thumb.wikimedia.org` no estaba en la allowlist de `WikipediaService` → imágenes rotas
2. 即使 fixing el host, el usuario decidió desactivarlas — complexity not worth it for MVP

### Qué se hizo
- `WikipediaService.php`: `thumb.wikimedia.org` agregado a `ALLOWED_THUMBNAIL_HOSTS` (seguridad, por si se reactiva)
- `ScanController::confirm()`: `$referenceImageUrl = null` hardcoded
- `ScanController::confirmStore()`: `$referenceImageUrl = null` hardcoded, no se llama WikipediaService
- `species.blade.php`: Cambiado de `$storedResult['reference_image_url']` a `route('scan.image', $scanId)` — ahora muestra **la foto subida por el usuario** en la página de especie

### Para reactivarla
1. Quitar los `$referenceImageUrl = null;` en `confirm()` y `confirmStore()`
2. Restaurar la llamada `$this->wikipedia->getSpeciesImage(...)` en `confirmStore()`
3. Cambiar `species.blade.php` línea 21 de vuelta a `$imageUrl = $storedResult['reference_image_url'] ?? null;`

---

## 5. Feature: Foto del usuario en página de especie

### Cómo funciona
1. El usuario sube una foto → se guarda en `storage/app/scans/{scan_id}.jpg`
2. En `confirmStore()`, se guarda `scan_id` en el cache de resultado de especie
3. `species.blade.php` usa `route('scan.image', $scanId)` para generar la URL de la imagen

### Ruta de imagen
`GET /scan/{scan}/image` → `ScanController@image` → sirve el archivo desde `storage/app/scans/`

### Notas
- Si el usuario no subió foto en el flujo label, no hay imagen → la página de especie no muestra imagen (comportamiento correcto, no broken image)
- La imagen se procesa con HEIC→JPEG conversion para iPhones (ver sección 7)

---

## 6. Feature: HEIC → JPEG conversion

### Problema
iPhones guardan fotos en formato HEIC, que PHP/GD no maneja nativamente → imágenes rotas.

### Solución
`ImageProcessor` que detecta formato:
- Si es HEIC y `imagick` está disponible → convierte via Imagick
- Si es HEIC sin `imagick` → fallback a `gd` (pero GD no soporta HEIC, así que intenta igual)
- Si es WebP → `imagecreatefromwebp()` si está disponible

### Ubicación
- `app/Services/ImageProcessor.php`
- `app/Http/Controllers/ScanController.php` línea ~90: `$path = (new ImageProcessor)->process($file->getPathname());`

### FrankenPHP runtime note
En Railway con FrankenPHP runtime, la extensión GD puede no cargarse al inicio. El código tiene un fallback: si `imagecreatefromjpeg` falla silenciosamente, se intenta con Imagick. Si ambas fallan → error visible al usuario.

---

## 7. Feature: Circuit Breaker para OpenRouter

### Qué hace
`OpenRouterCircuitBreaker` evita chiamar OpenRouter cuando detecta que está fallando (5xx, 429 repetidos, errores de transporte).

### Ubicación
- `app/Domain/Ai/Support/OpenRouterCircuitBreaker.php`
- Se inyecta en `OpenRouterVisionAdapter`
- TTL: 5 minutos de lockout cuando está abierto

### Logs
Cuando el breaker está abierto, cualquier identificación va a `IdentificationFailedException::REASON_CIRCUIT_OPEN` → el usuario ve un error amigable.

---

## 8. Feature: Label Scan (escaneo de etiqueta)

### Flujo
1. Usuario elige modo "etiqueta" → `UploadController@storeLabel`
2. `identifyFromLabel()` en `OpenRouterVisionAdapter` extrae texto de la imagen de la etiqueta
3. Se guarda en `scanState` y se redirige a `scan.confirm` con `mode=label`
4. El usuario confirma → `confirmStore()` genera la página de especie

### Diferencia con modo fish
- En modo fish: el modelo devuelve `scientific_name`, `common_name`, etc.
- En modo label: el modelo solo devuelve el texto leído de la etiqueta (`label_text`)

---

## 9. Estado de los paneles en la página de especie

| Panel | Estado | Notas |
|---|---|---|
| **Nutrición** | ✅ Funcionando | Datos de USDA FoodData Central o tabla local Species/nutritionProfile |
| **Para ti (🪄)** | ✅ Funcionando | Genera explicación personalizada via `generateText()` de OpenRouter. Cachea resultado. |
| **Sostenibilidad** | ❌ Placeholder | Texto "se completará cuando conectemos..." |
| **Preparación** | ❌ Placeholder | Texto "se completará cuando conectemos..." |

---

## 10. Caché — Keys y TTL

| Key | TTL | Contenido |
|---|---|---|
| `scan.{scan_id}.state` | 60 min | Estado del scan (resultado, imagen, modo) |
| `species.{slug}.result` | 30 min | Resultado confirmado (nombres, scan_id, image_path) |
| `species.{slug}.explain` | 60 min | Explicación "Para ti" generada por IA |
| `wikipedia.image.{scientific}` | 60 min | URL de imagen de Wikipedia (ya no se usa, pero cacheada) |

### Limpiar caché en producción
```bash
php artisan cache:clear
```
O targeting:
```bash
php artisan cache:forget scan.{id}.state
php artisan cache:forget species.{slug}.result
```

---

## 11. Environment variables (Railway + local)

### Requeridas
```
OPENROUTER_API_KEY=sk-...
OPENROUTER_MODEL=inclusionai/ling-3.0-flash-vl:free
APP_ENV=production  # o local
APP_DEBUG=false
```

### Para desarrollo local
El archivo `.env` local tiene las mismas keys. Asegurarse de que `APP_DEBUG=true` en local.

### Para deployar en Railway
1. Push a la rama `develop`
2. Railway deploya automáticamente en push a `develop`
3. Verificar que las variables de entorno estén seteadas en el panel de Railway (no confiar en que el `.env` local se suba)

---

## 12. Known issues / Pending work

### Alto priority
- **Ninguno crítico reportado**

### Medium priority
- Los paneles de Sostenibilidad y Preparación son placeholders (ver sección 9)

### Bajo priority / nice to have
- Wikipedia reference images podrían reactivarse en el futuro si se quiere
- Mejorar parsing de regional_names del modelo (a veces devuelve nombres en vez de array)
- Test coverage para `OpenRouterVisionAdapter::parseLabelResponse()` con casos edge

---

## 13. Testing notes

### Cómo testear localmente

1. **Start server**:
   ```bash
   php artisan serve
   ```

2. **Ver logs en tiempo real**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Test flujo completo fish**:
   - Ir a `/` → subir foto de pescado → esperar identificación → confirmar → ver página de especie

4. **Test flujo label**:
   - Ir a `/label` → subir foto de etiqueta → esperar lectura → confirmar → ver página de especie

5. **Test HEIC**:
   - Usar iPhone para sacar foto → subir → verificar que se convierta a JPEG sin error

### Logs relevantes
- `OpenRouter circuit breaker` — para ver si el breaker está activo
- `Wikipedia thumbnail URL host not in allowlist` — si se reactiva Wikipedia
- `OpenRouter HTTP error response` — si el modelo falla

---

## 14. Archivos clave y owners

| Archivo | Qué contiene |
|---|---|
| `app/Domain/Ai/Adapters/OpenRouterVisionAdapter.php` | Prompt, lógica de identificación, circuit breaker |
| `app/Http/Controllers/ScanController.php` | Flujo completo: upload → scan → confirm → species page |
| `app/Services/WikipediaService.php` | Wikipedia API (parcialmente desactivado) |
| `app/Services/ImageProcessor.php` | HEIC→JPEG, WebP handling |
| `app/Domain/Support/OpenRouterCircuitBreaker.php` | Circuit breaker para OpenRouter |
| `app/Domain/Nutrition/NutritionAdvisor.php` | Recomendaciones nutricionales basadas en datos |
| `app/Domain/Nutrition/SpeciesNutritionSeed.php` | Seed data nutricional offline para especies conocidas |
| `resources/views/pages/confirm.blade.php` | Vista de confirmación |
| `resources/views/pages/species.blade.php` | Ficha de especie (página final) |
| `resources/views/components/layouts/app.blade.php` | Layout base |

---

## 15. Decisiones de diseño documentadas

### Por qué no Imagenomics/OT-2
Se evaluó `anthropic/claude-3.5-sonnet` y `google/gemini-2.0-flash-exp` pero el modelo free de `inclusionai` funciona bien y no consume crédito.

### Por qué Wikipedia desactivado
El MVP no lo necesita. La foto del usuario es suficiente referencia visual.

### Por qué circuit breaker
OpenRouter tiene rate limits y outages. Sin breaker, cada request nuevo seguía chiamando un servicio caído → quota quemada + usuario viendo errores técnicos.

### Por qué Cache-based state (scanState)
No se usa DB para el estado intermedio del scan (identificación, confirmación). Todo va a Laravel Cache con TTL. La página de especie usa Cache definitivo (30 min TTL). Esto simplifica el flujo y evita DB writes en el happy path.

---

## 16. Git workflow actual

- **Rama principal**: `main` (producción)
- **Rama de trabajo**: `develop`
- **Flujo**: `develop` → PR → `main`
- **Deploy**: automático en push a `develop` via Railway

### Archivos modificados actualmente (sin commitear)
```
app/Http/Controllers/ScanController.php
app/Services/WikipediaService.php
resources/views/pages/species.blade.php
```

---

*Este documento es la fuente de verdad para cualquier agente que continúe el desarrollo.*
