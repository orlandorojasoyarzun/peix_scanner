<?php

declare(strict_types=1);

namespace App\Domain\Nutrition\Models;

use App\Domain\Species\Models\Species;
use Database\Factories\NutritionProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionProfile extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'nutrition_profiles';

    public $timestamps = false;

    protected $fillable = [
        'species_id',
        'calories',
        'protein',
        'omega3',
        'fat',
        'vitamins',
    ];

    protected $casts = [
        'calories' => 'decimal:2',
        'protein' => 'decimal:2',
        'omega3' => 'decimal:2',
        'fat' => 'decimal:2',
        'vitamins' => 'array',
        'created_at' => 'datetime',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    protected static function newFactory(): NutritionProfileFactory
    {
        return NutritionProfileFactory::new();
    }
}
