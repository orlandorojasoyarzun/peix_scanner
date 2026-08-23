<?php

declare(strict_types=1);

namespace App\Domain\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Exceptions\DriverException;
use RuntimeException;

/**
 * Re-encodes user uploads to a clean, EXIF-stripped JPEG.
 *
 * Why we re-encode instead of trusting the original file:
 *
 *   1. **EXIF / metadata leakage.** A photo straight off a phone contains
 *      GPS coordinates, device model, timestamps. We strip all of that by
 *      not copying any metadata to the output.
 *   2. **Polyglot files.** A PNG / WebP can be crafted to be a valid image
 *      AND a valid ZIP / HTML / PDF. Decoding into GD/Imagick and re-encoding
 *      deconstructs any hidden payload.
 *   3. **Memory bombs.** A 200-byte header can claim dimensions of 50,000 x
 *      50,000, which would OOM the server the moment we touched pixels.
 *      We cap the pixel count before any work happens.
 *   4. **Storage cost.** A 6 MB iPhone photo compresses to ~400 KB at JPEG
 *      quality 80 with no visible loss for fish identification.
 *
 * The output is always `.jpg` regardless of what the user uploaded — that
 * means the rest of the application only has to handle one image format.
 */
final class ImageProcessor
{
    /**
     * Hard cap on declared pixel count. 50 megapixels covers every
     * modern phone (iPhone Pro 48MP, Pixel 9 Pro 50MP) and any sane
     * camera, while staying well below the dimensions a malicious header
     * could claim (50,000 × 50,000 = 2.5 GP would OOM the worker).
     */
    private const MAX_PIXELS = 50_000_000;

    /**
     * Longest-side cap applied AFTER decode. Anything bigger gets
     * downscaled preserving aspect ratio. Keeps memory and storage
     * bounded regardless of how big the original was.
     *
     * 4096 px is enough for fish identification (most species can be
     * told apart at far lower resolutions) and produces ~300-600 KB
     * JPEGs at quality 80.
     */
    private const MAX_DIMENSION = 4096;

    /**
     * Re-encode quality. 80 is the sweet spot for photos — indistinguishable
     * from the original at normal viewing sizes, ~10× smaller.
     */
    private const JPEG_QUALITY = 80;

    /**
     * @return array{bytes: string, extension: 'jpg', width: int, height: int}
     *
     * @throws InvalidImageException the upload isn't a real image, exceeds
     *                                the pixel cap, or can't be decoded
     */
    public function reencode(UploadedFile $file): array
    {
        // 50MP photos (iPhone Pro 48MP, Pixel 9 Pro) decode into ~200 MB
        // of raw RGBA in GD, plus working buffers for the downscale. The
        // default PHP memory_limit (128M) OOMs there. Bumping to 512M
        // gives us headroom without changing the request-wide budget.
        $previousLimit = ini_set('memory_limit', '512M');

        try {
            return $this->reencodeInner($file);
        } finally {
            if ($previousLimit !== false) {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * @return array{bytes: string, extension: 'jpg', width: int, height: int}
     *
     * @throws InvalidImageException the upload isn't a real image, exceeds
     *                                the pixel cap, or can't be decoded
     */
    private function reencodeInner(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new InvalidImageException('Upload failed before reaching the processor.');
        }

        $tmpPath = $file->getRealPath();

        if ($tmpPath === false) {
            throw new InvalidImageException('Could not resolve the temporary upload path.');
        }

        // Cheap pre-flight check on declared dimensions without decoding
        // pixels. getimagesize() reads the header only — cheap and safe.
        $info = @getimagesize($tmpPath);

        if ($info === false) {
            throw new InvalidImageException('The uploaded file is not a recognised image.');
        }

        [$width, $height] = $info;

        if ($width <= 0 || $height <= 0) {
            throw new InvalidImageException('Image dimensions are unreadable.');
        }

        $pixels = $width * $height;

        if ($pixels > self::MAX_PIXELS) {
            Log::warning('Upload rejected: pixel count too high', [
                'declared_width' => $width,
                'declared_height' => $height,
                'pixel_count' => $pixels,
                'limit' => self::MAX_PIXELS,
            ]);

            throw new InvalidImageException(
                "La imagen es demasiado grande ({$width}×{$height}). Máximo permitido: ".
                self::MAX_PIXELS.' píxeles.'
            );
        }

        // Try GD first (faster, smaller memory footprint), fall back to
        // Imagick. The runtime that serves HTTP requests (FrankenPHP) does
        // not always ship with the same extension set as the CLI `php`
        // binary on the same image — Nixpacks-built PHP may lack GD while
        // Imagick is preserved, or vice versa. We probe GD first because
        // it's the lighter path for typical phone photos, but if either
        // driver throws DriverException at construction time we fall
        // through to the next.
        $drivers = [
            'gd' => static fn () => new GdDriver(),
            'imagick' => static fn () => new ImagickDriver(),
        ];

        $manager = null;
        $lastDriverError = null;

        foreach ($drivers as $name => $factory) {
            try {
                $manager = new ImageManager($factory());
                break;
            } catch (DriverException $e) {
                Log::warning('Image driver unavailable, trying next', [
                    'driver' => $name,
                    'error' => $e->getMessage(),
                ]);
                $lastDriverError = $e;
            }
        }

        if ($manager === null) {
            Log::error('No image driver available', [
                'last_error' => $lastDriverError?->getMessage(),
                'declared_mime' => $info[2] ?? null,
            ]);
            throw new InvalidImageException('No pudimos decodificar la imagen. Asegúrate de que sea JPG, PNG o WebP.');
        }

        try {
            $image = $manager->read($tmpPath);
        } catch (\Throwable $e) {
            Log::warning('Image decode failed', [
                'error' => $e->getMessage(),
                'declared_mime' => $info[2] ?? null,
            ]);

            throw new InvalidImageException('No pudimos decodificar la imagen. Asegúrate de que sea JPG, PNG o WebP.');
        }

        // Downscale huge photos (iPhone Pro 48MP, etc.) so the JPEG we
        // persist stays bounded. scaleDown() never enlarges, so photos
        // smaller than the cap are passed through untouched.
        if ($image->width() > self::MAX_DIMENSION || $image->height() > self::MAX_DIMENSION) {
            $image->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);

            Log::info('Image downscaled to fit max dimension', [
                'original_width' => $width,
                'original_height' => $height,
                'new_width' => $image->width(),
                'new_height' => $image->height(),
                'max_dimension' => self::MAX_DIMENSION,
            ]);
        }

        $encoded = $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));

        $bytes = (string) $encoded;

        if ($bytes === '') {
            throw new InvalidImageException('Image re-encode produced an empty payload.');
        }

        // After re-encode we know the real dimensions. Trust these, not the
        // header-declared ones, for downstream decisions (e.g. UI layout).
        $realWidth = $image->width();
        $realHeight = $image->height();

        return [
            'bytes' => $bytes,
            'extension' => 'jpg',
            'width' => $realWidth,
            'height' => $realHeight,
        ];
    }

    /**
     * Write the encoded bytes to the private disk under the given scan ID.
     * Returns the relative path so callers can stash it in the cache.
     *
     * @param  array{bytes: string, extension: 'jpg', width: int, height: int}  $encoded
     */
    public function persist(array $encoded, string $scanId, string $disk = 'local'): string
    {
        $filename = "{$scanId}.{$encoded['extension']}";
        $path = "scan-uploads/{$filename}";

        $written = \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $encoded['bytes']);

        if ($written === false) {
            throw new RuntimeException("Could not write re-encoded image to {$path}.");
        }

        return $path;
    }
}
