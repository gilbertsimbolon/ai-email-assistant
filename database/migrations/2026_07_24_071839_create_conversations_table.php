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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // GoHighLevel
            $table->string('ghl_conversation_id')->unique();
            $table->string('ghl_location_id');

            // Contact Info
            $table->string('contact_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // Conversation Info
            $table->enum('channel', ['email', 'whatsapp']);
            $table->string('subject')->nullable();

            $table->enum('status', ['pending_review', 'replied', 'closed'])->default('pending_review');

            $table->timestamp('last_message_at')->nullable();
            
            $table->timestamps();

            // Index
            $table->index('status');
            $table->index('last_message_at');
            $table->index('contact_email');
            $table->index('contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
