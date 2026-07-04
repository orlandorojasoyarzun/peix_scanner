<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Actions\IdentifySpeciesAction;
use App\Domain\Ai\Exceptions\IdentificationFailedException;
use App\Http\Requests\ScanImageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScanController extends Controller
{
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

            return redirect()->route('scan.confirm', $scanId);
        }

        Cache::put("scan.{$scanId}.result", [
            'scientific_name' => $result->scientificName,
            'common_name' => $result->commonName,
            'confidence' => $result->confidence,
            'high_confidence' => $result->isHighConfidence(),
        ], now()->addMinutes(10));

        Cache::put("scan.{$scanId}.image", $stored, now()->addMinutes(10));

        return redirect()->route('scan.confirm', $scanId);
    }

    public function confirm(string $scan): View
    {
        $result = Cache::get("scan.{$scan}.result");
        $storedPath = Cache::get("scan.{$scan}.image");
        $error = Cache::get("scan.{$scan}.error");
        $imageUrl = $storedPath ? Storage::url($storedPath) : null;

        return view('pages.confirm', [
            'scan' => $scan,
            'result' => $result,
            'imageUrl' => $imageUrl,
            'error' => $error,
        ]);
    }

    public function confirmStore(string $scan)
    {
        $result = Cache::get("scan.{$scan}.result");

        if (! $result) {
            return redirect()->route('home');
        }

        $speciesParam = Str::slug($result['common_name']).'__'.Str::slug($result['scientific_name']);

        return redirect()->route('species.show', $speciesParam);
    }

    public function show(string $species): View
    {
        return view('pages.species', ['species' => $species]);
    }
}
