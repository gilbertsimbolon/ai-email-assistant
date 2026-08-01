<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GHL conversations aren't limited to email/whatsapp (SMS, FB, IG, GMB, live
 * chat, etc.), so the old two-value enum can't represent them — widen to a
 * plain string instead of maintaining a growing enum list (claude.txt
 * section 16). Raw SQL (not ->change()) to avoid a doctrine/dbal dependency
 * this project doesn't have installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE conversations MODIFY channel VARCHAR(255) NULL');
        DB::statement('ALTER TABLE drafts MODIFY type VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE drafts SET type = 'email' WHERE type NOT IN ('email', 'whatsapp')");
        DB::statement("ALTER TABLE drafts MODIFY type ENUM('email', 'whatsapp') NOT NULL DEFAULT 'email'");

        DB::statement("UPDATE conversations SET channel = 'email' WHERE channel IS NULL OR channel NOT IN ('email', 'whatsapp')");
        DB::statement("ALTER TABLE conversations MODIFY channel ENUM('email', 'whatsapp') NOT NULL DEFAULT 'email'");
    }
};
