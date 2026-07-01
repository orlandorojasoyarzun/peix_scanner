<?php

declare(strict_types=1);

namespace App\Domain\Ai\Models;

use App\Domain\Recommendation\Models\Recommendation;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ai_generations';

    public $timestamps = false;

    protected $fillable = [
        'recommendation_id',
        'provider',
        'model',
        'prompt_hash',
        'response',
        'execution_time',
    ];

    protected $casts = [
        'execution_time' => 'integer',
        'created_at' => 'datetime',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    protected static function newFactory(): AiGenerationFactory
    {
        return AiGenerationFactory::new();
    }
}
