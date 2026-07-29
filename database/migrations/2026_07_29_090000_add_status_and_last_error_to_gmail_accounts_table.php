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
        Schema::table('gmail_accounts', function (Blueprint $table) {
            // Persisted sync health, so Reports' Gmail Analytics can show
            // "Status" / "Last Error" without re-deriving it from logs.
            $table->string('status')->default('connected')->after('last_synced_at');
            $table->text('last_error')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmail_accounts', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_error']);
        });
    }
};
