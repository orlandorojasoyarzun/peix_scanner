# Peix Scanner — Handover Document

> Generado: 2026-09-16
> Última sesión activa: orlandorojasoyarzun / develop

---

## 1. Proyecto

**Peix Scanner** es una app Laravel que permite identificar pescado fresco o etiquetas de packaged seafood via visión artificial, y mostrar una ficha nutricional detallada.

- **IA**: OpenAI `gpt-4o` (visión) via `OpenAIVisionAdapter`. Máxima accuracy.
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
│ OpenAIVisionAdapter │ ← identificación via IA (gpt-4o, máximo accuracy)
└─────────────────────┘
     │
     ▼
┌─────────────────────┐
│ SpeciesNutrition    │ ← datos nutricionales
│ Advisor + FDC API   │
└─────────────────────┘
```

---

## 3. Modelo de IA

### Modelo actual
**`gpt-4o`** via OpenAI API (`OpenAIVisionAdapter`).

- Máximo accuracy — robusto con luz rara, filetes cortados, reflejos
- Configurable via `OPENAI_MODEL` env var (default: `gpt-4o`)
- Para costo menor: `gpt-4o-mini` (mismo adapter, cambiar env var)

### Prompt del modelo
El prompt está en `OpenAIVisionAdapter::PROMPT` y define una lista curada de ~60 especies de pescado/marisco comercializadas en España. El modelo responde con las 3 especies más probables y su confidence.

### Ubicación del código
- `app/Domain/Ai/Adapters/OpenAIVisionAdapter.php` — implementación activa
- Binding en `AppServiceProvider`

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

## 7. Feature: Label Scan (escaneo de etiqueta)

### Flujo
1. Usuario elige modo "etiqueta" → `UploadController@storeLabel`
2. `identifyFromLabel()` en `OpenAIVisionAdapter` extrae texto de la imagen de la etiqueta
3. Se guarda en `scanState` y se redirige a `scan.confirm` con `mode=label`
4. El usuario confirma → `confirmStore()` genera la página de especie

### Diferencia con modo fish
- En modo fish: el modelo devuelve `scientific_name`, `common_name`, etc.
- En modo label: el modelo solo devuelve el texto leído de la etiqueta (`label_text`)

---

## 8. Estado de los paneles en la página de especie

| Panel | Estado | Notas |
|---|---|---|
| **Nutrición** | ✅ Funcionando | Datos de USDA FoodData Central o tabla local Species/nutritionProfile |
| **Para ti (🪄)** | ✅ Funcionando | Genera explicación personalizada via `generateText()` de OpenAI. Cachea resultado. |
| **Sostenibilidad** | ❌ Placeholder | Texto "se completará cuando conectemos..." |
| **Preparación** | ❌ Placeholder | Texto "se completará cuando conectemos..." |

---

## 9. Caché — Keys y TTL

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

## 10. Environment variables (Railway + local)

### Requeridas
```
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o
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

## 11. Known issues / Pending work

### Alto priority
- **Ninguno crítico reportado**

### Medium priority
- Los paneles de Sostenibilidad y Preparación son placeholders (ver sección 8)

### Bajo priority / nice to have
- Wikipedia reference images podrían reactivarse en el futuro si se quiere
- Mejorar parsing de regional_names del modelo (a veces devuelve nombres en vez de array)

---

## 12. Testing notes

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
- `OpenAI HTTP error response` — si el modelo falla
- `Wikipedia thumbnail URL host not in allowlist` — si se reactiva Wikipedia

---

## 13. Archivos clave y owners

| Archivo | Qué contiene |
|---|---|
| `app/Domain/Ai/Adapters/OpenAIVisionAdapter.php` | Prompt, lógica de identificación via GPT-4o |
| `app/Http/Controllers/ScanController.php` | Flujo completo: upload → scan → confirm → species page |
| `app/Services/WikipediaService.php` | Wikipedia API (parcialmente desactivado) |
| `app/Services/ImageProcessor.php` | HEIC→JPEG, WebP handling |
| `app/Domain/Nutrition/NutritionAdvisor.php` | Recomendaciones nutricionales basadas en datos |
| `app/Domain/Nutrition/SpeciesNutritionSeed.php` | Seed data nutricional offline para especies conocidas |
| `resources/views/pages/confirm.blade.php` | Vista de confirmación |
| `resources/views/pages/species.blade.php` | Ficha de especie (página final) |
| `resources/views/components/layouts/app.blade.php` | Layout base |

---

## 14. Decisiones de diseño documentadas

### Por qué GPT-4o y no otro modelo
Se evaluaron otros modelos de visión pero `gpt-4o` ofrece la mejor accuracy en condiciones de supermercado (luz irregular, filetes cortados, reflejos). El costo es aceptable con los créditos disponibles.

### Por qué Wikipedia desactivado
El MVP no lo necesita. La foto del usuario es suficiente referencia visual.

### Por qué Cache-based state (scanState)
No se usa DB para el estado intermedio del scan (identificación, confirmación). Todo va a Laravel Cache con TTL. La página de especie usa Cache definitivo (30 min TTL). Esto simplifica el flujo y evita DB writes en el happy path.

---

## 15. Git workflow actual

- **Rama principal**: `main` (producción)
- **Rama de trabajo**: `develop`
- **Flujo**: `develop` → PR → `main`
- **Deploy**: automático en push a `develop` via Railway

### Archivos modificados actualmente (sin commitear)
```
(ninguno — árbol limpio)
```

---

*Este documento es la fuente de verdad para cualquier agente que continúe el desarrollo.*
