<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanImageRequest extends FormRequest
{
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
            'photo.required' => 'Sube una foto del filete para identificarlo.',
            'photo.image' => 'El archivo tiene que ser una imagen.',
            'photo.mimes' => 'La imagen tiene que ser JPG, PNG o WebP.',
            'photo.max' => 'La imagen no puede pesar más de 8 MB.',
        ];
    }
}
