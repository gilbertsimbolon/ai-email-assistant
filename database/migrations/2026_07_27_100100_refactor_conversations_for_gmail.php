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
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['ghl_conversation_id', 'ghl_location_id']);

            $table->foreignId('gmail_account_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('gmail_thread_id')->after('gmail_account_id');

            $table->unique(['gmail_account_id', 'gmail_thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['gmail_account_id', 'gmail_thread_id']);
            $table->dropConstrainedForeignId('gmail_account_id');
            $table->dropColumn('gmail_thread_id');

            $table->string('ghl_conversation_id')->unique();
            $table->string('ghl_location_id');
        });
    }
};
