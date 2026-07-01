<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Recommendation\Models\Recommendation;
use App\Domain\Species\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    public function definition(): array
    {
        return [
            'species_id' => Species::factory(),
            'recommendation' => $this->faker->paragraph(),
            'language' => 'es',
            'created_at' => now(),
        ];
    }
}
