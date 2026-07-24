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
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->text('summary');
            $table->string('customer_intent')->nullable();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->default('neutral');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->text('last_customer_request')->nullable();
            $table->text('recommended_action')->nullable();
            $table->boolean('refund_requested')->default(false);
            $table->boolean('escalation_required')->default(false);
            $table->decimal('confidence_score', 5,2)->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
            $table->unique('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
