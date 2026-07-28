<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();

            $table->string('provider')->default('openai');

            // Encrypted at the model level (see AiSetting::casts()), same
            // pattern as GmailSetting's client_secret column.
            $table->text('api_key')->nullable();

            $table->string('base_url')->nullable();

            $table->string('model')->nullable();

            $table->decimal('temperature', 3, 2)->default(0.3);

            $table->unsignedInteger('max_tokens')->default(1200);

            $table->unsignedInteger('timeout')->default(60);

            // Global on/off switch for AI features, independent from
            // whether credentials happen to be filled in.
            $table->boolean('enabled')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
