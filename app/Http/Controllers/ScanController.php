<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Actions\IdentifySpeciesAction;
use App\Domain\Ai\Adapters\OpenRouterVisionAdapter;
use App\Domain\Ai\AllowedSpecies;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use App\Domain\Ai\SpeciesLabelMapper;
use App\Domain\Ai\SpeciesTranslations;
use App\Domain\Nutrition\Models\NutritionProfile;
use App\Domain\Species\Models\Species;
use App\Domain\Uploads\ImageProcessor;
use App\Domain\Uploads\InvalidImageException;
use App\Http\Requests\ScanImageRequest;
use App\Services\FoodDataCentralService;
use App\Services\WikipediaService;
use App\Support\CacheKeys;
use App\Support\ScanStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController
{
    public function __construct(
        private readonly WikipediaService $wikipedia,
        private readonly SpeciesLabelMapper $labelMapper,
        private readonly FoodDataCentralService $fdc,
        private readonly OpenRouterVisionAdapter $openRouter,
        private readonly ImageProcessor $imageProcessor,
        private readonly ScanStateStore $scanState,
    ) {}

    public function home(): View
    {
        return view('pages.home');
    }

    public function create(): View
    {
        return view('pages.scan');
    }

    public function store(ScanImageRequest $request, IdentifySpeciesAction $action): RedirectResponse
    {
        set_time_limit(180);

        $file = $request->file('photo');
        $scanId = (string) Str::uuid();

        try {
            $encoded = $this->imageProcessor->reencode($file);
            $stored = $this->imageProcessor->persist($encoded, $scanId);
        } catch (InvalidImageException $e) {
            Log::warning('Rejected upload in store()', ['scan_id' => $scanId, 'error' => $e->getMessage()]);

            return redirect()
                ->route('scan.create')
                ->withErrors(['photo' => $e->getMessage()]);
        }

        $absolutePath = Storage::disk('local')->path($stored);

        if (! is_file($absolutePath)) {
            Log::error('Stored file not found on disk', ['path' => $absolutePath, 'scan_id' => $scanId]);

            return redirect()
                ->route('scan.confirm', $scanId)
                ->withErrors(['photo' => 'No pudimos leer la imagen guardada. Inténtalo de nuevo.']);
        }

        try {
            $result = $action->execute($absolutePath);
        } catch (IdentificationFailedException $e) {
            // Log the safe message PLUS the upstream context (e.g. OpenRouter's
            // own 401/403 reason) so a freshly-revoked key shows up clearly in
            // logs without leaking that detail to the user-facing error.
            Log::warning('Identification failed', [
                'scan_id' => $scanId,
                'reason' => $e->getReason(),
                'upstream' => $e->getContext(),
                'message' => $e->getMessage(),
            ]);
            $this->scanState->put($scanId, [
                'mode' => 'fish',
                'image' => $stored,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('scan.confirm', $scanId);
        }

        $this->scanState->put($scanId, [
            'mode' => 'fish',
            'image' => $stored,
            'result' => [
                'scientific_name' => $result->scientificName,
                'common_name' => $result->commonName,
                'common_name_local' => $this->cleanSpanishName(
                    $result->commonNameLocal !== ''
                        ? $result->commonNameLocal
                        : (SpeciesTranslations::toSpanish($result->commonName) ?? ucfirst($result->commonName))
                ),
                'regional_names' => $result->regionalNames,
                'confidence' => $result->confidence,
                'high_confidence' => $result->isHighConfidence(),
            ],
        ]);

        return redirect()->route('scan.confirm', $scanId);
    }

    public function storeLabel(ScanImageRequest $request, OpenRouterVisionAdapter $adapter): RedirectResponse
    {
        set_time_limit(180);

        $file = $request->file('photo');
        $scanId = (string) Str::uuid();

        try {
            $encoded = $this->imageProcessor->reencode($file);
            $stored = $this->imageProcessor->persist($encoded, $scanId);
        } catch (InvalidImageException $e) {
            Log::warning('Rejected upload in storeLabel()', ['scan_id' => $scanId, 'error' => $e->getMessage()]);

            return redirect()
                ->route('scan.create')
                ->withErrors(['photo' => $e->getMessage()]);
        }

        $absolutePath = Storage::disk('local')->path($stored);

        if (! is_file($absolutePath)) {
            Log::error('Stored file not found on disk', ['path' => $absolutePath, 'scan_id' => $scanId]);

            return redirect()
                ->route('scan.confirm', $scanId)
                ->withErrors(['photo' => 'No pudimos leer la imagen guardada. Inténtalo de nuevo.']);
        }

        try {
            $labelText = $adapter->identifyFromLabel($absolutePath);
        } catch (IdentificationFailedException $e) {
            Log::warning('Label identification failed', [
                'scan_id' => $scanId,
                'reason' => $e->getReason(),
                'upstream' => $e->getContext(),
                'message' => $e->getMessage(),
            ]);
            $this->scanState->put($scanId, [
                'mode' => 'label',
                'image' => $stored,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('scan.confirm', $scanId);
        }

        if ($labelText === null) {
            $this->scanState->put($scanId, [
                'mode' => 'label',
                'image' => $stored,
                'error' => 'No pudimos leer ninguna especie en la etiqueta.',
            ]);

            return redirect()->route('scan.confirm', $scanId);
        }

        $scientificName = $this->labelMapper->map($labelText);

        if ($scientificName === null) {
            $this->scanState->put($scanId, [
                'mode' => 'label',
                'image' => $stored,
                'error' => "No reconocemos la especie: {$labelText}",
            ]);

            return redirect()->route('scan.confirm', $scanId);
        }

        // Defence in depth: even though SpeciesLabelMapper is a closed
        // table, the label text came from the model. Reject anything that
        // isn't on the species allowlist so we never cache a name that's
        // not recognised by the rest of the system.
        if (! AllowedSpecies::isAllowed($scientificName)) {
            Log::warning('Label scan mapped to off-list species', [
                'scan_id' => $scanId,
                'label_text' => $cleanLabelText ?? $labelText,
                'mapped_scientific' => $scientificName,
            ]);
            $this->scanState->put($scanId, [
                'mode' => 'label',
                'image' => $stored,
                'error' => "No reconocemos la especie: {$labelText}",
            ]);

            return redirect()->route('scan.confirm', $scanId);
        }

        $cleanLabelText = trim(preg_replace('/\s*CONFIDENCE:.*$/iu', '', $labelText) ?? '');
        $canonicalLocal = AllowedSpecies::commonEs($scientificName);
        $localName = $canonicalLocal ?? (SpeciesTranslations::toSpanish($cleanLabelText) ?? ucfirst($cleanLabelText));

        $this->scanState->put($scanId, [
            'mode' => 'label',
            'image' => $stored,
            'result' => [
                'scientific_name' => $scientificName,
                'common_name' => $cleanLabelText,
                'common_name_local' => $localName,
                'regional_names' => [],
                'confidence' => 1.0,
                'high_confidence' => true,
                'source' => 'label',
                'label_text' => $cleanLabelText,
            ],
        ]);

        return redirect()->route('scan.confirm', $scanId);
    }

    public function confirm(string $scan): View
    {
        $state = $this->scanState->get($scan) ?? [];
        $result = $state['result'] ?? null;
        $storedPath = $state['image'] ?? null;
        $error = $state['error'] ?? null;
        $mode = $state['mode'] ?? 'fish';
        $imageUrl = $storedPath ? route('scan.image', $scan) : null;
        $referenceImageUrl = $state['reference_image'] ?? null;

        if ($referenceImageUrl === null && $result !== null && isset($result['scientific_name'])) {
            $referenceImageUrl = $this->wikipedia->getSpeciesImage($result['scientific_name']);

            if ($referenceImageUrl !== null) {
                $state['reference_image'] = $referenceImageUrl;
                $this->scanState->put($scan, $state);
            }
        }

        return view('pages.confirm', [
            'scan' => $scan,
            'result' => $result,
            'imageUrl' => $imageUrl,
            'referenceImageUrl' => $referenceImageUrl,
            'error' => $error,
            'mode' => $mode,
        ]);
    }

    public function confirmStore(string $scan)
    {
        $state = $this->scanState->get($scan);
        $result = $state['result'] ?? null;

        if (! $result) {
            return redirect()->route('home');
        }

        $speciesParam = Str::slug($result['common_name']).'__'.Str::slug($result['scientific_name']);
        $referenceImageUrl = $state['reference_image']
            ?? $this->wikipedia->getSpeciesImage($result['scientific_name']);

        Cache::put(CacheKeys::speciesResult($speciesParam), [
            'scientific_name' => $result['scientific_name'],
            'common_name' => $result['common_name'],
            'common_name_local' => $this->cleanSpanishName($result['common_name_local'] ?? ''),
            'regional_names' => $result['regional_names'] ?? [],
            'image_path' => $state['image'] ?? null,
            'reference_image_url' => $referenceImageUrl,
        ], now()->addMinutes(30));

        return redirect()->route('species.show', $speciesParam);
    }

    public function rescan(string $scan, IdentifySpeciesAction $action, OpenRouterVisionAdapter $adapter): RedirectResponse
    {
        set_time_limit(180);

        $state = $this->scanState->get($scan);
        $storedPath = $state['image'] ?? null;
        $mode = $state['mode'] ?? 'fish';

        if (! $storedPath) {
            return redirect()->route('home');
        }

        $absolutePath = Storage::disk('local')->path($storedPath);

        if (! is_file($absolutePath)) {
            Log::error('Stored file not found on rescan', ['scan_id' => $scan, 'path' => $absolutePath]);

            return redirect()
                ->route('scan.confirm', $scan)
                ->withErrors(['photo' => 'No pudimos leer la imagen guardada. Inténtalo de nuevo.']);
        }

        if ($mode === 'label') {
            return $this->rescanLabel($scan, $absolutePath, $adapter);
        }

        return $this->rescanFish($scan, $absolutePath, $action);
    }

    private function rescanFish(string $scan, string $absolutePath, IdentifySpeciesAction $action): RedirectResponse
    {
        try {
            $result = $action->execute($absolutePath);
        } catch (IdentificationFailedException $e) {
            Log::warning('Rescan fish identification failed', ['scan_id' => $scan, 'error' => $e->getMessage()]);
            $state = $this->scanState->get($scan) ?? [];
            $state['error'] = $e->getMessage();
            $state['result'] = null;
            $this->scanState->put($scan, $state);

            return redirect()->route('scan.confirm', $scan);
        }

        $state = $this->scanState->get($scan) ?? [];
        $state['result'] = [
            'scientific_name' => $result->scientificName,
            'common_name' => $result->commonName,
            'common_name_local' => $this->cleanSpanishName(
                $result->commonNameLocal !== ''
                    ? $result->commonNameLocal
                    : (SpeciesTranslations::toSpanish($result->commonName) ?? ucfirst($result->commonName))
            ),
            'regional_names' => $result->regionalNames,
            'confidence' => $result->confidence,
            'high_confidence' => $result->isHighConfidence(),
        ];
        $state['error'] = null;
        $this->scanState->put($scan, $state);

        return redirect()->route('scan.confirm', $scan);
    }

    private function rescanLabel(string $scan, string $absolutePath, OpenRouterVisionAdapter $adapter): RedirectResponse
    {
        try {
            $labelText = $adapter->identifyFromLabel($absolutePath);
        } catch (IdentificationFailedException $e) {
            Log::warning('Rescan label identification failed', ['scan_id' => $scan, 'error' => $e->getMessage()]);
            $state = $this->scanState->get($scan) ?? [];
            $state['error'] = $e->getMessage();
            $state['result'] = null;
            $this->scanState->put($scan, $state);

            return redirect()->route('scan.confirm', $scan);
        }

        if ($labelText === null) {
            $state = $this->scanState->get($scan) ?? [];
            $state['error'] = 'No pudimos leer ninguna especie en la etiqueta.';
            $state['result'] = null;
            $this->scanState->put($scan, $state);

            return redirect()->route('scan.confirm', $scan);
        }

        $scientificName = $this->labelMapper->map($labelText);

        if ($scientificName === null) {
            $state = $this->scanState->get($scan) ?? [];
            $state['error'] = "No reconocemos la especie: {$labelText}";
            $state['result'] = null;
            $this->scanState->put($scan, $state);

            return redirect()->route('scan.confirm', $scan);
        }

        $localName = SpeciesTranslations::toSpanish($labelText) ?? ucfirst($labelText);

        $state = $this->scanState->get($scan) ?? [];
        $state['result'] = [
            'scientific_name' => $scientificName,
            'common_name' => $labelText,
            'common_name_local' => $localName,
            'regional_names' => [],
            'confidence' => 1.0,
            'high_confidence' => true,
        ];
        $state['error'] = null;
        $this->scanState->put($scan, $state);

        return redirect()->route('scan.confirm', $scan);
    }

    public function show(string $species): View
    {
        $storedResult = Cache::get(CacheKeys::speciesResult($species));
        $nutrition = null;

        $commonLocal = is_array($storedResult) ? ($storedResult['common_name_local'] ?? null) : null;
        $scientificName = is_array($storedResult) ? ($storedResult['scientific_name'] ?? null) : null;

        if ($commonLocal !== null && $commonLocal !== '') {
            $nutrition = $this->loadOrFetchNutrition($commonLocal, $scientificName);
        }

        $recommendations = $nutrition !== null
            ? \App\Domain\Nutrition\NutritionAdvisor::recommendAll($nutrition)
            : [];

        $cachedExplanation = Cache::get(CacheKeys::explain($species));

        return view('pages.species', [
            'species' => $species,
            'nutrition' => $nutrition,
            'recommendations' => $recommendations,
            'cached_explanation' => is_string($cachedExplanation) ? $cachedExplanation : null,
        ]);
    }

    private function loadOrFetchNutrition(string $commonLocal, ?string $scientificName): ?array
    {
        $seed = \App\Domain\Nutrition\SpeciesNutritionSeed::for($commonLocal);

        if ($seed !== null) {
            return $seed;
        }

        $speciesModel = null;

        if ($scientificName !== null && $scientificName !== '') {
            try {
                $speciesModel = Species::where('scientific_name', $scientificName)->first();

                if ($speciesModel !== null && $speciesModel->nutritionProfile !== null) {
                    return $this->formatNutrition($speciesModel->nutritionProfile);
                }
            } catch (\Throwable $e) {
                Log::warning('Species lookup skipped', ['error' => $e->getMessage()]);
            }
        }

        $data = $this->fdc->getNutritionData($commonLocal, $scientificName);

        if ($data === null) {
            return null;
        }

        if ($speciesModel !== null) {
            try {
                $this->persistNutritionProfile($speciesModel, $data);
            } catch (\Throwable) {
                // ignore persistence errors, keep showing the data
            }
        }

        return $data;
    }

    private function formatNutrition(NutritionProfile $profile): array
    {
        return [
            'calories' => (float) $profile->calories,
            'protein' => (float) $profile->protein,
            'fat' => (float) $profile->fat,
            'omega3' => (float) $profile->omega3,
            'vitamins' => $profile->vitamins ?? [],
        ];
    }

    private function persistNutritionProfile(Species $species, array $data): void
    {
        $existing = NutritionProfile::where('species_id', $species->id)->first();

        if ($existing !== null) {
            return;
        }

        NutritionProfile::create([
            'species_id' => $species->id,
            'calories' => $data['calories'] ?? null,
            'protein' => $data['protein'] ?? null,
            'fat' => $data['fat'] ?? null,
            'omega3' => $data['omega3'] ?? null,
            'vitamins' => $data['vitamins'] ?? null,
        ]);
    }

    /**
     * Generate a personalized explanation via OpenRouter (optional AI path).
     * Cached 24h per species + goal to avoid burning API calls.
     * Returns the explanation as plain text by default, JSON if requested via AJAX.
     */
    public function explain(Request $request, string $species)
    {
        $storedResult = Cache::get(CacheKeys::speciesResult($species));
        if (! is_array($storedResult)) {
            abort(404);
        }

        $commonLocal = (string) ($storedResult['common_name_local'] ?? $storedResult['common_name'] ?? '');
        $scientific = (string) ($storedResult['scientific_name'] ?? '');

        $nutrition = $this->loadOrFetchNutrition($commonLocal, $scientific);

        if ($nutrition === null) {
            abort(404);
        }

        $cacheKey = CacheKeys::explain($species);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $this->returnExplanation($request, $cached);
        }

        $prompt = $this->buildExplanationPrompt($commonLocal, $scientific, $nutrition);

        $explanation = $this->openRouter->generateText($prompt);

        if ($explanation === null) {
            $explanation = 'No se pudo generar una explicación personalizada en este momento. Las recomendaciones automáticas de arriba siguen aplicando.';
        }

        Cache::put($cacheKey, $explanation, now()->addHours(24));

        return $this->returnExplanation($request, $explanation);
    }

    private function returnExplanation(Request $request, string $text)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['explanation' => $text]);
        }

        return $text;
    }

    private function buildExplanationPrompt(string $commonLocal, string $scientific, array $nutrition): string
    {
        $lines = [];
        $lines[] = "Pescado: {$commonLocal}" . ($scientific !== '' ? " ({$scientific})" : '');
        $lines[] = '';
        $lines[] = 'Datos nutricionales por 100 g:';
        foreach (['calories', 'protein', 'fat', 'omega3'] as $field) {
            if (isset($nutrition[$field])) {
                $unit = $field === 'calories' ? 'kcal' : 'g';
                $lines[] = "- {$field}: {$nutrition[$field]} {$unit}";
            }
        }
        if (! empty($nutrition['vitamins'])) {
            $vits = [];
            foreach ($nutrition['vitamins'] as $k => $v) {
                $vits[] = "{$k}={$v}";
            }
            $lines[] = "- Vitaminas: " . implode(', ', $vits);
        }
        if (! empty($nutrition['minerals'])) {
            $mins = [];
            foreach ($nutrition['minerals'] as $k => $v) {
                $mins[] = "{$k}={$v}";
            }
            $lines[] = "- Minerales: " . implode(', ', $mins);
        }
        if (! empty($nutrition['contaminants']['methylmercury_mg_per_kg'])) {
            $lines[] = "- Mercurio: {$nutrition['contaminants']['methylmercury_mg_per_kg']} mg/kg";
        }
        $lines[] = '';
        $lines[] = 'Genera una recomendación breve (2-3 párrafos) sobre este pescado para un consumidor en España. Menciona sus puntos fuertes (aportes nutricionales, beneficios para la salud) y precauciones si las hay (mercurio, alto contenido graso). Sin listas, sin markdown, tono natural y directo.';

        return implode("\n", $lines);
    }

    /**
     * Strip stray "ALT:" / "ATL:" tokens or anything that follows them,
     * so a leaked regional line never appears inside the local name field.
     */
    private function cleanSpanishName(string $name): string
    {
        $name = (string) preg_replace('/\s*(?:ALT|ATL):.*$/iu', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        return $name;
    }
}
