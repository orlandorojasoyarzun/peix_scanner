<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Recommendation\Models\Recommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    protected $model = AiGeneration::class;

    public function definition(): array
    {
        return [
            'recommendation_id' => Recommendation::factory(),
            'provider' => $this->faker->randomElement(['openai', 'ollama', 'replicate']),
            'model' => $this->faker->randomElement(['gpt-4o', 'llava:13b', 'moondream']),
            'prompt_hash' => $this->faker->sha256(),
            'response' => $this->faker->paragraph(),
            'execution_time' => $this->faker->numberBetween(200, 5000),
            'created_at' => now(),
        ];
    }
}
