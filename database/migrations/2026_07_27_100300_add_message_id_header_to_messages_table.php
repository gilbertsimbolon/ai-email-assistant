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
            // The RFC 2822 "Message-ID" header (distinct from Gmail's own
            // internal gmail_message_id) — needed to set In-Reply-To/
            // References when sending a threaded reply via the Gmail API.
            $table->string('message_id_header')->nullable()->after('gmail_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('message_id_header');
        });
    }
};
