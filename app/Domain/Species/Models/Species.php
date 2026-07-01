<?php

declare(strict_types=1);

namespace App\Domain\Species\Models;

use App\Domain\Nutrition\Models\NutritionProfile;
use App\Domain\Recommendation\Models\Recommendation;
use Database\Factories\SpeciesFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Species extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'species';

    protected $fillable = [
        'common_name',
        'scientific_name',
        'description',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(SpeciesImage::class);
    }

    public function nutritionProfile(): HasOne
    {
        return $this->hasOne(NutritionProfile::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    protected static function newFactory(): SpeciesFactory
    {
        return SpeciesFactory::new();
    }
}
