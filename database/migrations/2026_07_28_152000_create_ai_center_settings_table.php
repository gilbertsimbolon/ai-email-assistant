<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_center_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('confidence_review_threshold', 5, 2)->default(0.70);
            $table->string('default_fallback_tone')->nullable()->default('professional');
            $table->string('default_escalation_target')->nullable()->default('human_agent');
            $table->string('company_name')->nullable();
            $table->string('default_agent_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_center_settings');
    }
};
