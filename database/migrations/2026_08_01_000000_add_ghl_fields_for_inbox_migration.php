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
            // Conversations now also arrive from GHL (shared inbox, no
            // per-user Gmail account), so the old Gmail identity columns can
            // no longer be required.
            $table->foreignId('gmail_account_id')->nullable()->change();
            $table->string('gmail_thread_id')->nullable()->change();

            $table->string('ghl_conversation_id')->nullable()->unique()->after('gmail_thread_id');
            $table->string('ghl_location_id')->nullable()->after('ghl_conversation_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('gmail_message_id')->nullable()->change();

            $table->string('ghl_message_id')->nullable()->unique()->after('gmail_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('ghl_message_id');

            $table->string('gmail_message_id')->nullable(false)->change();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['ghl_conversation_id', 'ghl_location_id']);

            $table->foreignId('gmail_account_id')->nullable(false)->change();
            $table->string('gmail_thread_id')->nullable(false)->change();
        });
    }
};
