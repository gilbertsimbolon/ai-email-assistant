<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('production');

            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('intent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reply_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->json('matched_rule_ids')->nullable();
            $table->json('matched_action_types')->nullable();
            $table->json('matched_knowledge_base_ids')->nullable();

            $table->longText('prompt')->nullable();
            $table->longText('response')->nullable();

            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('cost', 10, 4)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();

            $table->string('status')->default('success');
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
