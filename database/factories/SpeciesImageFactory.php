<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Species\Models\Species;
use App\Domain\Species\Models\SpeciesImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeciesImage>
 */
class SpeciesImageFactory extends Factory
{
    protected $model = SpeciesImage::class;

    public function definition(): array
    {
        return [
            'species_id' => Species::factory(),
            'path' => 'species/'.$this->faker->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'hash' => $this->faker->sha256(),
            'created_at' => now(),
        ];
    }
}
