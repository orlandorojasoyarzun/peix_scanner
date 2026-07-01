<?php

declare(strict_types=1);

namespace App\Domain\Species\Models;

use Database\Factories\SpeciesImageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeciesImage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'species_images';

    public $timestamps = false;

    protected $fillable = [
        'species_id',
        'path',
        'mime_type',
        'hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    protected static function newFactory(): SpeciesImageFactory
    {
        return SpeciesImageFactory::new();
    }
}
