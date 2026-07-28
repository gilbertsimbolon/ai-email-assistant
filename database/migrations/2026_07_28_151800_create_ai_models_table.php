<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider')->default('openai');

            // Encrypted at the model level (see AiModel::casts()), same
            // pattern as AiSetting::api_key.
            $table->text('api_key')->nullable();

            $table->string('base_url')->nullable();
            $table->string('model')->nullable();

            $table->decimal('temperature', 3, 2)->default(0.3);
            $table->decimal('top_p', 3, 2)->nullable();
            $table->unsignedInteger('max_tokens')->default(1200);
            $table->string('reasoning_effort')->nullable();
            $table->decimal('presence_penalty', 3, 2)->nullable();
            $table->decimal('frequency_penalty', 3, 2)->nullable();
            $table->string('response_format')->default('text');
            $table->unsignedInteger('timeout')->default(60);

            // Only one row may have is_default=true at a time; enforced at
            // the application layer (AiModel/AiCenterAiModelController), not
            // a DB partial unique index, so this stays portable across the
            // sqlite (tests) / mysql (prod) split already used elsewhere.
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->string('status')->default('published');
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
