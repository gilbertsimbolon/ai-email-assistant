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
        Schema::create('gmail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('email');

            // OAuth 2.0 credentials — encrypted at the model level (see
            // GmailAccount::casts()), so these are stored as ciphertext, not
            // plain tokens.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scope')->nullable();

            // Gmail History API incremental-sync cursor. Null means "never
            // synced yet" — the next sync run does a full backfill instead.
            $table->string('history_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_accounts');
    }
};
