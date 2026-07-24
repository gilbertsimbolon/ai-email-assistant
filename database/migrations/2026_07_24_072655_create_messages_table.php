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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('ghl_message_id')->unique();
            $table->enum('sender_type', ['customer', 'agent', 'system']);
            $table->enum('message_type', ['email', 'whatsapp']);
            $table->longText('body');
            $table->json('attachments')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index('conversation_id');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
