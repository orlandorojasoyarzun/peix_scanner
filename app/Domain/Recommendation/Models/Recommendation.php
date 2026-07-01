<?php

declare(strict_types=1);

namespace App\Domain\Recommendation\Models;

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Species\Models\Species;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recommendation extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'recommendations';

    public $timestamps = false;

    protected $fillable = [
        'species_id',
        'recommendation',
        'language',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function aiGeneration(): HasOne
    {
        return $this->hasOne(AiGeneration::class);
    }

    protected static function newFactory(): RecommendationFactory
    {
        return RecommendationFactory::new();
    }
}
