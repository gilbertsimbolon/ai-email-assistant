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
            // Conversations created directly from the email webhook have no
            // GoHighLevel counterpart, so these can no longer be required.
            $table->string('ghl_conversation_id')->nullable()->change();
            $table->string('ghl_location_id')->nullable()->change();

            $table->timestamp('synced_at')->nullable()->after('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('synced_at');
            $table->string('ghl_conversation_id')->nullable(false)->change();
            $table->string('ghl_location_id')->nullable(false)->change();
        });
    }
};
