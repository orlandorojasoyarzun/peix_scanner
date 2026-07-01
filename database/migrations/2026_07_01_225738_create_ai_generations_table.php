<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recommendation_id')->nullable()->constrained('recommendations')->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('prompt_hash')->index();
            $table->text('response');
            $table->unsignedInteger('execution_time')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
