<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('species_id')->constrained('species')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type');
            $table->string('hash')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_images');
    }
};
