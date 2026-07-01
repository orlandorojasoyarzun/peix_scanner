<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Species\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Species>
 */
class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    public function definition(): array
    {
        $common = $this->faker->unique()->word().' '.$this->faker->lastName();

        return [
            'common_name' => ucwords($common),
            'scientific_name' => Str::title($this->faker->unique()->words(2, true)),
            'description' => $this->faker->paragraph(),
        ];
    }
}
