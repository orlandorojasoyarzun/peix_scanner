<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanImageRequest extends FormRequest
{
    /**
     * Allowlist of file extensions the controller may persist to disk.
     * Kept in sync with the `mimes:` rule below — anything outside this
     * list must NOT become part of the stored filename, regardless of
     * what the client claims.
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:8192',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Sube una foto del filete o de la etiqueta para identificarlo.',
            'photo.image' => 'El archivo tiene que ser una imagen.',
            'photo.mimes' => 'La imagen tiene que ser JPG, PNG o WebP.',
            'photo.max' => 'La imagen no puede pesar más de 8 MB.',
        ];
    }

    /**
     * Return a lowercased, allowlisted extension safe to use in the
     * persisted filename. Falls back to `jpg` if the client provided an
     * extension outside the allowlist (which should never happen because
     * the `mimes:` rule already rejected it, but defense in depth is cheap).
     */
    public function safeExtension(): string
    {
        $file = $this->file('photo');

        if ($file === null) {
            return 'jpg';
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());

        return in_array($ext, self::ALLOWED_EXTENSIONS, true) ? $ext : 'jpg';
    }
}
