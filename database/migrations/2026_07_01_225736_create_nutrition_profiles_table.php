<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('species_id')->unique()->constrained('species')->cascadeOnDelete();
            $table->decimal('calories', 8, 2)->nullable();
            $table->decimal('protein', 8, 2)->nullable();
            $table->decimal('omega3', 8, 2)->nullable();
            $table->decimal('fat', 8, 2)->nullable();
            $table->json('vitamins')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_profiles');
    }
};
