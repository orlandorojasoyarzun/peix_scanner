<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Nutrition\Models\NutritionProfile;
use App\Domain\Species\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionProfile>
 */
class NutritionProfileFactory extends Factory
{
    protected $model = NutritionProfile::class;

    public function definition(): array
    {
        return [
            'species_id' => Species::factory(),
            'calories' => $this->faker->randomFloat(2, 60, 250),
            'protein' => $this->faker->randomFloat(2, 10, 30),
            'omega3' => $this->faker->randomFloat(2, 0.1, 3.0),
            'fat' => $this->faker->randomFloat(2, 0.5, 25),
            'vitamins' => [
                'A' => $this->faker->randomFloat(2, 0, 100),
                'D' => $this->faker->randomFloat(2, 0, 25),
                'B12' => $this->faker->randomFloat(2, 0, 10),
            ],
            'created_at' => now(),
        ];
    }
}
