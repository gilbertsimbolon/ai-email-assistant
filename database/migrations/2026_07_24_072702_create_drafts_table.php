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
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['email', 'whatsapp']);
            $table->string('provider')->default('openai');
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['active', 'regenerated', 'approved', 'sent', 'discarded'])->default('active');
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
