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
        Schema::table('drafts', function (Blueprint $table) {
            // Snapshot of the AI's raw output at creation time, so Reports
            // can tell "sent as-is" apart from "edited by agent" without
            // relying on the mutable `content` column, which is overwritten
            // in place whenever an agent edits the draft.
            $table->longText('original_content')->nullable()->after('content');

            // Links a draft back to the AiLog run that produced it (and, via
            // that log, to the SOP/Reply Template/Workflow/Intent involved).
            // Null for manually composed drafts (provider = 'manual').
            $table->foreignId('ai_log_id')->nullable()->after('provider')->constrained('ai_logs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_log_id');
            $table->dropColumn('original_content');
        });
    }
};
