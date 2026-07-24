<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Actions\IdentifySpeciesAction;
use App\Domain\Ai\Adapters\OpenRouterVisionAdapter;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use App\Domain\Ai\SpeciesLabelMapper;
use App\Domain\Ai\SpeciesTranslations;
use App\Http\Requests\ScanImageRequest;
use App\Services\WikipediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(
        private readonly WikipediaService $wikipedia,
        private readonly SpeciesLabelMapper $labelMapper,
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
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $scanId = (string) Str::uuid();
        $filename = "{$scanId}.{$extension}";

        Storage::disk('public')->makeDirectory('scan-uploads');
        $stored = $file->storeAs('scan-uploads', $filename, 'public');

        if ($stored === false) {
            Log::error('Failed to store scan upload', ['scan_id' => $scanId, 'original' => $file->getClientOriginalName()]);

            return redirect()
                ->route('scan.confirm', $scanId)
                ->withErrors(['photo' => 'No pudimos guardar la imagen. Inténtalo de nuevo.']);
        }

        $absolutePath = Storage::disk('public')->path($stored);

        if (! is_file($absolutePath)) {
            Log::error('Stored file not found on disk', ['path' => $absolutePath, 'scan_id' => $scanId]);

            return redirect()
                ->route('scan.confirm', $scanId)
                ->withErrors(['photo' => 'No pudimos leer la imagen guardada. Inténtalo de nuevo.']);
        }

        try {
            $result = $action->execute($absolutePath);
        } catch (IdentificationFailedException $e) {
            Log::warning('Identification failed', ['scan_id' => $scanId, 'error' => $e->getMessage()]);
            Cache::put("scan.{$scanId}.error", $e->getMessage(), now()->addMinutes(10));
            Cache::put("scan.{$scanId}.image", $stored, now()->addMinutes(10));
            Cache::put("scan.{$scanId}.mode", 'fish', now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scanId);
        }

        Cache::put("scan.{$scanId}.result", [
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
        ], now()->addMinutes(10));

        Cache::put("scan.{$scanId}.image", $stored, now()->addMinutes(10));
        Cache::put("scan.{$scanId}.mode", 'fish', now()->addMinutes(10));

        return redirect()->route('scan.confirm', $scanId);
    }

    public function storeLabel(ScanImageRequest $request, OpenRouterVisionAdapter $adapter): RedirectResponse
    {
        set_time_limit(180);

        $file = $request->file('photo');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $scanId = (string) Str::uuid();
        $filename = "{$scanId}.{$extension}";

        Storage::disk('public')->makeDirectory('scan-uploads');
        $stored = $file->storeAs('scan-uploads', $filename, 'public');

        if ($stored === false) {
            Log::error('Failed to store label upload', ['scan_id' => $scanId, 'original' => $file->getClientOriginalName()]);

            return redirect()
                ->route('scan.confirm', $scanId)
                ->withErrors(['photo' => 'No pudimos guardar la imagen. Inténtalo de nuevo.']);
        }

        $absolutePath = Storage::disk('public')->path($stored);

        if (! is_file($absolutePath)) {
            Log::error('Stored file not found on disk', ['path' => $absolutePath, 'scan_id' => $scanId]);

            return redirect()
                ->route('scan.confirm', $scanId)
                ->withErrors(['photo' => 'No pudimos leer la imagen guardada. Inténtalo de nuevo.']);
        }

        try {
            $labelText = $adapter->identifyFromLabel($absolutePath);
        } catch (IdentificationFailedException $e) {
            Log::warning('Label identification failed', ['scan_id' => $scanId, 'error' => $e->getMessage()]);
            Cache::put("scan.{$scanId}.error", $e->getMessage(), now()->addMinutes(10));
            Cache::put("scan.{$scanId}.image", $stored, now()->addMinutes(10));
            Cache::put("scan.{$scanId}.mode", 'label', now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scanId);
        }

        if ($labelText === null) {
            Cache::put("scan.{$scanId}.error", 'No pudimos leer ninguna especie en la etiqueta.', now()->addMinutes(10));
            Cache::put("scan.{$scanId}.image", $stored, now()->addMinutes(10));
            Cache::put("scan.{$scanId}.mode", 'label', now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scanId);
        }

        $scientificName = $this->labelMapper->map($labelText);

        if ($scientificName === null) {
            Cache::put("scan.{$scanId}.error", "No reconocemos la especie: {$labelText}", now()->addMinutes(10));
            Cache::put("scan.{$scanId}.image", $stored, now()->addMinutes(10));
            Cache::put("scan.{$scanId}.mode", 'label', now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scanId);
        }

        $cleanLabelText = trim(preg_replace('/\s*CONFIDENCE:.*$/iu', '', $labelText) ?? '');
        $localName = SpeciesTranslations::toSpanish($cleanLabelText) ?? ucfirst($cleanLabelText);

        Cache::put("scan.{$scanId}.result", [
            'scientific_name' => $scientificName,
            'common_name' => $cleanLabelText,
            'common_name_local' => $localName,
            'regional_names' => [],
            'confidence' => 1.0,
            'high_confidence' => true,
            'source' => 'label',
            'label_text' => $cleanLabelText,
        ], now()->addMinutes(10));

        Cache::put("scan.{$scanId}.image", $stored, now()->addMinutes(10));
        Cache::put("scan.{$scanId}.mode", 'label', now()->addMinutes(10));

        return redirect()->route('scan.confirm', $scanId);
    }

    public function confirm(string $scan): View
    {
        $result = Cache::get("scan.{$scan}.result");
        $storedPath = Cache::get("scan.{$scan}.image");
        $error = Cache::get("scan.{$scan}.error");
        $mode = Cache::get("scan.{$scan}.mode", 'fish');
        $imageUrl = $storedPath ? Storage::url($storedPath) : null;
        $referenceImageUrl = null;

        if ($result !== null && isset($result['scientific_name'])) {
            $referenceImageUrl = $this->wikipedia->getSpeciesImage($result['scientific_name']);
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
        $result = Cache::get("scan.{$scan}.result");

        if (! $result) {
            return redirect()->route('home');
        }

        $speciesParam = Str::slug($result['common_name']).'__'.Str::slug($result['scientific_name']);
        $referenceImageUrl = $this->wikipedia->getSpeciesImage($result['scientific_name']);

        Cache::put("species.{$speciesParam}.result", [
            'scientific_name' => $result['scientific_name'],
            'common_name' => $result['common_name'],
            'common_name_local' => $this->cleanSpanishName($result['common_name_local'] ?? ''),
            'regional_names' => $result['regional_names'] ?? [],
            'image_path' => Cache::get("scan.{$scan}.image"),
            'reference_image_url' => $referenceImageUrl,
        ], now()->addMinutes(30));

        return redirect()->route('species.show', $speciesParam);
    }

    public function rescan(string $scan, IdentifySpeciesAction $action, OpenRouterVisionAdapter $adapter): RedirectResponse
    {
        set_time_limit(180);

        $storedPath = Cache::get("scan.{$scan}.image");
        $mode = Cache::get("scan.{$scan}.mode", 'fish');

        if (! $storedPath) {
            return redirect()->route('home');
        }

        $absolutePath = Storage::disk('public')->path($storedPath);

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
            Cache::put("scan.{$scan}.error", $e->getMessage(), now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scan);
        }

        Cache::put("scan.{$scan}.result", [
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
        ], now()->addMinutes(10));

        Cache::forget("scan.{$scan}.error");

        return redirect()->route('scan.confirm', $scan);
    }

    private function rescanLabel(string $scan, string $absolutePath, OpenRouterVisionAdapter $adapter): RedirectResponse
    {
        try {
            $labelText = $adapter->identifyFromLabel($absolutePath);
        } catch (IdentificationFailedException $e) {
            Log::warning('Rescan label identification failed', ['scan_id' => $scan, 'error' => $e->getMessage()]);
            Cache::put("scan.{$scan}.error", $e->getMessage(), now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scan);
        }

        if ($labelText === null) {
            Cache::put("scan.{$scan}.error", 'No pudimos leer ninguna especie en la etiqueta.', now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scan);
        }

        $scientificName = $this->labelMapper->map($labelText);

        if ($scientificName === null) {
            Cache::put("scan.{$scan}.error", "No reconocemos la especie: {$labelText}", now()->addMinutes(10));

            return redirect()->route('scan.confirm', $scan);
        }

        $localName = SpeciesTranslations::toSpanish($labelText) ?? ucfirst($labelText);

        Cache::put("scan.{$scan}.result", [
            'scientific_name' => $scientificName,
            'common_name' => $labelText,
            'common_name_local' => $localName,
            'regional_names' => [],
            'confidence' => 1.0,
            'high_confidence' => true,
        ], now()->addMinutes(10));

        Cache::forget("scan.{$scan}.error");

        return redirect()->route('scan.confirm', $scan);
    }

    public function show(string $species): View
    {
        return view('pages.species', ['species' => $species]);
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
