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
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('ghl_message_id');

            $table->string('gmail_message_id')->unique()->after('conversation_id');

            // Gmail label ids (INBOX, UNREAD, STARRED, ...) as returned by the
            // API, kept for status/filtering — not modeled as a relation since
            // Gmail treats them as a flat set of string ids on the message.
            $table->json('label_ids')->nullable()->after('attachments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['gmail_message_id', 'label_ids']);

            $table->string('ghl_message_id')->unique();
        });
    }
};
